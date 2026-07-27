<?php

use App\Http\Controllers\Api\V1\AuthController as V1Auth;
use App\Http\Controllers\Api\V1\CrmController as V1Crm;
use App\Http\Controllers\Api\V1\FinanceController as V1Finance;
use App\Http\Controllers\Api\V1\FleetController as V1Fleet;
use App\Http\Controllers\Api\V1\HrAdminController as V1HrAdmin;
use App\Http\Controllers\Api\V1\HrController as V1Hr;
use App\Http\Controllers\Api\V1\CompanyController as V1Company;
use App\Http\Controllers\Api\V1\LoanController as V1Loan;
use App\Http\Controllers\Api\V1\HrActionsController as V1HrActions;
use App\Http\Controllers\Api\V1\HrDocumentsController as V1HrDocuments;
use App\Http\Controllers\Api\V1\HrFormsController as V1HrForms;
use App\Http\Controllers\Api\V1\HrContractController as V1HrContract;
use App\Http\Controllers\Api\V1\OcrController as V1Ocr;
use App\Http\Controllers\Api\V1\UploadController as V1Upload;
use App\Http\Controllers\Api\V1\CourierDailyController as V1CourierDaily;
use App\Http\Controllers\Api\V1\RecruitmentController as V1Recruitment;
use App\Http\Controllers\MobileApiController;
use Illuminate\Support\Facades\Route;

/*
 * ─── v1 — SPA API (Sanctum stateful cookies) ───────────────────────────
 * Consumed by the Next.js frontend. Role scoping mirrors the web routes.
 */
Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [V1Auth::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me',      [V1Auth::class, 'me']);
        Route::post('/auth/logout', [V1Auth::class, 'logout']);

        // Temporary file upload → private storage/app/temp (authenticated).
        Route::post('/uploadfile', [V1Upload::class, 'store']);

        Route::prefix('hr')->controller(V1Hr::class)->group(function () {
            Route::get('/dashboard',   'dashboard');
            Route::get('/employees',   'employees');
            Route::get('/employees/export', 'exportEmployees')->middleware('can:hr.view_all');
            Route::get('/employees/{id}', 'employee')->whereNumber('id');
            Route::get('/departments', 'departments')->middleware('can:hr.view_all');
            Route::get('/alerts', 'alerts')->middleware('can:hr.view_all');
        });
        Route::get('/hr/courier-daily', [V1CourierDaily::class, 'index'])->middleware('can:hr.view_all');
        Route::prefix('hr')->controller(V1Hr::class)->group(function () {
            Route::get('/leaves',      'leaves');
            Route::get('/leaves/export', 'exportLeaves'); // scoped to own records inside the controller
            Route::post('/leaves',     'storeLeave');
            Route::get('/leave-types', 'leaveTypes');
            Route::middleware('can:leaves.approve')->group(function () {
                Route::post('/leaves/{id}/approve', 'approveLeave')->whereNumber('id');
                Route::post('/leaves/{id}/refuse',  'refuseLeave')->whereNumber('id');
            });
            Route::get('/attendances',           'attendances');
            Route::get('/attendances/export',    'exportAttendances'); // scoped to own records inside the controller
            Route::post('/attendances/check-in', 'checkIn');
            Route::post('/attendances/{id}/check-out', 'checkOut')->whereNumber('id');
            Route::get('/payslips',        'payslips');
            Route::get('/payslips/export', 'exportPayslips'); // scoped to own records inside the controller
            Route::get('/payslips/{id}',   'payslip')->whereNumber('id');
        });

        // Companies & branches (res.company)
        Route::prefix('hr')->controller(V1Company::class)->group(function () {
            Route::get('/companies', 'index')->middleware('can:hr.view_all');
            Route::middleware('can:companies.manage')->group(function () {
                Route::post('/companies',               'store');
                Route::put('/companies/{id}',           'update')->whereNumber('id');
                Route::post('/companies/{id}/archive',  'archive')->whereNumber('id');
                Route::post('/companies/{id}/restore',  'restore')->whereNumber('id');
            });
        });

        // Employee loans (Odoo hr.loan via mj_loan addon)
        Route::prefix('hr')->controller(V1Loan::class)->group(function () {
            Route::get('/loans', 'index'); // staff: all, employees: own
            Route::get('/loans/employees', 'employees')->middleware('can:loans.manage');
            Route::middleware('can:loans.manage')->group(function () {
                Route::post('/loans',                'store');
                Route::post('/loans/{id}/approve',   'approve')->whereNumber('id');
                Route::post('/loans/{id}/cancel',    'cancel')->whereNumber('id');
                Route::post('/loans/{id}/repayments', 'storeRepayment')->whereNumber('id');
                Route::delete('/loans/{id}',         'destroy')->whereNumber('id');
            });
        });

        // HR Actions: salary adjustments + payslip payments (mj_hr_actions addon)
        Route::prefix('hr')->controller(V1HrActions::class)->group(function () {
            Route::get('/adjustments', 'adjustments')->middleware('can:hr.view_all');
            Route::middleware('can:payslips.create')->group(function () {
                Route::post('/adjustments',              'storeAdjustment');
                Route::post('/adjustments/{id}/approve', 'approveAdjustment')->whereNumber('id');
                Route::post('/adjustments/{id}/cancel',  'cancelAdjustment')->whereNumber('id');
                Route::delete('/adjustments/{id}',       'destroyAdjustment')->whereNumber('id');
            });
            Route::get('/payslips/{id}/payments', 'payments')->whereNumber('id')->middleware('can:payslips.view');
            Route::middleware('can:payslips.create')->group(function () {
                Route::post('/payslips/{id}/payments', 'storePayment')->whereNumber('id');
                Route::delete('/payments/{id}',        'destroyPayment')->whereNumber('id');
            });
        });

        // Employee documents (Odoo hr.employee.document via mj_hr_documents addon)
        Route::prefix('hr')->controller(V1HrDocuments::class)->group(function () {
            Route::get('/documents', 'documents'); // staff: all/filtered, employees: own
            Route::get('/documents/{id}/download', 'downloadDocument')->whereNumber('id');
            Route::middleware('can:employees.write')->group(function () {
                Route::get('/documents/employees', 'employees');
                Route::post('/documents',          'storeDocument');
                Route::delete('/documents/{id}',   'destroyDocument')->whereNumber('id');
            });
        });

        // ID / licence OCR auto-fill
        Route::post('/hr/ocr/extract', [V1Ocr::class, 'extract'])->middleware('can:employees.write');

        // Contract PDF / renew / e-signature (mj_hr_contract addon)
        Route::prefix('hr')->controller(V1HrContract::class)->group(function () {
            Route::get('/contracts/{id}/pdf', 'pdf')->whereNumber('id');
            Route::post('/contracts/{id}/sign', 'sign')->whereNumber('id');
            Route::post('/contracts/{id}/renew', 'renew')->whereNumber('id')->middleware('can:employees.write');
        });

        // HR Forms: warnings / sick-leave / end-of-service (mj_hr_forms addon)
        Route::prefix('hr/forms')->controller(V1HrForms::class)->group(function () {
            Route::get('/{type}', 'index'); // staff: all/filtered, employees: own
            Route::get('/{type}/{id}/pdf', 'pdf')->whereNumber('id');
            Route::middleware('can:employees.write')->group(function () {
                Route::post('/{type}', 'store');
                Route::post('/{type}/{id}/action/{action}', 'action')->whereNumber('id');
                Route::delete('/{type}/{id}', 'destroy')->whereNumber('id');
            });
        });

        // HR admin/staff surface — gated like the web routes
        Route::prefix('hr')->controller(V1HrAdmin::class)->group(function () {
            Route::middleware('can:employees.write')->group(function () {
                Route::post('/employees',      'storeEmployee');
                Route::put('/employees/{id}',  'updateEmployee')->whereNumber('id');
                Route::get('/countries',       'countries');
                // Suspension / access management (archive keeps history)
                Route::get('/employees/{id}/access',        'employeeAccess')->whereNumber('id');
                Route::post('/employees/{id}/archive',      'archiveEmployee')->whereNumber('id');
                Route::post('/employees/{id}/restore',      'restoreEmployee')->whereNumber('id');
                Route::post('/employees/{id}/access',       'setEmployeeAccess')->whereNumber('id');
                Route::post('/employees/{id}/end-contract', 'endContract')->whereNumber('id');
                Route::post('/employees/{id}/geofence',     'setGeofence')->whereNumber('id');
                Route::post('/employees/{id}/extend-probation', 'extendProbation')->whereNumber('id');
            });
            Route::delete('/employees/{id}', 'destroyEmployee')->whereNumber('id')
                ->middleware('can:employees.delete');
            Route::get('/org-chart', 'orgChart')->middleware('can:hr.view_all');

            Route::get('/departments/full', 'departmentsFull')->middleware('can:hr.view_all');
            Route::middleware('can:departments.write')->group(function () {
                Route::post('/departments',     'storeDepartment');
                Route::put('/departments/{id}', 'updateDepartment')->whereNumber('id');
            });
            Route::delete('/departments/{id}', 'destroyDepartment')->whereNumber('id')
                ->middleware('can:departments.delete');

            Route::get('/work-locations', 'workLocations')->middleware('can:hr.view_all');
            Route::middleware('can:work_locations.write')->group(function () {
                Route::post('/work-locations',     'storeWorkLocation');
                Route::put('/work-locations/{id}', 'updateWorkLocation')->whereNumber('id');
            });
            Route::delete('/work-locations/{id}', 'destroyWorkLocation')->whereNumber('id')
                ->middleware('can:work_locations.delete');

            Route::get('/contracts', 'contracts')->middleware('can:contracts.view');
            Route::get('/contracts/export', 'exportContracts')->middleware('can:contracts.view');

            Route::middleware('can:payslips.create')->group(function () {
                Route::get('/payable-employees',     'payableEmployees');
                Route::post('/payslips',             'storePayslip');
                Route::post('/payslips/{id}/compute', 'computePayslip')->whereNumber('id');
            });
            Route::delete('/payslips/{id}', 'destroyPayslip')->whereNumber('id')
                ->middleware('can:payslips.delete');

            Route::get('/leaves/attachments',      'leaveAttachments');
            Route::get('/leaves/attachments/{id}', 'leaveAttachment')->whereNumber('id');
            Route::delete('/leaves/{id}', 'destroyLeave')->whereNumber('id')
                ->middleware('can:leaves.delete');

            Route::delete('/attendances/{id}', 'destroyAttendance')->whereNumber('id')
                ->middleware('role:admin,hr_manager');

            Route::post('/sync', 'sync')->middleware('can:sync.run');
        });

        Route::prefix('recruitment')->controller(V1Recruitment::class)
            ->middleware('can:recruitment.view')->group(function () {
            Route::get('/jobs',       'jobs');
            Route::get('/applicants', 'applicants');
            Route::middleware('can:recruitment.write')->group(function () {
                Route::post('/applicants',               'storeApplicant');
                Route::post('/applicants/{id}/stage',    'moveStage')->whereNumber('id');
                Route::post('/applicants/{id}/refuse',   'refuse')->whereNumber('id');
                Route::post('/applicants/{id}/restore',  'restore')->whereNumber('id');
            });
        });

        Route::prefix('crm')->controller(V1Crm::class)->middleware('can:crm.view')->group(function () {
            Route::get('/pipeline',       'pipeline');
            Route::get('/config',         'config');
            Route::get('/customers',      'customers');
            Route::get('/customers/{id}', 'customer360')->whereNumber('id');
            Route::get('/leads/{id}',     'leadDetail')->whereNumber('id');
            Route::middleware('can:crm.write')->group(function () {
                Route::post('/leads',               'storeLead');
                Route::put('/leads/{id}',           'updateLead')->whereNumber('id');
                Route::post('/leads/{id}/stage',    'moveStage')->whereNumber('id');
                Route::post('/leads/{id}/won',      'won')->whereNumber('id');
                Route::post('/leads/{id}/lost',     'lost')->whereNumber('id');
                Route::post('/leads/{id}/restore',  'restore')->whereNumber('id');
                Route::post('/leads/{id}/activities', 'storeActivity')->whereNumber('id');
                Route::post('/leads/{id}/note',       'storeNote')->whereNumber('id');
                Route::post('/activities/{aid}/done', 'doneActivity')->whereNumber('aid');
                Route::post('/customers',           'storeCustomer');
                Route::put('/customers/{id}',       'updateCustomer')->whereNumber('id');
            });
        });

        Route::prefix('fleet')->controller(V1Fleet::class)->middleware('can:fleet.view')->group(function () {
            Route::get('/vehicles',      'vehicles');
            Route::get('/vehicles/{id}', 'vehicle')->whereNumber('id');
            Route::get('/services',      'services');
            Route::get('/inspections',   'inspections');
            Route::get('/usages',        'usages');
            Route::get('/fuel',          'fuel');
            Route::get('/accidents',     'accidents');
            Route::get('/alerts',        'alerts');
            Route::get('/models',        'models');
            Route::get('/categories',    'categories');
            Route::get('/drivers',       'drivers');
            Route::middleware('can:fleet.write')->group(function () {
                Route::post('/vehicles/{id}/fuel',      'addFuel')->whereNumber('id');
                Route::post('/vehicles/{id}/accidents', 'addAccident')->whereNumber('id');
                Route::post('/accidents/{id}/{action}', 'accidentAction')
                    ->whereNumber('id')->whereIn('action', ['confirm', 'close', 'reset', 'file', 'approve', 'reject', 'paid']);
                Route::post('/vehicles',                'storeVehicle');
                Route::post('/vehicles/{id}/state',     'updateState')->whereNumber('id');
                Route::post('/vehicles/{id}/odometer',  'updateOdometer')->whereNumber('id');
                Route::post('/vehicles/{id}/driver',    'assignDriver')->whereNumber('id');
                Route::post('/vehicles/{id}/services',  'addService')->whereNumber('id');
                Route::post('/vehicles/{id}/inspections', 'addInspection')->whereNumber('id');
                Route::post('/inspection-lines/{lineId}', 'setInspectionLine')->whereNumber('lineId');
                Route::post('/inspections/{id}/{action}', 'inspectionAction')
                    ->whereNumber('id')->whereIn('action', ['confirm', 'draft', 'cancel', 'delete']);
                Route::post('/vehicles/{id}/usages', 'addUsage')->whereNumber('id');
                Route::post('/usages/{id}/{action}', 'usageAction')
                    ->whereNumber('id')->whereIn('action', ['pick', 'return', 'cancel']);
            });
        });

        Route::prefix('finance')->controller(V1Finance::class)->middleware('can:finance.view')->group(function () {
            Route::get('/invoices',      'invoices');
            Route::get('/bills',         'bills');
            Route::get('/invoices/{id}', 'show')->whereNumber('id');
        });
    });
});

// Public
Route::post('/mobile/login', [MobileApiController::class, 'login']);

// Protected with Sanctum
Route::middleware('auth:sanctum')->prefix('mobile')->group(function () {
    Route::post('/logout',    [MobileApiController::class, 'logout']);
    Route::get('/me',         [MobileApiController::class, 'me']);

    Route::get('/leaves',         [MobileApiController::class, 'leaves']);
    Route::get('/leave-types',    [MobileApiController::class, 'leaveTypes']);
    Route::post('/leaves',        [MobileApiController::class, 'createLeave']);
    Route::get('/leaves/attachments/{id}', [MobileApiController::class, 'leaveAttachment'])->whereNumber('id');

    Route::get('/attendance',         [MobileApiController::class, 'attendance']);
    Route::get('/attendance/config',  [MobileApiController::class, 'attendanceConfig']);
    Route::get('/attendance/current', [MobileApiController::class, 'currentAttendance']);
    Route::post('/attendance/check-in',       [MobileApiController::class, 'checkIn']);
    Route::post('/attendance/{id}/check-out', [MobileApiController::class, 'checkOut'])->whereNumber('id');

    Route::get('/payslips',     [MobileApiController::class, 'payslips']);
    Route::get('/payslips/{id}', [MobileApiController::class, 'payslip'])->whereNumber('id');

    Route::get('/notifications', [MobileApiController::class, 'notifications']);
});
