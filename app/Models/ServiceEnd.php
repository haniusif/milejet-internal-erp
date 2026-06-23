<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceEnd extends Model
{
    protected $table = 'hr_service_ends';

    protected $guarded = ['id'];

    protected $casts = [
        'last_working_day'  => 'date',
        'notice_served'     => 'boolean',
        'custody_returned'  => 'boolean',
        'settlement_amount' => 'decimal:2',
        'synced_at'         => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'odoo_employee_id', 'odoo_id');
    }
}
