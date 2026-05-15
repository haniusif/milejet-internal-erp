<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\PayslipController;
use App\Http\Controllers\PreferencesController;
use Illuminate\Support\Facades\Route;

Route::get('/login',   [AuthController::class, 'showLogin'])->name('login');
Route::post('/login',  [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/locale/{locale}', [PreferencesController::class, 'setLocale'])
    ->whereIn('locale', ['ar', 'en'])->name('preferences.locale');
Route::get('/theme/{theme}', [PreferencesController::class, 'setTheme'])
    ->whereIn('theme', ['light', 'dark'])->name('preferences.theme');

Route::middleware('auth')->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/sync', [DashboardController::class, 'sync'])
        ->middleware('can:sync.run')->name('sync');

    Route::prefix('employees')->name('employees.')->controller(EmployeeController::class)->group(function () {
        Route::get('/',     'index')->name('index'); // any authenticated user can browse
        Route::middleware('can:employees.write')->group(function () {
            Route::get('/create',    'create')->name('create');
            Route::post('/',         'store')->name('store');
            Route::get('/{id}/edit', 'edit')->whereNumber('id')->name('edit');
            Route::put('/{id}',      'update')->whereNumber('id')->name('update');
        });
        Route::get('/{id}', 'show')->whereNumber('id')->name('show'); // public profile
        Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy')
            ->middleware('can:employees.delete');
    });

    Route::prefix('departments')->name('departments.')->controller(DepartmentController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::middleware('can:departments.write')->group(function () {
            Route::get('/create',    'create')->name('create');
            Route::post('/',         'store')->name('store');
            Route::get('/{id}/edit', 'edit')->whereNumber('id')->name('edit');
            Route::put('/{id}',      'update')->whereNumber('id')->name('update');
        });
        Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy')
            ->middleware('can:departments.delete');
    });

    Route::prefix('leaves')->name('leaves.')->controller(LeaveController::class)->group(function () {
        Route::get('/',       'index')->name('index');
        Route::get('/create', 'create')->name('create');
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
        Route::post('/check-in',       'checkIn')->name('check-in');
        Route::post('/{id}/check-out', 'checkOut')->whereNumber('id')->name('check-out');
        Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy')
            ->middleware('role:admin,hr_manager');
    });

    Route::prefix('contracts')->name('contracts.')->controller(ContractController::class)->group(function () {
        Route::get('/', 'index')->middleware('can:contracts.view')->name('index');
    });

    Route::prefix('payslips')->name('payslips.')->controller(PayslipController::class)->group(function () {
        Route::get('/',     'index')->middleware('can:payslips.view')->name('index');
        Route::get('/{id}', 'show')->whereNumber('id')->middleware('can:payslips.view')->name('show');
        Route::middleware('can:payslips.create')->group(function () {
            Route::get('/create',        'create')->name('create');
            Route::post('/',             'store')->name('store');
            Route::post('/{id}/compute', 'compute')->whereNumber('id')->name('compute');
        });
        Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy')
            ->middleware('can:payslips.delete');
    });
});
