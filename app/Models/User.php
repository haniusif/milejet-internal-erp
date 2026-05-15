<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Crypt;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'odoo_uid', 'odoo_api_key', 'password',
        'odoo_group_ids', 'roles', 'roles_synced_at',
    ];

    protected $hidden = ['password', 'remember_token', 'odoo_api_key'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
        'odoo_group_ids'    => 'array',
        'roles'             => 'array',
        'roles_synced_at'   => 'datetime',
    ];

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles ?? [], true);
    }

    public function hasAnyRole(array $roles): bool
    {
        return !empty(array_intersect($roles, $this->roles ?? []));
    }

    /** Convenience accessor for blade @if($user->is_admin) etc. */
    public function getIsAdminAttribute(): bool         { return $this->hasRole('admin'); }
    public function getIsHrManagerAttribute(): bool     { return $this->hasRole('hr_manager'); }
    public function getIsHrOfficerAttribute(): bool     { return $this->hasRole('hr_officer'); }
    public function getIsPayrollManagerAttribute(): bool{ return $this->hasRole('payroll_manager'); }
    public function getIsPayrollOfficerAttribute(): bool{ return $this->hasRole('payroll_officer'); }
    public function getIsLeaveManagerAttribute(): bool  { return $this->hasRole('leave_manager'); }

    /**
     * تشفير الـ API key تلقائياً عند الحفظ
     */
    public function setOdooApiKeyAttribute(?string $value): void
    {
        $this->attributes['odoo_api_key'] = $value ? Crypt::encryptString($value) : null;
    }

    /**
     * فك التشفير عند القراءة
     */
    public function getOdooApiKeyAttribute(?string $value): ?string
    {
        if (!$value) return null;

        try {
            return Crypt::decryptString($value);
        } catch (\Exception) {
            return null;
        }
    }
}
