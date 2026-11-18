<?php

namespace Tests\Unit;

use App\Routing\DrivingTimePlanner;
use PHPUnit\Framework\TestCase;

/** Die Lenk- und Ruhezeitenplanung nach VO (EG) 561/2006 (siehe DrivingTimePlanner). Reine Rechnung, keine Datenbank. */
class DrivingTimePlannerTest extends TestCase
{
    private const H = 3600;

    private function plan(array $legs, int $initial = 0, int $extended = 0): array
    {
        return (new DrivingTimePlanner)->plan($legs, $initial, $extended);
    }

    /** @return list<string> Typen der Ereignisse in Reihenfolge */
    private function types(array $plan): array
    {
        return array_column($plan['events'], 'type');
    }

    public function test_kurze_fahrt_hat_keine_pause(): void
    {
        $plan = $this->plan([['drive_s' => 3 * self::H]]);

        $this->assertSame(['drive'], $this->types($plan));
        $this->assertSame(3 * self::H, $plan['summary']['elapsed_s']);
        $this->assertSame(0, $plan['summary']['break_s']);
        $this->assertSame(1, $plan['summary']['days']);
    }

    public function test_nach_viereinhalb_stunden_folgt_eine_pause_von_45_minuten(): void
    {
        $plan = $this->plan([['drive_s' => 6 * self::H]]);

        $this->assertSame(['drive', 'break', 'drive'], $this->types($plan));
        $this->assertSame(16200, $plan['events'][0]['duration_s']);
        $this->assertSame(2700, $plan['events'][1]['duration_s']);
        $this->assertSame(6 * self::H + 2700, $plan['summary']['elapsed_s']);
    }

    public function test_genau_viereinhalb_stunden_brauchen_noch_keine_pause(): void
    {
        $plan = $this->plan([['drive_s' => 16200]]);

        $this->assertSame(['drive'], $this->types($plan));
    }

    public function test_nach_neun_stunden_folgt_die_tagesruhezeit_von_11_stunden(): void
    {
        $plan = $this->plan([['drive_s' => 10 * self::H]]);

        // 4,5 h, Pause, 4,5 h = 9 h; die Tagesruhezeit ersetzt die zweite Pause.
        $this->assertSame(['drive', 'break', 'drive', 'rest', 'drive'], $this->types($plan));
        $this->assertSame(10 * self::H, $plan['summary']['drive_s']);
        $this->assertSame(2700, $plan['summary']['break_s']);
        $this->assertSame(11 * self::H, $plan['summary']['rest_s']);
        $this->assertSame(2, $plan['summary']['days']);
        // Die letzte Fahrt (1 Stunde) liegt am zweiten Tag.
        $this->assertSame(2, end($plan['events'])['day']);
    }

    public function test_neun_stunden_lenkzeit_ohne_zusaetzliche_ruhezeit_am_ende(): void
    {
        $plan = $this->plan([['drive_s' => 9 * self::H]]);

        $this->assertNotContains('rest', $this->types($plan));
        $this->assertSame(1, $plan['summary']['days']);
    }

    public function test_zehn_stunden_an_verlaengerten_tagen(): void
    {
        $normal = $this->plan([['drive_s' => 10 * self::H]]);
        $verlaengert = $this->plan([['drive_s' => 10 * self::H]], 0, 1);

        $this->assertSame(1, $verlaengert['summary']['days']);
        $this->assertSame(0, $verlaengert['summary']['rest_s']);
        $this->assertSame(2, $normal['summary']['days']);
    }

    public function test_verlaengerte_tage_gelten_nur_fuer_die_ersten_n_tage(): void
    {
        // 30 Stunden Lenkzeit, 1 verlängerter Tag: 10 + 9 + 9 + 2
        $plan = $this->plan([['drive_s' => 30 * self::H]], 0, 1);

        $perDay = [];
        foreach ($plan['events'] as $e) {
            if ($e['type'] === 'drive') {
                $perDay[$e['day']] = ($perDay[$e['day']] ?? 0) + $e['duration_s'];
            }
        }
        $this->assertSame([10 * self::H, 9 * self::H, 9 * self::H, 2 * self::H], array_values($perDay));
    }

    public function test_der_dreizehn_stunden_arbeitstag_erzwingt_die_ruhezeit(): void
    {
        // Lenken 4,5 h + Halt (nicht frei) 8 h = 12,5 h. Für die nötige Pause von 45 Minuten reicht das Fenster nicht mehr:
        // Die Ruhezeit beginnt sofort (und ersetzt die Pause), statt über die 13 Stunden hinauszugehen.
        $plan = $this->plan([
            ['drive_s' => 16200, 'dwell_s' => 8 * self::H, 'driver_free' => false],
            ['drive_s' => 2 * self::H],
        ]);

        $types = $this->types($plan);
        $restAt = array_search('rest', $types, true);
        $this->assertNotFalse($restAt);
        // Bis zur Ruhezeit höchstens 13 Stunden seit Tagesbeginn.
        $this->assertLessThanOrEqual(13 * self::H, $plan['events'][$restAt]['start_s']);
    }

    public function test_halt_mit_freiem_fahrer_ab_45_minuten_zaehlt_als_pause(): void
    {
        // 4 h Lenken, 1 h Halt (Fahrer frei), 4 h Lenken: ohne Anrechnung käme nach 4,5 h Lenken eine Pause.
        $mitFreiem = $this->plan([['drive_s' => 4 * self::H, 'dwell_s' => self::H, 'driver_free' => true], ['drive_s' => 4 * self::H]]);
        $ohneFrei = $this->plan([['drive_s' => 4 * self::H, 'dwell_s' => self::H, 'driver_free' => false], ['drive_s' => 4 * self::H]]);

        $this->assertNotContains('break', $this->types($mitFreiem));
        $this->assertContains('break', $this->types($ohneFrei));
    }

    public function test_kurzer_halt_mit_freiem_fahrer_reicht_nicht_als_pause(): void
    {
        $plan = $this->plan([['drive_s' => 4 * self::H, 'dwell_s' => 30 * 60, 'driver_free' => true], ['drive_s' => 4 * self::H]]);

        $this->assertContains('break', $this->types($plan));
    }

    public function test_uebernachtung_mit_freiem_fahrer_zaehlt_als_tagesruhezeit(): void
    {
        $plan = $this->plan([
            ['drive_s' => 8 * self::H, 'dwell_s' => 12 * self::H, 'driver_free' => true],
            ['drive_s' => 8 * self::H],
        ]);

        // Keine zusätzliche Ruhezeit: Die Übernachtung ist die Ruhezeit.
        $this->assertNotContains('rest', $this->types($plan));
        $this->assertSame(2, $plan['summary']['days']);
        $this->assertSame([], $plan['warnings']);
    }

    public function test_langer_halt_ohne_freien_fahrer_ueberschreitet_den_arbeitstag(): void
    {
        $plan = $this->plan([['drive_s' => 2 * self::H, 'dwell_s' => 12 * self::H, 'driver_free' => false], ['drive_s' => self::H]]);

        $codes = array_column($plan['warnings'], 'code');
        $this->assertContains('window_exceeded', $codes);
    }

    public function test_anfangsarbeit_verbraucht_das_arbeitsfenster(): void
    {
        $plan = $this->plan([['drive_s' => self::H]], 2 * self::H);

        $this->assertSame(['stop', 'drive'], $this->types($plan));
        $this->assertSame(3 * self::H, $plan['summary']['elapsed_s']);
        $this->assertSame(2 * self::H, $plan['summary']['stop_s']);
    }

    public function test_ereignisse_schliessen_lueckenlos_aneinander_an(): void
    {
        $plan = $this->plan([
            ['drive_s' => 7 * self::H, 'dwell_s' => 90 * 60, 'driver_free' => true],
            ['drive_s' => 6 * self::H, 'dwell_s' => 600],
            ['drive_s' => 5 * self::H],
        ], 1800);

        $t = 0;
        foreach ($plan['events'] as $e) {
            $this->assertSame($t, $e['start_s'], 'Lücke oder Überlappung vor '.$e['type']);
            $t += $e['duration_s'];
        }
        $this->assertSame($t, $plan['summary']['elapsed_s']);
        $this->assertSame(
            $plan['summary']['elapsed_s'],
            $plan['summary']['drive_s'] + $plan['summary']['break_s'] + $plan['summary']['rest_s'] + $plan['summary']['stop_s'],
        );
    }

    public function test_nie_mehr_als_viereinhalb_stunden_am_stueck_und_nie_mehr_als_neun_pro_tag(): void
    {
        $plan = $this->plan([['drive_s' => 40 * self::H]]);

        $sinceBreak = 0;
        $today = 0;
        foreach ($plan['events'] as $e) {
            if ($e['type'] === 'drive') {
                $sinceBreak += $e['duration_s'];
                $today += $e['duration_s'];
            } elseif ($e['type'] === 'break') {
                $sinceBreak = 0;
            } elseif ($e['type'] === 'rest') {
                $sinceBreak = 0;
                $today = 0;
            }
            $this->assertLessThanOrEqual(16200, $sinceBreak);
            $this->assertLessThanOrEqual(32400, $today);
        }
        $this->assertSame(40 * self::H, $plan['summary']['drive_s']);
    }

    public function test_hinweis_bei_ueber_56_stunden_lenkzeit(): void
    {
        $plan = $this->plan([['drive_s' => 57 * self::H]]);

        $this->assertContains('weekly_driving', array_column($plan['warnings'], 'code'));
    }

    public function test_hinweis_bei_mehr_als_sechs_tagen(): void
    {
        $plan = $this->plan([['drive_s' => 8 * self::H, 'dwell_s' => 7 * 86400, 'driver_free' => true], ['drive_s' => self::H]]);

        $this->assertContains('weekly_rest', array_column($plan['warnings'], 'code'));
    }

    public function test_leere_tour_und_null_abschnitte(): void
    {
        $plan = $this->plan([['drive_s' => 0], ['drive_s' => 0, 'dwell_s' => 600]]);

        $this->assertSame(600, $plan['summary']['elapsed_s']);
        $this->assertSame(0, $plan['summary']['drive_s']);
        $this->assertSame(1, $plan['summary']['days']);
    }
}
