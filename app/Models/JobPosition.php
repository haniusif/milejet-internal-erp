<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobPosition extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'active'    => 'boolean',
        'synced_at' => 'datetime',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'odoo_department_id', 'odoo_id');
    }

    public function applicants(): HasMany
    {
        return $this->hasMany(Applicant::class, 'odoo_job_id', 'odoo_id');
    }
}
