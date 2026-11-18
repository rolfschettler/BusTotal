<?php

namespace App\Routing;

use InvalidArgumentException;

/** Ein Punkt auf der Erde: Breitengrad (lat, -90 bis 90) und Längengrad (lon, -180 bis 180). */
final readonly class Coordinate
{
    public function __construct(public float $lat, public float $lon)
    {
        if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
            throw new InvalidArgumentException('Koordinate außerhalb des gültigen Bereichs.');
        }
    }

    /** @return array{lat: float, lon: float} */
    public function toArray(): array
    {
        return ['lat' => $this->lat, 'lon' => $this->lon];
    }
}
