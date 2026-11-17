<?php

namespace App\Services;

use App\Models\NumberRange;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Vergibt fortlaufende Belegnummern (GoBD: lückenlos, keine Doppelvergabe).
 *
 * WICHTIG: next() immer in derselben Transaktion aufrufen, in der der Beleg gespeichert wird.
 * Scheitert das Speichern, wird die Transaktion zurückgerollt und die Nummer wird NICHT verbraucht.
 */
class NumberRangeService
{
    public function next(string $type, ?int $year = null): string
    {
        return DB::transaction(function () use ($type, $year) {
            // lockForUpdate sperrt die Zeile, bis die Transaktion endet: zwei parallele
            // Anfragen bekommen nie dieselbe Nummer.
            $range = NumberRange::where('type', $type)->lockForUpdate()->first()
                ?? throw new RuntimeException("Unbekannter Nummernkreis: {$type}");

            $year ??= (int) now()->format('Y');

            if ($range->reset_yearly && $range->last_year !== null && $range->last_year !== $year) {
                $range->next_number = 1;
            }

            $number = $range->next_number;
            $range->next_number = $number + 1;
            $range->last_year = $year;
            $range->save();

            return $this->format($range, $number, $year);
        });
    }

    /** Vorschau der nächsten Nummer, ohne sie zu vergeben. */
    public function preview(NumberRange $range, ?int $year = null): string
    {
        $year ??= (int) now()->format('Y');
        $number = ($range->reset_yearly && $range->last_year !== null && $range->last_year !== $year)
            ? 1 : $range->next_number;

        return $this->format($range, $number, $year);
    }

    private function format(NumberRange $range, int $number, int $year): string
    {
        $parts = array_filter([
            $range->prefix !== '' ? $range->prefix : null,
            $range->include_year ? (string) $year : null,
        ]);
        $digits = str_pad((string) $number, $range->padding, '0', STR_PAD_LEFT);

        return $parts === [] ? $digits : implode('-', $parts).'-'.$digits;
    }
}
