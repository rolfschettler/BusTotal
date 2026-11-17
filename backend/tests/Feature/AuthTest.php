<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attrs = []): User
    {
        return User::factory()->create(array_merge(['password' => 'geheim123', 'role' => Role::Disponent], $attrs));
    }

    public function test_login_mit_richtigen_daten_liefert_token(): void
    {
        $user = $this->user();

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'geheim123'])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role']])
            ->assertJsonPath('user.role', 'disponent');
    }

    public function test_login_mit_falschem_passwort_wird_abgelehnt(): void
    {
        $user = $this->user();

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'falsch'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_gesperrter_benutzer_kann_sich_nicht_anmelden(): void
    {
        $user = $this->user(['active' => false]);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'geheim123'])
            ->assertStatus(422);
    }

    public function test_me_braucht_anmeldung(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_me_und_logout_mit_token(): void
    {
        $user = $this->user();
        $token = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'geheim123'])->json('token');

        $this->withToken($token)->getJson('/api/me')->assertOk()->assertJsonPath('email', $user->email);
        $this->withToken($token)->postJson('/api/logout')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
