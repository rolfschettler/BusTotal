<?php

namespace App\Models;

use App\Routing\Coordinate;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Ein gesuchter Ort mit Koordinaten. Wird nur von App\Services\PlaceResolver angelegt und nie geändert: Neue Adresse, neuer Ort.
 * Ein Ort kann von mehreren Datensätzen verwendet werden (siehe App\Models\Concerns\HasPlace).
 */
class Place extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['lat' => 'float', 'lon' => 'float', 'geocoded_at' => 'datetime'];
    }

    public function coordinate(): Coordinate
    {
        return new Coordinate($this->lat, $this->lon);
    }
}
