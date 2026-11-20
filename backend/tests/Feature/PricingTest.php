<?php

namespace Tests\Feature;

use App\Enums\GermanState;
use App\Enums\Role;
use App\Enums\VehicleType;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\PricingSetting;
use App\Models\PricingVehicleRate;
use App\Models\User;
use App\Models\Vehicle;
use App\Pricing\PriceCalculator;
use App\Pricing\StandardPriceCalculator;
use App\Pricing\TourDay;
use App\Pricing\TourFigures;
use Carbon\Carbon;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
    }

    private function as(Role $role): static
    {
        return $this->actingAs(User::factory()->create(['role' => $role]), 'sanctum');
    }

    private function prices(array $override = []): void
    {
        PricingSetting::current()->update(array_merge([
            'price_per_km' => 1.80, 'empty_km_percent' => 50, 'hourly_rate' => 0, 'day_rate' => 0,
            'overnight_rate' => 0, 'minimum_price' => 0, 'tax_rate' => 19,
        ], $override));
    }

    private function tour(array $override = []): array
    {
        return array_merge(['loaded_km' => 400, 'empty_km' => 100, 'drive_s' => 18000, 'days' => 1, 'label' => 'München – Salzburg'], $override);
    }

    // ---------- Einstellungen ----------

    public function test_preise_lesen_duerfen_alle_ausser_fahrer_aendern_nur_admin(): void
    {
        $this->as(Role::Buchhaltung)->getJson('/api/settings/pricing')->assertOk()->assertJsonPath('empty_km_percent', 100);
        $this->as(Role::Fahrer)->getJson('/api/settings/pricing')->assertForbidden();

        $body = ['price_per_km' => 1.9, 'empty_km_percent' => 50, 'hourly_rate' => 30, 'day_rate' => 100,
            'overnight_rate' => 80, 'minimum_price' => 400, 'tax_rate' => 19];
        $this->as(Role::Disponent)->putJson('/api/settings/pricing', $body)->assertForbidden();
        $this->as(Role::Admin)->putJson('/api/settings/pricing', $body)->assertOk()->assertJsonPath('price_per_km', '1.90');
    }

    public function test_ungueltige_preise_werden_abgelehnt(): void
    {
        $this->as(Role::Admin)->putJson('/api/settings/pricing', [
            'price_per_km' => -1, 'empty_km_percent' => 150, 'hourly_rate' => 0, 'day_rate' => 0,
            'overnight_rate' => 0, 'minimum_price' => 0, 'tax_rate' => 19,
        ])->assertUnprocessable()->assertJsonValidationErrors(['price_per_km', 'empty_km_percent']);
    }

    // ---------- Rechnen ----------

    public function test_kilometer_mit_leerfahrt_anteil(): void
    {
        $this->prices();
        $lines = app(StandardPriceCalculator::class)->quote(new TourFigures(400, 100, 18000, 1));

        $this->assertCount(1, $lines);
        $this->assertSame('Fahrstrecke', $lines[0]['title']);
        $this->assertSame('450', $lines[0]['quantity']);   // 400 + 100 × 50 %
        $this->assertSame('km', $lines[0]['unit']);
        $this->assertStringContainsString('100 km Leerfahrt zu 50 %', $lines[0]['description']);
    }

    public function test_alle_bestandteile_und_naechte(): void
    {
        $this->prices(['hourly_rate' => 30, 'day_rate' => 120, 'overnight_rate' => 80]);
        $lines = app(StandardPriceCalculator::class)->quote(new TourFigures(400, 0, 18000, 3));

        $byTitle = collect($lines)->keyBy('title');
        $this->assertSame('5', $byTitle['Fahrerzeit']['quantity']);          // 18000 s
        $this->assertSame('3', $byTitle['Tagespauschale']['quantity']);
        $this->assertSame('2', $byTitle['Übernachtung Fahrer']['quantity']); // 3 Tage → 2 Nächte
        $this->assertNull($byTitle['Fahrstrecke']['description']);          // ohne Leerfahrt kein Zusatz
    }

    public function test_mindestpreis_fuellt_nur_auf(): void
    {
        $this->prices(['minimum_price' => 1000]);
        $lines = app(StandardPriceCalculator::class)->quote(new TourFigures(100, 0, 3600, 1)); // 100 km × 1,80 = 180

        $this->assertSame('Mindestpreis-Ausgleich', $lines[1]['title']);
        $this->assertSame('820.00', $lines[1]['unit_price']);                 // 1000 − 180

        $this->prices(['minimum_price' => 100]);
        $this->assertCount(1, app(StandardPriceCalculator::class)->quote(new TourFigures(100, 0, 3600, 1)));
    }

    public function test_zuschlag_in_prozent_auf_die_summe(): void
    {
        $this->prices(['price_per_km' => 2]);
        $lines = app(StandardPriceCalculator::class)->quote(new TourFigures(500, 0, 0, 1, 10));  // 1000 € + 10 %

        $this->assertSame('Zuschlag 10 %', $lines[1]['title']);
        $this->assertSame('100.00', $lines[1]['unit_price']);
    }

    // ---------- Zuschläge für Samstag, Sonntag und Feiertag ----------

    private function day(string $date, ?string $holiday = null): TourDay
    {
        $d = Carbon::parse($date);

        return new TourDay($date, $d->isSaturday(), $d->isSunday(), $holiday);
    }

    private function dayRates(int $sat, int $sun, int $holiday): void
    {
        $this->prices(['price_per_km' => 2, 'empty_km_percent' => 100, 'surcharge_saturday_percent' => $sat,
            'surcharge_sunday_percent' => $sun, 'surcharge_holiday_percent' => $holiday]);
    }

    public function test_tageszuschlaege_auf_den_anteil_des_tages(): void
    {
        $this->dayRates(25, 50, 100);
        // Fr 30.04., Sa 01.05. (Tag der Arbeit), So 02.05.: 1000 € auf 3 Tage
        $days = [$this->day('2027-04-30'), $this->day('2027-05-01', 'Tag der Arbeit'), $this->day('2027-05-02')];
        $lines = collect(app(StandardPriceCalculator::class)->quote(new TourFigures(500, 0, 0, 3, 0, null, $days)))->keyBy('title');

        $this->assertSame('333.33', $lines['Zuschlag Feiertag']['unit_price']);   // 1000 / 3 × 100 %
        $this->assertSame('166.67', $lines['Zuschlag Sonntag']['unit_price']);    // 1000 / 3 × 50 %
        $this->assertFalse($lines->has('Zuschlag Samstag'));                      // der Samstag ist zugleich Feiertag: nur der höhere Satz
        $this->assertStringContainsString('1 von 3 Tagen (Tag der Arbeit 01.05.), 100 %', $lines['Zuschlag Feiertag']['description']);
    }

    public function test_bei_zwei_zutreffenden_saetzen_gilt_nur_der_hoechste(): void
    {
        $this->dayRates(0, 50, 20);
        // 1. Weihnachtstag 2022 fiel auf einen Sonntag: Sonntag 50 % schlägt Feiertag 20 %
        $lines = app(StandardPriceCalculator::class)->quote(new TourFigures(500, 0, 0, 1, 0, null, [$this->day('2022-12-25', '1. Weihnachtstag')]));

        $this->assertSame(['Fahrstrecke', 'Zuschlag Sonntag'], array_column($lines, 'title'));
        $this->assertSame('500.00', $lines[1]['unit_price']);
    }

    public function test_satz_null_und_werktage_ergeben_keinen_zuschlag(): void
    {
        $this->dayRates(0, 0, 0);
        $days = [$this->day('2027-05-01', 'Tag der Arbeit'), $this->day('2027-05-02')];
        $this->assertCount(1, app(StandardPriceCalculator::class)->quote(new TourFigures(500, 0, 0, 2, 0, null, $days)));

        $this->dayRates(25, 50, 100);
        $this->assertCount(1, app(StandardPriceCalculator::class)->quote(new TourFigures(500, 0, 0, 1, 0, null, [$this->day('2027-05-04')])));
        $this->assertCount(1, app(StandardPriceCalculator::class)->quote(new TourFigures(500, 0, 0, 1)));   // ohne Kalendertage
    }

    public function test_handzuschlag_rechnet_auf_die_summe_ohne_tageszuschlag(): void
    {
        $this->dayRates(0, 50, 0);
        $lines = collect(app(StandardPriceCalculator::class)->quote(new TourFigures(500, 0, 0, 1, 10, null, [$this->day('2027-05-02')])))->keyBy('title');

        $this->assertSame('500.00', $lines['Zuschlag Sonntag']['unit_price']);
        $this->assertSame('100.00', $lines['Zuschlag 10 %']['unit_price']);   // 10 % von 1000, nicht von 1500
    }

    public function test_angebot_aus_tour_erkennt_feiertag_nach_bundesland(): void
    {
        $this->dayRates(0, 0, 50);
        CompanySetting::current()->update(['state' => 'NI']);
        $tour = $this->tour(['empty_km' => 0, 'loaded_km' => 500, 'start_at' => '2027-11-01T08:00:00+01:00']);   // Allerheiligen: Mo

        // Betriebssitz Niedersachsen: kein Feiertag
        $this->as(Role::Disponent)->postJson('/api/quotes/from-tour', $tour)->assertCreated()->assertJsonPath('net_total', '1000.00');
        // für diese Tour Bayern gewählt: Allerheiligen, 50 % auf 1000 €
        $this->as(Role::Disponent)->postJson('/api/quotes/from-tour', $tour + ['state' => 'BY'])->assertCreated()
            ->assertJsonPath('net_total', '1500.00')
            ->assertJsonPath('lines.1.title', 'Zuschlag Feiertag');
    }

    public function test_betriebssitz_ist_die_vorbelegung_und_ohne_bundesland_zaehlen_nur_wochenenden(): void
    {
        $this->dayRates(0, 50, 50);
        $tour = $this->tour(['empty_km' => 0, 'loaded_km' => 500, 'start_at' => '2027-11-01T08:00:00+01:00']);

        $this->as(Role::Admin)->postJson('/api/quotes/from-tour', $tour)->assertCreated()->assertJsonPath('net_total', '1000.00');

        CompanySetting::current()->update(['state' => 'BY']);
        $this->as(Role::Admin)->postJson('/api/quotes/from-tour', $tour)->assertCreated()->assertJsonPath('net_total', '1500.00');
    }

    public function test_der_tag_zaehlt_in_deutscher_zeit(): void
    {
        $this->dayRates(0, 0, 50);
        CompanySetting::current()->update(['state' => 'BY']);

        // 23:30 Uhr UTC am 31.10. ist in Deutschland bereits der 1.11. (Allerheiligen)
        $this->as(Role::Admin)->postJson('/api/quotes/from-tour', $this->tour(['empty_km' => 0, 'loaded_km' => 500, 'start_at' => '2027-10-31T23:30:00Z']))
            ->assertCreated()->assertJsonPath('service_date_from', '2027-11-01')->assertJsonPath('net_total', '1500.00');
    }

    public function test_mehrtagestour_zaehlt_die_tage_bis_zum_ende(): void
    {
        $this->dayRates(0, 0, 100);
        CompanySetting::current()->update(['state' => 'BY']);

        // Fr 29.10. bis Mo 01.11. (4 Kalendertage): nur der 01.11. ist Feiertag, also 1/4 der Summe
        $this->as(Role::Admin)->postJson('/api/quotes/from-tour', $this->tour([
            'empty_km' => 0, 'loaded_km' => 500, 'days' => 4, 'start_at' => '2027-10-29T08:00:00+02:00', 'end_at' => '2027-11-01T18:00:00+01:00',
        ]))->assertCreated()->assertJsonPath('net_total', '1250.00');
    }

    public function test_bundesland_und_zuschlagssaetze_speichern_und_pruefen(): void
    {
        $this->as(Role::Admin)->putJson('/api/settings/company', ['name' => 'Test Reisen', 'country' => 'DE', 'payment_term_days' => 14, 'state' => 'BY'])
            ->assertOk()->assertJsonPath('state', 'BY');
        $this->as(Role::Admin)->putJson('/api/settings/company', ['name' => 'Test Reisen', 'country' => 'DE', 'payment_term_days' => 14, 'state' => 'XX'])
            ->assertUnprocessable()->assertJsonValidationErrors(['state']);

        $body = ['price_per_km' => 1, 'empty_km_percent' => 100, 'hourly_rate' => 0, 'day_rate' => 0, 'overnight_rate' => 0, 'minimum_price' => 0, 'tax_rate' => 19];
        $this->as(Role::Admin)->putJson('/api/settings/pricing', $body + ['surcharge_saturday_percent' => 10, 'surcharge_sunday_percent' => 25.5, 'surcharge_holiday_percent' => 50])
            ->assertOk()->assertJsonPath('surcharge_sunday_percent', '25.50')->assertJsonPath('company_state', 'BY');
        $this->as(Role::Admin)->putJson('/api/settings/pricing', $body + ['surcharge_holiday_percent' => -5])
            ->assertUnprocessable()->assertJsonValidationErrors(['surcharge_holiday_percent']);

        $this->as(Role::Disponent)->getJson('/api/settings/pricing')->assertOk()->assertJsonPath('company_state', 'BY');
    }

    public function test_bundeslaenderliste_im_frontend_passt_zum_enum(): void
    {
        // Die Auswahl im Frontend ist fest eingebaut (damit sie nie von einer Abfrage abhängt): Beide Listen müssen übereinstimmen.
        $ts = file_get_contents(base_path('../frontend/src/app/core/german-states.ts'));
        preg_match_all("/value: '([A-Z]{2})', label: '([^']+)'/", $ts, $m, PREG_SET_ORDER);
        $frontend = array_column($m, 2, 1);

        $backend = [];
        foreach (GermanState::cases() as $s) {
            $backend[$s->value] = $s->label();
        }

        $this->assertSame($backend, $frontend);
    }

    public function test_ungueltiges_bundesland_in_der_tour_wird_abgelehnt(): void
    {
        $this->prices();
        $this->as(Role::Admin)->postJson('/api/quotes/from-tour', $this->tour(['state' => 'XX']))
            ->assertUnprocessable()->assertJsonValidationErrors(['state']);
    }

    // ---------- Preise je Fahrzeugtyp ----------

    private function typeRates(string $type, array $rates): void
    {
        PricingVehicleRate::create(['vehicle_type' => $type] + $rates);
    }

    public function test_fahrzeugtyp_ueberschreibt_nur_ausgefuellte_felder(): void
    {
        $this->prices(['price_per_km' => 1.80, 'hourly_rate' => 30]);
        $this->typeRates('doppeldecker', ['price_per_km' => 2.50]);  // Stundensatz leer = Standard

        $byTitle = collect(app(StandardPriceCalculator::class)->quote(new TourFigures(400, 0, 7200, 1, 0, VehicleType::Doppeldecker)))->keyBy('title');
        $this->assertSame('2.50', $byTitle['Fahrstrecke']['unit_price']);
        $this->assertSame('30.00', $byTitle['Fahrerzeit']['unit_price']);

        // anderer Typ und ohne Fahrzeug: Standardpreis
        foreach ([VehicleType::Reisebus, null] as $type) {
            $lines = app(StandardPriceCalculator::class)->quote(new TourFigures(400, 0, 7200, 1, 0, $type));
            $this->assertSame('1.80', $lines[0]['unit_price']);
        }
    }

    public function test_typpreis_null_schaltet_den_bestandteil_fuer_den_typ_aus(): void
    {
        $this->prices(['hourly_rate' => 30]);
        $this->typeRates('minibus', ['hourly_rate' => 0]);

        $lines = app(StandardPriceCalculator::class)->quote(new TourFigures(100, 0, 7200, 1, 0, VehicleType::Minibus));
        $this->assertSame(['Fahrstrecke'], array_column($lines, 'title'));
    }

    public function test_angebot_aus_tour_nimmt_den_typ_des_fahrzeugs(): void
    {
        $this->prices(['price_per_km' => 1.80]);
        $this->typeRates('doppeldecker', ['price_per_km' => 3.00]);
        $bus = Vehicle::factory()->create(['type' => 'doppeldecker']);

        $this->as(Role::Disponent)->postJson('/api/quotes/from-tour', $this->tour(['empty_km' => 0, 'vehicle_id' => $bus->id]))
            ->assertCreated()->assertJsonPath('net_total', '1200.00');   // 400 km × 3,00

        $this->as(Role::Disponent)->postJson('/api/quotes/from-tour', $this->tour(['empty_km' => 0]))
            ->assertCreated()->assertJsonPath('net_total', '720.00');    // ohne Fahrzeug: 400 km × 1,80
    }

    public function test_nur_typpreise_genuegen_fuer_ein_angebot(): void
    {
        $this->typeRates('reisebus', ['price_per_km' => 2]);
        $bus = Vehicle::factory()->create(['type' => 'reisebus']);

        $this->as(Role::Admin)->postJson('/api/quotes/from-tour', $this->tour(['vehicle_id' => $bus->id]))->assertCreated();
        $this->as(Role::Admin)->postJson('/api/quotes/from-tour', $this->tour())->assertUnprocessable();   // ohne Fahrzeug keine Standardpreise
    }

    public function test_typpreise_speichern_und_leeren(): void
    {
        $body = ['price_per_km' => 1.9, 'empty_km_percent' => 50, 'hourly_rate' => 30, 'day_rate' => 100,
            'overnight_rate' => 80, 'minimum_price' => 400, 'tax_rate' => 19];

        $res = $this->as(Role::Admin)->putJson('/api/settings/pricing', $body + [
            'vehicle_rates' => ['reisebus' => ['price_per_km' => 2.2, 'hourly_rate' => null], 'midibus' => ['price_per_km' => null]],
        ])->assertOk()
            ->assertJsonPath('vehicle_rates.reisebus.price_per_km', '2.20')
            ->assertJsonPath('vehicle_rates.reisebus.hourly_rate', null)
            ->assertJsonPath('vehicle_rates.midibus.price_per_km', null);
        $this->assertCount(5, $res->json('vehicle_types'));
        $this->assertDatabaseCount('pricing_vehicle_rates', 1);   // Midibus ganz leer: keine Zeile

        $this->as(Role::Admin)->putJson('/api/settings/pricing', $body + ['vehicle_rates' => ['reisebus' => ['price_per_km' => null]]])->assertOk();
        $this->assertDatabaseCount('pricing_vehicle_rates', 0);

        $this->as(Role::Buchhaltung)->getJson('/api/settings/pricing')->assertOk()->assertJsonStructure(['vehicle_rates' => ['reisebus' => ['price_per_km']]]);
    }

    public function test_ungueltiger_typpreis_wird_abgelehnt(): void
    {
        $this->as(Role::Admin)->putJson('/api/settings/pricing', [
            'price_per_km' => 1, 'empty_km_percent' => 100, 'hourly_rate' => 0, 'day_rate' => 0, 'overnight_rate' => 0, 'minimum_price' => 0, 'tax_rate' => 19,
            'vehicle_rates' => ['reisebus' => ['price_per_km' => -2]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['vehicle_rates.reisebus.price_per_km']);
    }

    public function test_ohne_preise_keine_positionen(): void
    {
        $this->assertSame([], app(StandardPriceCalculator::class)->quote(new TourFigures(400, 0, 18000, 1)));
    }

    public function test_preisfindung_ist_austauschbar(): void
    {
        $this->assertInstanceOf(StandardPriceCalculator::class, app(PriceCalculator::class));

        $this->app->bind(PriceCalculator::class, fn () => new class implements PriceCalculator
        {
            public function quote(TourFigures $tour): array
            {
                return [['title' => 'Festpreis', 'description' => null, 'quantity' => '1', 'unit' => 'pauschal',
                    'unit_price' => '99.00', 'tax_rate' => '19', 'tax_note' => null]];
            }
        });
        $this->prices();

        $this->as(Role::Admin)->postJson('/api/quotes/from-tour', $this->tour())->assertCreated()
            ->assertJsonPath('lines.0.title', 'Festpreis');
    }

    // ---------- Angebot aus Tour ----------

    public function test_angebot_aus_tour_wird_ein_entwurf_mit_positionen(): void
    {
        $this->prices();

        $this->as(Role::Disponent)->postJson('/api/quotes/from-tour', $this->tour(['start_at' => '2027-05-10T06:00:00+02:00']))
            ->assertCreated()
            ->assertJsonPath('type', 'angebot')
            ->assertJsonPath('status', 'entwurf')
            ->assertJsonPath('number', null)
            ->assertJsonPath('subject', 'Busfahrt München – Salzburg')
            ->assertJsonPath('service_date_from', '2027-05-10')
            ->assertJsonPath('customer_id', null)
            ->assertJsonPath('lines.0.quantity', '450.000')
            ->assertJsonPath('net_total', '810.00')      // 450 km × 1,80
            ->assertJsonPath('tax_total', '153.90');
    }

    public function test_angebot_aus_tour_kann_einen_kunden_tragen(): void
    {
        $this->prices();
        $customer = Customer::factory()->create();

        $this->as(Role::Admin)->postJson('/api/quotes/from-tour', $this->tour(['customer_id' => $customer->id]))
            ->assertCreated()->assertJsonPath('customer_id', $customer->id);
    }

    public function test_ohne_hinterlegte_preise_gibt_es_eine_klare_meldung(): void
    {
        $res = $this->as(Role::Admin)->postJson('/api/quotes/from-tour', $this->tour())->assertUnprocessable();

        $this->assertStringContainsString('Preise', $res->json('errors.pricing.0'));
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_angebot_aus_tour_nur_fuer_admin_und_disponent(): void
    {
        $this->prices();

        $this->as(Role::Buchhaltung)->postJson('/api/quotes/from-tour', $this->tour())->assertForbidden();
        $this->as(Role::Fahrer)->postJson('/api/quotes/from-tour', $this->tour())->assertForbidden();
    }

    public function test_ohne_anmeldung_kein_zugriff(): void
    {
        $this->postJson('/api/quotes/from-tour', $this->tour())->assertUnauthorized();
        $this->getJson('/api/settings/pricing')->assertUnauthorized();
    }

    public function test_unsinnige_kennzahlen_werden_abgelehnt(): void
    {
        $this->prices();

        $this->as(Role::Admin)->postJson('/api/quotes/from-tour', $this->tour(['loaded_km' => -5, 'days' => 0]))
            ->assertUnprocessable()->assertJsonValidationErrors(['loaded_km', 'days']);
    }
}
