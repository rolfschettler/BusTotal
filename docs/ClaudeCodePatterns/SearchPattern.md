# CodePattern: Suche in Listen und Auswahlfeldern

Gilt für jede Suche über Personen und Adressen (Kunden, Fahrer, Belege mit Kunde, Kundenauswahl im Formular, künftig Haltestellen,
Reiseziele …). Die Datenbank kann Millionen Adressen enthalten, die Suche muss bei jedem Tastendruck schnell bleiben.

**Lebende Referenzen:** `backend/app/Support/NameSearch.php`, `CustomerController::index`, `DocumentController::index`,
`frontend/src/app/shared/customer-name.component.ts`, `shared/highlight.ts`. Tests: `CustomerTest::test_suche_am_wortanfang_nachname_komma_vorname`.

## Regeln

1. **Nur am Wortanfang suchen**, nie `LIKE '%text%'` auf Adressfeldern. `feld LIKE 'text%'` nutzt den Index, `%text%` liest jede Zeile.
   Groß-/Kleinschreibung und Umlaute spielen keine Rolle (Kollation der Datenbank).
2. **Immer über `NameSearch::apply($query, $q, $spalten, $alias, $extra)`**, nie eigene `LIKE`-Ketten und nie `CONCAT` über Namen.
   `%` und `_` im Suchtext sind wörtlich gemeint (macht `NameSearch::start()`).
3. **Jedes durchsuchte Feld hat einen Index** (neue Felder: Migration, Vorbild `add_search_indexes`). Mehrere Felder pro Wort sind ODER, mehrere Wörter UND.
4. **Eingabeformen** (gleich in allen Suchen):
   - `muster` trifft Nachname, Vorname, Ort, PLZ, Nummer, E-Mail oder Firma.
   - `max hamburg` jedes Wort muss irgendwo treffen, Reihenfolge egal.
   - `Mustermann,Max` mit Komma: Nachname UND Vorname, beide als Teilbegriff (`Muster,M`). `,Max` und `Muster,` sind erlaubt.
5. **Ausnahmen mit Begründung:** Firmennamen treffen auch nach Leerzeichen oder Bindestrich (ab 3 Zeichen, einzige Bedingung ohne Index).
   Belegnummern mit Ziffer im Suchwort treffen mittendrin (kleine Tabelle). Mehr nur nach Absprache.
6. **Verbundene Tabellen per Join statt `whereHas`** (Belege: Kunde `c`). So bleibt die Suche schnell und Belege archivierter Kunden bleiben auffindbar.

## Anzeige

- **Treffer sind immer sichtbar markiert:** `<app-highlight … mode="start">`. Der Modus `start` markiert genau wie der Server am Wortanfang
  und trennt Wörter an Leerzeichen und Komma. Nummern in Belegen (Treffer mittendrin): `mode="words"`.
- **Kunden zeigen `<app-customer-name [customer]="…" [query]="…">`:** Zeile 1 fett „Nachname, Vorname“ (Privatkunden haben keine Firma, die Person steht
  vorn; nur ohne Person die Firma), Zeile 2 klein Firma · PLZ Ort · Kundennummer (`[showNumber]`, `[showPlace]="false"` wenn die Liste eine Ortsspalte hat).
  Gilt für Liste UND Auswahlliste (Autocomplete). Bei festgeschriebenen Belegen den Empfänger-Schnappschuss übergeben (`d.recipient ?? d.customer`).
- Felder, die die Liste nicht zeigt, aber treffen können: „Treffer in …“ (`hiddenHits`). Alles Sichtbare vorher ausschöpfen.
- **Sortierung nach Name folgt der Anzeige:** `COALESCE(NULLIF(last_name,''), NULLIF(company_name,''), customer_number)`, dann `first_name`.
- Suchfeld: Platzhalter nennt die Felder („Nachname, Kundennr., Ort …“), `title` nennt die Komma-Schreibweise.
- Auswahlfelder: ab 2 Zeichen, 300 ms Verzögerung, höchstens 8 Treffer.

## Neue Suche anlegen

1. Controller: `NameSearch::apply(...)` mit den Spalten, die die Liste zeigt; Besonderes über `$extra`.
2. Migration: Index je Spalte, dann `php artisan migrate`.
3. Liste: `<app-customer-name>` bzw. `<app-highlight mode="start">` für jedes sichtbare Suchfeld.
4. Test: Wortanfang, Komma, mehrere Wörter, `%` wörtlich (siehe Referenztest).
