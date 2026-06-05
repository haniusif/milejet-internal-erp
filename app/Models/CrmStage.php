<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmStage extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_won'    => 'boolean',
        'fold'      => 'boolean',
        'synced_at' => 'datetime',
    ];
}
