<?php

namespace App\Providers;

use App\Services\OdooService;
use App\Services\SyncService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class OdooServiceProvider extends ServiceProvider
{
    /**
     * نسجّل OdooService كـ singleton ونعيد ضبطه ليستخدم بيانات المستخدم المسجّل دخول
     */
    public function register(): void
    {
        $this->app->singleton(OdooService::class, function ($app) {
            $service = new OdooService();

            // إذا فيه مستخدم مسجل دخول، استخدم بياناته
            if (Auth::check()) {
                $user = Auth::user();
                if ($user->odoo_uid && $user->odoo_api_key) {
                    $service->setCredentials(
                        $user->email,
                        $user->odoo_api_key,
                        $user->odoo_uid
                    );
                }
            }

            return $service;
        });

        $this->app->singleton(SyncService::class, function ($app) {
            return new SyncService($app->make(OdooService::class));
        });
    }

    public function boot(): void {}
}
