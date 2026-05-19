<?php

use App\Http\Controllers\MobileApiController;
use Illuminate\Support\Facades\Route;

// Public
Route::post('/mobile/login', [MobileApiController::class, 'login']);

// Protected with Sanctum
Route::middleware('auth:sanctum')->prefix('mobile')->group(function () {
    Route::post('/logout',    [MobileApiController::class, 'logout']);
    Route::get('/me',         [MobileApiController::class, 'me']);

    Route::get('/leaves',         [MobileApiController::class, 'leaves']);
    Route::get('/leave-types',    [MobileApiController::class, 'leaveTypes']);
    Route::post('/leaves',        [MobileApiController::class, 'createLeave']);

    Route::get('/attendance',         [MobileApiController::class, 'attendance']);
    Route::get('/attendance/current', [MobileApiController::class, 'currentAttendance']);
    Route::post('/attendance/check-in',       [MobileApiController::class, 'checkIn']);
    Route::post('/attendance/{id}/check-out', [MobileApiController::class, 'checkOut'])->whereNumber('id');

    Route::get('/payslips',     [MobileApiController::class, 'payslips']);
    Route::get('/payslips/{id}', [MobileApiController::class, 'payslip'])->whereNumber('id');

    Route::get('/notifications', [MobileApiController::class, 'notifications']);
});
