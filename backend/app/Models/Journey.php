<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Reisevorhaben: schlanker, optionaler Container (Kunde, Titel, Zeitraum) mit Abschnitten. Er entsteht mit dem ersten Angebot aus
 * der Streckenplanung. Summen, Marge und Stand werden aus den Belegen abgeleitet, nie hier gespeichert.
 */
class Journey extends Model
{
    use Auditable, HasUuids;

    protected $fillable = ['title', 'customer_id', 'date_from', 'date_to'];

    protected function casts(): array
    {
        return ['date_from' => 'date:Y-m-d', 'date_to' => 'date:Y-m-d'];
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
