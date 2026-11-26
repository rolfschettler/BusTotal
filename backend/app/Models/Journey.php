<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Reisevorhaben: schlanker, optionaler Container (Kunde, Titel, Zeitraum) mit Abschnitten. Er wird von Hand geplant („Neue Reise
 * planen“, `manual`) oder entsteht mit dem Kurzweg „Angebot erstellen“ aus der Streckenplanung. Summen, Marge und Stand werden aus
 * den Belegen abgeleitet, nie hier gespeichert.
 */
class Journey extends Model
{
    use Auditable, HasUuids;

    protected $fillable = ['title', 'customer_id', 'manual', 'date_from', 'date_to'];

    protected function casts(): array
    {
        return ['date_from' => 'date:Y-m-d', 'date_to' => 'date:Y-m-d', 'manual' => 'boolean'];
    }

    public function sections(): HasMany
    {
        return $this->hasMany(JourneySection::class)->orderBy('position');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
