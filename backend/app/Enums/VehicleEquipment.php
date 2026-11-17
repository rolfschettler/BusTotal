<?php

namespace App\Enums;

/** Ausstattung eines Fahrzeugs. Wird als Liste von Schlüsseln am Fahrzeug gespeichert. */
enum VehicleEquipment: string
{
    case Klima = 'klima';
    case Wc = 'wc';
    case Kueche = 'kueche';
    case Wlan = 'wlan';
    case Steckdosen = 'steckdosen';
    case Video = 'video';
    case Rollstuhllift = 'rollstuhllift';
    case Anhaengerkupplung = 'anhaengerkupplung';

    public function label(): string
    {
        return match ($this) {
            self::Klima => 'Klimaanlage',
            self::Wc => 'WC',
            self::Kueche => 'Bordküche',
            self::Wlan => 'WLAN',
            self::Steckdosen => 'Steckdosen',
            self::Video => 'Video / Monitore',
            self::Rollstuhllift => 'Rollstuhllift',
            self::Anhaengerkupplung => 'Anhängerkupplung',
        };
    }
}
