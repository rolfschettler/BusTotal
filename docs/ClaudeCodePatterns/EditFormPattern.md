# CodePattern: Bearbeiten-/Anlegen-Formular (verbindlicher Standard)

Das Muster für **jedes** Formular in BusTotal. Abweichungen nur nach Absprache mit dem Nutzer und mit Eintrag in diesem Dokument.
Stand 2026-11-28 (zweiter Entwurf, Vorlage `docs/redesign/vorschlag-2.html`).
**Ausgelegt für große PC-Bildschirme** (Maßstab 1920 × 1080). Mobile Seiten kommen erst mit dem Fahrerportal.

**Lebende Referenz:** `frontend/src/app/pages/customers/customer-form.component.(ts|html)`.
**Vorlage zum Kopieren:** `EditFormTemplate.html`. **Globale Optik:** `frontend/src/styles/_edit-form.scss` (`EditFormTemplate.scss` ist eine Kopie).
Grundwerte (Schrift, Farben, Tasten, Felder, Meldungen): `frontend/src/styles.scss`. **Kein eigenes CSS pro Seite, keine festen Farben.**

## So entsteht ein neues Formular (ohne Designentscheidungen)

1. `EditFormTemplate.html` kopieren, Feldnamen und Texte ersetzen.
2. Komponente: `host: { class: 'form-page form-page-record' }` (viele Felder) oder `'form-page'` (wenige Felder, schmal).
3. Route: `canDeactivate: [unsavedGuard]`; in der Komponente `canLeave = useFormPage({ unsaved, save })` (`core/form-page.ts`).
4. Felder in Abschnitte gliedern, Abschnitte auf zwei Spalten verteilen.
5. Hilfetext ergänzen, `ng build` und Tests müssen laufen.

Wer ein Designproblem findet, behebt es **hier im Muster**, nicht in einem einzelnen Formular.

## Aufbau

```
.form-head     [‹ Zurück]  Titel  [Zustand]                      .head-actions: Aktionen und nächster Schritt
               .form-meta: Zeile mit den wichtigsten Angaben
.app-tabs      Reiter, falls es mehrere Bereiche gibt
<form class="form-area">   Abschnitte untereinander: Überschrift links, Felder rechts
.form-bar      dunkle Speicherleiste, nur bei ungespeicherten Änderungen
```

- **Nur ein Rückweg:** „Zurück“ oben im Kopf. `back()` ruft nur `BackNavigation.back('/liste')`; die Rückfrage kommt von `canLeave`.
- **Aktionen** (Drucken, Archivieren, nächster Schritt) stehen oben rechts in `.head-actions`. Höchstens **eine** davon ist
  gefüllt (`btn-primary`): die, die jetzt dran ist.
- **Infospalte** (`.form-cols` mit `<aside class="form-side">`) nur, wo es Angaben oder Hinweise gibt (Nummern, Dubletten,
  Summen, Verweise, Löschen).
- Abschnitt = `<section class="form-section"><h2 class="section-title">…</h2><div class="row g-2">…</div></section>`.
  Ab 1600 px steht die Überschrift links. Breite Inhalte (Tabellen): zusätzlich `.form-section-wide`.
  `.form-columns` und `.form-col` sind nur noch Gliederung ohne eigene Darstellung.
- Keine Rahmen um Abschnitte, keine grauen Hintergründe hinter Formularen.

## Feld, Taste, Anzeige

Der Benutzer muss auf einen Blick sehen, was er ändern, was er anklicken und was er nur lesen kann:

| Art | Aussehen |
|---|---|
| **Eingabefeld** | weiß, grauer Rand, wenig gerundet; Auswahlfelder mit Pfeil |
| **Taste** | ganz rund; Haupttaste in der Firmenfarbe gefüllt, andere weiß mit Rand und Schrift in der Firmenfarbe; gesperrt grau und flach |
| **Verweis** | Schrift in der Firmenfarbe, unterstrichen |
| **Nur Anzeige** | Text ohne Kasten, ohne Pfeil und Kalender |

- Gesperrte (`disabled`) und schreibgeschützte (`readonly`) Felder werden von selbst zu reinem Text. Komponenten mit
  Datumsfeldern importieren `EmptyDateDirective`, damit leere Felder nicht „tt.mm.jjjj“ zeigen.
- Reine Werte nie in einen Rahmen oder eine Fläche setzen, die wie Feld oder Taste aussieht.
- Reiter sind Text mit Balken darunter, keine Tastenform.

## Felder

- **Label oben**, keine Floating Labels, kein `placeholder`. Labels kurz und einzeilig, Hinweise als `form-text` darunter.
- **Feldbreiten nur über das Bootstrap-Raster** (`col-12 col-lg-N`), nie eigene Breiten. Je Abschnittsspalte ergibt eine Zeile höchstens 12.
- **Ein Raster je Formular:** Drittel (4, 8, 12) oder Viertel (3, 6, 9, 12), damit die Kanten fluchten. Bei Adressen: PLZ 3, Ort 6, Land 3.
  Zeilen müssen nicht voll sein.
- Pflichtfeld: rotes `*` im Label. Fehler erst nach dem ersten Speichern-Versuch (`formSubmitted`), als `invalid-feedback` am Feld.
  Gültige Felder werden nie grün. Teil-Formulare mit `ngModel`: `required` und `[class.was-validated]`.
- Archivierte oder festgeschriebene Datensätze: `form.disable()`.

## Speicherleiste (`.form-bar`)

Dunkle Leiste unten in der Mitte. **Sichtbar nur, wenn es etwas zu speichern gibt**
(`[class.d-none]="id && !form.dirty"`; beim Anlegen immer).

- Inhalt: Hinweis „Ungespeicherte Änderungen“ (`.form-bar-hint`), **Speichern** mit `<kbd>Strg+S</kbd>` (`btn-primary`),
  **Verwerfen**. Beim Speichern Spinner und „Speichert …“.
- Keine anderen Aktionen in der Leiste.
- Teil-Formulare (Ansprechpartner, Adressen, Abwesenheiten): **Speichern** und **Abbrechen**.
- Inline-Formulare auf Listenseiten und „Zahlung erfassen“: `form-bar form-bar-static` (schlicht, im Fluss).

## Tastatur und ungespeicherte Eingaben

`useFormPage` (`core/form-page.ts`) liefert für jedes Formular: **Strg+S**, Warnung beim Neuladen und die Rückfrage
„Änderungen verwerfen?“ beim Verlassen (Menü, Link, Zurück). Wer nach Speichern, Archivieren oder Löschen selbst weiterleitet,
setzt vorher `form.markAsPristine()`.

## Meldungen

Ein `message`-Signal, `alert` über dem Blatt. `notify()` setzt die Meldung und scrollt den eigenen Host nach oben.
Meldungen, die zur Leiste gehören (lange Seiten), stehen in `.form-bar-messages`.

## Sonderfälle

| Fall | Regel | Beispiel |
|---|---|---|
| Formular ohne Listenseite | `host`-Klasse oder ein `<div class="form-page">` um Formular **und** Leiste. „Zurück“ entfällt, wenn es kein Zurück gibt. | Einstellungen, Mein Konto |
| Formular in einem Reiter | Nur der Reiter mit Formular bekommt `.form-page`. | Einstellungen |
| Inline-Formular auf einer Listenseite | `.form-area` in `.list-body` (`.list-box`), Leiste `form-bar form-bar-static`: Speichern, Abbrechen. | Benutzer |
| Werte direkt in der Zeile | Felder `form-control-sm`, je Zeile „Speichern“. | Steuersätze, Nummernkreise |
| Positionen eines Belegs | Tabelle `.line-table`, eine Zeile je Position, Details aufklappbar; Summen als `.sum-table` in der Infospalte; Verweise auf andere Belege über `openDocument` (Rückfrage bei offenen Eingaben). | Belege |
| Planungsseite mit Karte | `form-page form-page-wide`; Leiste über die ganze Seite. | Streckenplanung (noch nicht umgestellt) |
| Anmeldeseite | Zentrierte Karte, volle Tastenbreite. | Login |

## Prüfung

Ein Formular gilt erst als fertig, wenn der Nutzer es im Browser angesehen hat.
