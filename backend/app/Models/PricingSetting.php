<?php

namespace App\Models;

use App\Enums\TripKind;
use App\Enums\VehicleType;
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
            'tax_rate_line' => 'decimal:2',
            'tax_rate_school' => 'decimal:2',
            'surcharge_saturday_percent' => 'decimal:2',
            'surcharge_sunday_percent' => 'decimal:2',
            'surcharge_holiday_percent' => 'decimal:2',
            'empty_km_percent' => 'integer',
        ];
    }

    /** Die einzige Zeile holen (oder mit den Standardwerten anlegen). */
    public static function current(): self
    {
        return static::first() ?? static::create([])->fresh();
    }

    /**
     * Der Steuersatz einer Fahrtart. Gelegenheitsverkehr, Sonstiges und „keine Angabe“ nehmen den Regelsatz (tax_rate),
     * Linienverkehr und Schülerfahrten ihre eigenen Sätze (Standard 7 %, gilt nur bis 50 km, siehe TripKind).
     */
    public function taxRateFor(?TripKind $kind): string
    {
        return (string) match ($kind) {
            TripKind::Linie => $this->tax_rate_line,
            TripKind::Schule => $this->tax_rate_school,
            default => $this->tax_rate,
        };
    }

    /**
     * Die Preise, die für einen Fahrzeugtyp gelten: der Standardpreis, überschrieben von den Feldern, die für den Typ
     * ausgefüllt sind. Ein Typ-Preis von 0 gilt (schaltet den Bestandteil für diesen Typ aus), ein leeres Feld nicht.
     *
     * @return array{price_per_km: string, hourly_rate: string, day_rate: string, overnight_rate: string, minimum_price: string}
     */
    public function ratesFor(?VehicleType $type): array
    {
        $rates = array_intersect_key($this->attributesToArray(), array_flip(PricingVehicleRate::FIELDS));
        $rates = array_map(fn ($v) => (string) $v, $rates);

        if ($type !== null) {
            $own = PricingVehicleRate::where('vehicle_type', $type->value)->first();
            foreach (PricingVehicleRate::FIELDS as $field) {
                if ($own?->{$field} !== null) {
                    $rates[$field] = (string) $own->{$field};
                }
            }
        }

        return $rates;
    }

    /** Ist für diesen Fahrzeugtyp (oder ohne Typ) überhaupt ein Preis hinterlegt? Sonst kann die Preisfindung nichts vorschlagen. */
    public function hasAnyPrice(?VehicleType $type = null): bool
    {
        foreach ($this->ratesFor($type) as $value) {
            if ((float) $value > 0) {
                return true;
            }
        }

        return false;
    }
}
