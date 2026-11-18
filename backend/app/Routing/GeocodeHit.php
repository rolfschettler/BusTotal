<?php

namespace App\Routing;

/** Ein Treffer der Adresssuche. */
final readonly class GeocodeHit
{
    public function __construct(
        public string $label,
        public Coordinate $coordinate,
        public ?string $postalCode = null,
        public ?string $city = null,
        public ?string $country = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['label' => $this->label, 'postal_code' => $this->postalCode, 'city' => $this->city, 'country' => $this->country] + $this->coordinate->toArray();
    }
}
