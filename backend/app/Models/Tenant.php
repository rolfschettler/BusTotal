<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Ein Mandant (eine Firma, die BusTotal nutzt). Liegt in der ZENTRALEN Datenbank.
 *
 * Das Datenbank-Passwort wird mit dem APP_KEY verschlüsselt gespeichert. Geht der APP_KEY verloren,
 * sind alle Mandanten-Passwörter unlesbar: APP_KEY gehört in die Datensicherung (getrennt vom Code).
 */
class Tenant extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $hidden = ['db_password'];

    protected function casts(): array
    {
        return [
            'db_password' => 'encrypted',
            'db_port' => 'integer',
            'is_active' => 'boolean',
            'migrated_at' => 'datetime',
        ];
    }

    public function getConnectionName(): string
    {
        return config('tenancy.central_connection');
    }
}
