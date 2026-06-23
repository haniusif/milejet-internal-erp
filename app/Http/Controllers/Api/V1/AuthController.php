<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OdooAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * SPA auth — Sanctum stateful cookies. Flow:
 *   GET  /sanctum/csrf-cookie   → XSRF-TOKEN cookie
 *   POST /api/v1/auth/login     → Laravel session cookie
 *   GET  /api/v1/auth/me        → user + abilities (SPA nav/permissions)
 *   POST /api/v1/auth/logout
 */
class AuthController extends Controller
{
    /** Gates the SPA needs to decide what to render. Keep in sync with AppServiceProvider. */
    protected const ABILITIES = [
        'hr.view_all', 'employees.write', 'employees.delete', 'employees.view_sensitive',
        'leaves.approve', 'leaves.delete', 'contracts.view',
        'payslips.view', 'payslips.create', 'payslips.delete',
        'loans.view', 'loans.manage', 'companies.manage',
        'recruitment.view', 'recruitment.write', 'sync.run',
        'crm.view', 'crm.write', 'fleet.view', 'fleet.write',
        'finance.view', 'finance.write',
    ];

    public function __construct(protected OdooAuthService $auth) {}

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = $this->auth->attempt($credentials['email'], $credentials['password']);
        if (!$user) {
            return response()->json(['message' => __('Invalid email or password in Odoo.')], 422);
        }

        Auth::guard('web')->login($user, remember: true);
        $request->session()->regenerate();

        return response()->json(['user' => $this->userPayload($user)]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'ok']);
    }

    protected function userPayload(User $user): array
    {
        $abilities = [];
        foreach (self::ABILITIES as $ability) {
            $abilities[$ability] = $user->can($ability);
        }

        $emp = $user->employeeRecord();

        return [
            'id'        => $user->id,
            'name'      => $user->name,
            'email'     => $user->email,
            'roles'     => $user->roles ?? [],
            'abilities' => $abilities,
            'employee'  => $emp ? [
                'id'         => $emp->id,
                'odoo_id'    => $emp->odoo_id,
                'name'       => $emp->name,
                'job_title'  => $emp->job_title,
                'department' => $emp->department_name,
                'avatar'     => $emp->avatar_data_uri,
            ] : null,
        ];
    }
}
