<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\CrmController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\FleetController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\PayslipController;
use App\Http\Controllers\PreferencesController;
use App\Http\Controllers\RecruitmentController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\WorkLocationController;
use Illuminate\Support\Facades\Route;

/*
 * Domain-bound module roots (portal hub, CRM, Fleet) — registered first
 * so they win the '/' match on their own subdomains. Everything below is
 * domain-agnostic and answers on any host pointed at this app (today
 * portal.milejet.space; later hr.milejet.space too).
 */
require __DIR__.'/portal.php';
require __DIR__.'/crm.php';
require __DIR__.'/fleet.php';
require __DIR__.'/finance.php';

Route::get('/login',   [AuthController::class, 'showLogin'])->name('login');
Route::post('/login',  [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/locale/{locale}', [PreferencesController::class, 'setLocale'])
    ->whereIn('locale', ['ar', 'en'])->name('preferences.locale');
Route::get('/theme/{theme}', [PreferencesController::class, 'setTheme'])
    ->whereIn('theme', ['light', 'dark'])->name('preferences.theme');

Route::middleware('auth')->group(function () {

    // '/' on the portal host is the hub (routes/portal.php); on every
    // other host it falls through to here and lands on the dashboard.
    Route::redirect('/', '/dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/sync', [DashboardController::class, 'sync'])
        ->middleware('can:sync.run')->name('sync');

    Route::prefix('employees')->name('employees.')->controller(EmployeeController::class)->group(function () {
        Route::get('/',     'index')->name('index'); // plain employees are redirected to their own profile
        Route::get('/export', 'export')->middleware('can:hr.view_all')->name('export');
        Route::get('/org-chart', 'orgChart')->middleware('can:hr.view_all')->name('org-chart');
        Route::middleware('can:employees.write')->group(function () {
            Route::get('/create',    'create')->name('create');
            Route::post('/',         'store')->name('store');
            Route::get('/{id}/edit', 'edit')->whereNumber('id')->name('edit');
            Route::put('/{id}',      'update')->whereNumber('id')->name('update');
        });
        Route::get('/{id}', 'show')->whereNumber('id')->name('show'); // staff: any profile; plain employees: own only
        Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy')
            ->middleware('can:employees.delete');
    });

    // HR settings hub — reference data cards (Countries first)
    Route::prefix('settings')->name('settings.')->controller(SettingsController::class)
        ->middleware('can:hr.view_all')->group(function () {
        Route::get('/',          'index')->name('index');
        Route::get('/countries', 'countries')->name('countries');
        Route::get('/config',    'config')->middleware('can:config.view')->name('config');
    });

    Route::prefix('departments')->name('departments.')->controller(DepartmentController::class)->group(function () {
        Route::get('/', 'index')->middleware('can:hr.view_all')->name('index');
        Route::middleware('can:departments.write')->group(function () {
            Route::get('/create',    'create')->name('create');
            Route::post('/',         'store')->name('store');
            Route::get('/{id}/edit', 'edit')->whereNumber('id')->name('edit');
            Route::put('/{id}',      'update')->whereNumber('id')->name('update');
        });
        Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy')
            ->middleware('can:departments.delete');
    });

    Route::prefix('work-locations')->name('work-locations.')->controller(WorkLocationController::class)->group(function () {
        Route::get('/', 'index')->middleware('can:hr.view_all')->name('index');
        Route::middleware('can:work_locations.write')->group(function () {
            Route::get('/create',    'create')->name('create');
            Route::post('/',         'store')->name('store');
            Route::get('/{id}/edit', 'edit')->whereNumber('id')->name('edit');
            Route::put('/{id}',      'update')->whereNumber('id')->name('update');
        });
        Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy')
            ->middleware('can:work_locations.delete');
    });

    Route::prefix('leaves')->name('leaves.')->controller(LeaveController::class)->group(function () {
        Route::get('/',       'index')->name('index');
        Route::get('/export', 'export')->name('export'); // scoped to own records inside the controller
        Route::get('/create', 'create')->name('create');
        Route::get('/attachments/{id}', 'attachment')->whereNumber('id')->name('attachment');
        Route::post('/',      'store')->name('store');  // any employee can request
        Route::middleware('can:leaves.approve')->group(function () {
            Route::post('/{id}/approve', 'approve')->whereNumber('id')->name('approve');
            Route::post('/{id}/refuse',  'refuse')->whereNumber('id')->name('refuse');
        });
        Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy')
            ->middleware('can:leaves.delete');
    });

    Route::prefix('attendances')->name('attendances.')->controller(AttendanceController::class)->group(function () {
        Route::get('/',                'index')->name('index');
        Route::get('/export',          'export')->name('export'); // scoped to own records inside the controller
        Route::post('/check-in',       'checkIn')->name('check-in');
        Route::post('/{id}/check-out', 'checkOut')->whereNumber('id')->name('check-out');
        Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy')
            ->middleware('role:admin,hr_manager');
    });

    Route::prefix('recruitment')->name('recruitment.')->controller(RecruitmentController::class)
        ->middleware('can:recruitment.view')->group(function () {
        Route::get('/', 'jobs')->name('jobs');
        Route::get('/applicants', 'applicants')->name('applicants');
        Route::middleware('can:recruitment.write')->group(function () {
            Route::get('/applicants/create',       'createApplicant')->name('applicants.create');
            Route::post('/applicants',             'storeApplicant')->name('applicants.store');
            Route::post('/applicants/{id}/stage',  'moveStage')->whereNumber('id')->name('applicants.stage');
            Route::post('/applicants/{id}/refuse', 'refuse')->whereNumber('id')->name('applicants.refuse');
            Route::post('/applicants/{id}/restore', 'restore')->whereNumber('id')->name('applicants.restore');
        });
    });

    Route::prefix('crm')->name('crm.')->controller(CrmController::class)
        ->middleware('can:crm.view')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/customers', 'customers')->name('customers');
        Route::middleware('can:crm.write')->group(function () {
            Route::get('/leads/create',        'createLead')->name('leads.create');
            Route::post('/leads',              'storeLead')->name('leads.store');
            Route::post('/leads/{id}/stage',   'moveStage')->whereNumber('id')->name('leads.stage');
            Route::post('/leads/{id}/won',     'won')->whereNumber('id')->name('leads.won');
            Route::post('/leads/{id}/lost',    'lost')->whereNumber('id')->name('leads.lost');
            Route::post('/leads/{id}/restore', 'restore')->whereNumber('id')->name('leads.restore');
            Route::get('/customers/create',    'createCustomer')->name('customers.create');
            Route::post('/customers',          'storeCustomer')->name('customers.store');
        });
    });

    Route::prefix('fleet')->name('fleet.')->controller(FleetController::class)
        ->middleware('can:fleet.view')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/services', 'services')->name('services');
        Route::middleware('can:fleet.write')->group(function () {
            Route::get('/vehicles/create',        'create')->name('vehicles.create');
            Route::post('/vehicles',              'store')->name('vehicles.store');
            Route::post('/vehicles/{id}/state',   'updateState')->whereNumber('id')->name('vehicles.state');
            Route::post('/vehicles/{id}/odometer', 'updateOdometer')->whereNumber('id')->name('vehicles.odometer');
            Route::post('/vehicles/{id}/driver',  'assignDriver')->whereNumber('id')->name('vehicles.driver');
            Route::post('/vehicles/{id}/services', 'addService')->whereNumber('id')->name('vehicles.services.store');
        });
        Route::get('/vehicles/{id}', 'show')->whereNumber('id')->name('vehicles.show');
    });

    Route::prefix('finance')->name('finance.')->controller(FinanceController::class)
        ->middleware('can:finance.view')->group(function () {
        Route::get('/', 'index')->name('invoices');
        Route::get('/bills', 'bills')->name('bills');
        Route::get('/{id}', 'show')->whereNumber('id')->name('show');
    });

    Route::prefix('contracts')->name('contracts.')->controller(ContractController::class)->group(function () {
        Route::get('/', 'index')->middleware('can:contracts.view')->name('index');
        Route::get('/export', 'export')->middleware('can:contracts.view')->name('export');
    });

    Route::prefix('payslips')->name('payslips.')->controller(PayslipController::class)->group(function () {
        // Payroll/HR see everything; everyone else is scoped to their own
        // payslips inside the controller.
        Route::get('/',       'index')->name('index');
        Route::get('/export', 'export')->name('export'); // scoped to own records inside the controller
        Route::get('/{id}',   'show')->whereNumber('id')->name('show');
        Route::middleware('can:payslips.create')->group(function () {
            Route::get('/create',        'create')->name('create');
            Route::post('/',             'store')->name('store');
            Route::post('/{id}/compute', 'compute')->whereNumber('id')->name('compute');
        });
        Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy')
            ->middleware('can:payslips.delete');
    });
});
