<?php

use Illuminate\Support\Facades\Route;

/*
 * fleet.milejet.space — Fleet module (placeholder until built).
 * NOTE: no DNS record exists for fleet.* yet (only fleets.*, which serves
 * the legacy app). Add the record / repoint nginx when ready.
 */
Route::domain(config('domains.fleet'))
    ->middleware('auth')
    ->name('fleet.')
    ->group(function () {
        Route::view('/', 'fleet.home')->name('home');
    });
