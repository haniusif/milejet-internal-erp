<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Employee extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'active'             => 'boolean',
        'synced_at'          => 'datetime',
        'master_imported_at' => 'datetime',
        'date_of_joining'    => 'date',
        'contract_end_date'  => 'date',
        'birthday'           => 'date',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'odoo_department_id', 'odoo_id');
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class, 'odoo_employee_id', 'odoo_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'odoo_employee_id', 'odoo_id');
    }
}
