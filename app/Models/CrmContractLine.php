<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmContractLine extends Model
{
    protected $guarded = ['id'];
    protected $casts = [
        'quantity' => 'float', 'price_unit' => 'float', 'price_subtotal' => 'float',
    ];
}
