# Mandanten (Firmen) – so funktioniert es

Jede Firma hat eine **eigene Datenbank**. Eine **zentrale Datenbank** kennt nur die Firmen und ihre Zugangsdaten.

| | Zentrale Datenbank (`central`) | Firmen-Datenbank (`tenant`) |
|---|---|---|
| Inhalt | Tabelle `tenants` (Firma, Unterdomäne, Zugangsdaten) | Kunden, Benutzer, Einstellungen, Token, Protokoll |
| Migrationen | `database/migrations/central/` | `database/migrations/` |
| Aufbau | `php artisan migrate --database=central --path=database/migrations/central --force` | `php artisan tenant:migrate` |

**Ablauf einer Anfrage:** Host `muster.bustotal.de` → Unterdomäne `muster` → Zeile in `tenants` → Standardverbindung zeigt auf deren Datenbank
(`ResolveTenant`, läuft vor der Anmeldung). Unbekannte und gesperrte Firmen bekommen dieselbe 404-Antwort.
Ein Anmelde-Token gilt nur in der Datenbank seiner Firma, also nur auf deren Adresse.

**Sicherheitsnetz:** Die Standardverbindung ist ohne Mandant die zentrale Datenbank. Dort gibt es keine Kundentabellen,
ein vergessener Mandant führt also zu einem Fehler statt zu fremden Daten.

## Befehle

```bash
php artisan tenant:create "Muster Reisen" muster --db-name=... --db-user=... --db-password=... --admin-email=chef@muster.example
php artisan tenant:list
php artisan tenant:migrate                  # alle aktiven Firmen, nach jedem Update
php artisan tenant:migrate --tenant=muster  # nur eine
php artisan bustotal:admin-anlegen chef@muster.example --tenant=muster
php artisan bustotal:demo-daten --tenant=muster
```

`tenant:create` prüft Zugangsdaten, verweigert reservierte Unterdomänen, Datenbanken die schon vergeben oder **nicht leer** sind
(Vertipper-Schutz) und die zentrale Datenbank. Die leere Datenbank legt man vorher im Kundencenter des Hosters an.

**Nicht** `php artisan migrate` ohne Angaben benutzen: Das würde Firmen-Tabellen in der zentralen Datenbank anlegen.

## Wichtig
- Das Datenbank-Passwort steht verschlüsselt (mit `APP_KEY`) in `tenants`. **`APP_KEY` sichern** (getrennt vom Code), sonst sind alle Zugänge unlesbar.
- Dateien (Logo) liegen je Firma unter `storage/app/private/tenants/<id>/`.
- Lokal: `http://dev.localhost:4200` (moderne Browser lösen `*.localhost` selbst auf) oder `http://localhost:4200` mit `TENANT_LOCAL_FALLBACK=dev` (gilt nur in `local`).
- Reservierte Unterdomänen: siehe `config/tenancy.php`.

## Noch offen
- Superadmin-Oberfläche (Firma anlegen per Maske), mit eigener Anmeldung unter einer reservierten Adresse.
- Warteschlange und `schedule:run` müssen je Firma laufen (`TenantManager::each`) – mit Phase 8 (Cronjobs).
- Wildcard-Subdomain und SSL bei Mittwald klären (Fahrplan, Phase 0).
