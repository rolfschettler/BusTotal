# CodePattern: Tabellen-/Listenansicht

Das Muster für jede Listenseite in BusTotal (Kunden, später Fahrzeuge, Fahrer, Belege …).
Herkunft: `D:\angular\DATACUR26\ClaudeCodePatterns\TableViewTemplate.html/.scss`, für BusTotal angepasst.

**Lebende Referenzen:**
- Server-seitig (Seiten, Sortierung im Backend): `frontend/src/app/pages/customers/customer-list.component.(ts|html)`
- Client-seitig (kleine Liste, Filtern und Sortieren im Browser): `frontend/src/app/pages/users/users.component.(ts|html)`.
  Die Logik steht dort in `users-view.ts` (reine Funktionen `filterUsers`, `sortUsers`) mit Testfällen in `users-view.spec.ts`:
  So lässt sie sich ohne Browser prüfen, besonders leere Werte beim Sortieren.
**Globale Optik:** `frontend/src/styles/_list-page.scss` (einmal definiert, jede Liste nutzt nur die Klassen).
Eine neue Liste kopiert die Referenz und passt Spalten und Daten an. **Kein eigenes CSS pro Seite.**

## Was das Muster liefert

- **Seitenkopf** in der Markenfarbe: Titel, Zähler („194 Kunden“), Schaltflächen „Aktualisieren“ und „Neu …“. Er steht fest oben.
- **Klebende Filterleiste** mit Suchfeld und Filtern. Ein aktiver Filter ist farbig getönt (`filter-active`).
- **Klebender Tabellenkopf** direkt unter der Filterleiste, getönt in der Firmenfarbe. Die aktiv sortierte Spalte ist voll eingefärbt mit weißer Schrift.
- **Aktionsspalte** (immer die erste): Öffnen/Bearbeiten, Archivieren (bzw. Wiederherstellen).
- **Zebrastreifen**, dezente Spaltenlinien, Hover, **Auswahlzeile** (Klick markiert).
- **Dimmen statt Spinner** beim Nachladen: Die Tabelle springt nicht.
- **Treffer markiert** über `<app-highlight>` (Regel für alle Suchen), mit Hinweis „Treffer in …“ bei unsichtbaren Feldern.

## Aktuelle Zeile („currentId“) und Rücksprung

Ein Klick auf eine Zeile (auch auf eine Aktion in der Aktionsspalte, dort **kein** `stopPropagation`) macht sie zur aktuellen Zeile.
Springt man weg und kommt zurück, steht die Liste wieder wie vorher (Seite, Suche, Filter, Sortierung), die Zeile ist markiert
und scrollt in den sichtbaren Bereich. Bausteine:

- `core/list-memory.service.ts` (`ListMemory`): merkt je Liste (`'belege'`, `'kunden'` …) Zeile und Einstellungen, nur im Arbeitsspeicher.
- `shared/reveal-current.directive.ts`: `[appRevealCurrent]="x.id === currentId()"` am `<tr>` scrollt die Zeile beim Entstehen ins Bild.
- **Liste**: `currentId = signal(memory.current(KEY))`, in `ngOnInit` `memory.params(KEY)` zurückladen (eine Suche per `?q=` hat Vorrang),
  nach jedem erfolgreichen Laden `memory.saveParams(KEY, …)`, Zeilenklick `memory.select(KEY, id)`. Vorbild: `customer-list.component.ts`.
- **Formular**: beim Laden eines Datensatzes `memory.follow(KEY, id)`. Ein anderer als der gemerkte Datensatz (neu angelegt, Folgebeleg, Storno)
  setzt die Liste auf Seite 1 ohne Filter zurück, damit er sicher zu sehen ist. Nach dem Löschen `memory.forget(KEY, id)`.
- Bei Belegen folgt die Markierung der Beleg-Kette: Angebot → Auftragsbestätigung → …, „Zurück“ landet immer auf dem zuletzt gezeigten Beleg.

## Wann dieses Muster, wann nicht

| Menge | Vorgehen |
|---|---|
| Alle Datensätze passen locker in den Browser (bis etwa 1000) | Client-seitig filtern und sortieren (Signale `rows`, `displayRows` als `computed`). |
| Kann groß werden (Kunden, Belege, Protokoll) | **Server-seitig**: Der Server filtert, sortiert und blättert. So macht es die Kundenliste. |

Server-seitig heißt: Spalten per **feste Liste** sortieren, nie einen Spaltennamen aus der Anfrage in die Abfrage übernehmen.
Siehe `CustomerController::SORTS`: Die Anfrage wählt nur einen Eintrag dieser Liste aus. Dazu gehören:
leere Werte immer am Ende, die Kundennummer als Entscheidung bei Gleichstand (sonst erscheint ein Datensatz beim Blättern
auf zwei Seiten oder fehlt) und deutsche Fehlermeldung bei unbekannter Spalte (HTTP 422).
Getestet in `tests/Feature/CustomerSortTest.php`, inklusive Versuchen, SQL einzuschleusen.

## So baut man eine neue Liste

1. **Komponente** mit `host: { class: 'list-page', style: '--sheet-w: <Summe der Spaltenbreiten>px' }`.
2. **Route** mit `data: { help: '…', fullPage: true }`. `fullPage` lässt die Seite die ganze Fläche nutzen und selbst scrollen
   (`layout.component`), das braucht der klebende Tabellenkopf.
3. **Markup** wie in `customer-list.component.html`: `.page-header`, `.page-content` mit `.filter-bar appStickyFilterBar`,
   `.card.table-card` mit `table.data-table`, `colgroup`, sortierbare `th`, Zeilen, Blätterleiste `.pager`.
4. **Backend**: Liste mit Seiten, Suche, Filtern und Sortierung nach dem Vorbild von `CustomerController::index`.
5. **Hilfetext** der Liste ergänzen (`public/help/…`), Treffer-Hinweis für nicht angezeigte Suchfelder nicht vergessen.

## Regeln, die man leicht verletzt

- **Genau EIN scrollendes Element**: `.page-content`. Kein `overflow` auf einem Wrapper um die Tabelle, sonst klebt der Tabellenkopf nicht mehr.
- **`.table-card` nie mit `overflow: hidden`.**
- **`--sheet-w`** = Summe der `colgroup`-Breiten, einmal auf `:host`. Filterleiste, Kartenrahmen und Tabelle bekommen dadurch dieselbe Breite.
  Ohne das scheinen bei schmalen Fenstern die durchscrollenden Zeilen über dem Tabellenkopf durch (im DATACUR26-Original ein dokumentierter Fehler).
- **Aktionsspalte breit genug**: zwei Symbole brauchen 76 px, bei 64 px wird das zweite abgeschnitten.
- **Leerraum zwischen Bausteinen** wie `<app-highlight> <app-highlight>` entfernt Angular. Ein ausdrückliches `{{ ' ' }}` setzen.
- **Keine Schatten** im Listenlayout, klare Trennung durch scharfe 1–2-px-Linien.

## Unterschiede zum DATACUR26-Original

| Original | BusTotal | Grund |
|---|---|---|
| Client-seitig filtern/sortieren | Server-seitig (Seiten, Sortierung) | Kundenbestand kann groß werden |
| `[innerHTML]="highlight(…)"` | `<app-highlight>` | Kein HTML aus Daten einfügen (Schadcode), gemeinsame Regel für alle Suchen |
| `app-confirm-dialog` | `ConfirmService` | Unser Dialog-Baustein |
| Festes Primärblau (`--bs-primary-*-subtle` aus dem Sass) | Töne zur Laufzeit aus der Firmenfarbe (`BrandService`) | Jede Firma hat ihre eigene Farbe |
| Spinner beim Laden | Dimmen (`.dimmed`) | Regel des Musters für Server-Listen |
| Löschen (Papierkorb) | Archivieren (`bi-archive`), Wiederherstellen nur für den Administrator | GoBD: Kunden werden nie gelöscht |
| Mobile Kartenansicht | Nicht vorhanden | BusTotal ist für den PC gebaut, Mobil nur auf Zuruf |
| `shared/sticky-filter-bar.directive.ts` aus DATACUR26 | Eigene Direktive `StickyFilterBarDirective` | Lag nicht im Projekt |
| Hinweis „Treffer in …“ | Neu | Suche trifft auch Felder, die die Liste nicht zeigt |

## Die Markenfarbe in der Tabelle

Bootstrap rechnet `--bs-primary-bg-subtle` & Co. beim Bauen fest aus einer Farbe. Bei uns setzt der `BrandService` zur Laufzeit
`--brand-subtle`, `--brand-border-subtle` und `--brand-text-emphasis` (dieselbe Formel: 80 % bzw. 60 % Weiß, 60 % Schwarz),
und `styles.scss` hängt `--bs-primary-*-subtle` daran. Dadurch folgt die ganze Tabelle der Firmenfarbe, auch Hinweisfelder und
Auswahl. **Neue Markenfarb-Töne gehören in `BrandService.applyColor`, nicht fest in ein SCSS.**

## Symbole

Bootstrap Icons (`bi bi-…`, Paket `bootstrap-icons`, in `angular.json` eingebunden).
