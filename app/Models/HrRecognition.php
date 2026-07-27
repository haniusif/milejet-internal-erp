<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrRecognition extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['date' => 'datetime', 'synced_at' => 'datetime'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'odoo_employee_id', 'odoo_id');
    }
}
