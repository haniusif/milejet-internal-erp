<?php

use Illuminate\Support\Facades\Route;

/*
 * finance.milejet.space — Finance module (placeholder until built).
 */
Route::domain(config('domains.finance'))
    ->middleware('auth')
    ->name('finance.')
    ->group(function () {
        Route::view('/', 'finance.home')->name('home');
    });
