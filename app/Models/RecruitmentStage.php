<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecruitmentStage extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'hired_stage' => 'boolean',
        'fold'        => 'boolean',
        'synced_at'   => 'datetime',
    ];
}
