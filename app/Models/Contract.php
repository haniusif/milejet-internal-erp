<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contract extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'date_start' => 'date',
        'date_end'   => 'date',
        'wage'       => 'decimal:2',
        'synced_at'  => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'odoo_employee_id', 'odoo_id');
    }

    public function stateLabel(): string
    {
        return match ($this->state) {
            'draft'  => 'مسودة',
            'open'   => 'سارٍ',
            'pending'=> 'بانتظار التجديد',
            'close'  => 'منتهٍ',
            'cancel' => 'ملغى',
            default  => $this->state,
        };
    }

    public function stateColor(): string
    {
        return match ($this->state) {
            'open'    => 'bg-green-100 text-green-800',
            'pending' => 'bg-yellow-100 text-yellow-800',
            'close'   => 'bg-gray-200 text-gray-600',
            'cancel'  => 'bg-red-100 text-red-700',
            default   => 'bg-gray-100 text-gray-700',
        };
    }
}
