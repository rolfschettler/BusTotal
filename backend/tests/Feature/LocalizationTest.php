<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
    }

    private function as(User|Role $who): static
    {
        $user = $who instanceof Role ? User::factory()->create(['role' => $who]) : $who;

        return $this->actingAs($user, 'sanctum');
    }

    // ---------- Vollständigkeit ----------

    /** Fehlt bei einem Laravel-Update ein neuer Schlüssel, schlägt dieser Test an. */
    public function test_deutsche_dateien_enthalten_alle_schluessel_der_laravel_vorlage(): void
    {
        $vorlage = base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en');

        foreach (['validation', 'auth', 'pagination', 'passwords'] as $datei) {
            $en = Arr::dot(require "{$vorlage}/{$datei}.php");
            $de = Arr::dot(require lang_path("de/{$datei}.php"));

            // Eigene Meldungen ("custom") und Feldnamen ("attributes") sind unsere Sache, nur die Regeln
            // müssen vollständig sein. In der Vorlage sind beide leer bzw. nur ein Muster.
            $erwartet = array_filter(array_keys($en), fn ($k) => ! preg_match('/^(custom|attributes)(\.|$)/', $k));

            $this->assertSame([], array_values(array_diff($erwartet, array_keys($de))), "In lang/de/{$datei}.php fehlen Schlüssel.");
        }
    }

    public function test_deutsche_texte_sind_nicht_aus_versehen_noch_englisch(): void
    {
        $de = Arr::dot(require lang_path('de/validation.php'));

        foreach ($de as $schluessel => $text) {
            $this->assertDoesNotMatchRegularExpression('/\b(must|field|is required|The :attribute)\b/', (string) $text, "lang/de/validation.php: {$schluessel} ist noch englisch.");
        }
    }

    public function test_sprache_ist_deutsch(): void
    {
        $this->assertSame('de', app()->getLocale());
    }

    // ---------- Eingabeprüfung ----------

    public function test_pflichtfeld_meldung_ist_deutsch_mit_feldnamen_der_oberflaeche(): void
    {
        $c = Customer::factory()->create();

        $this->as(Role::Admin)->postJson("/api/customers/{$c->id}/contacts", [])
            ->assertStatus(422)
            ->assertJsonPath('errors.last_name.0', 'Nachname ist erforderlich.');
    }

    public function test_ungueltige_email_meldung_ist_deutsch(): void
    {
        $c = Customer::factory()->create();

        $this->as(Role::Admin)->postJson("/api/customers/{$c->id}/contacts", ['last_name' => 'Muster', 'email' => 'kein-email'])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Das ist keine gültige E-Mail-Adresse.');
    }

    public function test_laengenbegrenzung_nennt_feld_und_grenze(): void
    {
        $c = Customer::factory()->create();

        $this->as(Role::Admin)->postJson("/api/customers/{$c->id}/contacts", ['last_name' => str_repeat('x', 300)])
            ->assertStatus(422)
            ->assertJsonPath('errors.last_name.0', 'Nachname darf höchstens 255 Zeichen lang sein.');
    }

    public function test_auswahl_und_vergebener_wert(): void
    {
        $this->as(Role::Admin)->postJson('/api/users', ['name' => 'X', 'email' => 'x@y.test', 'role' => 'chef'])
            ->assertStatus(422)->assertJsonPath('errors.role.0', 'Diese Rolle gibt es nicht.');

        // Steuersatz-Name ist schon vergeben (Standardtext der Regel "unique")
        $this->as(Role::Admin)->postJson('/api/settings/tax-rates', ['name' => 'Steuerfrei', 'rate' => 0])
            ->assertStatus(422)->assertJsonPath('errors.name.0', 'Name ist bereits vergeben.');
    }

    public function test_sammelmeldung_ist_deutsch_inklusive_anzahl_weiterer_fehler(): void
    {
        $c = Customer::factory()->create();

        // Zwei Fehler: Nachname fehlt, E-Mail ungültig
        $message = $this->as(Role::Admin)->postJson("/api/customers/{$c->id}/contacts", ['email' => 'kein-email'])
            ->assertStatus(422)->json('message');

        $this->assertStringContainsString('(und 1 weiterer Fehler)', $message);
        $this->assertStringNotContainsString('and 1 more', $message);
    }

    public function test_passwortregeln_sind_deutsch(): void
    {
        $u = User::factory()->create(['password' => 'AltesPasswort1']);

        $this->as($u)->postJson('/api/me/password', ['current_password' => 'AltesPasswort1', 'password' => 'abc', 'password_confirmation' => 'abc'])
            ->assertStatus(422)->assertJsonPath('errors.password.0', 'Das Passwort muss mindestens 10 Zeichen lang sein.');
    }

    // ---------- Fehlerseiten der API ----------

    public function test_nicht_angemeldet_ist_deutsch(): void
    {
        $this->getJson('/api/customers')->assertUnauthorized()->assertExactJson(['message' => 'Sie sind nicht angemeldet.']);
    }

    public function test_nicht_gefunden_ist_deutsch_und_verraet_keine_interna(): void
    {
        $res = $this->as(Role::Admin)->getJson('/api/customers/999999')->assertNotFound();

        $this->assertSame('Der Eintrag wurde nicht gefunden.', $res->json('message'));
        $this->assertStringNotContainsString('App\\', $res->getContent());
        $this->assertStringNotContainsString('No query results', $res->getContent());
    }

    public function test_unbekannte_adresse_ist_deutsch(): void
    {
        $this->as(Role::Admin)->getJson('/api/gibt-es-nicht')->assertNotFound()
            ->assertJsonPath('message', 'Der Eintrag wurde nicht gefunden.');
    }

    public function test_falsche_methode_ist_deutsch(): void
    {
        $this->as(Role::Admin)->deleteJson('/api/me')->assertStatus(405)
            ->assertJsonPath('message', 'Diese Anfrage ist hier nicht erlaubt.');
    }

    public function test_zu_viele_anmeldeversuche_sind_deutsch(): void
    {
        $last = null;
        foreach (range(1, 6) as $i) {
            $last = $this->postJson('/api/login', ['email' => 'x@y.test', 'password' => 'falsch']);
        }

        $last->assertStatus(429)->assertJsonPath('message', 'Zu viele Anfragen. Bitte warten Sie einen Moment.');
        $this->assertNotNull($last->headers->get('Retry-After'));
    }

    public function test_serverfehler_zeigen_ohne_debug_keine_technischen_einzelheiten(): void
    {
        config(['app.debug' => false]);
        Route::get('/api/_testfehler', fn () => throw new \RuntimeException('Datenbankpasswort: geheim123'));

        $res = $this->getJson('/api/_testfehler')->assertStatus(500);

        $this->assertSame('Es ist ein Fehler aufgetreten. Bitte versuchen Sie es später erneut.', $res->json('message'));
        $this->assertStringNotContainsString('geheim123', $res->getContent());
        $this->assertStringNotContainsString('RuntimeException', $res->getContent());
    }

    public function test_403_bleibt_wie_gehabt_deutsch(): void
    {
        $this->as(Role::Fahrer)->getJson('/api/customers')->assertForbidden()->assertJsonPath('message', 'Keine Berechtigung.');
    }
}
