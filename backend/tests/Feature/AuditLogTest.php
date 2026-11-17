<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_anlegen_und_aendern_werden_protokolliert_ohne_passwort(): void
    {
        $user = User::factory()->create(['name' => 'Alt', 'password' => 'geheim123']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'created', 'auditable_id' => $user->id]);

        $user->update(['name' => 'Neu', 'password' => 'anderes-passwort']);

        $log = AuditLog::where('event', 'updated')->firstOrFail();
        $this->assertSame(['name' => 'Alt'], $log->old_values);
        $this->assertSame(['name' => 'Neu'], $log->new_values);   // Passwort taucht nicht auf

        $created = AuditLog::where('event', 'created')->firstOrFail();
        $this->assertArrayNotHasKey('password', $created->new_values);
    }

    public function test_loeschen_wird_protokolliert(): void
    {
        $user = User::factory()->create();
        $user->delete();

        $this->assertDatabaseHas('audit_logs', ['event' => 'deleted', 'auditable_id' => $user->id]);
    }

    public function test_eintraege_sind_unveraenderlich(): void
    {
        $log = AuditLog::record('test');

        $this->expectException(LogicException::class);
        $log->update(['event' => 'manipuliert']);
    }

    public function test_eintraege_koennen_nicht_geloescht_werden(): void
    {
        $log = AuditLog::record('test');

        $this->expectException(LogicException::class);
        $log->delete();
    }

    public function test_login_und_fehlversuch_werden_protokolliert(): void
    {
        $user = User::factory()->create(['password' => 'geheim123', 'role' => Role::Admin]);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'falsch']);
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'geheim123']);

        $this->assertDatabaseHas('audit_logs', ['event' => 'login_failed', 'user_id' => $user->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'login', 'user_id' => $user->id]);
    }
}
