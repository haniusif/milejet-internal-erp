<?php

use Illuminate\Support\Facades\Route;

/*
 * crm.milejet.space — CRM module (placeholder until built).
 * NOTE: this answers only once the nginx vhost for crm.milejet.space is
 * repointed to this app; today that subdomain serves the legacy CRM.
 */
Route::domain(config('domains.crm'))
    ->middleware('auth')
    ->name('crm.')
    ->group(function () {
        Route::view('/', 'crm.home')->name('home');
    });
