<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use Yajra\DataTables\Facades\DataTables;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            [$from, $to] = date_range_from_request($request, 'month');
            $query = Activity::query()->with('causer')
                ->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])
                ->when($request->log_name, fn ($q, $v) => $q->where('log_name', $v))
                ->when($request->causer_id, fn ($q, $v) => $q->where('causer_id', $v)->where('causer_type', (new User)->getMorphClass()));

            return DataTables::eloquent($query)
                ->editColumn('created_at', fn ($a) => format_date($a->created_at, true))
                ->addColumn('user', fn ($a) => e($a->causer->name ?? 'System'))
                ->editColumn('log_name', fn ($a) => '<span class="badge badge-primary">'.e($a->log_name).'</span>')
                ->editColumn('description', fn ($a) => e(ucfirst($a->description)).($a->subject_id ? ' <small class="text-muted">#'.$a->subject_id.'</small>' : ''))
                ->addColumn('changes', function ($a) {
                    $attrs = $a->properties['attributes'] ?? [];
                    $old = $a->properties['old'] ?? [];
                    if (! $attrs) {
                        return '';
                    }
                    $lines = collect($attrs)->except(['updated_at', 'created_at', 'password'])->take(6)->map(function ($v, $k) use ($old) {
                        $fmt = fn ($x) => is_scalar($x) || $x === null ? \Illuminate\Support\Str::limit((string) $x, 40) : json_encode($x);

                        return '<b>'.e($k).'</b>: '.(array_key_exists($k, $old) ? '<span class="text-muted">'.e($fmt($old[$k])).'</span> &rarr; ' : '').e($fmt($v));
                    });

                    return '<small>'.$lines->implode('<br>').'</small>';
                })
                ->rawColumns(['log_name', 'description', 'changes', 'user'])
                ->make(true);
        }

        return view('activity-log.index', [
            'logNames' => Activity::query()->distinct()->orderBy('log_name')->pluck('log_name'),
            'users' => User::orderBy('name')->pluck('name', 'id'),
        ]);
    }
}
