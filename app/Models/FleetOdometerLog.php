<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FleetOdometerLog extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['value' => 'decimal:2', 'date' => 'date', 'synced_at' => 'datetime'];
}
