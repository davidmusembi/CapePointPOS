<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\DeliveryNote;
use App\Models\Sale;
use App\Models\User;
use App\Services\ReferenceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class DeliveryNoteController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return static::crudPermissions('delivery_notes', ['view' => ['print'], 'edit' => ['updateStatus']]);
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = DeliveryNote::query()->select('delivery_notes.*')->with(['sale', 'customer', 'deliveryPerson'])
                ->when($request->filled('start_date') && $request->filled('end_date'),
                    fn ($q) => $q->whereBetween('delivery_notes.date', [$request->start_date, $request->end_date]))
                ->when($request->status, fn ($q, $v) => $q->where('delivery_notes.status', $v))
                ->when($request->customer_id, fn ($q, $v) => $q->where('delivery_notes.customer_id', $v));

            return DataTables::eloquent($query)
                ->editColumn('date', fn ($d) => format_date($d->date))
                ->editColumn('delivery_no', fn ($d) => '<a href="'.route('delivery-notes.show', $d).'" class="font-weight-600">'.e($d->delivery_no).'</a>'
                    .($d->from_sale ? ' <span class="badge badge-primary" title="Created from the invoice shipping section">Invoice</span>' : ''))
                ->addColumn('invoice_no', fn ($d) => $d->sale ? '<a href="'.route('sales.show', $d->sale_id).'">'.e($d->sale->invoice_no).'</a>' : '<span class="text-muted">Standalone</span>')
                ->addColumn('customer_name', fn ($d) => e($d->customer->display_name ?? '-'))
                ->editColumn('delivery_address', fn ($d) => e($d->delivery_address ?? '-'))
                ->addColumn('transport', fn ($d) => e(collect([$d->deliveryPerson?->name ?? $d->driver_name, $d->vehicle_no])->filter()->implode(' / ') ?: '-'))
                ->editColumn('status', fn ($d) => shipping_status_badge($d->status))
                ->addColumn('action', fn ($d) => $this->actions(array_merge(
                    [
                        ['label' => 'View', 'icon' => 'fas fa-eye', 'url' => route('delivery-notes.show', $d)],
                        ['label' => 'Print', 'icon' => 'fas fa-print', 'url' => route('delivery-notes.print', $d), 'blank' => true],
                        ['label' => 'Edit', 'icon' => 'fas fa-edit', 'url' => route('delivery-notes.edit', $d), 'can' => 'delivery_notes.edit'],
                        '-',
                    ],
                    $this->statusActions($d),
                    $d->from_sale ? [] : ['-', ['label' => 'Delete', 'delete' => route('delivery-notes.destroy', $d), 'can' => 'delivery_notes.delete']],
                )))
                ->filterColumn('invoice_no', fn ($q, $k) => $q->whereHas('sale', fn ($s) => $s->where('invoice_no', 'like', "%{$k}%")))
                ->filterColumn('customer_name', fn ($q, $k) => $q->whereHas('customer', fn ($c) => $c->where('name', 'like', "%{$k}%")))
                ->rawColumns(['delivery_no', 'invoice_no', 'status', 'action', 'customer_name', 'delivery_address', 'transport'])
                ->make(true);
        }

        return view('delivery-notes.index');
    }

    /** "Mark as …" actions for every status after the current one (cancelled only while not delivered). */
    protected function statusActions(DeliveryNote $d): array
    {
        $keys = array_keys(DeliveryNote::statuses());
        $pos = array_search($d->status, $keys, true);
        $items = [];
        foreach ($keys as $i => $key) {
            if ($key === $d->status || $d->status === 'cancelled') {
                continue;
            }
            if ($key === 'cancelled' ? $d->status === 'delivered' : ($pos !== false && $i < $pos)) {
                continue;
            }
            $label = DeliveryNote::statuses()[$key];
            $items[] = ['label' => "Mark {$label}", 'icon' => $key === 'cancelled' ? 'fas fa-ban' : 'fas fa-truck',
                'confirm' => route('delivery-notes.status', [$d, 'status' => $key]), 'can' => 'delivery_notes.edit',
                'message' => "Mark {$d->delivery_no} as {$label}?"];
        }

        return $items;
    }

    public function show(DeliveryNote $deliveryNote)
    {
        $deliveryNote->load(['sale', 'customer', 'items.product.unit', 'creator', 'deliveryPerson']);

        return view('delivery-notes.show', ['note' => $deliveryNote]);
    }

    public function edit(DeliveryNote $deliveryNote)
    {
        $deliveryNote->load(['sale', 'customer', 'items.product.unit']);

        return view('delivery-notes.form', [
            'note' => $deliveryNote,
            'sale' => $deliveryNote->sale,
            'customer' => $deliveryNote->customer,
            'items' => $deliveryNote->items->map(fn ($i) => $this->itemRow($i->product, $i->description, $i->quantity))->values()->all(),
            'deliveryPeople' => $this->deliveryPeople($deliveryNote->delivery_person_id),
        ]);
    }

    public function update(Request $request, DeliveryNote $deliveryNote)
    {
        $data = $this->validated($request, $deliveryNote);
        $data['driver_name'] = $this->personName($data['delivery_person_id'] ?? null, $data['driver_name'] ?? null);

        DB::transaction(function () use ($deliveryNote, $data) {
            if ($deliveryNote->from_sale) {
                // Items & customer follow the invoice; transport / status details are editable here and flow back.
                $deliveryNote->update(collect($data)->except(['items', 'sale_id', 'customer_id'])->all());
                $deliveryNote->sale?->forceFill([
                    'shipping_address' => $deliveryNote->delivery_address,
                    'delivered_to' => $deliveryNote->contact_person,
                    'delivery_person_id' => $deliveryNote->delivery_person_id,
                    'shipping_status' => $deliveryNote->status,
                ])->saveQuietly();

                return;
            }
            $deliveryNote->update(collect($data)->except(['items', 'sale_id'])->all());
            $deliveryNote->items()->delete();
            $this->syncItems($deliveryNote, $data['items']);
        });

        return redirect()->route('delivery-notes.show', $deliveryNote)->with('success', 'Delivery note updated successfully');
    }

    public function updateStatus(Request $request, DeliveryNote $deliveryNote)
    {
        $status = $request->validate(['status' => ['required', Rule::in(array_keys(DeliveryNote::statuses()))]])['status'];
        $deliveryNote->update(['status' => $status]); // model event keeps the invoice's shipping status in step

        return $this->success("Delivery note {$deliveryNote->delivery_no} marked as ".DeliveryNote::statuses()[$status]);
    }

    public function destroy(DeliveryNote $deliveryNote)
    {
        if ($deliveryNote->from_sale && $deliveryNote->sale && ! $deliveryNote->sale->trashed()) {
            return $this->failure("Delivery note {$deliveryNote->delivery_no} belongs to invoice {$deliveryNote->sale->invoice_no} and is removed together with the invoice.");
        }
        $deliveryNote->delete();

        return $this->success("Delivery note {$deliveryNote->delivery_no} deleted", route('delivery-notes.index'));
    }

    public function print(Request $request, DeliveryNote $deliveryNote)
    {
        $deliveryNote->load(['sale', 'customer', 'items.product.unit', 'creator', 'deliveryPerson']);
        $pdf = Pdf::loadView('pdf.delivery-note', ['note' => $deliveryNote])->setPaper('a4');
        $file = 'DeliveryNote-'.$deliveryNote->delivery_no.'.pdf';

        return $request->boolean('download') ? $pdf->download($file) : $pdf->stream($file);
    }

    protected function deliveryPeople(?int $current = null)
    {
        return User::where(fn ($q) => $q->where('is_active', true)->when($current, fn ($w) => $w->orWhere('id', $current)))
            ->orderBy('name')->pluck('name', 'id');
    }

    /** Store the delivery person's name as the driver for display / printing; typed name wins when no user chosen. */
    protected function personName($userId, ?string $typed): ?string
    {
        return $userId ? (User::withTrashed()->find($userId)?->name ?? $typed) : $typed;
    }

    protected function itemRow($product, ?string $description, float $qty): array
    {
        return [
            'id' => $product->id ?? null,
            'text' => $product->name ?? 'Deleted product',
            'sku' => $product->sku ?? '',
            'unit' => $product->unit->short_name ?? '',
            'description' => $description,
            'quantity' => round($qty, 3),
        ];
    }

    protected function syncItems(DeliveryNote $note, array $items): void
    {
        foreach ($items as $row) {
            $note->items()->create([
                'product_id' => $row['product_id'],
                'description' => $row['description'] ?? null,
                'quantity' => $row['quantity'],
            ]);
        }
    }

    protected function validated(Request $request, ?DeliveryNote $note = null): array
    {
        $fromSale = (bool) $note?->from_sale;

        return $request->validate([
            'sale_id' => ['nullable', 'exists:sales,id'],
            'customer_id' => [$fromSale ? 'nullable' : 'required', 'exists:customers,id'],
            'date' => ['required', 'date'],
            'delivery_address' => ['nullable', 'string', 'max:1000'],
            'contact_person' => ['nullable', 'string', 'max:190'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'vehicle_no' => ['nullable', 'string', 'max:30'],
            'driver_name' => ['nullable', 'string', 'max:190'],
            'delivery_person_id' => ['nullable', Rule::exists('users', 'id')],
            'status' => ['required', Rule::in(array_keys(DeliveryNote::statuses()))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => [$fromSale ? 'nullable' : 'required', 'array', $fromSale ? 'min:0' : 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ], ['items.required' => 'Add at least one item to the delivery note.']);
    }
}
