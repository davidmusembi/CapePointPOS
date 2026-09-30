<?php

namespace App\Models;

use App\Models\Concerns\HasCreator;
use App\Models\Concerns\LogsModelActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Supplier extends Model
{
    use HasCreator, LogsModelActivity, SoftDeletes;

    protected $fillable = [
        'code', 'name', 'company', 'email', 'phone', 'address', 'city', 'tax_number',
        'opening_balance', 'payment_terms', 'notes', 'is_active', 'created_by',
    ];

    protected $casts = [
        'opening_balance' => 'float',
        'is_active' => 'boolean',
    ];

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function returns()
    {
        return $this->hasMany(PurchaseReturn::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->company ? $this->name.' ('.$this->company.')' : $this->name;
    }

    /**
     * Adds total_invoiced, total_returned, total_paid, total_refunded and balance (amount payable) columns.
     * balance = opening + purchased - returned - paid + refunded
     */
    public function scopeWithBalance(Builder $query): Builder
    {
        $invoiced = DB::table('purchases')->selectRaw('COALESCE(SUM(total),0)')
            ->whereColumn('purchases.supplier_id', 'suppliers.id')->whereNull('purchases.deleted_at');
        $returned = DB::table('purchase_returns')->selectRaw('COALESCE(SUM(total),0)')
            ->whereColumn('purchase_returns.supplier_id', 'suppliers.id')->whereNull('purchase_returns.deleted_at');
        $refunded = DB::table('purchase_returns')->selectRaw('COALESCE(SUM(refund_amount),0)')
            ->whereColumn('purchase_returns.supplier_id', 'suppliers.id')->whereNull('purchase_returns.deleted_at');
        $paid = DB::table('payments')->selectRaw('COALESCE(SUM(amount),0)')
            ->whereColumn('payments.supplier_id', 'suppliers.id')->where('payments.party_type', 'supplier');

        if (is_null($query->getQuery()->columns)) {
            $query->select('suppliers.*');
        }

        return $query->selectSub($invoiced, 'total_invoiced')
            ->selectSub($returned, 'total_returned')
            ->selectSub($paid, 'total_paid')
            ->selectSub($refunded, 'total_refunded')
            ->selectRaw('(suppliers.opening_balance + ('.$invoiced->toRawSql().') - ('.$returned->toRawSql().') - ('.$paid->toRawSql().') + ('.$refunded->toRawSql().')) as balance');
    }

    public function currentBalance(): float
    {
        return (float) static::query()->withBalance()->whereKey($this->id)->value('balance');
    }
}
