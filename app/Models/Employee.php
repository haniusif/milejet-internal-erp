<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Employee extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'active'             => 'boolean',
        'synced_at'          => 'datetime',
        'master_imported_at' => 'datetime',
        'date_of_joining'    => 'date',
        'contract_end_date'  => 'date',
        'birthday'           => 'date',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'odoo_department_id', 'odoo_id');
    }

    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class, 'odoo_work_location_id', 'odoo_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'odoo_parent_id', 'odoo_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(Employee::class, 'odoo_parent_id', 'odoo_id');
    }

    /**
     * image_small is raw base64 from Odoo with no stored mimetype —
     * sniff it from the base64 prefix (real photos are jpeg/png; employees
     * without a photo get Odoo's SVG initials placeholder).
     */
    public function getAvatarDataUriAttribute(): ?string
    {
        if (!$this->image_small) {
            return null;
        }

        $mime = match (true) {
            str_starts_with($this->image_small, '/9j/')   => 'image/jpeg',
            str_starts_with($this->image_small, 'iVBOR')  => 'image/png',
            str_starts_with($this->image_small, 'R0lGOD') => 'image/gif',
            str_starts_with($this->image_small, 'UklGR')  => 'image/webp',
            default                                       => 'image/svg+xml',
        };

        return "data:{$mime};base64,{$this->image_small}";
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class, 'odoo_employee_id', 'odoo_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'odoo_employee_id', 'odoo_id');
    }
}
