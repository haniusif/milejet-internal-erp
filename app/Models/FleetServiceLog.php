<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FleetServiceLog extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'date'      => 'date',
        'synced_at' => 'datetime',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(FleetVehicle::class, 'odoo_vehicle_id', 'odoo_id');
    }

    public function stateColor(): string
    {
        return match ($this->state) {
            'done'      => 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:ring-emerald-800',
            'running'   => 'bg-sky-50 text-sky-700 ring-sky-200 dark:bg-sky-900/30 dark:text-sky-300 dark:ring-sky-800',
            'cancelled' => 'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-900/30 dark:text-rose-300 dark:ring-rose-800',
            default     => 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:ring-amber-800',
        };
    }

    public function stateLabel(): string
    {
        return match ($this->state) {
            'done'      => __('Done'),
            'running'   => __('Running'),
            'cancelled' => __('Cancelled'),
            default     => __('To do'),
        };
    }
}
