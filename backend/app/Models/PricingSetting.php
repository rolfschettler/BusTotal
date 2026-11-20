<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Preisparameter der Firma für die Standard-Preisfindung (eine Zeile je Mandanten-Datenbank). */
class PricingSetting extends Model
{
    use Auditable, HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'price_per_km' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
            'day_rate' => 'decimal:2',
            'overnight_rate' => 'decimal:2',
            'minimum_price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'empty_km_percent' => 'integer',
        ];
    }

    /** Die einzige Zeile holen (oder mit den Standardwerten anlegen). */
    public static function current(): self
    {
        return static::first() ?? static::create([])->fresh();
    }

    /** Ist überhaupt ein Preis hinterlegt? Sonst kann die Preisfindung nichts vorschlagen. */
    public function hasAnyPrice(): bool
    {
        return (float) $this->price_per_km > 0 || (float) $this->hourly_rate > 0
            || (float) $this->day_rate > 0 || (float) $this->overnight_rate > 0
            || (float) $this->minimum_price > 0;
    }
}
