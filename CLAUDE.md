# BusTotal – Hinweise für Claude

- Listenseiten folgen dem Tabellen-Muster: `docs/ClaudeCodePatterns/TableViewPattern.md` (Vorlagen `TableViewTemplate.html/.scss` im selben Ordner).
- Formulare folgen **verbindlich** dem Formular-Muster: `docs/ClaudeCodePatterns/EditFormPattern.md` (Vorlagen `EditFormTemplate.html/.scss`).
  Kurz: Label oben (keine Floating Labels, Labels kurz und einzeilig, Hinweise als form-text darunter), weiße Bootstrap-Felder auf grauer `.form-area` (Seite hell), Tastenleiste `.form-bar` sticky unten in Formularbreite,
  links Speichern und Zurück, rechts seltene Aktion und Abbrechen. Feldbreiten immer über das Bootstrap-Raster (`col-lg-3/4/6/8/12`, Zeile = 12), nie eigene Breiten. Abweichungen nur nach Absprache. Neue Formulare ohne Design-Rückfragen nach dem Ablauf im Muster bauen; Designfehler im Muster beheben, nicht im einzelnen Formular.
- Suchen markieren Treffer immer mit `<app-highlight>`.
- Prüfungen im Browser und Start von Servern nur auf ausdrückliche Anweisung des Nutzers.
- „BusTotal“ ist nur ein **Arbeitsname**. Produktnamen nie fest in Texte schreiben: Frontend `APP_NAME` (`core/app-info.ts`), Hilfetexte `{{app}}`,
  Backend `config('app.name')`. Umbenennung: `docs/UMBENENNUNG.md`.
- Fahrplan und Stand: `docs/FAHRPLAN.md`.
- Fehlermeldungen von Server-Anfragen immer über `httpErrorText(err, 'Ersatztext')` aus `core/http-error.ts`. Es trennt „Server nicht erreichbar“ (keine Antwort, Proxy- oder Gateway-Fehler) von Eingabefehlern, Rechten (403), Sperre (429) und Serverfehlern. Nie eigene `err.error?.message ?? '…'`-Ketten schreiben.
