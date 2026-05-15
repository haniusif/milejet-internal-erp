<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayslipLine extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['total' => 'decimal:2'];

    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class, 'odoo_payslip_id', 'odoo_id');
    }
}
