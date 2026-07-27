<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingSession extends Model
{
    protected $guarded = ['id'];
    protected $casts = [
        'date_start' => 'datetime', 'date_end' => 'datetime', 'synced_at' => 'datetime',
    ];

    public function enrollments(): HasMany
    {
        return $this->hasMany(TrainingEnrollment::class, 'odoo_session_id', 'odoo_id');
    }
}
