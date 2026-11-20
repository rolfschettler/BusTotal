<?php

namespace App\Pricing;

/** Ein Kalendertag der Tour mit dem, was für Zuschläge zählt. Die Preisfindung wertet nur diese Angaben aus. */
final readonly class TourDay
{
    public function __construct(
        /** Kalendertag, YYYY-MM-DD */
        public string $date,
        public bool $saturday,
        public bool $sunday,
        /** Name des gesetzlichen Feiertags, sonst null */
        public ?string $holiday = null,
    ) {}
}
