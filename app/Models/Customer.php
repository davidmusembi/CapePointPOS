<?php

namespace App\Models;

use App\Models\Concerns\HasCreator;
use App\Models\Concerns\LogsModelActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Customer extends Model
{
    use HasCreator, LogsModelActivity, SoftDeletes;

    protected $fillable = [
        'code', 'name', 'company', 'email', 'phone', 'address', 'city', 'tax_number',
        'credit_limit', 'opening_balance', 'payment_terms', 'notes', 'is_active', 'created_by',
    ];

    protected $casts = [
        'credit_limit' => 'float',
        'opening_balance' => 'float',
        'is_active' => 'boolean',
    ];

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function returns()
    {
        return $this->hasMany(SaleReturn::class);
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
     * Adds total_invoiced, total_returned, total_paid, total_refunded and balance columns.
     * balance = opening + invoiced - returned - paid + refunded
     */
    public function scopeWithBalance(Builder $query): Builder
    {
        $invoiced = DB::table('sales')->selectRaw('COALESCE(SUM(total),0)')
            ->whereColumn('sales.customer_id', 'customers.id')->whereNull('sales.deleted_at');
        $returned = DB::table('sale_returns')->selectRaw('COALESCE(SUM(total),0)')
            ->whereColumn('sale_returns.customer_id', 'customers.id')->whereNull('sale_returns.deleted_at');
        $refunded = DB::table('sale_returns')->selectRaw('COALESCE(SUM(refund_amount),0)')
            ->whereColumn('sale_returns.customer_id', 'customers.id')->whereNull('sale_returns.deleted_at');
        $paid = DB::table('payments')->selectRaw('COALESCE(SUM(amount),0)')
            ->whereColumn('payments.customer_id', 'customers.id')->where('payments.party_type', 'customer');

        if (is_null($query->getQuery()->columns)) {
            $query->select('customers.*');
        }

        return $query->selectSub($invoiced, 'total_invoiced')
            ->selectSub($returned, 'total_returned')
            ->selectSub($paid, 'total_paid')
            ->selectSub($refunded, 'total_refunded')
            ->selectRaw('(customers.opening_balance + ('.$invoiced->toRawSql().') - ('.$returned->toRawSql().') - ('.$paid->toRawSql().') + ('.$refunded->toRawSql().')) as balance');
    }

    public function currentBalance(): float
    {
        return (float) static::query()->withBalance()->whereKey($this->id)->value('balance');
    }
}
