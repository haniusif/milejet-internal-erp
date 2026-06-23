<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FleetInspection extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'date_inspected' => 'datetime',
        'odometer'       => 'decimal:2',
        'synced_at'      => 'datetime',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(FleetVehicle::class, 'odoo_vehicle_id', 'odoo_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(FleetInspectionLine::class, 'odoo_inspection_id', 'odoo_id')->orderBy('sequence');
    }
}
