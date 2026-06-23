<?php

namespace App\Http\Controllers;

use App\Services\OdooAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function __construct(protected OdooAuthService $auth) {}

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

        // المصادقة على Odoo + تحديث المستخدم المحلي (منطق مشترك مع API الـ SPA)
        $user = $this->auth->attempt($credentials['email'], $credentials['password']);

        if (!$user) {
            return back()->withErrors([
                'email' => __('Invalid email or password in Odoo.'),
            ])->withInput($request->except('password'));
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'))
            ->with('status', __('Welcome, :name!', ['name' => $user->name]));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
