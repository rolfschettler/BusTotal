<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Http\Controllers\Api\UserController;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'password' => 'AdminPass123']);
    }

    private function as(User|Role $who): static
    {
        $user = $who instanceof Role ? User::factory()->create(['role' => $who]) : $who;

        return $this->actingAs($user, 'sanctum');
    }

    /** Echte Anmeldung: liefert den Token. */
    private function login(string $email, string $password): string
    {
        return $this->postJson('/api/login', ['email' => $email, 'password' => $password])->json('token') ?? '';
    }

    private function newUser(array $o = []): array
    {
        return array_merge(['name' => 'Erika Muster', 'email' => 'erika@firma.test', 'role' => 'disponent'], $o);
    }

    // ---------- Rechte ----------

    public function test_nur_der_admin_darf_benutzer_verwalten(): void
    {
        $u = User::factory()->create();

        // Zuerst ohne Anmeldung: actingAs merkt sich den Benutzer sonst für alle späteren Anfragen.
        $this->getJson('/api/users')->assertUnauthorized();

        foreach ([Role::Disponent, Role::Buchhaltung, Role::Fahrer] as $role) {
            $this->as($role)->getJson('/api/users')->assertForbidden();
            $this->as($role)->postJson('/api/users', $this->newUser())->assertForbidden();
            $this->as($role)->putJson("/api/users/{$u->id}", $this->newUser() + ['active' => true])->assertForbidden();
            $this->as($role)->postJson("/api/users/{$u->id}/reset-password")->assertForbidden();
        }
    }

    // ---------- Liste ----------

    public function test_liste_zeigt_rolle_status_und_letzte_anmeldung_aber_keine_passwoerter(): void
    {
        $admin = $this->admin();
        $other = User::factory()->create(['email' => 'x@firma.test', 'password' => 'Geheim12345']);
        $this->login('x@firma.test', 'Geheim12345');

        $rows = collect($this->as($admin)->getJson('/api/users')->assertOk()->json());

        $row = $rows->firstWhere('email', 'x@firma.test');
        $this->assertNotNull($row['last_login_at']);
        $this->assertNull($rows->firstWhere('id', $admin->id)['last_login_at']);
        $this->assertArrayNotHasKey('password', $row);
        $this->assertArrayNotHasKey('temporary_password', $row);
    }

    // ---------- Anlegen ----------

    public function test_anlegen_liefert_vorlaeufiges_passwort_mit_dem_man_sich_anmelden_kann(): void
    {
        $res = $this->as($this->admin())->postJson('/api/users', $this->newUser(['email' => 'Erika@Firma.TEST']))
            ->assertCreated()
            ->assertJsonPath('email', 'erika@firma.test')
            ->assertJsonPath('role', 'disponent')
            ->assertJsonPath('must_change_password', true);

        $temporary = $res->json('temporary_password');
        $this->assertGreaterThanOrEqual(10, strlen($temporary));
        $this->assertMatchesRegularExpression('/[A-Za-z]/', $temporary);
        $this->assertMatchesRegularExpression('/\d/', $temporary);

        $this->postJson('/api/login', ['email' => 'erika@firma.test', 'password' => $temporary])
            ->assertOk()->assertJsonPath('user.must_change_password', true);
    }

    public function test_passwort_wird_weder_gespeichert_noch_protokolliert(): void
    {
        $temporary = $this->as($this->admin())->postJson('/api/users', $this->newUser())->json('temporary_password');

        $user = User::where('email', 'erika@firma.test')->first();
        $this->assertNotSame($temporary, $user->password);
        $this->assertTrue(Hash::check($temporary, $user->password));

        $log = AuditLog::where('event', 'created')->where('auditable_id', $user->id)->first();
        $this->assertStringNotContainsString($temporary, json_encode($log->new_values));
        $this->assertArrayNotHasKey('password', $log->new_values);
    }

    public function test_anlegen_prueft_eingaben(): void
    {
        $admin = $this->admin();
        User::factory()->create(['email' => 'vergeben@firma.test']);

        $this->as($admin)->postJson('/api/users', $this->newUser(['email' => 'vergeben@firma.test']))
            ->assertStatus(422)->assertJsonValidationErrors('email')
            ->assertJsonPath('errors.email.0', 'Diese E-Mail-Adresse wird bereits verwendet.');
        $this->as($admin)->postJson('/api/users', $this->newUser(['email' => 'kein-email']))->assertStatus(422);
        $this->as($admin)->postJson('/api/users', $this->newUser(['role' => 'chef']))->assertStatus(422)->assertJsonValidationErrors('role');
        $this->as($admin)->postJson('/api/users', $this->newUser(['name' => '']))->assertStatus(422)->assertJsonValidationErrors('name');
    }

    public function test_anlegen_ignoriert_eingeschleuste_felder(): void
    {
        $this->as($this->admin())->postJson('/api/users', $this->newUser() + ['active' => false, 'must_change_password' => false, 'password' => 'selbstgewaehlt1'])
            ->assertCreated()->assertJsonPath('active', true)->assertJsonPath('must_change_password', true);

        $this->postJson('/api/login', ['email' => 'erika@firma.test', 'password' => 'selbstgewaehlt1'])->assertStatus(422);
    }

    // ---------- Zwangswechsel ----------

    public function test_mit_vorlaeufigem_passwort_ist_nur_passwortwechsel_erlaubt(): void
    {
        $temporary = $this->as($this->admin())->postJson('/api/users', $this->newUser())->json('temporary_password');
        $this->app['auth']->forgetGuards(); // ab hier handelt der neue Benutzer, nicht mehr der Admin
        $token = $this->login('erika@firma.test', $temporary);

        $this->withToken($token)->getJson('/api/me')->assertOk();
        foreach (['/api/customers', '/api/settings/company', '/api/dashboard/recent-customers'] as $path) {
            $this->withToken($token)->getJson($path)->assertForbidden()->assertJsonPath('code', 'password_change_required');
        }

        $this->withToken($token)->postJson('/api/me/password', [
            'current_password' => $temporary, 'password' => 'NeuesPasswort42', 'password_confirmation' => 'NeuesPasswort42',
        ])->assertOk();

        $this->withToken($token)->getJson('/api/customers')->assertOk();
        $this->withToken($token)->getJson('/api/me')->assertJsonPath('must_change_password', false);
    }

    // ---------- Eigenes Passwort ändern ----------

    public function test_passwortwechsel_prueft_aktuelles_passwort(): void
    {
        $u = User::factory()->create(['password' => 'AltesPasswort1']);

        $this->as($u)->postJson('/api/me/password', ['current_password' => 'falsch', 'password' => 'NeuesPasswort42', 'password_confirmation' => 'NeuesPasswort42'])
            ->assertStatus(422)->assertJsonPath('errors.current_password.0', 'Das aktuelle Passwort ist nicht korrekt.');

        $this->assertTrue(Hash::check('AltesPasswort1', $u->fresh()->password));
    }

    public function test_schwache_oder_unpassende_neue_passwoerter_werden_abgelehnt(): void
    {
        $u = User::factory()->create(['password' => 'AltesPasswort1']);
        $send = fn (array $o) => $this->as($u)->postJson('/api/me/password', $o + ['current_password' => 'AltesPasswort1']);

        $send(['password' => 'kurz1', 'password_confirmation' => 'kurz1'])->assertStatus(422)->assertJsonValidationErrors('password');
        $send(['password' => 'nurbuchstabenhier', 'password_confirmation' => 'nurbuchstabenhier'])->assertStatus(422);
        $send(['password' => '1234567890123', 'password_confirmation' => '1234567890123'])->assertStatus(422);
        $send(['password' => 'NeuesPasswort42', 'password_confirmation' => 'AnderesPasswort42'])->assertStatus(422);
        $send(['password' => 'AltesPasswort1', 'password_confirmation' => 'AltesPasswort1'])->assertStatus(422);

        $this->assertTrue(Hash::check('AltesPasswort1', $u->fresh()->password));
    }

    public function test_passwortwechsel_beendet_andere_anmeldungen_und_wird_protokolliert(): void
    {
        User::factory()->create(['email' => 'a@firma.test', 'password' => 'AltesPasswort1']);
        $tokenA = $this->login('a@firma.test', 'AltesPasswort1');
        $tokenB = $this->login('a@firma.test', 'AltesPasswort1');

        $this->withToken($tokenA)->postJson('/api/me/password', [
            'current_password' => 'AltesPasswort1', 'password' => 'NeuesPasswort42', 'password_confirmation' => 'NeuesPasswort42',
        ])->assertOk();

        // Der Test-Client merkt sich den Benutzer der ersten Anfrage, daher frisch anfragen.
        $this->app['auth']->forgetGuards();
        $this->withToken($tokenB)->getJson('/api/me')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($tokenA)->getJson('/api/me')->assertOk();

        $this->assertDatabaseHas('audit_logs', ['event' => 'password_changed']);
        $this->assertSame(0, AuditLog::where('event', 'password_changed')->whereNotNull('new_values')->count());
    }

    // ---------- Bearbeiten ----------

    public function test_admin_kann_name_email_und_rolle_aendern(): void
    {
        $u = User::factory()->create(['role' => Role::Fahrer]);

        $this->as($this->admin())->putJson("/api/users/{$u->id}", ['name' => 'Neu', 'email' => 'Neu@Firma.test', 'role' => 'buchhaltung', 'active' => true])
            ->assertOk()->assertJsonPath('role', 'buchhaltung')->assertJsonPath('email', 'neu@firma.test');

        $log = AuditLog::where('event', 'updated')->where('auditable_id', $u->id)->latest('id')->first();
        $this->assertSame('fahrer', $log->old_values['role']);
        $this->assertSame('buchhaltung', $log->new_values['role']);
    }

    public function test_email_darf_beim_bearbeiten_bei_sich_selbst_gleich_bleiben(): void
    {
        $u = User::factory()->create(['email' => 'gleich@firma.test', 'role' => Role::Fahrer]);

        $this->as($this->admin())->putJson("/api/users/{$u->id}", ['name' => 'Anders', 'email' => 'gleich@firma.test', 'role' => 'fahrer', 'active' => true])->assertOk();
    }

    public function test_admin_kann_sich_nicht_selbst_sperren_oder_herabstufen(): void
    {
        $admin = $this->admin();
        $base = ['name' => $admin->name, 'email' => $admin->email];

        $this->as($admin)->putJson("/api/users/{$admin->id}", $base + ['role' => 'admin', 'active' => false])->assertStatus(422);
        $this->as($admin)->putJson("/api/users/{$admin->id}", $base + ['role' => 'disponent', 'active' => true])->assertStatus(422);
        $this->as($admin)->putJson("/api/users/{$admin->id}", $base + ['role' => 'admin', 'active' => true])->assertOk();
    }

    public function test_ein_admin_darf_einen_anderen_admin_sperren_solange_er_selbst_aktiv_bleibt(): void
    {
        $a = $this->admin();
        $b = User::factory()->create(['role' => Role::Admin]);

        $this->as($a)->putJson("/api/users/{$b->id}", ['name' => $b->name, 'email' => $b->email, 'role' => 'admin', 'active' => false])->assertOk();
        $this->assertFalse($b->fresh()->active);
    }

    /**
     * Über die Oberfläche ist diese Regel nicht auslösbar: Wer ändert, ist selbst ein aktiver Admin
     * und bleibt es. Sie schützt vor gleichzeitigen Anfragen (zwei Admins sperren sich gegenseitig,
     * jeder war beim Prüfen noch aktiv). Deshalb rufen wir den Controller direkt mit einem Handelnden
     * auf, der inzwischen gesperrt wurde.
     */
    public function test_der_letzte_aktive_admin_bleibt_auch_bei_gleichzeitiger_sperrung_geschuetzt(): void
    {
        $letzter = User::factory()->create(['role' => Role::Admin]);
        $handelnder = User::factory()->create(['role' => Role::Admin, 'active' => false]);

        $request = Request::create("/api/users/{$letzter->id}", 'PUT', [
            'name' => $letzter->name, 'email' => $letzter->email, 'role' => 'fahrer', 'active' => true,
        ]);
        $request->setUserResolver(fn () => $handelnder);

        $response = app(UserController::class)->update($request, $letzter);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(Role::Admin, $letzter->fresh()->role);
        $this->assertTrue($letzter->fresh()->active);
    }

    // ---------- Sperren ----------

    public function test_gesperrter_benutzer_ist_sofort_abgemeldet_und_kann_sich_nicht_mehr_anmelden(): void
    {
        $admin = $this->admin();
        $u = User::factory()->create(['email' => 'g@firma.test', 'password' => 'Geheim12345', 'role' => Role::Disponent]);
        $token = $this->login('g@firma.test', 'Geheim12345');
        $this->withToken($token)->getJson('/api/customers')->assertOk();

        $this->app['auth']->forgetGuards();
        $this->as($admin)->putJson("/api/users/{$u->id}", ['name' => $u->name, 'email' => $u->email, 'role' => 'disponent', 'active' => false])->assertOk();

        $this->assertSame(0, $u->tokens()->count());
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/customers')->assertUnauthorized();
        $this->postJson('/api/login', ['email' => 'g@firma.test', 'password' => 'Geheim12345'])->assertStatus(422);
    }

    public function test_gesperrter_benutzer_mit_noch_gueltigem_token_wird_abgewiesen(): void
    {
        // Sicherheitsnetz: selbst wenn ein Token übrig bliebe, lässt die Kontoprüfung ihn nicht durch.
        $u = User::factory()->create(['role' => Role::Admin, 'active' => false]);

        $this->as($u)->getJson('/api/customers')->assertUnauthorized();
    }

    public function test_entsperren_funktioniert(): void
    {
        $u = User::factory()->create(['email' => 'e@firma.test', 'password' => 'Geheim12345', 'active' => false]);

        $this->as($this->admin())->putJson("/api/users/{$u->id}", ['name' => $u->name, 'email' => $u->email, 'role' => $u->role->value, 'active' => true])->assertOk();

        $this->postJson('/api/login', ['email' => 'e@firma.test', 'password' => 'Geheim12345'])->assertOk();
    }

    // ---------- Passwort zurücksetzen ----------

    public function test_zuruecksetzen_vergibt_neues_vorlaeufiges_passwort_und_beendet_anmeldungen(): void
    {
        $u = User::factory()->create(['email' => 'v@firma.test', 'password' => 'VergessenesPw1']);
        $oldToken = $this->login('v@firma.test', 'VergessenesPw1');

        $this->app['auth']->forgetGuards();
        $res = $this->as($this->admin())->postJson("/api/users/{$u->id}/reset-password")
            ->assertOk()->assertJsonPath('must_change_password', true);
        $temporary = $res->json('temporary_password');

        $this->postJson('/api/login', ['email' => 'v@firma.test', 'password' => 'VergessenesPw1'])->assertStatus(422);
        $this->postJson('/api/login', ['email' => 'v@firma.test', 'password' => $temporary])->assertOk()->assertJsonPath('user.must_change_password', true);

        $this->app['auth']->forgetGuards();
        $this->withToken($oldToken)->getJson('/api/me')->assertUnauthorized();

        $this->assertDatabaseHas('audit_logs', ['event' => 'password_reset', 'auditable_id' => $u->id]);
    }

    public function test_eigenes_passwort_nicht_ueber_zuruecksetzen(): void
    {
        $admin = $this->admin();

        $this->as($admin)->postJson("/api/users/{$admin->id}/reset-password")->assertStatus(422);
        $this->assertTrue(Hash::check('AdminPass123', $admin->fresh()->password));
    }

    // ---------- Absicherung ----------

    public function test_seeder_legt_ausserhalb_von_lokal_keine_testbenutzer_an(): void
    {
        $this->app['env'] = 'production';
        // --force: In "production" fragt db:seed sonst nach einer Bestätigung.
        $this->artisan('db:seed', ['--class' => \Database\Seeders\DatabaseSeeder::class, '--force' => true]);

        $this->assertSame(0, User::where('email', 'like', '%@bustotal.test')->count());
    }

    public function test_befehl_legt_admin_mit_vorlaeufigem_passwort_an(): void
    {
        $this->artisan('bustotal:admin-anlegen', ['email' => 'Chef@Firma.test', 'name' => 'Der Chef'])
            ->expectsOutputToContain('angelegt')
            ->assertSuccessful();

        $u = User::where('email', 'chef@firma.test')->first();
        $this->assertSame(Role::Admin, $u->role);
        $this->assertTrue($u->must_change_password);

        $this->artisan('bustotal:admin-anlegen', ['email' => 'chef@firma.test'])->assertFailed();
    }
}
