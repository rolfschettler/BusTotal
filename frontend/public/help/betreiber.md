# Betreiber-Oberfläche {#betreiber}

Diese Oberfläche ist für den **Betreiber** von {{app}}, nicht für die Firmen. Sie erreichen sie unter der Betreiber-Adresse (`admin.` vor der Hauptadresse). Hier legen Sie Firmen an und halten sie in Ordnung. Die Daten der Firmen (Kunden, Benutzer, Einstellungen) sehen Sie hier **nie**: Jede Firma hat ihre eigene Datenbank.

## Firmenliste {#firmenliste}

Unter **Firmen** sehen Sie alle Firmen mit Adresse, Datenbank, Status und dem Zeitpunkt, an dem ihre Datenbank zuletzt aktualisiert wurde.

- **Suchen und filtern:** Das Suchfeld durchsucht Name, Adresse und Datenbank, die Treffer sind gelb markiert. Der Filter *Status* zeigt nur aktive oder nur gesperrte Firmen.
- **Sortieren:** Ein Klick auf eine Spaltenüberschrift sortiert, ein zweiter dreht die Richtung um. „Noch nie“ steht in beiden Richtungen am Ende.
- **Symbole in der ersten Spalte:**
  - der **Stift** bearbeitet die Firma,
  - die **Datenbank mit Pfeil** [aktualisiert die Datenbank](#aktualisieren),
  - die **Person mit Plus** [legt einen Administrator an](#administrator),
  - das **Schloss** sperrt die Firma (das geöffnete Schloss entsperrt sie wieder).

Firmen werden **nie gelöscht**. Die Daten gehören dem Kunden und müssen aufbewahrt werden. Wer nicht mehr zahlt, wird gesperrt.

## Neue Firma anlegen {#anlegen}

1. Legen Sie im Kundencenter des Hosters eine **leere Datenbank** für die Firma an und notieren Sie Server, Datenbankname, Benutzer und Passwort.
2. Wählen Sie **Neue Firma** und tragen Sie Name, Unterdomäne und die Datenbank-Zugangsdaten ein, dazu die E-Mail-Adresse des ersten Administrators.
3. Wählen Sie **Firma anlegen**. Das dauert einige Sekunden: Das System prüft den Zugang, baut die Tabellen auf und richtet Steuersätze und Nummernkreise ein.

Die **Unterdomäne** wird Teil der Adresse der Firma (`muster` wird zu `muster.<Hauptadresse>`). Sie kann später nicht geändert werden, weil die Firma ihre Adresse benutzt. Reservierte Namen wie `admin` oder `www` sind nicht erlaubt.

Das System lehnt eine Datenbank ab, die schon einer anderen Firma gehört, die zentrale Datenbank ist oder **nicht leer** ist. Das schützt vor Vertippern, bei denen sonst Daten durcheinander kämen.

### Die Datenbank {#datenbank}

Die Zugangsdaten werden **verschlüsselt** gespeichert und nie wieder angezeigt. Beim Bearbeiten bedeutet ein leeres Passwortfeld: unverändert. Geänderte Zugangsdaten werden vor dem Speichern geprüft, damit eine falsche Angabe die Firma nicht lahmlegt. Den **Datenbanknamen** können Sie nicht ändern, dort liegen die Daten.

### Der erste Administrator und das vorläufige Passwort {#vorlaeufiges-passwort}

Das System erzeugt für den ersten Administrator ein **vorläufiges Passwort** und zeigt es beim Anlegen an. Geben Sie es dem Administrator persönlich weiter, nicht per E-Mail. Beim ersten Anmelden muss er sich ein eigenes Passwort vergeben.

Solange er das noch nicht getan hat, bleibt das vorläufige Passwort **verschlüsselt aufbewahrt**. Über das Symbol *Schlüssel* (Administratoren) in der Firmenliste können Sie es jederzeit wieder einblenden und erneut nennen. Sobald der Administrator sein eigenes Passwort gesetzt hat, wird das vorläufige gelöscht, danach ist nichts mehr einsehbar. Jedes Einblenden steht im Betreiber-Protokoll.

Ist nichts mehr aufbewahrt (zum Beispiel bei einer älteren Firma) oder soll ein Passwort ersetzt werden, wählen Sie bei dem Administrator *Neu vergeben*: Das alte Passwort und seine Anmeldungen werden sofort ungültig, und es gibt ein neues vorläufiges Passwort.

## Datenbank aktualisieren {#aktualisieren}

Nach jedem Update der Software brauchen die Firmen-Datenbanken neue Tabellen oder Felder. Das Symbol *Datenbank aktualisieren* erledigt das für eine Firma. Vorhandene Daten bleiben erhalten. Für alle Firmen auf einmal gibt es auf dem Server den Befehl `php artisan tenant:migrate`.

## Administrator anlegen {#administrator}

Ist der Zugang einer Firma verloren, vergeben Sie dem vorhandenen Administrator über das Symbol *Schlüssel* ein neues vorläufiges Passwort, wie [oben beschrieben](#vorlaeufiges-passwort). Mit der *Person mit Plus* legen Sie einen zusätzlichen Administrator an; die E-Mail-Adresse darf in dieser Firma noch nicht vorkommen.

## Firma sperren {#sperren}

Mit dem Schloss (nach einer Rückfrage) ist die Firma sofort nicht mehr erreichbar, auch nicht von bereits angemeldeten Geräten. Die Daten bleiben unverändert. Mit dem geöffneten Schloss entsperren Sie sie wieder.

## Ihr Konto und Passwort {#passwort}

Betreiber-Konten werden bewusst nur auf dem Server angelegt (`php artisan superadmin:anlegen`). Beim ersten Anmelden mit dem vorläufigen Passwort **müssen** Sie ein eigenes vergeben: mindestens **12 Zeichen**, davon mindestens ein Buchstabe und eine Ziffer. Das Ändern beendet alle anderen Anmeldungen. Über *Passwort ändern* im Benutzermenü ändern Sie es später jederzeit.

Alle Aktionen (Anmelden, Firmen anlegen, sperren, aktualisieren) werden in einem eigenen, nur anfügbaren Protokoll festgehalten.
