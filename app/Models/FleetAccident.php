<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FleetAccident extends Model
{
    protected $guarded = ['id'];
    protected $casts = [
        'date' => 'datetime', 'repair_cost' => 'decimal:2',
        'claim_amount' => 'decimal:2', 'synced_at' => 'datetime',
    ];
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(FleetVehicle::class, 'odoo_vehicle_id', 'odoo_id');
    }
}
