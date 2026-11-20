<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\VehicleType;
use App\Models\Customer;
use App\Models\PricingSetting;
use App\Models\PricingVehicleRate;
use App\Models\User;
use App\Models\Vehicle;
use App\Pricing\PriceCalculator;
use App\Pricing\StandardPriceCalculator;
use App\Pricing\TourFigures;
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
