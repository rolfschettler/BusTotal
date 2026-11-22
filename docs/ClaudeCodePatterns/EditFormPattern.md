# CodePattern: Bearbeiten-/Anlegen-Formular (verbindlicher Standard)

Das Muster für **jedes** Formular in BusTotal (Kunde, später Fahrzeuge, Fahrer …). Es ist der Standard: Abweichungen nur
nach Absprache mit dem Nutzer und mit Eintrag in diesem Dokument.
Herkunft: `D:\angular\DATACUR26\ClaudeCodePatterns\EditFormTemplate.html/.scss`, für BusTotal überarbeitet (Stand 2026-11-17).

**Lebende Referenz:** `frontend/src/app/pages/customers/customer-form.component.(ts|html)`
(Stammdaten als Reactive Form, Ansprechpartner und Adressen als kleine Teil-Formulare).
**Vorlage zum Kopieren:** `EditFormTemplate.html` (generisch, alle Feldtypen). **Globale Optik:** `frontend/src/styles/_edit-form.scss`
(einmal definiert, `EditFormTemplate.scss` ist eine Kopie davon). **Kein eigenes CSS pro Seite.**

## So entsteht ein neues Formular (ohne Designentscheidungen)

Das Design ist entschieden und wird **nicht** pro Formular neu verhandelt. Der Nutzer liefert nur die Felder (Name, Art, Pflicht, Länge).
Claude baut daraus das Formular so:

1. `EditFormTemplate.html` kopieren, Feldnamen und Texte ersetzen. Kein eigenes CSS, keine eigenen Farben oder Breiten.
2. Komponente nach der Referenz anlegen (Bausteine siehe unten), `host: { class: 'form-page' }`.
3. **Ein Raster wählen** (Drittel oder Viertel, siehe „Ein Formular, ein Raster“), jedes Feld einer Breite dieses Rasters zuordnen, Reihenfolge nach Lesefluss und in Abschnitte gliedern.
4. Labels kurz halten (ein Wort bis wenige Wörter), Hinweise als `form-text` darunter, Pflichtfelder mit rotem `*`.
5. Tastenleiste unverändert aus der Vorlage übernehmen (Speichern, Zurück | Archivieren, Abbrechen).
6. `ng build` muss laufen. Der Nutzer sieht das Formular nur noch inhaltlich an (stimmen Felder, Pflichtfelder, Texte), nicht mehr das Design.

Wer ein Designproblem findet, behebt es **hier im Muster** (`_edit-form.scss`, Vorlage, dieses Dokument), nicht in einem einzelnen Formular.

## Aufbau einer Formularseite

1. Rücklink „‹ Alle …“, Titelzeile (Name oder „Neuer …“), Spinner solange ein vorhandener Datensatz lädt.
2. **Meldung** (Erfolg und Fehler) direkt unter der Titelzeile.
3. `<form class="form-area">` = graue Fläche mit den weißen Feldern. **Keine Tasten im Formular.**
4. Seltene Zusatzbereiche (z. B. DSGVO) unter dem Formular, abgesetzt durch eine Linie.
5. **Tastenleiste** `.form-bar` als letztes Element der Seite, klebt unten (siehe unten).

Komponente mit `host: { class: 'form-page' }` (Breite max. 960 px). Das Formular bekommt eine `id`, die Speichern-Taste verweist
mit `form="id"` darauf (so liegt sie in der Leiste, löst aber das Formular aus; Enter im Feld speichert auch).

## Felder

- **Label oben** (`<label class="form-label">` vor dem Feld). **Keine Floating Labels, kein `placeholder`.**
  **Labels sind kurz und einzeilig** (CSS: `nowrap`, sonst Auslassungspunkte). Umbrechende Labels schieben Felder derselben Zeile auseinander,
  das darf nie passieren. Hinweise gehören nie ins Label, sondern darunter als `<div class="form-text">z. B. DE123456789</div>`.
- **Feldbreiten immer über das Bootstrap-Raster (12 Spalten), nie über eigene Breiten, `style="width"` oder `maxlength` als Breitenersatz.**
  Ein Breakpoint: `col-12 col-lg-N`, darunter steht alles untereinander. Bewusst `lg` statt `md`: Die Seitenleiste nimmt dem Formular rund 300 px, bei `md` wären die Spalten zu schmal. Die Spaltenzahlen einer Zeile ergeben 12.
  Die Breite folgt der Länge des Inhalts:

  | Inhalt | Breite | Beispiel |
  |---|---|---|
  | kurz (PLZ, Land, Anrede, Zahl) | `col-lg-3` | PLZ, Land |
  | mittel, drei gleich lange Felder | `col-lg-4` | Anrede, Vorname, Nachname |
  | normal, gepaarte Felder | `col-lg-6` | E-Mail, Telefon |
  | lang, Rest einer Zeile | `col-lg-8` oder `col-lg-6` neben zwei kurzen | Ort neben PLZ und Land |
  | sehr lang, mehrzeilig | `col-12` | Straße, Notizen (`rows="4"`) |

  Zusammengehörige Felder stehen in einer Zeile (PLZ, Ort, Land), die Reihenfolge folgt dem Lesefluss.
- **Ein Formular, ein Raster (Regel seit 2026-11-22):** Die Kanten der Felder müssen **über alle Zeilen fluchten**. Darum wählt jedes Formular **eines**
  von zwei Rastern und bleibt dabei:
  - **Drittelraster:** Felder sind `col-lg-4` (ein Drittel), lange Felder `col-lg-8` (zwei Drittel) oder `col-12`.
  - **Viertelraster:** Felder sind `col-lg-3` (ein Viertel), mittlere `col-lg-6` (die Hälfte), lange `col-lg-9` oder `col-12`.
  - **Nie mischen:** `col-lg-4` und `col-lg-6` im selben Formular (3+3+6 neben 4+4+4) ergeben Spalten, die nicht untereinander stehen.
  - **Zeilen müssen nicht voll sein:** Eine Zeile mit zwei Feldern im Drittelraster bleibt rechts leer. Die Felder werden **nicht** auf 6+6 gestreckt,
    nur um die Zeile zu füllen. Gleichartige Felder sind überall gleich breit (zum Beispiel alle Preisfelder ein Drittel).
  - Im Zweifel gilt das **Drittelraster** für Einstellungen und Zahlenfelder, das **Viertelraster** für Adressen (PLZ 3, Ort 6, Land 3).
  - **Bestand:** Formulare, die beide Raster mischen, werden bei Gelegenheit angeglichen (Liste in `docs/FAHRPLAN.md`). Zuerst angeglichen: Einstellungen › Preise.
- **Drei Helligkeitsstufen:** Seite nahezu weiß (`$body-bg` #fbfcfd), Formularfläche `.form-area` dezent grau (#f1f3f5, Rand #dee2e6), Felder weiß mit Rand #adb5bd.
  Ursache früherer Grau-in-Grau-Felder: Bootstrap leitet `$input-bg` von `$body-bg` ab, deshalb steht `$input-bg: #fff` in `styles.scss`.
- **Optik = Bootstrap.** Felder, Labels, Checkboxen werden nicht per CSS überschrieben, nur über Bootstrap-Variablen in `styles.scss`
  (`$input-bg`, `$input-border-color`, `$input-disabled-bg`, `$form-check-input-bg`).
- Kontrollkästchen: normales `form-check`, bündig zur Feldunterkante (`d-flex align-items-end`).
- Abschnitte mit `<div class="col-12"><div class="section-title">…</div></div>`, keine Karten pro Abschnitt, kein `<hr>`.
- Pflichtfeld: rotes `*` im Label. Fehlermeldung als `invalid-feedback` direkt am Feld.
- **Fehler erst nach dem ersten Speichern-Versuch** markieren (`formSubmitted`). Gültige Felder werden nie grün.
  - Reactive Form: `[class.is-invalid]="invalid('feld')"`. `invalid()` meldet auch Fehler, die der Server zurückgibt (`fieldErrors`).
  - Teil-Formular mit `ngModel`: natives `required`, `<form novalidate [class.was-validated]="…Submitted()">`, im Absenden `f.invalid` prüfen.
    Server-Fehler zusätzlich über `[class.is-invalid]`.

## Tastenleiste (`.form-bar`)

Klebt am unteren Rand des Sichtbereichs (`position: sticky`) in Formularbreite, damit Tasten nie wegscrollen.
**Anordnung ist in allen Formularen gleich:**

| Position | Taste | Aussehen |
|---|---|---|
| links, erste | **Speichern** (beim Anlegen „Kunde anlegen“) | `btn-primary` (Firmenfarbe), einzige gefüllte Taste. Beim Speichern Spinner und „Speichert …“ |
| links, zweite | **Zurück** | `btn-outline-secondary`. Bei ungespeicherten Änderungen Rückfrage (`ConfirmService`) |
| links, Hinweis | „Ungespeicherte Änderungen“ | nur wenn `form.dirty` |
| rechts (`.form-bar-right`) | seltene Aktion: **Archivieren** / **Wiederherstellen** | `btn-outline-secondary`, nie Löschen (GoBD) |
| rechts, ganz außen | **Abbrechen** | verwirft Eingaben, setzt das Formular auf den gespeicherten Stand zurück. Deaktiviert, solange nichts geändert ist |

- Teil-Formulare (Ansprechpartner, Adressen): Leiste nur mit **Speichern** links und **Abbrechen** rechts (Abbrechen schließt das Teil-Formular).
- Archivierte Datensätze: Formular gesperrt (`form.disable()`), kein Speichern, kein Abbrechen; nur Zurück und Wiederherstellen.
- **Sekundäre Tasten** sind weiß mit dunklem Rand (`#6c757d`), damit sie auch ohne Farbe als Taste erkennbar sind. Das gilt in der **ganzen Formularseite**
  (`.form-page`), nicht nur in der Tastenleiste: Auch „Position hinzufügen“ oder „Zahlung erfassen“ im Formular sehen so aus. (Fehler beim Testen
  2026-11-22: Die Regel galt nur in `.form-bar`, eine Taste im Formular sah in der blassen Bootstrap-Optik deaktiviert aus.)
- **Deaktiviert** (`[disabled]`) = graue Fläche, gestrichelter Rand, Mauszeiger „verboten“, `title` mit dem Grund. Wo möglich
  bleibt eine Taste aktiv und meldet das Problem erst beim Klick (Speichern ist nie deaktiviert, außer während des Speicherns).

## Meldungen

Ein `message`-Signal, Hinweis unter der Titelzeile. Weil die Seite lang sein kann, setzt `notify()` die Meldung **und** scrollt mit
`scrollIntoView` am eigenen Host nach oben, nicht am Fenster.

## Sonderfälle (bewusste, dokumentierte Abweichungen)

| Fall | Regel | Beispiel |
|---|---|---|
| Formular ohne Listenseite | Statt eines Wrappers `host` einen `<div class="form-page">` um Formular **und** Leiste legen (die Leiste klebt nur innerhalb ihres Elternelements). „Zurück“ führt zur Startseite. | Einstellungen › Firmendaten, Mein Konto |
| Formular innerhalb eines Reiters | Nur der Reiter mit Formular bekommt `.form-page`; Tabellen-Reiter bleiben unberührt. | Einstellungen |
| Kurzes Inline-Formular auf einer Listenseite | Wie ein normales Formular (`.form-area`, Labels oben), aber Leiste mit `form-bar form-bar-static` (nicht klebend, die Liste scrollt selbst). Nur **Speichern** links, **Abbrechen** rechts. | Benutzer anlegen und bearbeiten |
| Pflichtänderung | Taste „Zurück“ entfällt, solange der Nutzer nichts anderes tun darf. | Mein Konto bei vorläufigem Passwort |
| Tabellen-Bearbeitung (Werte direkt in der Zeile) | Kein Formular-Muster. Felder `form-control-sm`, je Zeile eine Taste „Speichern“. | Steuersätze, Nummernkreise |
| Anmeldeseite | Eigenes Layout (zentrierte Karte, volle Tastenbreite). Label oben und `invalid-feedback` wie im Muster. | Login |

Weitere lebende Referenzen: `pages/settings` (mehrere Abschnitte in einem Formular, Logo-Upload im Formular),
`pages/account` (Passwortfelder, Pflichtänderung), `pages/users` (Inline-Formular).

## Bausteine in der Komponente

`form` (Reactive Form), `formSubmitted`, `fieldErrors`, `invalid()`, `message`/`notify()`, `loading`, `saving`, `archived`,
`save()` (setzt nach Erfolg `form.markAsPristine()`), `back()` (Rückfrage bei `form.dirty`), `cancel()` (Reset auf gespeicherten Stand).
Siehe Referenzkomponente.

## Unterschiede zum DATACUR26-Original

| Original | BusTotal | Grund |
|---|---|---|
| Floating Labels | Label oben, kein placeholder | Label immer lesbar, leere Felder sind klar leer, weniger Sonderfälle |
| Felder grau auf weißer Karte (Ableitung von `$body-bg`) | Seite hell, Formular grau, Felder weiß, Rand `#adb5bd` | Drei klare Stufen, Eingaben sofort erkennbar |
| Tasten am Ende des Formulars | Sticky Tastenleiste unten, feste Reihenfolge | Tasten scrollen nie weg, überall gleich |
| Löschen (rechts) | Archivieren / Wiederherstellen (rechts) | Kunden werden nie gelöscht |
| Template-Formular mit `ngModel` und `setField` | Reactive Forms (Stammdaten) | Typ-abhängige Pflichtfelder (Firma/Privat) |
| `.section-title` in `--bs-primary` | Firmenfarbe (`--brand`) | Jede Firma hat ihre eigene Farbe |
| Fehler-Signal `errorMessage` | `message` mit Erfolg und Fehler | Auch „Gespeichert.“ muss sichtbar werden |
| Grüne gültige Felder nach Absenden | Unterdrückt | Nur Fehler sollen auffallen |

## Prüfung

Es gibt keine Tests für Formular-Optik. Logik (Pflichtfelder, `invalid()`) bleibt in der Komponente klein. Ein Formular gilt erst als fertig,
wenn der Nutzer es im Browser angesehen hat.
