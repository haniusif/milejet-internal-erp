<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FleetVehicleUsage extends Model
{
    protected $guarded = ['id'];
    protected $casts = [
        'date_picking' => 'datetime',
        'date_return'  => 'datetime',
        'synced_at'    => 'datetime',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(FleetVehicle::class, 'odoo_vehicle_id', 'odoo_id');
    }
}
