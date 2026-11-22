<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BrandingController;
use App\Http\Controllers\Api\CustomerChildController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\DocumentPaymentController;
use App\Http\Controllers\Api\DriverAbsenceController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\DepotController;
use App\Http\Controllers\Api\PricingController;
use App\Http\Controllers\Api\RouteController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\Superadmin\AuthController as SuperadminAuthController;
use App\Http\Controllers\Api\Superadmin\TenantController as SuperadminTenantController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VehicleController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

// Betreiber-Oberfläche: gibt es nur an der Betreiber-Adresse (admin.<basis>), siehe ResolveTenant.
Route::prefix('superadmin')->group(function () {
    Route::post('/login', [SuperadminAuthController::class, 'login'])->middleware('throttle:5,1');

    Route::middleware(['auth:superadmin', 'superadmin.ok'])->group(function () {
        Route::post('/logout', [SuperadminAuthController::class, 'logout']);
        Route::get('/me', [SuperadminAuthController::class, 'me']);
        Route::post('/me/password', [SuperadminAuthController::class, 'changePassword'])->middleware('throttle:5,1');

        // Firmen werden nie gelöscht, nur gesperrt.
        Route::get('/tenants', [SuperadminTenantController::class, 'index']);
        Route::post('/tenants', [SuperadminTenantController::class, 'store']);
        Route::put('/tenants/{tenant}', [SuperadminTenantController::class, 'update']);
        Route::post('/tenants/{tenant}/migrate', [SuperadminTenantController::class, 'migrate']);
        Route::post('/tenants/{tenant}/admin', [SuperadminTenantController::class, 'createAdmin']);
        Route::get('/tenants/{tenant}/admins', [SuperadminTenantController::class, 'admins']);
        Route::post('/tenants/{tenant}/admins/{user}/password', [SuperadminTenantController::class, 'showAdminPassword']);
        Route::post('/tenants/{tenant}/admins/{user}/password/reset', [SuperadminTenantController::class, 'resetAdminPassword']);
    });
});

// Öffentlich: Name, Farbe und Logo für die Login-Seite.
Route::get('/branding', [BrandingController::class, 'show']);
Route::get('/branding/logo', [BrandingController::class, 'logo']);

// account.ok: gesperrte Benutzer fliegen sofort raus; wer noch das vorläufige Passwort hat,
// darf nur sein Passwort ändern.
Route::middleware(['auth:sanctum', 'account.ok'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/me/password', [AccountController::class, 'changePassword'])->middleware('throttle:5,1');

    // Kacheln der Startseite: jede mit eigener Rollenprüfung.
    Route::get('/dashboard/setup', [DashboardController::class, 'setup'])->middleware('role:admin');
    Route::get('/dashboard/recent-customers', [DashboardController::class, 'recentCustomers'])
        ->middleware('role:admin,disponent,buchhaltung');

    // Einstellungen: Admin, Disponent und Buchhaltung dürfen lesen, nur der Admin darf ändern.
    // Fahrer sehen sie nicht (Bankdaten, Steuernummer). Name, Farbe und Logo kommen für alle
    // über die öffentliche /branding-Schnittstelle.
    Route::middleware('role:admin,disponent,buchhaltung')->group(function () {
        Route::get('/settings/company', [SettingsController::class, 'company']);
        Route::get('/settings/tax-rates', [SettingsController::class, 'taxRates']);
        Route::get('/settings/number-ranges', [SettingsController::class, 'numberRanges']);
    });

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

    // Fuhrpark: Lesen für Admin, Disponent und Buchhaltung (Kostensatz), Ändern für Admin und Disponent.
    // Fahrer haben (noch) keinen Zugriff. Feste Pfade (options) stehen VOR /vehicles/{vehicle}.
    Route::middleware('role:admin,disponent,buchhaltung')->prefix('vehicles')->group(function () {
        Route::get('/', [VehicleController::class, 'index']);
        Route::get('/options', [VehicleController::class, 'options']);
        Route::get('/choices', [VehicleController::class, 'choices']);
        Route::get('/{vehicle}', [VehicleController::class, 'show'])->withTrashed();
    });
    Route::middleware('role:admin,disponent')->prefix('vehicles')->group(function () {
        Route::post('/', [VehicleController::class, 'store']);
        Route::put('/{vehicle}', [VehicleController::class, 'update']);
        Route::post('/{vehicle}/tachograph-read', [VehicleController::class, 'markTachographRead']);
        Route::delete('/{vehicle}', [VehicleController::class, 'destroy']);
    });
    Route::get('/dashboard/vehicle-deadlines', [DashboardController::class, 'vehicleDeadlines'])
        ->middleware('role:admin,disponent');

    // Fahrer und Personal: Personendaten, deshalb nur Admin und Disponent (lesen und ändern). Buchhaltung und Fahrer
    // haben keinen Zugriff. Wiederherstellen archivierter Fahrer nur der Admin (siehe unten).
    Route::middleware('role:admin,disponent')->prefix('drivers')->group(function () {
        Route::get('/', [DriverController::class, 'index']);
        Route::post('/', [DriverController::class, 'store']);
        Route::get('/options', [DriverController::class, 'options']);
        Route::get('/{driver}', [DriverController::class, 'show'])->withTrashed();
        Route::put('/{driver}', [DriverController::class, 'update']);
        Route::post('/{driver}/card-read', [DriverController::class, 'markCardRead']);
        Route::delete('/{driver}', [DriverController::class, 'destroy']);

        Route::post('/{driver}/absences', [DriverAbsenceController::class, 'store']);
        Route::put('/{driver}/absences/{absence}', [DriverAbsenceController::class, 'update']);
        Route::delete('/{driver}/absences/{absence}', [DriverAbsenceController::class, 'destroy']);
    });
    Route::get('/dashboard/driver-deadlines', [DashboardController::class, 'driverDeadlines'])
        ->middleware('role:admin,disponent');

    // Streckenplanung (Phase 5): Adresssuche und Tourberechnung über einen externen Routing-Dienst (Kontingent!),
    // deshalb begrenzt. Nur Admin und Disponent.
    // Die Ortssuche ist am knappsten (das Kontingent teilen sich alle Firmen): höchstens 30 je Minute und Benutzer.
    // Ein hängender Browser oder ein Skript kann so das gemeinsame Kontingent nicht leeren.
    // Preisfindung: Angebot (Entwurf) aus den Kennzahlen einer Tour. Preise lesen Admin, Disponent, Buchhaltung; ändern nur Admin.
    Route::post('/quotes/from-tour', [PricingController::class, 'quoteFromTour'])->middleware('role:admin,disponent');
    Route::get('/settings/pricing', [PricingController::class, 'show'])->middleware('role:admin,disponent,buchhaltung');
    Route::put('/settings/pricing', [PricingController::class, 'update'])->middleware('role:admin');

    // Betriebshöfe: lesen Admin, Disponent und Buchhaltung, ändern nur Admin.
    Route::get('/depots', [DepotController::class, 'index'])->middleware('role:admin,disponent,buchhaltung');
    Route::middleware('role:admin')->group(function () {
        Route::post('/depots', [DepotController::class, 'store']);
        Route::put('/depots/{depot}', [DepotController::class, 'update']);
        Route::delete('/depots/{depot}', [DepotController::class, 'destroy']);
    });

    Route::middleware('role:admin,disponent')->group(function () {
        Route::get('/geocode', [RouteController::class, 'geocode'])->middleware('throttle:30,1');
        Route::post('/routes/calculate', [RouteController::class, 'calculate'])->middleware('throttle:60,1');
    });

    // Belege (Phase 6): Entwürfe anlegen und bearbeiten dürfen Admin, Disponent und Buchhaltung.
    // Festschreiben (vergibt die Nummer, sperrt den Beleg) und Stornieren nur Admin und Buchhaltung.
    // Feste Pfade (types) stehen VOR /documents/{document}.
    Route::middleware('role:admin,disponent,buchhaltung')->prefix('documents')->group(function () {
        Route::get('/', [DocumentController::class, 'index']);
        Route::get('/types', [DocumentController::class, 'types']);
        Route::post('/', [DocumentController::class, 'store']);
        Route::get('/{document}', [DocumentController::class, 'show']);
        Route::get('/{document}/pdf', [DocumentController::class, 'pdf']);
        Route::get('/{document}/einvoice', [DocumentController::class, 'einvoice']);
        Route::get('/{document}/zugferd', [DocumentController::class, 'zugferd']);
        Route::put('/{document}', [DocumentController::class, 'update']);
        Route::delete('/{document}', [DocumentController::class, 'destroy']);
        Route::post('/{document}/convert', [DocumentController::class, 'convert']);
        Route::get('/{document}/payments', [DocumentPaymentController::class, 'index']);
    });
    Route::middleware('role:admin,buchhaltung')->prefix('documents')->group(function () {
        Route::post('/{document}/finalize', [DocumentController::class, 'finalize']);
        Route::post('/{document}/cancel', [DocumentController::class, 'cancel']);
        // Zahlungseingang und Rückzahlung buchen, Buchung stornieren (nie löschen)
        Route::post('/{document}/payments', [DocumentPaymentController::class, 'store']);
        Route::post('/{document}/payments/{payment}/cancel', [DocumentPaymentController::class, 'cancel']);
    });

    // DSGVO-Löschung (unwiderruflich): Administrator und Buchhaltung.
    Route::post('/customers/{customer}/anonymize', [CustomerController::class, 'anonymize'])
        ->middleware('role:admin,buchhaltung');

    Route::middleware('role:admin')->group(function () {
        // Benutzerverwaltung. Benutzer werden gesperrt, nie gelöscht.
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword']);

        Route::post('/customers/{id}/restore', [CustomerController::class, 'restore']);
        Route::post('/vehicles/{id}/restore', [VehicleController::class, 'restore']);
        Route::post('/drivers/{id}/restore', [DriverController::class, 'restore']);

        Route::put('/settings/company', [SettingsController::class, 'updateCompany']);
        Route::post('/settings/logo', [SettingsController::class, 'uploadLogo']);
        Route::delete('/settings/logo', [SettingsController::class, 'deleteLogo']);
        Route::post('/settings/tax-rates', [SettingsController::class, 'storeTaxRate']);
        Route::put('/settings/tax-rates/{taxRate}', [SettingsController::class, 'updateTaxRate']);
        Route::put('/settings/number-ranges/{numberRange}', [SettingsController::class, 'updateNumberRange']);
    });
});
