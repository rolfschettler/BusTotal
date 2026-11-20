# BusTotal – Hinweise für Claude

- Listenseiten folgen dem Tabellen-Muster: `docs/ClaudeCodePatterns/TableViewPattern.md` (Vorlagen `TableViewTemplate.html/.scss` im selben Ordner).
- Formulare folgen **verbindlich** dem Formular-Muster: `docs/ClaudeCodePatterns/EditFormPattern.md` (Vorlagen `EditFormTemplate.html/.scss`).
  Kurz: Label oben (keine Floating Labels, Labels kurz und einzeilig, Hinweise als form-text darunter), weiße Bootstrap-Felder auf grauer `.form-area` (Seite hell), Tastenleiste `.form-bar` sticky unten in Formularbreite,
  links Speichern und Zurück, rechts seltene Aktion und Abbrechen. Feldbreiten immer über das Bootstrap-Raster (`col-lg-3/4/6/8/12`, Zeile = 12), nie eigene Breiten. Abweichungen nur nach Absprache. Neue Formulare ohne Design-Rückfragen nach dem Ablauf im Muster bauen; Designfehler im Muster beheben, nicht im einzelnen Formular.
- Suchen markieren Treffer immer mit `<app-highlight>`.
- Prüfungen im Browser und Start von Servern nur auf ausdrückliche Anweisung des Nutzers.
- „BusTotal“ ist nur ein **Arbeitsname**. Produktnamen nie fest in Texte schreiben: Frontend `APP_NAME` (`core/app-info.ts`), Hilfetexte `{{app}}`,
  Backend `config('app.name')`. Umbenennung: `docs/UMBENENNUNG.md`.
- **Orte und Koordinaten:** Jeder Datensatz mit Adresse, der in einer Geo-Funktion vorkommen kann (Betriebshof, Kunde, Haltestelle, Reiseziel), verweist mit `place_id` auf die zentrale Tabelle `places` (`HasPlace`, `PlaceResolver::assign()`). Nie `lat`/`lon` in Fachtabellen, nie direkt beim Adressdienst anfragen, wenn der Datensatz gespeichert wird: Koordinaten werden immer gespeichert, eine bekannte Adresse wird nie erneut gesucht. Muster und Anbindung: `docs/STRECKENPLANUNG.md` (Gespeicherte Orte), Beispiel `Depot`. Die Lizenzfrage zum Speichern ist vom Nutzer entschieden (immer speichern), nicht erneut aufwerfen.
- Fahrplan und Stand: `docs/FAHRPLAN.md`.
- **Neue oder geänderte Migrationen führt Claude selbst aus**, sofort nach dem Schreiben und vor Tests und Build: `php artisan migrate` im Ordner `backend`. Der Befehl ist ersetzt (`app/Console/Commands/Migrate.php`) und baut zentrale Datenbank und alle Firmen auf, siehe `docs/MANDANTEN.md`. Das Ergebnis („N Firma(en) migriert, 0 mit Fehler“) im Abschluss nennen. Der Nutzer migriert nie von Hand.
- Fehlermeldungen von Server-Anfragen immer über `httpErrorText(err, 'Ersatztext')` aus `core/http-error.ts`. Es trennt „Server nicht erreichbar“ (keine Antwort, Proxy- oder Gateway-Fehler) von Eingabefehlern, Rechten (403), Sperre (429) und Serverfehlern. Nie eigene `err.error?.message ?? '…'`-Ketten schreiben.
