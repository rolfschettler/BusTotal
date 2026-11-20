<?php

namespace Tests\Unit;

use App\Enums\GermanState;
use App\Pricing\GermanHolidays;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class GermanHolidaysTest extends TestCase
{
    private function on(string $date, GermanState $state): ?string
    {
        return GermanHolidays::name(Carbon::parse($date), $state);
    }

    public function test_ostersonntag_stimmt_fuer_mehrere_jahre(): void
    {
        foreach (['2024-03-31', '2025-04-20', '2026-04-05', '2027-03-28', '2038-04-25'] as $easter) {
            $this->assertSame($easter, GermanHolidays::easter((int) substr($easter, 0, 4))->toDateString());
        }
    }

    public function test_bewegliche_feiertage_haengen_an_ostern(): void
    {
        // Ostern 2027: 28.03.
        $this->assertSame('Karfreitag', $this->on('2027-03-26', GermanState::Berlin));
        $this->assertSame('Ostermontag', $this->on('2027-03-29', GermanState::Berlin));
        $this->assertSame('Christi Himmelfahrt', $this->on('2027-05-06', GermanState::Berlin));
        $this->assertSame('Pfingstmontag', $this->on('2027-05-17', GermanState::Berlin));
    }

    public function test_feiertage_die_nur_in_manchen_laendern_gelten(): void
    {
        // Fronleichnam 2027: 27.05.
        $this->assertSame('Fronleichnam', $this->on('2027-05-27', GermanState::Bayern));
        $this->assertNull($this->on('2027-05-27', GermanState::Berlin));
        $this->assertNull($this->on('2027-05-27', GermanState::Sachsen));

        $this->assertSame('Allerheiligen', $this->on('2027-11-01', GermanState::NordrheinWestfalen));
        $this->assertNull($this->on('2027-11-01', GermanState::Niedersachsen));

        $this->assertSame('Reformationstag', $this->on('2027-10-31', GermanState::Niedersachsen));
        $this->assertNull($this->on('2027-10-31', GermanState::Bayern));

        $this->assertSame('Heilige Drei Könige', $this->on('2027-01-06', GermanState::SachsenAnhalt));
        $this->assertNull($this->on('2027-01-06', GermanState::Hessen));

        $this->assertSame('Mariä Himmelfahrt', $this->on('2027-08-15', GermanState::Saarland));
        $this->assertNull($this->on('2027-08-15', GermanState::Bayern));
    }

    public function test_buss_und_bettag_nur_in_sachsen(): void
    {
        $this->assertSame('Buß- und Bettag', $this->on('2026-11-18', GermanState::Sachsen));   // 22.11.2026 ist ein Sonntag
        $this->assertSame('Buß- und Bettag', $this->on('2025-11-19', GermanState::Sachsen));
        $this->assertSame('Buß- und Bettag', $this->on('2023-11-22', GermanState::Sachsen));   // 22.11.2023 ist selbst ein Mittwoch
        $this->assertNull($this->on('2026-11-18', GermanState::Bayern));
    }

    public function test_landesfeiertage_mit_einfuehrungsjahr(): void
    {
        $this->assertSame('Internationaler Frauentag', $this->on('2026-03-08', GermanState::Berlin));
        $this->assertNull($this->on('2026-03-08', GermanState::Brandenburg));
        $this->assertNull($this->on('2022-03-08', GermanState::MecklenburgVorpommern));
        $this->assertSame('Internationaler Frauentag', $this->on('2023-03-08', GermanState::MecklenburgVorpommern));
        $this->assertSame('Weltkindertag', $this->on('2026-09-20', GermanState::Thueringen));
    }

    public function test_bundesweite_feiertage_gelten_ueberall(): void
    {
        foreach (GermanState::cases() as $state) {
            foreach (['2027-01-01', '2027-05-01', '2027-10-03', '2027-12-25', '2027-12-26'] as $date) {
                $this->assertNotNull($this->on($date, $state), "$date in {$state->value}");
            }
            $this->assertNull($this->on('2027-06-15', $state));
        }
    }
}
