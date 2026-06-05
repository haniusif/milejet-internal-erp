<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class FinanceInvoice extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'invoice_date'     => 'date',
        'invoice_date_due' => 'date',
        'amount_total'     => 'decimal:2',
        'amount_residual'  => 'decimal:2',
        'synced_at'        => 'datetime',
    ];

    /** Customer invoices + credit notes. */
    public function scopeCustomer(Builder $q): Builder
    {
        return $q->whereIn('move_type', ['out_invoice', 'out_refund']);
    }

    /** Vendor bills + refunds. */
    public function scopeVendor(Builder $q): Builder
    {
        return $q->whereIn('move_type', ['in_invoice', 'in_refund']);
    }

    public function isRefund(): bool
    {
        return in_array($this->move_type, ['out_refund', 'in_refund'], true);
    }

    public function isOverdue(): bool
    {
        return $this->state === 'posted'
            && $this->payment_state !== 'paid'
            && $this->invoice_date_due
            && $this->invoice_date_due->isPast();
    }

    public function stateLabel(): string
    {
        return match ($this->state) {
            'posted' => __('Posted'),
            'cancel' => __('Cancelled'),
            default  => __('Draft'),
        };
    }

    public function stateColor(): string
    {
        return match ($this->state) {
            'posted' => 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:ring-emerald-800',
            'cancel' => 'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-900/30 dark:text-rose-300 dark:ring-rose-800',
            default  => 'bg-slate-100 text-slate-600 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700',
        };
    }

    public function paymentStateLabel(): string
    {
        return match ($this->payment_state) {
            'paid'       => __('Paid'),
            'in_payment' => __('In payment'),
            'partial'    => __('Partially paid'),
            'reversed'   => __('Reversed'),
            default      => __('Not paid'),
        };
    }

    public function paymentStateColor(): string
    {
        return match ($this->payment_state) {
            'paid', 'in_payment' => 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:ring-emerald-800',
            'partial'            => 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:ring-amber-800',
            'reversed'           => 'bg-slate-100 text-slate-600 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700',
            default              => 'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-900/30 dark:text-rose-300 dark:ring-rose-800',
        };
    }
}
