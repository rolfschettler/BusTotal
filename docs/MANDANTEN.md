# Mandanten (Firmen) – so funktioniert es

Jede Firma hat eine **eigene Datenbank**. Eine **zentrale Datenbank** kennt nur die Firmen und ihre Zugangsdaten.

| | Zentrale Datenbank (`central`) | Firmen-Datenbank (`tenant`) |
|---|---|---|
| Inhalt | Tabelle `tenants` (Firma, Unterdomäne, Zugangsdaten) | Kunden, Benutzer, Einstellungen, Token, Protokoll |
| Migrationen | `database/migrations/central/` | `database/migrations/` |
| Aufbau | beides erledigt `php artisan migrate` in einem Schritt (siehe „Befehle“) | |

**Ablauf einer Anfrage:** Host `muster.bustotal.de` → Unterdomäne `muster` → Zeile in `tenants` → Standardverbindung zeigt auf deren Datenbank
(`ResolveTenant`, läuft vor der Anmeldung). Unbekannte und gesperrte Firmen bekommen dieselbe 404-Antwort.
Ein Anmelde-Token gilt nur in der Datenbank seiner Firma, also nur auf deren Adresse.

**Sicherheitsnetz:** Die Standardverbindung ist ohne Mandant die zentrale Datenbank. Dort gibt es keine Kundentabellen,
ein vergessener Mandant führt also zu einem Fehler statt zu fremden Daten.

## Befehle

```bash
php artisan tenant:create "Muster Reisen" muster --db-name=... --db-user=... --db-password=... --admin-email=chef@muster.example
php artisan tenant:list
php artisan migrate                         # DER Befehl nach jedem Update: erst zentrale Datenbank, dann alle aktiven Firmen
php artisan tenant:migrate --tenant=muster  # nur eine Firma (Sonderfall)
php artisan bustotal:admin-anlegen chef@muster.example --tenant=muster
php artisan bustotal:demo-daten --tenant=muster
```

`tenant:create` prüft Zugangsdaten, verweigert reservierte Unterdomänen, Datenbanken die schon vergeben oder **nicht leer** sind
(Vertipper-Schutz) und die zentrale Datenbank. Die leere Datenbank legt man vorher im Kundencenter des Hosters an.

**`php artisan migrate`** ist in diesem Projekt ersetzt (`App\Console\Commands\Migrate`): Ohne Angaben auf der zentralen Verbindung
baut er die zentrale Datenbank und danach alle Firmen-Datenbanken auf. Das ursprüngliche Verhalten (Firmen-Tabellen in die zentrale Datenbank)
gibt es nur noch, wenn man es mit `--database` oder `--path` ausdrücklich verlangt.

## Wichtig
- Das Datenbank-Passwort steht verschlüsselt (mit `APP_KEY`) in `tenants`. **`APP_KEY` sichern** (getrennt vom Code), sonst sind alle Zugänge unlesbar.
- Dateien (Logo) liegen je Firma unter `storage/app/private/tenants/<id>/`.
- Lokal: `http://dev.localhost:4200` (moderne Browser lösen `*.localhost` selbst auf) oder `http://localhost:4200` mit `TENANT_LOCAL_FALLBACK=dev` (gilt nur in `local`).
- Reservierte Unterdomänen: siehe `config/tenancy.php`.

## Betreiber-Oberfläche (Superadmin)

Unter `admin.<basis-domäne>` (lokal `http://admin.localhost:4200`) läuft eine eigene Oberfläche für den Betreiber: Firmen anlegen,
bearbeiten, sperren, Datenbank aktualisieren, Administrator nachlegen. Sie sieht **nie** Daten einer Firma.

- **Eigene Welt:** Konten in `superadmins` (zentrale Datenbank), eigener Sanctum-Guard `superadmin`, eigene Token-Tabelle,
  eigenes Protokoll `superadmin_audit_logs` (nur anfügen). Token der Firmen und der Betreiber gelten gegenseitig nicht.
- **Strikt getrennte Adressen:** `/api/superadmin/*` gibt es nur an `admin.<basis>`, und dort gibt es nur das (404 sonst). `ResolveTenant` erzwingt das.
- **Frontend:** Dieselbe Angular-Anwendung. An der Betreiber-Adresse (`isSuperadminHost()` in `core/app-info.ts`) werden die Routen
  aus `src/app/superadmin/` geladen, sonst die Firmen-Routen. Dunkle Erkennungsfarbe, damit man sie nie mit einer Firma verwechselt.
- **Betreiber-Konto anlegen** (bewusst nur auf dem Server): `php artisan superadmin:anlegen <e-mail> "<Name>"`. Vorläufiges Passwort
  wird einmal angezeigt und muss beim ersten Anmelden geändert werden (mind. 12 Zeichen).
- **Keine Löschfunktion** für Firmen, nur Sperren (GoBD, Aufbewahrung).
- Gemeinsame Logik für Befehl und Oberfläche: `App\Tenancy\TenantProvisioner`.
- Zentrale Datenbank aufbauen: `php artisan migrate --database=central --path=database/migrations/central --force`
  (enthält auch `cache`, weil die Anmeldebremse den Zwischenspeicher braucht). In `.env` muss `DB_CONNECTION=central` stehen.
- Hilfe: `public/help/betreiber.md`, eigener Index `index-betreiber.json` (Firmen-Benutzer sehen dieses Thema nicht).

## Vorläufiges Passwort des Firmen-Administrators
- Spalte `users.temporary_password` (verschlüsselt mit `APP_KEY`, im Protokoll ausgeschlossen, nie in API-Antworten der Firma). Gefüllt nur, wenn der **Betreiber**
  den Administrator anlegt oder das Passwort neu vergibt (`TenantProvisioner`, `bustotal:admin-anlegen`). Gelöscht, sobald der Benutzer ein eigenes Passwort setzt
  (`AccountController::changePassword`) oder ein Firmen-Administrator es zurücksetzt.
- Betreiber-Oberfläche: Symbol Schlüssel in der Firmenliste → Administratoren → *Anzeigen* / *Neu vergeben*. Beides steht im Betreiber-Protokoll
  (`tenant_admin_password_shown`, `tenant_admin_password_reset`), ohne das Passwort selbst.
- Benutzer, die ein Firmen-Administrator anlegt, behalten das Muster „einmal anzeigen, nirgends gespeichert“.
- Neue Spalte: nach dem Update `php artisan migrate` ausführen.

## Noch offen
- Weitere Betreiber-Konten in der Oberfläche anlegen (heute nur per Befehl).
- Warteschlange und `schedule:run` müssen je Firma laufen (`TenantManager::each`) – mit Phase 8 (Cronjobs).
- Wildcard-Subdomain und SSL bei Mittwald klären (Fahrplan, Phase 0).
