<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Applicant extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'active'           => 'boolean',
        'availability'     => 'date',
        'odoo_create_date' => 'datetime',
        'synced_at'        => 'datetime',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(JobPosition::class, 'odoo_job_id', 'odoo_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(RecruitmentStage::class, 'odoo_stage_id', 'odoo_id');
    }

    /** Refused applicants are archived in Odoo (active = false). */
    public function isRefused(): bool
    {
        return !$this->active;
    }

    public function isHired(): bool
    {
        return (bool) $this->stage?->hired_stage;
    }

    public function statusColor(): string
    {
        return match (true) {
            $this->isRefused() => 'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-900/30 dark:text-rose-300 dark:ring-rose-800',
            $this->isHired()   => 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:ring-emerald-800',
            default            => 'bg-sky-50 text-sky-700 ring-sky-200 dark:bg-sky-900/30 dark:text-sky-300 dark:ring-sky-800',
        };
    }

    public function statusLabel(): string
    {
        return match (true) {
            $this->isRefused() => __('Refused'),
            $this->isHired()   => __('Hired'),
            default            => $this->stage_name ?: __('New'),
        };
    }
}
