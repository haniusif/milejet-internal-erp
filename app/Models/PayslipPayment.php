<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayslipPayment extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'date'      => 'date',
        'amount'    => 'decimal:2',
        'synced_at' => 'datetime',
    ];

    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class, 'odoo_payslip_id', 'odoo_id');
    }
}
