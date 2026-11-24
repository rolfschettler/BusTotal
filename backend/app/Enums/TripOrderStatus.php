<?php

namespace App\Enums;

/** Stand eines Fahrtauftrags: Entwurf → disponiert (Fahrzeug und Fahrer zugewiesen) → ausgegeben (Nummer, Fahrer-PDF) → erledigt (Rückerfassung). */
enum TripOrderStatus: string
{
    case Entwurf = 'entwurf';
    case Disponiert = 'disponiert';
    case Ausgegeben = 'ausgegeben';
    case Erledigt = 'erledigt';

    public function label(): string
    {
        return match ($this) {
            self::Entwurf => 'Zu disponieren',
            self::Disponiert => 'Disponiert',
            self::Ausgegeben => 'Ausgegeben',
            self::Erledigt => 'Erledigt',
        };
    }
}
