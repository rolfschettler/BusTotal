<?php

namespace App\Enums;

/** Wie bezahlt wurde. Reine Angabe für das Büro, sie ändert keine Berechnung. */
enum PaymentMethod: string
{
    case Ueberweisung = 'ueberweisung';
    case Bar = 'bar';
    case Karte = 'karte';
    case Lastschrift = 'lastschrift';
    case Sonstig = 'sonstig';

    public function label(): string
    {
        return match ($this) {
            self::Ueberweisung => 'Überweisung',
            self::Bar => 'Bar',
            self::Karte => 'Karte',
            self::Lastschrift => 'Lastschrift',
            self::Sonstig => 'Sonstiges',
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $m) => ['value' => $m->value, 'label' => $m->label()], self::cases());
    }
}
