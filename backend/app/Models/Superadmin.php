<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Betreiber von BusTotal (nicht ein Benutzer einer Firma). Liegt in der ZENTRALEN Datenbank
 * und meldet sich nur an der reservierten Adresse (config tenancy.superadmin_subdomain) an.
 */
#[Fillable(['name', 'email', 'password', 'active', 'must_change_password'])]
#[Hidden(['password'])]
class Superadmin extends Authenticatable
{
    use HasApiTokens, HasUuids;

    protected $attributes = [
        'active' => true,
        'must_change_password' => false,
    ];

    public function getConnectionName(): string
    {
        return config('tenancy.central_connection');
    }

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'active' => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }
}
