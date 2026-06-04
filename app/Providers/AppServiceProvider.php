<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Action-level gates. The closures receive the authenticated User.
        // Admin always wins (Gate::before doesn't exist in this app, so we
        // include admin explicitly in each list — clearer for blade @can).
        $gateMap = [
            'employees.write'   => ['admin', 'hr_manager', 'hr_officer'],
            'employees.delete'  => ['admin', 'hr_manager'],
            'departments.write' => ['admin', 'hr_manager'],
            'departments.delete'=> ['admin', 'hr_manager'],
            'work_locations.write'  => ['admin', 'hr_manager'],
            'work_locations.delete' => ['admin', 'hr_manager'],
            'leaves.approve'    => ['admin', 'leave_manager', 'hr_manager'],
            'leaves.delete'     => ['admin', 'hr_manager', 'leave_manager'],
            'contracts.view'    => ['admin', 'hr_manager', 'hr_officer', 'payroll_manager', 'payroll_officer'],
            'payslips.view'     => ['admin', 'payroll_manager', 'payroll_officer', 'hr_manager'],
            'payslips.create'   => ['admin', 'payroll_manager', 'payroll_officer'],
            'payslips.delete'   => ['admin', 'payroll_manager'],
            'sync.run'          => ['admin', 'hr_manager', 'payroll_manager'],
        ];

        foreach ($gateMap as $ability => $allowedRoles) {
            Gate::define($ability, fn (User $user) => $user->hasAnyRole($allowedRoles));
        }
    }
}
