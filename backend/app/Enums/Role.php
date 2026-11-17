<?php

namespace App\Enums;

/**
 * Rollen innerhalb eines Mandanten.
 */
enum Role: string
{
    case Admin = 'admin';
    case Disponent = 'disponent';
    case Buchhaltung = 'buchhaltung';
    case Fahrer = 'fahrer';
}
