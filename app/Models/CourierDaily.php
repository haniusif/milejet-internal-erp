<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourierDaily extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'date'        => 'date',
        'present'     => 'boolean',
        'performance' => 'decimal:2',
        'synced_at'   => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'odoo_employee_id', 'odoo_id');
    }
}
