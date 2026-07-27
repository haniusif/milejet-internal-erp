<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingCourse extends Model
{
    protected $guarded = ['id'];
    protected $casts = [
        'duration_hours' => 'float', 'is_mandatory' => 'boolean',
        'active' => 'boolean', 'synced_at' => 'datetime',
    ];
}
