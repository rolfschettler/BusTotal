<?php

namespace App\Support;

/**
 * Erzeugt vorläufige Passwörter, die der Administrator weitergibt.
 * Ohne leicht verwechselbare Zeichen (0/O, 1/l/I), damit man sie abtippen oder vorlesen kann.
 */
class TemporaryPassword
{
    private const LETTERS = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';

    private const DIGITS = '23456789';

    public static function generate(int $length = 12): string
    {
        // Mindestens ein Buchstabe und eine Ziffer, damit die Passwort-Regeln erfüllt sind.
        $chars = [self::pick(self::LETTERS), self::pick(self::DIGITS)];
        $all = self::LETTERS.self::DIGITS;

        while (count($chars) < $length) {
            $chars[] = self::pick($all);
        }

        // Mischen mit kryptografisch sicherem Zufall (Fisher-Yates).
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }

    private static function pick(string $set): string
    {
        return $set[random_int(0, strlen($set) - 1)];
    }
}
