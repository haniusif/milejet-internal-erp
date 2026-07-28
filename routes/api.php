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
use App\Http\Controllers\Api\V1\HrRequestController as V1HrRequest;
use App\Http\Controllers\Api\V1\HrAppraisalController as V1HrAppraisal;
use App\Http\Controllers\Api\V1\TrainingController as V1Training;
use App\Http\Controllers\Api\V1\CrmContractController as V1CrmContract;
use App\Http\Controllers\Api\V1\CrmTicketController as V1CrmTicket;
use App\Http\Controllers\Api\V1\NewsController as V1News;
use App\Http\Controllers\Api\V1\MobileDomainController as V1MobileDomain;
use App\Http\Controllers\Api\V1\FleetKpiController as V1FleetKpi;
use App\Http\Controllers\Api\V1\RecruitmentController as V1Recruitment;
use App\Http\Controllers\MobileApiController;
use Illuminate\Support\Facades\Route;

/*
 * ─── v1 — SPA API (Sanctum stateful cookies) ───────────────────────────
 * Consumed by the Next.js frontend. Role scoping mirrors the web routes.
 */
// Public Android app download (shown on the login page).
Route::get('/download/app', [App\Http\Controllers\AppDownloadController::class, 'download']);
Route::get('/download/app/info', [App\Http\Controllers\AppDownloadController::class, 'info']);

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

        // Employee self-service requests (mj_hr_ess) — scoping inside the controller.
        Route::prefix('hr')->controller(V1HrRequest::class)->group(function () {
            Route::get('/requests',              'index');
            Route::get('/requests/config',       'config');
            Route::get('/requests/{id}/certificate', 'certificate')->whereNumber('id');
            Route::post('/requests',             'store');
            Route::post('/requests/{id}/submit', 'submit')->whereNumber('id');
            Route::post('/requests/{id}/{action}', 'action')->whereNumber('id')
                ->whereIn('action', ['approve', 'refuse', 'issue', 'cancel', 'reset']);
            Route::get('/expenses',  'expenses');
            Route::post('/expenses', 'storeExpense');
        });

        // Company news / announcements (Laravel-only) — read open, manage = hr.view_all.
        Route::prefix('hr')->controller(V1News::class)->group(function () {
            Route::get('/news',        'index');
            Route::post('/news',       'store');
            Route::put('/news/{id}',   'update')->whereNumber('id');
            Route::delete('/news/{id}', 'destroy')->whereNumber('id');
        });

        // Performance appraisals (mj_hr_performance) — scoping inside the controller.
        Route::prefix('hr')->controller(V1HrAppraisal::class)->group(function () {
            Route::get('/appraisals',            'index');
            Route::get('/appraisals/{id}',       'show')->whereNumber('id');
            Route::post('/appraisals',           'store');
            Route::post('/appraisals/{id}/open', 'open')->whereNumber('id');
            Route::put('/appraisals/{id}/lines', 'updateLines')->whereNumber('id');
            Route::post('/appraisals/{id}/reward', 'reward')->whereNumber('id');
            Route::post('/appraisals/{id}/{action}', 'action')->whereNumber('id')
                ->whereIn('action', ['submit', 'finalize', 'cancel', 'reset']);
            Route::get('/recognition',  'recognitions');
            Route::post('/recognition', 'storeRecognition');
        });

        // Training & development (mj_hr_training) — scoping inside the controller.
        Route::prefix('hr/training')->controller(V1Training::class)->group(function () {
            Route::get('/courses',  'courses');
            Route::post('/courses', 'storeCourse');
            Route::get('/skills',   'skills');
            Route::get('/sessions', 'sessions');
            Route::post('/sessions', 'storeSession');
            Route::get('/sessions/{id}', 'showSession')->whereNumber('id');
            Route::post('/sessions/{id}/{action}', 'sessionAction')->whereNumber('id')
                ->whereIn('action', ['confirm', 'start', 'close', 'cancel', 'reset']);
            Route::post('/enroll', 'enroll');
            Route::post('/enrollments/{id}/{action}', 'enrollmentAction')->whereNumber('id')
                ->whereIn('action', ['attend', 'complete', 'fail', 'no_show', 'cancel', 'reset']);
            Route::get('/enrollments/{id}/certificate', 'certificate')->whereNumber('id');
            Route::get('/mine', 'myTraining');
            Route::get('/needs', 'needs');
            Route::post('/needs/{id}/{action}', 'needAction')->whereNumber('id')
                ->whereIn('action', ['plan', 'close']);
        });
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

        // Customer service contracts (mj_crm_contract) — gates inside the controller.
        Route::prefix('crm')->controller(V1CrmContract::class)->middleware('can:crm.view')->group(function () {
            Route::get('/contracts',       'index');
            Route::get('/contracts/products', 'products');
            Route::get('/contracts/{id}',  'show')->whereNumber('id');
            Route::post('/contracts',      'store');
            Route::put('/contracts/{id}',  'update')->whereNumber('id');
            Route::post('/contracts/from-lead/{leadId}', 'fromLead')->whereNumber('leadId');
            Route::post('/contracts/{id}/invoice', 'invoice')->whereNumber('id');
            Route::post('/contracts/{id}/{action}', 'action')->whereNumber('id')
                ->whereIn('action', ['confirm', 'renew', 'close', 'cancel', 'reset']);
        });

        // Support tickets (mj_crm_helpdesk) — gates inside the controller.
        Route::prefix('crm')->controller(V1CrmTicket::class)->middleware('can:crm.view')->group(function () {
            Route::get('/tickets',        'index');
            Route::get('/tickets/config', 'config');
            Route::get('/tickets/stats',  'stats');
            Route::get('/tickets/{id}',   'show')->whereNumber('id');
            Route::post('/tickets',       'store');
            Route::put('/tickets/{id}',   'update')->whereNumber('id');
            Route::post('/tickets/{id}/{action}', 'action')->whereNumber('id')
                ->whereIn('action', ['assign', 'wait', 'resume', 'resolve', 'close', 'reopen', 'cancel', 'reset']);
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
            Route::get('/kpi',           [V1FleetKpi::class, 'kpi']);
            Route::get('/kpi/drivers',   [V1FleetKpi::class, 'drivers']);
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

    Route::get('/news', [V1News::class, 'feed']);

    // CRM / Fleet / Finance domain feeds (read-only, gated by view ability).
    Route::get('/crm/leads',        [V1MobileDomain::class, 'crmLeads']);
    Route::get('/crm/customers',    [V1MobileDomain::class, 'crmCustomers']);
    Route::get('/fleet/vehicles',   [V1MobileDomain::class, 'fleetVehicles']);
    Route::get('/finance/invoices', [V1MobileDomain::class, 'financeInvoices']);
    Route::get('/finance/expenses', [V1MobileDomain::class, 'financeExpenses']);
    Route::get('/dashboard',        [V1MobileDomain::class, 'dashboard']);

    // CRM detail/actions — reuse the web CrmController (reads crm.view, writes crm.write).
    Route::middleware('can:crm.view')->group(function () {
        Route::get('/crm/config',        [V1Crm::class, 'config']);
        Route::get('/crm/leads/{id}',    [V1Crm::class, 'leadDetail'])->whereNumber('id');
        Route::get('/crm/customers/{id}', [V1Crm::class, 'customer360'])->whereNumber('id');
        Route::middleware('can:crm.write')->group(function () {
            Route::post('/crm/leads',               [V1Crm::class, 'storeLead']);
            Route::put('/crm/leads/{id}',           [V1Crm::class, 'updateLead'])->whereNumber('id');
            Route::post('/crm/leads/{id}/won',      [V1Crm::class, 'won'])->whereNumber('id');
            Route::post('/crm/leads/{id}/lost',     [V1Crm::class, 'lost'])->whereNumber('id');
            Route::post('/crm/leads/{id}/activities', [V1Crm::class, 'storeActivity'])->whereNumber('id');
            Route::post('/crm/leads/{id}/note',     [V1Crm::class, 'storeNote'])->whereNumber('id');
        });
    });

    Route::get('/notifications', [MobileApiController::class, 'notifications']);
    Route::post('/notifications/read', [MobileApiController::class, 'markNotificationsRead']);
    Route::post('/notifications/test', [MobileApiController::class, 'testPush']);

    // Push device registration (FCM)
    Route::post('/device-token',   [MobileApiController::class, 'registerDevice']);
    Route::delete('/device-token', [MobileApiController::class, 'unregisterDevice']);

    // Gap-fill: reuse the existing employee-scoped controllers (same Sanctum guard,
    // so scoping/validation is identical to the web app — single source of truth).
    // ESS requests (mj_hr_ess)
    Route::get('/requests',              [V1HrRequest::class, 'index']);
    Route::get('/requests/config',       [V1HrRequest::class, 'config']);
    Route::post('/requests',             [V1HrRequest::class, 'store']);
    Route::post('/requests/{id}/submit', [V1HrRequest::class, 'submit'])->whereNumber('id');
    Route::post('/requests/{id}/cancel', [V1HrRequest::class, 'action'])->whereNumber('id')->defaults('action', 'cancel');
    Route::get('/requests/{id}/certificate', [V1HrRequest::class, 'certificate'])->whereNumber('id');

    // Performance appraisals (mj_hr_performance) — self-assessment
    Route::get('/appraisals',            [V1HrAppraisal::class, 'index']);
    Route::get('/appraisals/{id}',       [V1HrAppraisal::class, 'show'])->whereNumber('id');
    Route::put('/appraisals/{id}/lines', [V1HrAppraisal::class, 'updateLines'])->whereNumber('id');
    Route::post('/appraisals/{id}/submit', [V1HrAppraisal::class, 'action'])->whereNumber('id')->defaults('action', 'submit');
    Route::get('/recognition',           [V1HrAppraisal::class, 'recognitions']);

    // Training (mj_hr_training)
    Route::get('/training',              [V1Training::class, 'myTraining']);
    Route::get('/training/courses',      [V1Training::class, 'courses']);
    Route::get('/training/sessions',     [V1Training::class, 'sessions']);
    Route::post('/training/enroll',      [V1Training::class, 'enroll']);
    Route::get('/training/enrollments/{id}/certificate', [V1Training::class, 'certificate'])->whereNumber('id');

    // Employee loans (mj_loan) — read own; request a draft loan.
    Route::get('/loans',  [V1Loan::class, 'index']);
    Route::post('/loans', [MobileApiController::class, 'requestLoan']);

    // Employee documents (mj_hr_documents)
    Route::get('/documents',             [V1HrDocuments::class, 'documents']);
    Route::get('/documents/{id}/download', [V1HrDocuments::class, 'downloadDocument'])->whereNumber('id');

    // Manager approvals — inbox + reuse the existing gated approve/refuse actions.
    Route::get('/approvals', [MobileApiController::class, 'approvals']);
    Route::post('/approvals/leaves/{id}/approve', [V1Hr::class, 'approveLeave'])->whereNumber('id')->middleware('can:leaves.approve');
    Route::post('/approvals/leaves/{id}/refuse',  [V1Hr::class, 'refuseLeave'])->whereNumber('id')->middleware('can:leaves.approve');
    Route::post('/approvals/requests/{id}/approve', [V1HrRequest::class, 'action'])->whereNumber('id')->defaults('action', 'approve')->middleware('can:hr.view_all');
    Route::post('/approvals/requests/{id}/refuse',  [V1HrRequest::class, 'action'])->whereNumber('id')->defaults('action', 'refuse')->middleware('can:hr.view_all');
});
