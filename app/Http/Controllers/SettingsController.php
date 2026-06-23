<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\Department;
use App\Models\SyncLog;
use App\Models\WorkLocation;
use App\Services\OdooService;
use Illuminate\Http\Request;
use Throwable;

class SettingsController extends Controller
{
    /** HR settings hub — one card per reference-data area. */
    public function index()
    {
        $counts = [
            'countries'   => Country::count(),
            'departments' => Department::count(),
            'offices'     => WorkLocation::count(),
        ];
        return view('settings.index', compact('counts'));
    }

    public function countries(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $countries = Country::query()
            ->when($q !== '', fn ($query) => $query
                ->where('name', 'like', "%{$q}%")
                ->orWhere('code', 'like', "%{$q}%"))
            ->withCount('employees')
            ->orderBy('name')
            ->get();

        return view('settings.countries', compact('countries', 'q'));
    }

    /** Read-only system configuration (admin only — gated in routes). */
    public function config(OdooService $odoo)
    {
        // Live Odoo connectivity check — degrade gracefully if Odoo is down.
        try {
            $odooUid    = $odoo->useServiceAccount()->authenticate();
            $odooStatus = ['ok' => true, 'detail' => "uid {$odooUid}"];
        } catch (Throwable $e) {
            $odooStatus = ['ok' => false, 'detail' => $e->getMessage()];
        }

        $odooConfig = [
            'url'        => config('odoo.url'),
            'db'         => config('odoo.db'),
            'username'   => config('odoo.username'),
            'api_key'    => config('odoo.api_key') ? '••••••••' : __('Not set'),
            'verify_ssl' => config('odoo.verify_ssl'),
        ];

        $attendance = [
            'geofence_radius'  => config('attendance.geofence_radius'),
            'geofence_enforce' => config('attendance.geofence_enforce'),
        ];

        $domains = collect(config('domains'))->only(['portal', 'hr', 'crm', 'fleet', 'finance']);

        $app = [
            'env'      => config('app.env'),
            'debug'    => config('app.debug'),
            'timezone' => config('app.timezone'),
            'locale'   => config('app.locale'),
            'laravel'  => app()->version(),
            'php'      => PHP_VERSION,
        ];

        // Latest sync log per model
        $syncLogs = SyncLog::whereIn('id', SyncLog::selectRaw('MAX(id)')->groupBy('model'))
            ->orderBy('model')->get();

        return view('settings.config', compact('odooConfig', 'odooStatus', 'attendance', 'domains', 'app', 'syncLogs'));
    }
}
