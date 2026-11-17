<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    use Auditable;

    public const DEFAULT_COLOR = '#1d4e89';

    protected $guarded = ['id', 'logo_path'];

    // Den Speicherpfad des Logos nach außen nicht preisgeben, nur ob es eins gibt.
    protected $hidden = ['logo_path'];

    protected $appends = ['has_logo'];

    public function getHasLogoAttribute(): bool
    {
        return $this->logo_path !== null;
    }

    /** Die einzige Zeile holen (oder leer anlegen). */
    public static function current(): self
    {
        return static::first() ?? static::create(['name' => 'Neue Firma']);
    }
}
