<?php

use Illuminate\Support\Facades\Route;

/*
 * finance.milejet.space — root lands on the Finance module (paths live under
 * /finance in web.php, domain-agnostic, so the module also works via portal.*).
 */
Route::domain(config('domains.finance'))
    ->group(function () {
        Route::redirect('/', '/finance')->name('finance.home');
    });
