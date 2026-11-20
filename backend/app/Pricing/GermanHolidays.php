<?php

namespace App\Pricing;

use App\Enums\GermanState;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Gesetzliche Feiertage je Bundesland, selbst gerechnet (keine Fremddaten, kein Dienst). Stand der Regeln: ab 2019.
 *
 * Bewusst nicht enthalten: Feiertage, die nur in Teilen eines Landes gelten (Mariä Himmelfahrt in Bayern nur in
 * überwiegend katholischen Gemeinden, Augsburger Friedensfest). Wer dort fährt, trägt den Zuschlag von Hand ein.
 */
final class GermanHolidays
{
    /** @var array<string, array<string, string>> Zwischenspeicher je Jahr und Land */
    private static array $cache = [];

    /** Name des Feiertags an diesem Tag in diesem Land, sonst null. */
    public static function name(CarbonInterface $date, GermanState $state): ?string
    {
        return self::forYear($date->year, $state)[$date->toDateString()] ?? null;
    }

    /** @return array<string, string> Datum (Y-m-d) => Name */
    public static function forYear(int $year, GermanState $state): array
    {
        $key = $year.$state->value;
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $s = $state;
        $easter = self::easter($year);
        $days = [
            $year.'-01-01' => 'Neujahr',
            $easter->copy()->subDays(2)->toDateString() => 'Karfreitag',
            $easter->copy()->addDay()->toDateString() => 'Ostermontag',
            $year.'-05-01' => 'Tag der Arbeit',
            $easter->copy()->addDays(39)->toDateString() => 'Christi Himmelfahrt',
            $easter->copy()->addDays(50)->toDateString() => 'Pfingstmontag',
            $year.'-10-03' => 'Tag der Deutschen Einheit',
            $year.'-12-25' => '1. Weihnachtstag',
            $year.'-12-26' => '2. Weihnachtstag',
        ];

        if (in_array($s, [GermanState::BadenWuerttemberg, GermanState::Bayern, GermanState::SachsenAnhalt], true)) {
            $days[$year.'-01-06'] = 'Heilige Drei Könige';
        }
        if (in_array($s, [GermanState::BadenWuerttemberg, GermanState::Bayern, GermanState::Hessen, GermanState::NordrheinWestfalen, GermanState::RheinlandPfalz, GermanState::Saarland], true)) {
            $days[$easter->copy()->addDays(60)->toDateString()] = 'Fronleichnam';
        }
        if ($s === GermanState::Saarland) {
            $days[$year.'-08-15'] = 'Mariä Himmelfahrt';
        }
        if (in_array($s, [GermanState::Brandenburg, GermanState::Bremen, GermanState::Hamburg, GermanState::MecklenburgVorpommern, GermanState::Niedersachsen,
            GermanState::Sachsen, GermanState::SachsenAnhalt, GermanState::SchleswigHolstein, GermanState::Thueringen], true)) {
            $days[$year.'-10-31'] = 'Reformationstag';
        }
        if (in_array($s, [GermanState::BadenWuerttemberg, GermanState::Bayern, GermanState::NordrheinWestfalen, GermanState::RheinlandPfalz, GermanState::Saarland], true)) {
            $days[$year.'-11-01'] = 'Allerheiligen';
        }
        if ($s === GermanState::Sachsen) {
            // Mittwoch vor dem 23. November (fällt der 22. selbst auf einen Mittwoch, ist es dieser)
            $repentance = Carbon::create($year, 11, 22);
            while ($repentance->dayOfWeek !== Carbon::WEDNESDAY) {
                $repentance->subDay();
            }
            $days[$repentance->toDateString()] = 'Buß- und Bettag';
        }
        if ($s === GermanState::Berlin && $year >= 2019) {
            $days[$year.'-03-08'] = 'Internationaler Frauentag';
        }
        if ($s === GermanState::MecklenburgVorpommern && $year >= 2023) {
            $days[$year.'-03-08'] = 'Internationaler Frauentag';
        }
        if ($s === GermanState::Thueringen && $year >= 2019) {
            $days[$year.'-09-20'] = 'Weltkindertag';
        }

        ksort($days);

        return self::$cache[$key] = $days;
    }

    /** Ostersonntag nach dem Verfahren von Meeus, Jones und Butcher (gregorianischer Kalender). */
    public static function easter(int $year): Carbon
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return Carbon::create($year, $month, $day)->startOfDay();
    }
}
