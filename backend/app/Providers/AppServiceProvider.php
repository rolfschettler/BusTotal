<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Tenancy\TenantManager::class);

        // Streckenplanung: Die Anwendung kennt nur die Schnittstellen. Ein anderer Dienst (GraphHopper, eigener
        // Server) wird hier statt OpenRouteService eingetragen.
        $this->app->singleton(\App\Routing\OpenRouteService::class);
        $this->app->alias(\App\Routing\OpenRouteService::class, \App\Routing\RoutingProvider::class);
        $this->app->alias(\App\Routing\OpenRouteService::class, \App\Routing\GeocodingProvider::class);

        // Preisfindung: Die Anwendung kennt nur die Schnittstelle. Das endgültige Kalkulationsschema wird eine neue Klasse,
        // die hier statt StandardPriceCalculator eingetragen wird.
        $this->app->bind(\App\Pricing\PriceCalculator::class, \App\Pricing\StandardPriceCalculator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
