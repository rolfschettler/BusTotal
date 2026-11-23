<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;

/**
 * Gemeinsame Suche für alle Listen mit Personen und Adressen (Kunden, Fahrer, Belege, Kundenauswahl).
 *
 * Gedacht für Millionen Datensätze: Es wird nur am ANFANG eines Feldes gesucht (`feld LIKE 'text%'`). Das nutzt den Index.
 * Eine Suche „irgendwo im Text“ (`LIKE '%text%'`) müsste jede Zeile lesen und wird ab einigen 100 000 Zeilen unbrauchbar.
 * Groß-/Kleinschreibung und Umlaute spielen keine Rolle (Kollation utf8mb4_unicode_ci).
 *
 * Eingabe:
 *  - „Mustermann,Max“  Mit Komma: Nachname beginnt mit „Mustermann“ UND Vorname beginnt mit „Max“. Beide Teile sind
 *                      Teilbegriffe: „Muster,M“ findet Max und Marie Mustermann. Ein Teil darf fehlen („,Max“ / „Muster,“).
 *  - „muster“          Ein Wort: trifft Nachname, Vorname, Ort, PLZ, Nummer, E-Mail oder Firma.
 *  - „max hamburg“     Mehrere Wörter: JEDES Wort muss irgendwo treffen (Reihenfolge egal).
 *
 * Die Markierung der Treffer macht die Oberfläche mit demselben Muster (`shared/highlight.ts`, Modus „start“).
 */
final class NameSearch
{
    /** Felder, in denen ein Wort am Anfang treffen darf (Kunden). Personenfelder zuerst, die Firma ist nachrangig. */
    public const CUSTOMER_COLUMNS = ['last_name', 'first_name', 'city', 'postal_code', 'customer_number', 'email', 'company_name'];

    /** @return array{comma: bool, last: string, first: string, tokens: list<string>} */
    public static function parse(string $q): array
    {
        $q = trim($q);

        if (str_contains($q, ',')) {
            [$last, $first] = array_map('trim', explode(',', $q, 2));

            return ['comma' => true, 'last' => $last, 'first' => $first, 'tokens' => []];
        }

        return ['comma' => false, 'last' => '', 'first' => '', 'tokens' => preg_split('/\s+/u', $q, -1, PREG_SPLIT_NO_EMPTY) ?: []];
    }

    /** Anfangsmuster für LIKE: % und _ sind Platzhalter und hier wörtlich gemeint. */
    public static function start(string $text): string
    {
        return addcslashes($text, '%_\\').'%';
    }

    /** Muster für „irgendwo im Text“ (nur für kleine Tabellen oder kurze Felder, nie für Adressen). */
    public static function anywhere(string $text): string
    {
        return '%'.addcslashes($text, '%_\\').'%';
    }

    /**
     * Wendet die Suche auf eine Abfrage an (UND-verknüpft, alle Bedingungen stehen in eigenen Klammern).
     *
     * @param  EloquentBuilder|Builder  $query
     * @param  list<string>  $columns  Felder mit Anfangssuche
     * @param  string  $alias  Tabellenname oder -alias, wenn die Spalten nicht eindeutig sind (z. B. „c“)
     * @param  Closure|null  $extra  fn($w, string $token): weitere ODER-Bedingungen je Wort (z. B. Belegnummer)
     */
    public static function apply($query, string $q, array $columns = self::CUSTOMER_COLUMNS, string $alias = '', ?Closure $extra = null): void
    {
        $p = $alias === '' ? '' : $alias.'.';
        $parsed = self::parse($q);

        if ($parsed['comma']) {
            if ($parsed['last'] === '' && $parsed['first'] === '') {
                return;
            }
            $query->where(function ($w) use ($parsed, $p) {
                if ($parsed['last'] !== '') {
                    $w->where($p.'last_name', 'like', self::start($parsed['last']));
                }
                if ($parsed['first'] !== '') {
                    $w->where($p.'first_name', 'like', self::start($parsed['first']));
                }
            });

            return;
        }

        foreach ($parsed['tokens'] as $token) {
            $query->where(function ($w) use ($token, $columns, $p, $extra) {
                foreach ($columns as $col) {
                    $w->orWhere($p.$col, 'like', self::start($token));
                }
                // Firmennamen bestehen aus mehreren Wörtern („Reisebüro Sonnenschein“, „Albert-Schweitzer-Realschule“): Hier trifft
                // auch der Anfang eines späteren Wortes. Das ist die einzige Bedingung ohne Index (sie liest den Firmennamen,
                // nicht die ganze Zeile); sie gilt erst ab 3 Zeichen, damit kurze Eingaben nicht alles treffen.
                if (in_array('company_name', $columns, true) && mb_strlen($token) >= 3) {
                    $w->orWhere($p.'company_name', 'like', '% '.self::start($token))
                        ->orWhere($p.'company_name', 'like', '%-'.self::start($token));
                }
                if ($extra) {
                    $extra($w, $token);
                }
            });
        }
    }
}
