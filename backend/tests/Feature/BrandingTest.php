<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CompanySetting;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    /** Ein gültiges 1x1-PNG. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(SettingsSeeder::class);
    }

    private function as(Role $role): static
    {
        return $this->actingAs(User::factory()->create(['role' => $role]), 'sanctum');
    }

    private function png(string $name = 'logo.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(self::PNG));
    }

    private function company(array $override = []): array
    {
        return array_merge(['name' => 'Muster GmbH', 'country' => 'DE', 'payment_term_days' => 14], $override);
    }

    // ---------- öffentliche Schnittstelle ----------

    public function test_branding_ist_ohne_anmeldung_lesbar_und_gibt_nur_das_noetige_aus(): void
    {
        $this->getJson('/api/branding')
            ->assertOk()
            ->assertJsonPath('brand_color', '#1d4e89')
            ->assertJsonPath('has_logo', false)
            ->assertJsonMissingPath('iban')
            ->assertJsonMissingPath('tax_number');
    }

    public function test_ohne_logo_gibt_es_404(): void
    {
        $this->get('/api/branding/logo')->assertNotFound();
    }

    // ---------- Farbe ----------

    public function test_admin_kann_markenfarbe_setzen(): void
    {
        $this->as(Role::Admin)->putJson('/api/settings/company', $this->company(['brand_color' => '#B91C1C']))
            ->assertOk()->assertJsonPath('brand_color', '#b91c1c');

        $this->getJson('/api/branding')->assertJsonPath('brand_color', '#b91c1c');
    }

    public function test_ungueltige_farben_werden_abgelehnt(): void
    {
        foreach (['rot', '#fff', '#gggggg', '1d4e89'] as $bad) {
            $this->as(Role::Admin)->putJson('/api/settings/company', $this->company(['brand_color' => $bad]))
                ->assertStatus(422)->assertJsonValidationErrors('brand_color');
        }
    }

    public function test_auch_helle_farben_werden_gespeichert(): void
    {
        foreach (['#ffff00', '#228b22', '#000000'] as $color) {
            $this->as(Role::Admin)->putJson('/api/settings/company', $this->company(['brand_color' => $color]))
                ->assertOk()->assertJsonPath('brand_color', $color);
        }
    }

    public function test_ohne_farbfeld_bleibt_die_farbe_unveraendert(): void
    {
        CompanySetting::current()->update(['brand_color' => '#b91c1c']);
        $this->as(Role::Admin)->putJson('/api/settings/company', $this->company())->assertOk();

        $this->assertSame('#b91c1c', CompanySetting::current()->brand_color);
    }

    // ---------- Logo ----------

    public function test_admin_kann_logo_hochladen_und_es_wird_ausgeliefert(): void
    {
        $res = $this->as(Role::Admin)->postJson('/api/settings/logo', ['logo' => $this->png()])
            ->assertOk()->assertJsonPath('has_logo', true)->assertJsonMissingPath('logo_path');

        $this->assertNotNull($res->json('updated_at'));
        $this->getJson('/api/branding')->assertJsonPath('has_logo', true);
        $this->get('/api/branding/logo')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertCount(1, Storage::disk('local')->files('logos'));
    }

    public function test_neues_logo_ersetzt_das_alte_datei(): void
    {
        $this->as(Role::Admin)->postJson('/api/settings/logo', ['logo' => $this->png('a.png')])->assertOk();
        $this->as(Role::Admin)->postJson('/api/settings/logo', ['logo' => $this->png('b.png')])->assertOk();

        $this->assertCount(1, Storage::disk('local')->files('logos'));
    }

    public function test_nur_bilder_sind_als_logo_erlaubt(): void
    {
        $svg = UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->as(Role::Admin)->postJson('/api/settings/logo', ['logo' => $svg])
            ->assertStatus(422)->assertJsonValidationErrors('logo');

        // Textdatei mit Bild-Endung: wird am Inhalt erkannt und abgelehnt.
        // Bewusst eine echte Datei statt UploadedFile::fake(): Das Test-Hilfsobjekt erkennt den Typ
        // nur am Namen, eine echte Datei wird dagegen am Inhalt geprüft wie im Betrieb.
        $path = tempnam(sys_get_temp_dir(), 'logo');
        file_put_contents($path, 'das ist gar kein bild');
        $fake = new UploadedFile($path, 'logo.png', 'image/png', null, true);
        $this->as(Role::Admin)->postJson('/api/settings/logo', ['logo' => $fake])->assertStatus(422);
        @unlink($path);

        $this->assertCount(0, Storage::disk('local')->files('logos'));
    }

    public function test_zu_grosses_logo_wird_abgelehnt(): void
    {
        $big = UploadedFile::fake()->createWithContent('big.png', base64_decode(self::PNG).str_repeat('0', 1100 * 1024));
        $this->as(Role::Admin)->postJson('/api/settings/logo', ['logo' => $big])
            ->assertStatus(422)->assertJsonValidationErrors('logo');
    }

    public function test_nur_admin_darf_logo_aendern(): void
    {
        $this->as(Role::Disponent)->postJson('/api/settings/logo', ['logo' => $this->png()])->assertForbidden();
        $this->as(Role::Disponent)->deleteJson('/api/settings/logo')->assertForbidden();
    }

    public function test_logo_kann_entfernt_werden(): void
    {
        $this->as(Role::Admin)->postJson('/api/settings/logo', ['logo' => $this->png()])->assertOk();
        $this->as(Role::Admin)->deleteJson('/api/settings/logo')->assertOk()->assertJsonPath('has_logo', false);

        $this->assertCount(0, Storage::disk('local')->files('logos'));
        $this->get('/api/branding/logo')->assertNotFound();
    }
}
