<?php

namespace App\Models;

use App\Enums\Role;
use App\Models\Concerns\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'active', 'must_change_password'])]
#[Hidden(['password', 'remember_token', 'temporary_password'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    // HasUuids: Die ID ist eine UUID (Version 7) und wird beim Anlegen automatisch vergeben.
    use Auditable, HasApiTokens, HasFactory, HasUuids, Notifiable;

    protected array $auditExclude = ['password', 'remember_token', 'temporary_password'];

    // Dieselben Standardwerte wie in der Datenbank. Ohne sie kennt ein frisch erzeugtes Objekt
    // (vor dem Neuladen) diese Felder nicht, und "active" wäre null, also scheinbar gesperrt.
    protected $attributes = [
        'role' => 'disponent',
        'active' => true,
        'must_change_password' => false,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'active' => 'boolean',
            'must_change_password' => 'boolean',
            'temporary_password' => 'encrypted',
        ];
    }

    /**
     * Setzt ein vorläufiges Passwort, das der Betreiber später wieder einsehen darf (bis der Benutzer es ändert).
     * Nur für den Weg über die Betreiber-Oberfläche; im Alltag der Firma wird ein vorläufiges Passwort nur einmal angezeigt.
     */
    public function setRetrievableTemporaryPassword(string $password): void
    {
        $this->forceFill(['password' => $password, 'must_change_password' => true, 'temporary_password' => $password]);
    }

    public function hasRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }
}
