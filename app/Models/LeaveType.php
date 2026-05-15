<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['synced_at' => 'datetime'];
}
