<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    protected $guarded = ['id'];
    protected $casts = [
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];
}
