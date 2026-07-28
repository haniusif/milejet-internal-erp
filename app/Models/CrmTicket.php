<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmTicket extends Model
{
    protected $guarded = ['id'];
    protected $casts = [
        'sla_deadline' => 'datetime', 'date_open' => 'datetime',
        'date_closed' => 'datetime', 'synced_at' => 'datetime',
    ];
}
