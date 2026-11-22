<?php

namespace App\Enums;

/** Art einer Zahlungsbuchung am Beleg: Geld kommt herein oder wird zurückgezahlt (Storno, Gutschrift, Überzahlung). */
enum PaymentKind: string
{
    case Eingang = 'eingang';
    case Rueckzahlung = 'rueckzahlung';

    public function label(): string
    {
        return match ($this) {
            self::Eingang => 'Zahlungseingang',
            self::Rueckzahlung => 'Rückzahlung',
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $k) => ['value' => $k->value, 'label' => $k->label()], self::cases());
    }
}
