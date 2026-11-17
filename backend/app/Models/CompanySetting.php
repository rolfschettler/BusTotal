<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    /** Die einzige Zeile holen (oder leer anlegen). */
    public static function current(): self
    {
        return static::first() ?? static::create(['name' => 'Neue Firma']);
    }
}
