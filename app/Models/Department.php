<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['synced_at' => 'datetime'];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'odoo_department_id', 'odoo_id');
    }
}
