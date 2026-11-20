<?php

namespace App\Pricing;

use App\Enums\VehicleType;

/**
 * Die Kennzahlen einer geplanten Tour, auf die sich ein Preis stützt. Bewusst nur Zahlen (keine Route, keine Orte):
 * Die Preisfindung kennt die Streckenplanung nicht, sie bekommt nur diese Werte.
 */
final readonly class TourFigures
{
    public function __construct(
        /** Kilometer mit Fahrgästen */
        public float $loadedKm,
        /** Leerkilometer (Anfahrt und Rückfahrt zum Betriebshof) */
        public float $emptyKm,
        /** reine Lenkzeit in Sekunden */
        public int $driveSeconds,
        /** Einsatztage (mindestens 1) */
        public int $days,
        /** Zuschlag in Prozent auf die Summe (z. B. Wochenende, Feiertag), von Hand gewählt */
        public float $surchargePercent = 0.0,
        /** Typ des eingeplanten Fahrzeugs; bestimmt, ob Preise des Typs gelten. Ohne Fahrzeug null (Standardpreise). */
        public ?VehicleType $vehicleType = null,
    ) {}

    /** Übernachtungen des Fahrers: je Einsatztag nach dem ersten eine. */
    public function nights(): int
    {
        return max(0, $this->days - 1);
    }
}
