<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'active'    => 'boolean',
        'synced_at' => 'datetime',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'odoo_parent_id', 'odoo_id');
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Company::class, 'odoo_parent_id', 'odoo_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'odoo_company_id', 'odoo_id');
    }
}
