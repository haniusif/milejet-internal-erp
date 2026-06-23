<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SickLeave extends Model
{
    protected $table = 'hr_sick_leaves';

    protected $guarded = ['id'];

    protected $casts = [
        'date_from' => 'date',
        'date_to'   => 'date',
        'synced_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'odoo_employee_id', 'odoo_id');
    }
}
