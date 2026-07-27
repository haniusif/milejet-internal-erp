<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FleetFuelLog extends Model
{
    protected $guarded = ['id'];
    protected $casts = [
        'date' => 'date', 'liters' => 'decimal:2', 'amount' => 'decimal:2',
        'price_per_liter' => 'decimal:3', 'odometer' => 'decimal:2', 'synced_at' => 'datetime',
    ];
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(FleetVehicle::class, 'odoo_vehicle_id', 'odoo_id');
    }
}
