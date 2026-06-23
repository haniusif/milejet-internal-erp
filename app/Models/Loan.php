<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'date'          => 'date',
        'amount'        => 'decimal:2',
        'installment'   => 'decimal:2',
        'repaid_amount' => 'decimal:2',
        'balance'       => 'decimal:2',
        'synced_at'     => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'odoo_employee_id', 'odoo_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(LoanLine::class, 'odoo_loan_id', 'odoo_id');
    }
}
