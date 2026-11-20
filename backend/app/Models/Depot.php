<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPlace;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Betriebshof einer Firma (Start und Ziel der Leerfahrten). Eine Firma kann mehrere haben, einer ist der Standard. */
class Depot extends Model
{
    use Auditable, HasPlace, HasUuids;

    // Den Ort (place_id) setzt nur der Server nach der Adresssuche, nie der Browser.
    protected $fillable = ['name', 'address', 'is_default'];

    // Die Koordinaten kommen mit jedem Betriebshof, ohne weitere Abfrage.
    protected $with = ['place'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function placeFields(): array
    {
        return ['address'];
    }

    public function placeQuery(): ?string
    {
        return $this->address;
    }
}
