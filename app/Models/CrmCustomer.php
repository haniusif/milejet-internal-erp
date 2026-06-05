<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmCustomer extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_company' => 'boolean',
        'active'     => 'boolean',
        'synced_at'  => 'datetime',
    ];

    public function leads(): HasMany
    {
        return $this->hasMany(CrmLead::class, 'odoo_partner_id', 'odoo_id');
    }
}
