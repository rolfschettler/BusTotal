<?php

namespace App\Routing;

/**
 * Plant aus den reinen Fahrzeiten einer Tour den zeitlichen Ablauf mit den gesetzlichen Lenk- und Ruhezeiten
 * (Verordnung (EG) Nr. 561/2006) und sagt, wie lange die Tour wirklich dauert.
 *
 * Gerechnet wird mit diesen Regeln (bewusst die einfache, sichere Auslegung für die PLANUNG):
 *  - Nach höchstens 4,5 Stunden Lenkzeit folgt eine Pause von 45 Minuten am Stück (die erlaubte Teilung in
 *    15 + 30 Minuten wird nicht ausgenutzt, das ist die Reserve für Verzögerungen).
 *  - Die Tageslenkzeit beträgt höchstens 9 Stunden; zweimal je Woche sind 10 Stunden erlaubt (Parameter
 *    $extendedDays: die ersten n Tage der Tour dürfen 10 Stunden lenken).
 *  - Danach folgt eine Tagesruhezeit von 11 Stunden. Sie muss spätestens 24 Stunden nach Beginn des Tagesabschnitts
 *    beginnen, also darf ein Arbeitstag höchstens 13 Stunden dauern (Lenken, Pausen und Halte zusammen).
 *  - Halte am Ort zählen als Arbeitszeit und verbrauchen das 13-Stunden-Fenster. Ist der Fahrer währenddessen frei
 *    (driver_free), zählt ein Halt von mindestens 45 Minuten als Pause und einer von mindestens 11 Stunden
 *    (Übernachtung) als Tagesruhezeit.
 *
 * NICHT geprüft (steht als Hinweis im Ergebnis): Wochenlenkzeit (56 Stunden), Doppelwochenlenkzeit (90 Stunden),
 * Wochenruhezeit, verkürzte Tagesruhezeit (9 Stunden), mehrere Fahrer, Fahrer, die vorher schon gelenkt haben.
 * Die Planung ersetzt nicht die Auswertung des Fahrtenschreibers.
 *
 * Die Klasse rechnet nur mit Sekunden und kennt weder Datenbank noch Routing-Dienst.
 */
class DrivingTimePlanner
{
    public const BREAK_AFTER_S = 16200;   // 4,5 Stunden Lenkzeit

    public const BREAK_S = 2700;          // 45 Minuten

    public const DAILY_DRIVE_S = 32400;   // 9 Stunden

    public const EXTENDED_DRIVE_S = 36000; // 10 Stunden

    public const DAILY_REST_S = 39600;    // 11 Stunden

    public const WORK_WINDOW_S = 46800;   // 13 Stunden = 24 Stunden - 11 Stunden Ruhezeit

    public const WEEKLY_DRIVE_S = 201600; // 56 Stunden

    /**
     * @param  list<array{drive_s: int, dwell_s?: int, driver_free?: bool}>  $legs  Fahrzeit je Abschnitt; der Halt (dwell_s) folgt am Ende des Abschnitts
     * @param  int  $initialDwellS  Arbeitszeit vor der ersten Fahrt (z. B. Einsteigen der Fahrgäste am Start)
     * @param  int  $extendedDays  Anzahl der Tage (vom ersten an), an denen 10 statt 9 Stunden gelenkt werden dürfen (0 bis 2)
     * @return array{events: list<array<string, mixed>>, summary: array<string, int>, warnings: list<array{code: string, message: string}>}
     */
    public function plan(array $legs, int $initialDwellS = 0, int $extendedDays = 0): array
    {
        $events = [];
        $warnings = [];
        $t = 0;
        $driveSinceBreak = 0;
        $driveToday = 0;
        $dayStart = 0;
        $day = 1;
        $extendedLeft = max(0, min(2, $extendedDays));
        $dayLimit = $extendedLeft-- > 0 ? self::EXTENDED_DRIVE_S : self::DAILY_DRIVE_S;
        $totals = ['drive_s' => 0, 'break_s' => 0, 'rest_s' => 0, 'stop_s' => 0];
        $drivingDays = [];

        $add = function (string $type, int $duration, ?int $leg = null, array $extra = []) use (&$events, &$t, &$day) {
            $events[] = ['type' => $type, 'start_s' => $t, 'duration_s' => $duration, 'leg' => $leg, 'day' => $day] + $extra;
            $t += $duration;
        };

        $rest = function () use (&$t, &$driveSinceBreak, &$driveToday, &$dayStart, &$day, &$dayLimit, &$extendedLeft, &$totals, $add) {
            $add('rest', self::DAILY_REST_S);
            $totals['rest_s'] += self::DAILY_REST_S;
            $driveSinceBreak = 0;
            $driveToday = 0;
            $dayStart = $t;
            $day++;
            $dayLimit = $extendedLeft-- > 0 ? self::EXTENDED_DRIVE_S : self::DAILY_DRIVE_S;
        };

        if ($initialDwellS > 0) {
            $add('stop', $initialDwellS, null, ['driver_free' => false]);
            $totals['stop_s'] += $initialDwellS;
        }

        foreach ($legs as $i => $leg) {
            $remaining = max(0, (int) $leg['drive_s']);

            while ($remaining > 0) {
                $windowLeft = self::WORK_WINDOW_S - ($t - $dayStart);
                $untilBreak = self::BREAK_AFTER_S - $driveSinceBreak;
                $dayLeft = $dayLimit - $driveToday;

                if ($windowLeft <= 0 || $dayLeft <= 0) {
                    $rest();

                    continue;
                }
                if ($untilBreak <= 0) {
                    // Reicht das Arbeitsfenster nicht mehr für die Pause, beginnt gleich die Tagesruhezeit (sie ersetzt die Pause).
                    if ($windowLeft <= self::BREAK_S) {
                        $rest();

                        continue;
                    }
                    $add('break', self::BREAK_S);
                    $totals['break_s'] += self::BREAK_S;
                    $driveSinceBreak = 0;

                    continue;
                }

                $chunk = min($remaining, $untilBreak, $dayLeft, $windowLeft);
                $add('drive', $chunk, $i);
                $driveSinceBreak += $chunk;
                $driveToday += $chunk;
                $remaining -= $chunk;
                $totals['drive_s'] += $chunk;
                $drivingDays[$day] = true;
            }

            $dwell = max(0, (int) ($leg['dwell_s'] ?? 0));
            if ($dwell > 0) {
                $free = (bool) ($leg['driver_free'] ?? false);
                $add('stop', $dwell, $i, ['driver_free' => $free]);
                $totals['stop_s'] += $dwell;

                if ($free && $dwell >= self::DAILY_REST_S) {
                    // Übernachtung: zählt als Tagesruhezeit, ein neuer Tagesabschnitt beginnt.
                    $driveSinceBreak = 0;
                    $driveToday = 0;
                    $dayStart = $t;
                    $day++;
                    $dayLimit = $extendedLeft-- > 0 ? self::EXTENDED_DRIVE_S : self::DAILY_DRIVE_S;
                } else {
                    if ($free && $dwell >= self::BREAK_S) {
                        $driveSinceBreak = 0;
                    }
                    if ($t - $dayStart > self::WORK_WINDOW_S) {
                        $warnings[] = ['code' => 'window_exceeded', 'message' => 'Nach dem Halt nach Abschnitt '.($i + 1).' ist der 13-Stunden-Arbeitstag überschritten: Die Tagesruhezeit hätte schon vorher beginnen müssen. Den Halt kürzen oder die Tour auf mehrere Tage verteilen.'];
                    }
                }
            }
        }

        if ($totals['drive_s'] > self::WEEKLY_DRIVE_S) {
            $warnings[] = ['code' => 'weekly_driving', 'message' => 'Die Gesamtlenkzeit liegt über 56 Stunden (Wochenlenkzeit). Die Tour muss auf mehrere Wochen oder mehrere Fahrer verteilt werden.'];
        }
        if ($t > 6 * 86400) {
            $warnings[] = ['code' => 'weekly_rest', 'message' => 'Die Tour dauert länger als 6 Tage: Spätestens dann ist eine Wochenruhezeit (regelmäßig 45 Stunden) nötig. Sie ist in dieser Planung nicht enthalten.'];
        }

        return [
            'events' => $events,
            'summary' => [
                'elapsed_s' => $t,
                'drive_s' => $totals['drive_s'],
                'break_s' => $totals['break_s'],
                'rest_s' => $totals['rest_s'],
                'stop_s' => $totals['stop_s'],
                'days' => max(1, count($drivingDays)),
            ],
            'warnings' => $warnings,
        ];
    }
}
