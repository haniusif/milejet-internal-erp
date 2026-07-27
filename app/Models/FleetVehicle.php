<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FleetVehicle extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'active'           => 'boolean',
        'in_use'           => 'boolean',
        'acquisition_date' => 'date',
        'inspection_expiry' => 'date',
        'fuel_capacity'    => 'decimal:2',
        'synced_at'        => 'datetime',
    ];

    public function state(): BelongsTo
    {
        return $this->belongsTo(FleetVehicleState::class, 'odoo_state_id', 'odoo_id');
    }

    public function serviceLogs(): HasMany
    {
        return $this->hasMany(FleetServiceLog::class, 'odoo_vehicle_id', 'odoo_id');
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(FleetInspection::class, 'odoo_vehicle_id', 'odoo_id');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(FleetVehicleUsage::class, 'odoo_vehicle_id', 'odoo_id');
    }

    public function stateColor(): string
    {
        return match ($this->state_name) {
            'Registered' => 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:ring-emerald-800',
            'Downgraded' => 'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-900/30 dark:text-rose-300 dark:ring-rose-800',
            'To Order'   => 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:ring-amber-800',
            default      => 'bg-sky-50 text-sky-700 ring-sky-200 dark:bg-sky-900/30 dark:text-sky-300 dark:ring-sky-800',
        };
    }
}
