<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkLocation extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'active'    => 'boolean',
        'synced_at' => 'datetime',
        'latitude'  => 'float',
        'longitude' => 'float',
    ];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'odoo_work_location_id', 'odoo_id');
    }

    /** True when coordinates are set, i.e. the geofence can be enforced. */
    public function hasGeofence(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /** Effective radius in meters (falls back to the global default). */
    public function radius(): int
    {
        return $this->geofence_radius ?? (int) config('attendance.geofence_radius');
    }
}
