<?php

use Illuminate\Support\Facades\Route;

/*
 * crm.milejet.space — root lands on the CRM module (paths live under /crm
 * in web.php, domain-agnostic, so the module also works via portal.*).
 * NOTE: this answers only once the nginx vhost for crm.milejet.space is
 * repointed to this app; today that subdomain serves the legacy CRM.
 */
Route::domain(config('domains.crm'))
    ->group(function () {
        Route::redirect('/', '/crm')->name('crm.home');
    });
