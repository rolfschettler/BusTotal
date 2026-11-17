<?php

/*
| Mandantenfähigkeit: Eine Codebasis, je Firma (Mandant) eine eigene Datenbank.
|
| - Die ZENTRALE Datenbank (Verbindung "central") kennt nur die Mandanten und deren Zugangsdaten.
| - Die Daten einer Firma (Kunden, Benutzer, Einstellungen ...) liegen ausschließlich in deren
|   eigener Datenbank (Verbindung "tenant", wird je Anfrage auf die Firma eingestellt).
*/

return [

    // Aus: Es gibt nur eine Datenbank (Standardverbindung). Nur für Tests und die allererste Entwicklung.
    'enabled' => (bool) env('TENANCY_ENABLED', true),

    'central_connection' => 'central',
    'tenant_connection' => 'tenant',

    // Der Mandant steht im Host: <unterdomäne>.<basis-domäne>, z. B. firma.bustotal.de
    // Lokal: firma.localhost (moderne Browser lösen *.localhost selbst auf).
    'base_domain' => env('TENANT_BASE_DOMAIN', 'localhost'),

    // Nur in der lokalen Entwicklung: Mandant, wenn der Host keine Unterdomäne hat (z. B. http://localhost:4200).
    // Auf einem echten Server wird dieser Wert bewusst ignoriert.
    'local_fallback' => env('TENANT_LOCAL_FALLBACK'),

    // Diese Unterdomänen sind für BusTotal selbst reserviert (z. B. die spätere Superadmin-Oberfläche).
    'reserved_subdomains' => ['www', 'admin', 'superadmin', 'api', 'app', 'mail', 'ftp', 'status', 'hilfe', 'central'],
];
