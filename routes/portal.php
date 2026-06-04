<?php

use Illuminate\Support\Facades\Route;

/*
 * portal.milejet.space — the hub. Its root shows the app-switcher page;
 * every other path falls through to the shared (domain-agnostic) routes
 * in web.php, so the full HR module keeps working on this host.
 */
Route::domain(config('domains.portal'))
    ->middleware('auth')
    ->group(function () {
        Route::view('/', 'portal.home')->name('portal.home');
    });
