<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Zugangsschlüssel einer Firma zu einem externen Dienst. Bewusst OHNE `Auditable`: Das Protokoll würde den Schlüssel im Klartext
 * speichern. Änderungen protokolliert der Controller ohne Schlüssel.
 */
class ServiceCredential extends Model
{
    use HasUuids;

    protected $fillable = ['service', 'api_key'];

    protected $hidden = ['api_key'];

    protected function casts(): array
    {
        return ['api_key' => 'encrypted'];
    }
}
