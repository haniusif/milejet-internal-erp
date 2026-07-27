<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrRequest extends Model
{
    protected $guarded = ['id'];
    protected $casts = [
        'date_request' => 'date', 'last_working_day' => 'date',
        'has_certificate' => 'boolean', 'synced_at' => 'datetime',
    ];
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'odoo_employee_id', 'odoo_id');
    }
}
