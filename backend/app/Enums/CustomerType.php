<?php

namespace App\Enums;

enum CustomerType: string
{
    case Firma = 'firma';
    case Privat = 'privat';
    case Schule = 'schule';
    case Verein = 'verein';
    case Reisebuero = 'reisebuero';

    /** Privatkunden haben keinen Firmennamen, alle anderen schon. */
    public function isOrganisation(): bool
    {
        return $this !== self::Privat;
    }
}
