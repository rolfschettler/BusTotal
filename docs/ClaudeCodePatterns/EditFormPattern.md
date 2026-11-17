# CodePattern: Bearbeiten-/Anlegen-Formular

Das Muster für jedes Formular in BusTotal (Kunde, später Fahrzeuge, Fahrer …).
Herkunft: `D:\angular\DATACUR26\ClaudeCodePatterns\EditFormTemplate.html/.scss`, für BusTotal angepasst.

**Lebende Referenz:** `frontend/src/app/pages/customers/customer-form.component.(ts|html)`
(Stammdaten als Reactive Form, Ansprechpartner und Adressen als kleine Teil-Formulare).
**Globale Optik:** `frontend/src/styles/_edit-form.scss` (einmal definiert). **Kein eigenes CSS pro Seite.**

## Regeln

- Komponente mit `host: { class: 'form-page' }` (maximale Breite 960 px).
- Striktes Bootstrap-Raster, **ein** Breakpoint: nur `col-12 col-md-6`. Höchstens zwei Spalten, gepaarte Felder gleich breit.
  Bei ungerader Zahl bleibt ein Feld allein, kein Platzhalter daneben.
- **Floating Labels** (`.form-floating`) für alle Text-, Auswahl- und Datumsfelder. Das `<label>` steht **nach** dem Feld,
  das `placeholder`-Attribut ist Pflicht (auch wenn man es nie sieht). Kurze Hinweise stehen im Label („Land (z. B. DE)“)
  oder darunter als `form-text`, nicht als sichtbarer Platzhalter.
- Kontrollkästchen: normales `form-check`, ohne Floating Label.
- Abschnitte mit `<div class="col-12"><div class="section-title">…</div></div>`, keine Karten pro Abschnitt, kein `<hr>`.
- Pflichtfeld: rotes `*` im Label. Fehlermeldung als `invalid-feedback` direkt am Feld.
- **Fehler erst nach dem ersten Speichern-Versuch** markieren (`formSubmitted`). Gültige Felder werden nie grün (siehe `_edit-form.scss`).
  - Reactive Form: `[class.is-invalid]="invalid('feld')"`. Die Hilfsfunktion `invalid()` meldet auch Fehler, die der Server zurückgibt (`fieldErrors`).
  - Teil-Formular mit `ngModel`: natives `required`, `<form novalidate [class.was-validated]="…Submitted()">`, im Absenden `f.invalid` prüfen.
    Server-Fehler zusätzlich über `[class.is-invalid]`.
- **Meldungen ganz oben** (ein `message`-Signal, Hinweis unter der Titelzeile). Weil „Speichern“ weit unten sitzt, setzt `notify()` die Meldung
  **und** scrollt mit `scrollIntoView` am eigenen Host nach oben, nicht am Fenster.
- Beim Laden eines vorhandenen Datensatzes Spinner (`loading`), nicht ein halbleeres Formular.
- Tasten: **Speichern links** (`btn-primary`, mit Spinner beim Speichern), **seltene Aktionen rechts** (`ms-auto`, `btn-outline-secondary`),
  Archivieren statt Löschen (GoBD). DSGVO-Aktionen stehen unter dem Formular, abgesetzt durch eine Linie.
- Archivierte Datensätze: Formular gesperrt (`form.disable()`), kein Speichern-Knopf.

## Unterschiede zum DATACUR26-Original

| Original | BusTotal | Grund |
|---|---|---|
| Template-Formular mit `ngModel` und `setField` | Reactive Forms (Stammdaten) | Bereits im Projekt, Typ-abhängige Pflichtfelder (Firma/Privat) |
| Pflichtfeld nur über natives `required` | Zusätzlich `invalid()` für Reactive Forms und Server-Fehler | Reactive Validatoren setzen kein `:invalid` ohne natives Attribut |
| `.section-title` in `--bs-primary` | Firmenfarbe (`--brand`) | Jede Firma hat ihre eigene Farbe |
| Löschen (rechts) | Archivieren / Wiederherstellen (rechts) | Kunden werden nie gelöscht |
| Fehler-Signal `errorMessage` | `message` mit Erfolg und Fehler | Auch „Gespeichert.“ muss sichtbar werden |
| Grüne gültige Felder nach Absenden | Unterdrückt | Nur Fehler sollen auffallen |

## Prüfung

Es gibt keine Tests für Formular-Optik. Logik (Pflichtfelder, `invalid()`) bleibt in der Komponente klein. Ein Formular gilt erst als fertig,
wenn der Nutzer es im Browser angesehen hat.
