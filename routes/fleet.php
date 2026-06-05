<?php

use Illuminate\Support\Facades\Route;

/*
 * fleet.milejet.space — root lands on the Fleet module (paths live under
 * /fleet in web.php, domain-agnostic, so the module also works via portal.*).
 */
Route::domain(config('domains.fleet'))
    ->group(function () {
        Route::redirect('/', '/fleet')->name('fleet.home');
    });
