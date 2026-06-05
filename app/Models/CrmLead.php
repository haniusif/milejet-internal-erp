<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmLead extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'active'           => 'boolean',
        'date_deadline'    => 'date',
        'odoo_create_date' => 'datetime',
        'synced_at'        => 'datetime',
    ];

    public function stage(): BelongsTo
    {
        return $this->belongsTo(CrmStage::class, 'odoo_stage_id', 'odoo_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(CrmCustomer::class, 'odoo_partner_id', 'odoo_id');
    }

    /** Lost leads are archived in Odoo (active = false). */
    public function isLost(): bool
    {
        return !$this->active;
    }

    public function isWon(): bool
    {
        return (bool) $this->stage?->is_won;
    }

    public function statusColor(): string
    {
        return match (true) {
            $this->isLost() => 'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-900/30 dark:text-rose-300 dark:ring-rose-800',
            $this->isWon()  => 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:ring-emerald-800',
            default         => 'bg-sky-50 text-sky-700 ring-sky-200 dark:bg-sky-900/30 dark:text-sky-300 dark:ring-sky-800',
        };
    }

    public function statusLabel(): string
    {
        return match (true) {
            $this->isLost() => __('Lost'),
            $this->isWon()  => __('Won'),
            default         => $this->stage_name ?: __('New'),
        };
    }
}
