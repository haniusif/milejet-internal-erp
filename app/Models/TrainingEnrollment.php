<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingEnrollment extends Model
{
    protected $guarded = ['id'];
    protected $casts = [
        'completion_date' => 'date', 'expiry_date' => 'date',
        'has_certificate' => 'boolean', 'synced_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'odoo_employee_id', 'odoo_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class, 'odoo_session_id', 'odoo_id');
    }
}
