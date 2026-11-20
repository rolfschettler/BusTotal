<?php

namespace App\Enums;

enum DocumentStatus: string
{
    /** Bearbeitbar, noch ohne Nummer. Darf gelöscht werden, weil keine Nummer verbraucht ist. */
    case Entwurf = 'entwurf';

    /** Nummer vergeben, Inhalt gesperrt (GoBD). Korrektur nur über Storno. */
    case Festgeschrieben = 'festgeschrieben';
}
