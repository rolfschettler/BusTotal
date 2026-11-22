<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\PricingSetting;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Etappe 0.1: Fahrtart je Position und Steuersatz je Fahrtart.
 * Regeln: docs/fachlich/loesungskonzept-abrechnung.md Abschnitt 2.1, Recherche docs/fachlich/befoerderung-ergebnisse.md.
 */
class TripKindTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(SettingsSeeder::class);

        CompanySetting::current()->update([
            'name' => 'Busreisen Muster GmbH', 'street' => 'Hafenstr. 5', 'postal_code' => '20457',
            'city' => 'Hamburg', 'tax_number' => '12/345/67890', 'payment_term_days' => 14,
        ]);
        $this->customer = Customer::factory()->create([
            'company_name' => 'Reisebüro Sonnenschein', 'street' => 'Hauptstr. 1', 'postal_code' => '80331', 'city' => 'München',
        ]);
    }

    private function as(Role $role): static
    {
        return $this->actingAs(User::factory()->create(['role' => $role]), 'sanctum');
    }

    private function line(array $override = []): array
    {
        return array_merge([
            'title' => 'Linienfahrt Biberach – Ulm', 'quantity' => '1', 'unit' => 'Fahrt',
            'unit_price' => '100.00', 'tax_rate' => '7', 'trip_kind' => 'linie',
        ], $override);
    }

    private function draft(array $lines, string $type = 'rechnung'): string
    {
        return $this->as(Role::Buchhaltung)->postJson('/api/documents', [
            'type' => $type, 'customer_id' => $this->customer->id, 'service_date_from' => '2027-05-10', 'lines' => $lines,
        ])->assertCreated()->json('id');
    }

    private function prices(array $override = []): void
    {
        PricingSetting::current()->update(array_merge([
            'price_per_km' => 2.00, 'empty_km_percent' => 0, 'hourly_rate' => 0, 'day_rate' => 0,
            'overnight_rate' => 0, 'minimum_price' => 0, 'tax_rate' => 19,
        ], $override));
    }

    private function tour(array $override = []): array
    {
        return array_merge(['loaded_km' => 40, 'empty_km' => 0, 'drive_s' => 3600, 'days' => 1, 'label' => 'Biberach – Ulm'], $override);
    }

    // ---------- Fahrtart an der Position ----------

    public function test_fahrtart_wird_an_der_position_gespeichert_und_ausgegeben(): void
    {
        $id = $this->draft([$this->line(), $this->line(['title' => 'Charter', 'tax_rate' => '19', 'trip_kind' => 'gelegenheit']), $this->line(['title' => 'Maut', 'tax_rate' => '19', 'trip_kind' => null])]);

        $this->as(Role::Buchhaltung)->getJson("/api/documents/{$id}")->assertOk()
            ->assertJsonPath('lines.0.trip_kind', 'linie')
            ->assertJsonPath('lines.1.trip_kind', 'gelegenheit')
            ->assertJsonPath('lines.2.trip_kind', null);
    }

    public function test_unbekannte_fahrtart_wird_abgelehnt(): void
    {
        $this->as(Role::Buchhaltung)->postJson('/api/documents', [
            'type' => 'angebot', 'customer_id' => $this->customer->id, 'lines' => [$this->line(['trip_kind' => 'taxi-sonderfahrt'])],
        ])->assertUnprocessable()->assertJsonValidationErrors('lines.0.trip_kind');
    }

    public function test_fahrtart_bleibt_im_folgebeleg_und_im_storno(): void
    {
        $id = $this->draft([$this->line()], 'angebot');
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$id}/finalize")->assertOk();

        $invoice = $this->as(Role::Buchhaltung)->postJson("/api/documents/{$id}/convert", ['type' => 'rechnung'])->assertCreated();
        $invoice->assertJsonPath('lines.0.trip_kind', 'linie');

        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$invoice->json('id')}/finalize")->assertOk();
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$invoice->json('id')}/cancel")->assertCreated()
            ->assertJsonPath('lines.0.trip_kind', 'linie');
    }

    public function test_fahrtart_aendern_im_entwurf(): void
    {
        $id = $this->draft([$this->line()]);
        $this->as(Role::Buchhaltung)->putJson("/api/documents/{$id}", [
            'customer_id' => $this->customer->id, 'lines' => [$this->line(['trip_kind' => 'schule'])],
        ])->assertOk()->assertJsonPath('lines.0.trip_kind', 'schule');
    }

    // ---------- Prüfhinweise (warnen, sperren nie) ----------

    public function test_gelegenheitsverkehr_mit_ermaessigtem_satz_warnt(): void
    {
        $id = $this->draft([$this->line(['title' => 'Charter München', 'trip_kind' => 'gelegenheit', 'tax_rate' => '7'])]);

        $response = $this->as(Role::Buchhaltung)->getJson("/api/documents/{$id}")->assertOk();
        $this->assertCount(1, $response->json('warnings'));
        $this->assertStringContainsString('Gelegenheitsverkehr', $response->json('warnings.0'));
        $this->assertStringContainsString('Position 1', $response->json('warnings.0'));
    }

    public function test_ermaessigter_satz_bei_linie_erinnert_an_die_50_km(): void
    {
        $id = $this->draft([$this->line(), $this->line(['title' => 'Schulfahrt', 'trip_kind' => 'schule'])]);

        $warnings = $this->as(Role::Buchhaltung)->getJson("/api/documents/{$id}")->json('warnings');
        $this->assertCount(1, $warnings);   // beide Positionen in EINER Meldung
        $this->assertStringContainsString('Positionen 1 und 2', $warnings[0]);
        $this->assertStringContainsString('50 km', $warnings[0]);
    }

    public function test_passende_saetze_erzeugen_keine_warnung(): void
    {
        $id = $this->draft([
            $this->line(['title' => 'Charter', 'trip_kind' => 'gelegenheit', 'tax_rate' => '19']),
            $this->line(['title' => 'Maut', 'trip_kind' => null, 'tax_rate' => '19']),
        ]);

        $this->as(Role::Buchhaltung)->getJson("/api/documents/{$id}")->assertJsonPath('warnings', []);
    }

    public function test_warnung_sperrt_das_festschreiben_nicht(): void
    {
        $id = $this->draft([$this->line(['trip_kind' => 'gelegenheit', 'tax_rate' => '7'])]);

        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$id}/finalize")->assertOk();
    }

    // ---------- Steuersatz je Fahrtart in der Preisfindung ----------

    public function test_angebot_aus_tour_nimmt_ohne_angabe_gelegenheitsverkehr_mit_regelsatz(): void
    {
        $this->prices();

        $this->as(Role::Disponent)->postJson('/api/quotes/from-tour', $this->tour())->assertCreated()
            ->assertJsonPath('lines.0.tax_rate', '19.00')
            ->assertJsonPath('lines.0.trip_kind', 'gelegenheit');
    }

    public function test_angebot_aus_tour_nimmt_fuer_linie_und_schule_ihre_eigenen_saetze(): void
    {
        $this->prices();
        PricingSetting::current()->update(['tax_rate_line' => 7, 'tax_rate_school' => 7]);

        $this->as(Role::Disponent)->postJson('/api/quotes/from-tour', $this->tour(['trip_kind' => 'linie']))->assertCreated()
            ->assertJsonPath('lines.0.tax_rate', '7.00')
            ->assertJsonPath('lines.0.trip_kind', 'linie')
            ->assertJsonPath('tax_summary.0.rate', '7.00');

        PricingSetting::current()->update(['tax_rate_school' => 5]);
        $this->as(Role::Disponent)->postJson('/api/quotes/from-tour', $this->tour(['trip_kind' => 'schule']))->assertCreated()
            ->assertJsonPath('lines.0.tax_rate', '5.00');
    }

    public function test_zuschlaege_tragen_die_fahrtart_der_tour(): void
    {
        $this->prices();
        $response = $this->as(Role::Disponent)->postJson('/api/quotes/from-tour', $this->tour(['trip_kind' => 'linie', 'surcharge_percent' => 10]))->assertCreated();

        foreach ($response->json('lines') as $line) {
            $this->assertSame('linie', $line['trip_kind']);
            $this->assertSame('7.00', $line['tax_rate']);
        }
    }

    public function test_unbekannte_fahrtart_beim_angebot_wird_abgelehnt(): void
    {
        $this->prices();
        $this->as(Role::Disponent)->postJson('/api/quotes/from-tour', $this->tour(['trip_kind' => 'rakete']))
            ->assertUnprocessable()->assertJsonValidationErrors('trip_kind');
    }

    // ---------- Einstellungen ----------

    public function test_preiseinstellungen_enthalten_saetze_und_fahrtarten(): void
    {
        $response = $this->as(Role::Disponent)->getJson('/api/settings/pricing')->assertOk()
            ->assertJsonPath('tax_rate_line', '7.00')
            ->assertJsonPath('tax_rate_school', '7.00');

        $values = array_column($response->json('trip_kinds'), 'value');
        $this->assertSame(['gelegenheit', 'linie', 'schule', 'sonstig'], $values);
    }

    public function test_saetze_je_fahrtart_aendern_nur_admin(): void
    {
        $body = ['price_per_km' => 2, 'empty_km_percent' => 0, 'hourly_rate' => 0, 'day_rate' => 0, 'overnight_rate' => 0,
            'minimum_price' => 0, 'tax_rate' => 19, 'tax_rate_line' => 10.7, 'tax_rate_school' => 0];

        $this->as(Role::Buchhaltung)->putJson('/api/settings/pricing', $body)->assertForbidden();
        $this->as(Role::Admin)->putJson('/api/settings/pricing', $body)->assertOk()
            ->assertJsonPath('tax_rate_line', '10.70')->assertJsonPath('tax_rate_school', '0.00');
    }

    public function test_aelterer_aufruf_ohne_neue_felder_funktioniert_weiter(): void
    {
        $body = ['price_per_km' => 2, 'empty_km_percent' => 0, 'hourly_rate' => 0, 'day_rate' => 0, 'overnight_rate' => 0,
            'minimum_price' => 0, 'tax_rate' => 19];

        $this->as(Role::Admin)->putJson('/api/settings/pricing', $body)->assertOk()->assertJsonPath('tax_rate_line', '7.00');
    }
}
