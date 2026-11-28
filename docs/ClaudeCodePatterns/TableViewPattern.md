# CodePattern: Tabellen-/Listenansicht (verbindlicher Standard)

Das Muster für jede Listenseite in BusTotal. Stand 2026-11-28 (zweiter Entwurf, Vorlage `docs/redesign/vorschlag-2.html`).
**Ausgelegt für große PC-Bildschirme** (Maßstab 1920 × 1080, benutzbar ab 1024 px Breite).

**Lebende Referenzen:**
- Server-seitig (Seiten, Sortierung im Backend): `frontend/src/app/pages/customers/customer-list.component.(ts|html)`
- Client-seitig (kleine Liste): `frontend/src/app/pages/users/users.component.(ts|html)`, Logik als reine Funktionen in `users-view.ts`.

**Vorlage:** `TableViewTemplate.html`. **Globale Optik:** `frontend/src/styles/_list-page.scss` (`TableViewTemplate.scss` ist eine Kopie).
**Kein eigenes CSS pro Seite.**

## Aufbau

```
.list-head   Zeile 1: Titel, Zähler, Tasten · Zeile 2: Suche und Filter   (fest)
.list-body   Meldungen · table.data-table mit klebendem Kopf                (der EINE Scroll-Container)
.list-foot   Zähler · „Pfeiltasten wählen, Enter öffnet“ · Blättern         (fest)
```

- Komponente `host: { class: 'list-page' }`, Route `data: { fullPage: true }`.
- **Volle Breite.** `colgroup`: feste Breiten für Kurzes (Nummer, Datum, Aktion), Anteile für Namen, **genau eine Spalte ohne Breite**.
  Mindestbreite als `style="min-width: …px"` an der Tabelle.
- Höchstens eine gefüllte Taste: **„Neu …“** (`btn-primary`).
- Aktiver Filter: Klasse `filter-active`. Dauerhafte Erklärtexte vermeiden; wenn nötig `.list-note`.
- Beim Nachladen `.dimmed` statt Spinner.

## Zeilen wählen und öffnen

`shared/row-nav.directive.ts`:

- Am `.list-body`: `appRowNav #nav="appRowNav" (rowSelect)="select($event)" (rowOpen)="open($event)"`.
- Zeile: `<tr [attr.data-row-id]="x.id" [class.row-current]="x.id === currentId()" [appRevealCurrent]="x.id === currentId()">`.
- Suchfeld: `appListSearch [nav]="nav" [pending]="q.trim() !== shownQuery() || loading()" (searchNow)="searchNow()"`.
  Es bekommt beim Öffnen den Cursor.

**Ein Klick öffnet**, Pfeiltasten wählen, Enter öffnet. Links und Tasten in der Zeile tun nur ihr Eigenes.

**Vorschau** (Vorbild Kundenliste): `.list-split` mit `.list-body` und `<aside class="list-peek">`, am Container `[preview]="true"`.
Ab 1500 px wählt der Klick nur, Doppelklick und Enter öffnen. Schnellfilter als `.list-chips` mit `.app-tab`.

**Namen von Personen und Firmen** bekommen den Kürzel-Kreis: `<div class="who"><app-avatar [name]="…" /><div>…</div></div>`.

**Aktionsspalte** (die letzte, `.col-actions`, `.btn-action`) nur für Aktionen außer Öffnen (Drucken, Archivieren). Ohne solche entfällt sie.

## Aktuelle Zeile und Rücksprung

Springt man weg und kommt zurück, steht die Liste wie vorher und die Zeile ist markiert und sichtbar.

- `core/list-memory.service.ts` (`ListMemory`) merkt je Liste Zeile und Einstellungen.
- Liste: `currentId = signal(memory.current(KEY))`, in `ngOnInit` `memory.params(KEY)` laden (eine Suche per `?q=` hat Vorrang),
  nach dem Laden `memory.saveParams`, in `select()` `memory.select`.
- Formular: `memory.follow(KEY, id)`; nach dem Löschen `memory.forget(KEY, id)`.

## Wann server-, wann client-seitig

| Menge | Vorgehen |
|---|---|
| Passt locker in den Browser (bis etwa 1000) | Client-seitig filtern und sortieren |
| Kann groß werden (Kunden, Belege) | **Server-seitig** filtern, sortieren, blättern |

Server-seitig sortiert nur nach einer **festen Liste** von Spalten (`CustomerController::SORTS`), leere Werte ans Ende, eindeutiger
Gleichstand-Entscheid, unbekannte Spalte = HTTP 422. Test: `tests/Feature/CustomerSortTest.php`.

## Regeln, die man leicht verletzt

- Genau **ein** scrollendes Element (`.list-body`). Kein `overflow`-Wrapper um die Tabelle, sonst klebt der Kopf nicht.
- Treffer immer mit `<app-highlight>`; trifft die Suche ein unsichtbares Feld: „Treffer in …“. Siehe `SearchPattern.md`.
- Leerraum zwischen Bausteinen entfernt Angular: ausdrücklich `{{ ' ' }}` setzen.
- Leere Liste: sagen, warum sie leer ist und was man tun kann.
- Farben nur aus den Variablen (`--brand`, `--brand-ink`, `--ui-…`). Schrift auf der Firmenfarbe immer `--brand-on`.
- Symbole: Bootstrap Icons.
