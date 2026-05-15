<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\OdooRoleMapper;
use App\Services\OdooService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function __construct(
        protected OdooService $odoo,
        protected OdooRoleMapper $roleMapper,
    ) {}

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        // محاولة المصادقة على Odoo
        $uid = $this->odoo->tryAuthenticate(
            $credentials['email'],
            $credentials['password']
        );

        if (!$uid) {
            return back()->withErrors([
                'email' => __('Invalid email or password in Odoo.'),
            ])->withInput($request->except('password'));
        }

        $this->odoo->setCredentials($credentials['email'], $credentials['password'], $uid);

        // Fetch name + groups in one go
        $name = $credentials['email'];
        $groupIds = [];
        try {
            $odooUserData = $this->odoo->read('res.users', [$uid], ['name', 'groups_id']);
            if (!empty($odooUserData[0]['name'])) {
                $name = $odooUserData[0]['name'];
            }
            $groupIds = $odooUserData[0]['groups_id'] ?? [];
        } catch (\Exception) {
            // fall through with defaults
        }

        // Resolve roles from Odoo groups
        $roles = ['employee'];
        try {
            if (!empty($groupIds)) {
                $groups = $this->odoo->read('res.groups', $groupIds, ['name', 'category_id']);
                $roles = $this->roleMapper->rolesFromGroups($groups);
            }
        } catch (\Exception) {
            // keep default
        }

        $user = User::updateOrCreate(
            ['odoo_uid' => $uid],
            [
                'name'             => $name,
                'email'            => $credentials['email'],
                'odoo_api_key'     => $credentials['password'],
                'odoo_group_ids'   => $groupIds,
                'roles'            => $roles,
                'roles_synced_at'  => now(),
            ]
        );

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'))
            ->with('status', "مرحباً {$user->name}!");
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
