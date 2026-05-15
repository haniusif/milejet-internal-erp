<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payslip extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'date_from'        => 'date',
        'date_to'          => 'date',
        'synced_at'        => 'datetime',
        'basic_total'      => 'decimal:2',
        'allowance_total'  => 'decimal:2',
        'gross_total'      => 'decimal:2',
        'deduction_total'  => 'decimal:2',
        'net_total'        => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'odoo_employee_id', 'odoo_id');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'odoo_contract_id', 'odoo_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PayslipLine::class, 'odoo_payslip_id', 'odoo_id')->orderBy('sequence');
    }

    public function stateLabel(): string
    {
        return match ($this->state) {
            'draft'  => 'مسودة',
            'verify' => 'بانتظار التحقق',
            'done'   => 'مُعتمدة',
            'cancel' => 'ملغاة',
            default  => $this->state,
        };
    }

    public function stateColor(): string
    {
        return match ($this->state) {
            'draft'  => 'bg-gray-100 text-gray-700',
            'verify' => 'bg-yellow-100 text-yellow-800',
            'done'   => 'bg-green-100 text-green-800',
            'cancel' => 'bg-red-100 text-red-700',
            default  => 'bg-gray-100',
        };
    }
}
