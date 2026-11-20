<?php

namespace App\Enums;

/** Die 16 Bundesländer (amtliche Kürzel). Nur für die Feiertage gebraucht: Sie gelten je Land verschieden. */
enum GermanState: string
{
    case BadenWuerttemberg = 'BW';
    case Bayern = 'BY';
    case Berlin = 'BE';
    case Brandenburg = 'BB';
    case Bremen = 'HB';
    case Hamburg = 'HH';
    case Hessen = 'HE';
    case MecklenburgVorpommern = 'MV';
    case Niedersachsen = 'NI';
    case NordrheinWestfalen = 'NW';
    case RheinlandPfalz = 'RP';
    case Saarland = 'SL';
    case Sachsen = 'SN';
    case SachsenAnhalt = 'ST';
    case SchleswigHolstein = 'SH';
    case Thueringen = 'TH';

    public function label(): string
    {
        return match ($this) {
            self::BadenWuerttemberg => 'Baden-Württemberg',
            self::Bayern => 'Bayern',
            self::Berlin => 'Berlin',
            self::Brandenburg => 'Brandenburg',
            self::Bremen => 'Bremen',
            self::Hamburg => 'Hamburg',
            self::Hessen => 'Hessen',
            self::MecklenburgVorpommern => 'Mecklenburg-Vorpommern',
            self::Niedersachsen => 'Niedersachsen',
            self::NordrheinWestfalen => 'Nordrhein-Westfalen',
            self::RheinlandPfalz => 'Rheinland-Pfalz',
            self::Saarland => 'Saarland',
            self::Sachsen => 'Sachsen',
            self::SachsenAnhalt => 'Sachsen-Anhalt',
            self::SchleswigHolstein => 'Schleswig-Holstein',
            self::Thueringen => 'Thüringen',
        };
    }
}
