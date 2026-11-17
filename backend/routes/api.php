<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerChildController;
use App\Http\Controllers\Api\CustomerController;
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

    // Kunden: Admin, Disponent und Buchhaltung. Fahrer haben keinen Zugriff.
    // Feste Pfade (duplicates, types) müssen VOR /customers/{customer} stehen.
    Route::middleware('role:admin,disponent,buchhaltung')->prefix('customers')->group(function () {
        Route::get('/', [CustomerController::class, 'index']);
        Route::post('/', [CustomerController::class, 'store']);
        Route::get('/duplicates', [CustomerController::class, 'duplicates']);
        Route::get('/types', [CustomerController::class, 'types']);
        Route::get('/{customer}', [CustomerController::class, 'show'])->withTrashed();
        Route::put('/{customer}', [CustomerController::class, 'update']);
        Route::delete('/{customer}', [CustomerController::class, 'destroy']);
        Route::get('/{customer}/export', [CustomerController::class, 'export'])->withTrashed();

        Route::post('/{customer}/contacts', [CustomerChildController::class, 'storeContact']);
        Route::put('/{customer}/contacts/{contact}', [CustomerChildController::class, 'updateContact']);
        Route::delete('/{customer}/contacts/{contact}', [CustomerChildController::class, 'destroyContact']);
        Route::post('/{customer}/addresses', [CustomerChildController::class, 'storeAddress']);
        Route::put('/{customer}/addresses/{address}', [CustomerChildController::class, 'updateAddress']);
        Route::delete('/{customer}/addresses/{address}', [CustomerChildController::class, 'destroyAddress']);
    });

    Route::middleware('role:admin')->group(function () {
        Route::post('/customers/{id}/restore', [CustomerController::class, 'restore']);
        Route::post('/customers/{customer}/anonymize', [CustomerController::class, 'anonymize']);

        Route::put('/settings/company', [SettingsController::class, 'updateCompany']);
        Route::post('/settings/tax-rates', [SettingsController::class, 'storeTaxRate']);
        Route::put('/settings/tax-rates/{taxRate}', [SettingsController::class, 'updateTaxRate']);
        Route::put('/settings/number-ranges/{numberRange}', [SettingsController::class, 'updateNumberRange']);
    });
});
