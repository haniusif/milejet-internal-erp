<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrAppraisalLine extends Model
{
    protected $guarded = ['id'];
    protected $casts = [
        'weight' => 'float', 'auto_value' => 'float', 'score' => 'float',
        'is_auto' => 'boolean',
    ];
}
