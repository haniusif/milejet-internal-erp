<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Leave extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'date_from' => 'datetime',
        'date_to'   => 'datetime',
        'synced_at' => 'datetime',
        'number_of_days' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'odoo_employee_id', 'odoo_id');
    }

    public function stateLabel(): string
    {
        return match ($this->state) {
            'draft'    => 'مسودة',
            'confirm'  => 'قيد الموافقة',
            'validate' => 'موافق عليها',
            'refuse'   => 'مرفوضة',
            'cancel'   => 'ملغاة',
            default    => $this->state,
        };
    }

    public function stateColor(): string
    {
        return match ($this->state) {
            'draft'    => 'bg-gray-100 text-gray-700',
            'confirm'  => 'bg-yellow-100 text-yellow-800',
            'validate' => 'bg-green-100 text-green-800',
            'refuse'   => 'bg-red-100 text-red-800',
            'cancel'   => 'bg-gray-200 text-gray-600',
            default    => 'bg-gray-100',
        };
    }
}
