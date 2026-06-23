<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanLine extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'date'      => 'date',
        'amount'    => 'decimal:2',
        'synced_at' => 'datetime',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class, 'odoo_loan_id', 'odoo_id');
    }
}
