<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SettingsController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Einstellungen: alle Angemeldeten dürfen lesen, nur der Admin darf ändern.
    Route::get('/settings/company', [SettingsController::class, 'company']);
    Route::get('/settings/tax-rates', [SettingsController::class, 'taxRates']);
    Route::get('/settings/number-ranges', [SettingsController::class, 'numberRanges']);

    Route::middleware('role:admin')->group(function () {
        Route::put('/settings/company', [SettingsController::class, 'updateCompany']);
        Route::post('/settings/tax-rates', [SettingsController::class, 'storeTaxRate']);
        Route::put('/settings/tax-rates/{taxRate}', [SettingsController::class, 'updateTaxRate']);
        Route::put('/settings/number-ranges/{numberRange}', [SettingsController::class, 'updateNumberRange']);
    });
});
