<?php

namespace App\Services;

use App\Models\ServiceCredential;

/**
 * Welcher Zugangsschlüssel gilt für einen Dienst? Zuerst der eigene Schlüssel der Firma (Datenbank), sonst als Rückfall der
 * allgemeine Schlüssel des Servers (.env). Sonst keiner. Dadurch teilen sich Firmen mit eigenem Schlüssel kein Kontingent.
 */
class ServiceKeys
{
    public const OPENROUTESERVICE = 'openrouteservice';

    /** Dienste, deren Schlüssel die Firma eintragen kann. */
    public const SERVICES = [self::OPENROUTESERVICE];

    /** @return array{key: ?string, source: 'own'|'shared'|'none'} */
    public function resolve(string $service): array
    {
        $own = ServiceCredential::where('service', $service)->value('api_key');
        if (is_string($own) && $own !== '') {
            return ['key' => $own, 'source' => 'own'];
        }

        $shared = $this->shared($service);

        return $shared ? ['key' => $shared, 'source' => 'shared'] : ['key' => null, 'source' => 'none'];
    }

    /** Gibt es einen allgemeinen Schlüssel des Servers (Rückfall)? */
    public function hasShared(string $service): bool
    {
        return $this->shared($service) !== null;
    }

    private function shared(string $service): ?string
    {
        $key = $service === self::OPENROUTESERVICE ? config('services.openrouteservice.key') : null;

        return is_string($key) && $key !== '' ? $key : null;
    }

    public function save(string $service, string $key): void
    {
        ServiceCredential::updateOrCreate(['service' => $service], ['api_key' => trim($key)]);
    }

    public function forget(string $service): void
    {
        ServiceCredential::where('service', $service)->delete();
    }

    /** Die letzten vier Zeichen zur Wiedererkennung, nie der ganze Schlüssel. */
    public function hint(string $key): string
    {
        return '…'.substr($key, -4);
    }
}
