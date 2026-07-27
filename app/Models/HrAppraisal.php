<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrAppraisal extends Model
{
    protected $guarded = ['id'];
    protected $casts = [
        'date_from' => 'date', 'date_to' => 'date',
        'overall_rating' => 'float', 'synced_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'odoo_employee_id', 'odoo_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(HrAppraisalLine::class, 'odoo_appraisal_id', 'odoo_id');
    }
}
