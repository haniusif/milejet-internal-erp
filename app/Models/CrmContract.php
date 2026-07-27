<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmContract extends Model
{
    protected $guarded = ['id'];
    protected $casts = [
        'date_start' => 'date', 'date_end' => 'date', 'next_invoice_date' => 'date',
        'auto_renew' => 'boolean', 'amount_recurring' => 'float', 'synced_at' => 'datetime',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(CrmContractLine::class, 'odoo_contract_id', 'odoo_id');
    }
}
