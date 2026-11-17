<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\NumberRange;
use App\Models\User;
use App\Services\NumberRangeService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
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

    private function companyData(array $override = []): array
    {
        return array_merge([
            'name' => 'Muster Reisen GmbH', 'country' => 'DE', 'payment_term_days' => 14,
            'iban' => 'DE89 3704 0044 0532 0130 00', 'bic' => 'COBADEFFXXX', 'vat_id' => 'DE123456789',
        ], $override);
    }

    public function test_ohne_anmeldung_kein_zugriff(): void
    {
        $this->getJson('/api/settings/company')->assertUnauthorized();
    }

    public function test_disponent_darf_lesen_aber_nicht_aendern(): void
    {
        $this->as(Role::Disponent)->getJson('/api/settings/company')->assertOk();
        $this->as(Role::Disponent)->putJson('/api/settings/company', $this->companyData())->assertForbidden();
    }

    public function test_admin_kann_firmendaten_speichern_und_iban_wird_normalisiert(): void
    {
        $this->as(Role::Admin)->putJson('/api/settings/company', $this->companyData())
            ->assertOk()
            ->assertJsonPath('name', 'Muster Reisen GmbH')
            ->assertJsonPath('iban', 'DE89370400440532013000');
    }

    public function test_ungueltige_iban_wird_abgelehnt(): void
    {
        $this->as(Role::Admin)->putJson('/api/settings/company', $this->companyData(['iban' => 'DE00 1234 5678 9012 3456 78']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('iban');
    }

    public function test_steuersaetze_sind_vorhanden_und_aenderbar(): void
    {
        $this->as(Role::Buchhaltung)->getJson('/api/settings/tax-rates')
            ->assertOk()->assertJsonCount(3)->assertJsonPath('0.rate', '19.00');

        $id = \App\Models\TaxRate::where('name', 'Steuerfrei')->value('id');
        $this->as(Role::Admin)->putJson("/api/settings/tax-rates/{$id}", ['name' => 'Steuerfrei', 'rate' => 0, 'active' => false])
            ->assertOk()->assertJsonPath('active', false);
    }

    public function test_nummernkreis_vergibt_fortlaufend_und_formatiert(): void
    {
        $service = app(NumberRangeService::class);

        $this->assertSame('RE-2026-00001', $service->next('rechnung', 2026));
        $this->assertSame('RE-2026-00002', $service->next('rechnung', 2026));
        $this->assertSame('K-00001', $service->next('kunde', 2026));   // ohne Jahr
    }

    public function test_nummernkreis_beginnt_im_neuen_jahr_bei_eins(): void
    {
        $service = app(NumberRangeService::class);
        $service->next('rechnung', 2026);
        $service->next('rechnung', 2026);

        $this->assertSame('RE-2027-00001', $service->next('rechnung', 2027));
    }

    public function test_rollback_verbraucht_keine_nummer(): void
    {
        $service = app(NumberRangeService::class);

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($service) {
                $service->next('angebot', 2026);
                throw new \RuntimeException('Beleg konnte nicht gespeichert werden');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame('AN-2026-00001', $service->next('angebot', 2026));
    }

    public function test_naechste_nummer_darf_nicht_sinken(): void
    {
        $range = NumberRange::where('type', 'rechnung')->first();
        $range->update(['next_number' => 50]);

        $payload = fn (int $next) => [
            'prefix' => 'RE', 'include_year' => true, 'padding' => 5, 'reset_yearly' => true, 'next_number' => $next,
        ];

        $this->as(Role::Admin)->putJson("/api/settings/number-ranges/{$range->id}", $payload(10))
            ->assertStatus(422)->assertJsonValidationErrors('next_number');
        $this->as(Role::Admin)->putJson("/api/settings/number-ranges/{$range->id}", $payload(100))
            ->assertOk()->assertJsonPath('next_number', 100);
    }
}
