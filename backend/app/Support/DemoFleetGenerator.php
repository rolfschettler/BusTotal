<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Erzeugt einen erfundenen Fuhrpark (Fahrzeuge, Fahrer, Abwesenheiten) für Schulungen, Messen und Tests.
 *
 * Wiederholbar: Fahrzeug Nr. 3 und Fahrer Nr. 7 sehen bei jedem Lauf gleich aus (fester Startwert je Nummer).
 * Die Fristen und Abwesenheiten sind relativ zu HEUTE festgelegt, damit die Kacheln "Fällige Fristen" und die
 * Anzeige "heute abwesend" immer etwas zum Vorführen haben: Je Gruppe von Fahrzeugen bzw. Fahrern ist eine
 * Frist abgelaufen, mehrere laufen bald ab, der Rest ist in Ordnung.
 *
 * Alles ist erfunden: E-Mail-Adressen enden auf ".example", Telefonnummern, Führerschein- und Fahrerkartennummern
 * sind Zufallswerte.
 */
class DemoFleetGenerator
{
    /** [Art, Hersteller, Modell, Sitzplätze, Länge, Breite, Höhe, Gesamtgewicht kg, Kosten je km, Fahrgestell-Kennung] */
    private const MODELS = [
        ['reisebus', 'Setra', 'S 515 HD', 49, 12.00, 2.55, 3.65, 18000, 1.45, 'WKK'],
        ['reisebus', 'Mercedes-Benz', 'Tourismo RHD', 51, 12.30, 2.55, 3.70, 19500, 1.50, 'WDB'],
        ['reisebus', 'MAN', 'Lion\'s Coach', 53, 13.00, 2.55, 3.70, 19500, 1.55, 'WMA'],
        ['reisebus', 'Neoplan', 'Tourliner', 50, 12.20, 2.55, 3.70, 19000, 1.48, 'WNE'],
        ['doppeldecker', 'Setra', 'S 431 DT', 79, 13.90, 2.55, 4.00, 26000, 1.95, 'WKK'],
        ['midibus', 'Mercedes-Benz', 'Tourino', 32, 8.70, 2.35, 3.20, 12500, 1.10, 'WDB'],
        ['midibus', 'Iveco', 'Crossway LE', 35, 9.50, 2.50, 3.25, 13500, 1.15, 'ZCF'],
        ['minibus', 'Mercedes-Benz', 'Sprinter Tourer', 19, 7.37, 1.99, 2.80, 5000, 0.85, 'WDB'],
        ['minibus', 'Volkswagen', 'Crafter Kombi', 17, 7.39, 2.04, 2.82, 5500, 0.80, 'WV1'],
    ];

    private const EQUIPMENT_BY_TYPE = [
        'reisebus' => ['klima', 'wc', 'wlan', 'steckdosen', 'video', 'kueche', 'anhaengerkupplung'],
        'doppeldecker' => ['klima', 'wc', 'wlan', 'steckdosen', 'video', 'kueche'],
        'midibus' => ['klima', 'wlan', 'steckdosen', 'rollstuhllift'],
        'minibus' => ['klima', 'steckdosen', 'rollstuhllift', 'anhaengerkupplung'],
    ];

    /** Ort => [Postleitzahl, Vorwahl] rund um den (erfundenen) Betriebssitz */
    private const CITIES = [
        'Ansbach' => ['91522', '0981'], 'Nürnberg' => ['90402', '0911'], 'Fürth' => ['90762', '0911'],
        'Erlangen' => ['91052', '09131'], 'Schwabach' => ['91126', '09122'], 'Rothenburg' => ['91541', '09861'],
        'Dinkelsbühl' => ['91550', '09851'], 'Gunzenhausen' => ['91710', '09831'],
    ];

    private const FIRST_NAMES = [
        'Stefan', 'Michael', 'Thomas', 'Andreas', 'Frank', 'Jürgen', 'Klaus', 'Peter', 'Markus', 'Uwe', 'Ralf',
        'Dieter', 'Wolfgang', 'Bernd', 'Manfred', 'Holger', 'Jens', 'Martin', 'Sabine', 'Petra', 'Monika',
        'Gabriele', 'Heike', 'Karin', 'Birgit',
    ];

    private const LAST_NAMES = [
        'Bauer', 'Schuster', 'Wagner', 'Hofmann', 'Lang', 'Kraus', 'Fink', 'Brandl', 'Reuter', 'Ziegler', 'Haas',
        'Vogel', 'Eder', 'Pohl', 'Lehner', 'Specht', 'Metz', 'Dietz', 'Kunz', 'Seidel', 'Roth', 'Engel', 'Moser',
        'Ott', 'Graf', 'Fuchs', 'Winkler', 'Berger', 'Maier',
    ];

    private const STREETS = ['Hauptstraße', 'Bahnhofstraße', 'Gartenweg', 'Schulstraße', 'Lindenallee', 'Am Anger', 'Mühlweg', 'Ringstraße', 'Waldstraße'];

    private const ABSENCE_NOTES = ['Sommerurlaub', 'Familienfeier', 'Kur', 'Brückentage', 'Erkältung', 'Bandscheibe', 'Weiterbildung Code 95', 'Erste-Hilfe-Kurs'];

    /** Nummer des Fahrzeugs, das archiviert ist (z. B. verkauft): jedes 12. */
    private const ARCHIVE_VEHICLE_EVERY = 12;

    /** Nummer des Fahrers, der archiviert ist (z. B. ausgetreten): jeder 15. */
    private const ARCHIVE_DRIVER_EVERY = 15;

    public function __construct(private readonly CarbonImmutable $today) {}

    /** @return array<string, mixed> Felder des Fahrzeugs Nr. $index (ab 1) plus 'archived' */
    public function vehicle(int $index): array
    {
        $r = $this->rng('fahrzeug', $index);
        $m = self::MODELS[($index - 1) % count(self::MODELS)];
        [$type, $manufacturer, $model, $seats, $length, $width, $height, $weight, $cost, $wmi] = $m;

        $equipment = [];
        foreach (self::EQUIPMENT_BY_TYPE[$type] as $key) {
            // Klima und WLAN fast immer, Rest nach Zufall
            $chance = in_array($key, ['klima', 'wlan'], true) ? 90 : 55;
            if ($r->getInt(1, 100) <= $chance) {
                $equipment[] = $key;
            }
        }

        $yearBuilt = (int) $this->today->format('Y') - $r->getInt(0, 11);

        return [
            'fleet_number' => 'B-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
            'license_plate' => $this->plate($index, $r),
            'type' => $type,
            'manufacturer' => $manufacturer,
            'model' => $model,
            'year_built' => $yearBuilt,
            'vin' => $wmi.$this->chars($r, 14),
            'seats' => $seats,
            'equipment' => $equipment,
            'length_m' => $length,
            'width_m' => $width,
            'height_m' => $height,
            'gross_weight_kg' => $weight,
            // Kostensatz leicht streuen (Alter, Verbrauch), auf 5 Cent gerundet
            'cost_per_km' => round(($cost + $r->getInt(-8, 8) / 100) * 20) / 20,
            // Je Gruppe von 10 Fahrzeugen: Nr. 3 hat eine abgelaufene HU, Nr. 4 eine bald fällige SP, Nr. 7 eine bald fällige
            // Wartung, Nr. 9 einen bald fälligen Fahrtenschreiber. Alle anderen Fristen liegen weit in der Zukunft.
            'hu_due_date' => $this->deadline($r, $index % 10 === 3 ? -20 : null),
            'sp_due_date' => $this->deadline($r, $index % 10 === 4 ? 12 : null),
            'tachograph_due_date' => $this->deadline($r, $index % 10 === 9 ? 25 : null),
            // Auslesen der Fahrzeugeinheit (alle 90 Tage): ohne Zufall, nur bei ohnehin auffälligen Fahrzeugen (Nr. 3 überfällig, Nr. 4 bald fällig).
            'tachograph_read_due_date' => $this->today->addDays(match ($index % 10) { 3 => -3, 4 => 9, default => 35 + ($index % 6) * 10 })->toDateString(),
            'service_due_date' => $this->deadline($r, $index % 10 === 7 ? 6 : null),
            'notes' => $index % 7 === 0 ? 'Bevorzugt für Mehrtagesfahrten.' : null,
            'created_at' => $this->today->subDays($r->getInt(60, 700)),
            'archived' => $index % self::ARCHIVE_VEHICLE_EVERY === 0,
        ];
    }

    /**
     * @return array{driver: array<string, mixed>, absences: list<array<string, mixed>>, archived: bool}
     */
    public function driver(int $index): array
    {
        $r = $this->rng('fahrer', $index);

        $firstCount = count(self::FIRST_NAMES);
        $first = self::FIRST_NAMES[($index - 1) % $firstCount];
        // 25 Vornamen und 29 Nachnamen: Das Paar wiederholt sich erst nach 725 Fahrern.
        $last = self::LAST_NAMES[($index - 1) % count(self::LAST_NAMES)];

        $city = array_keys(self::CITIES)[$r->getInt(0, count(self::CITIES) - 1)];
        [$postal, $area] = self::CITIES[$city];

        $employment = $this->weighted($r, ['vollzeit' => 62, 'teilzeit' => 14, 'aushilfe' => 14, 'extern' => 10]);
        $rate = match ($employment) {
            'vollzeit' => $r->getInt(180, 240),
            'teilzeit' => $r->getInt(170, 220),
            'aushilfe' => $r->getInt(140, 180),
            default => $r->getInt(280, 380),
        } / 10;

        $classes = $this->weighted($r, ['D,DE' => 45, 'D' => 30, 'D1,D' => 10, 'CE,D,DE' => 15]);
        $age = $r->getInt(24, 63);
        $birth = $this->today->subYears($age)->subDays($r->getInt(0, 364));
        $slug = fn (string $s) => strtolower(strtr($s, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']));

        $driver = [
            'personnel_number' => 'P-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
            'first_name' => $first,
            'last_name' => $last,
            'birth_date' => $birth->toDateString(),
            'email' => $slug($first).'.'.$slug($last).$index.'@fahrer.example',
            'phone' => $area.' '.$r->getInt(100000, 999999),
            'mobile' => '0171 '.$r->getInt(1000000, 9999999),
            'street' => self::STREETS[$r->getInt(0, count(self::STREETS) - 1)].' '.$r->getInt(1, 80),
            'postal_code' => $postal,
            'city' => $city,
            'employment_type' => $employment,
            // Eintritt frühestens mit 21 Jahren
            'hired_on' => $this->today->subDays($r->getInt(60, 365 * min(14, $age - 21)))->toDateString(),
            'hourly_rate' => $rate,
            'license_classes' => explode(',', $classes),
            'license_number' => strtoupper($this->chars($r, 11)),
            'driver_card_number' => 'DE'.$r->getInt(10000000, 99999999).$r->getInt(10, 99),
            // Je Gruppe von 8 Fahrern: Nr. 2 hat eine abgelaufene Fahrerkarte, Nr. 4 ein bald fälliges Code 95, Nr. 6 eine bald fällige Untersuchung.
            'license_due_date' => $this->deadline($r, null, 365 * 8),
            'pbs_due_date' => $this->deadline($r, null, 365 * 3),
            'driver_card_due_date' => $this->deadline($r, $index % 8 === 2 ? -9 : null, 365 * 2),
            // Auslesen der Fahrerkarte (alle 28 Tage): ohne Zufall; Nr. 2 überfällig, Nr. 4 bald fällig, alle anderen mit reichlich Zeit.
            'driver_card_read_due_date' => $this->today->addDays(match ($index % 8) { 2 => -2, 4 => 4, default => 10 + ($index % 6) * 3 })->toDateString(),
            'code95_due_date' => $this->deadline($r, $index % 8 === 4 ? 21 : null, 365 * 3),
            'medical_due_date' => $this->deadline($r, $index % 8 === 6 ? 14 : null, 365 * 2),
            'notes' => $index % 9 === 0 ? 'Fährt bevorzugt Schulfahrten.' : null,
            'created_at' => $this->today->subDays($r->getInt(30, 600)),
        ];

        return ['driver' => $driver, 'absences' => $this->absences($index, $r), 'archived' => $index % self::ARCHIVE_DRIVER_EVERY === 0];
    }

    /**
     * Abwesenheiten eines Fahrers: nacheinander, nie überlappend (die Anwendung lehnt Überschneidungen ab).
     * Fahrer Nr. 3 hat HEUTE Urlaub, Fahrer Nr. 5 ist HEUTE krank, damit die Anzeige "heute abwesend" etwas zeigt.
     *
     * @return list<array{type: string, start_date: string, end_date: string, notes: ?string}>
     */
    private function absences(int $index, Randomizer $r): array
    {
        $list = [];
        $add = function (string $type, CarbonImmutable $start, int $days, ?string $note) use (&$list) {
            $list[] = ['type' => $type, 'start_date' => $start->toDateString(), 'end_date' => $start->addDays($days - 1)->toDateString(), 'notes' => $note];
        };

        if ($index % 8 === 3) {
            $add('urlaub', $this->today->subDays(2), 11, 'Sommerurlaub');
        } elseif ($index % 8 === 5) {
            $add('krank', $this->today->subDays(1), 5, 'Erkältung');
        }

        if ($r->getInt(1, 100) <= 55) {
            // Vergangene und künftige Abwesenheiten: Startpunkte liegen weit genug auseinander, daher keine Überschneidung
            // mit der obigen Abwesenheit von heute (die liegt höchstens 11 Tage um heute herum).
            $slots = [-200, -120, 40, 90, 150];
            $count = $r->getInt(1, 3);
            for ($k = 0; $k < $count; $k++) {
                $type = $this->weighted($r, ['urlaub' => 60, 'krank' => 20, 'schulung' => 15, 'sonstige' => 5]);
                $days = match ($type) {
                    'urlaub' => $r->getInt(5, 14),
                    'krank' => $r->getInt(1, 7),
                    'schulung' => $r->getInt(1, 5),
                    default => $r->getInt(1, 2),
                };
                $start = $this->today->addDays($slots[$k * 2 % count($slots)] + $r->getInt(0, 20));
                $add($type, $start, $days, self::ABSENCE_NOTES[$r->getInt(0, count(self::ABSENCE_NOTES) - 1)]);
            }
        }

        // Nach Beginn sortieren und Überschneidungen sicher ausschließen (nur zur Sicherheit; Slots liegen auseinander).
        usort($list, fn ($a, $b) => $a['start_date'] <=> $b['start_date']);
        $clean = [];
        foreach ($list as $a) {
            if ($clean && $a['start_date'] <= end($clean)['end_date']) {
                continue;
            }
            $clean[] = $a;
        }

        return $clean;
    }

    /** Fester Zufallsstrom je Art und Nummer. */
    private function rng(string $kind, int $index): Randomizer
    {
        return new Randomizer(new Mt19937(crc32("demo-fuhrpark|$kind|$index")));
    }

    /** "AN-BT 417": eindeutig je Nummer (die Buchstaben ergeben sich aus der Nummer). */
    private function plate(int $index, Randomizer $r): string
    {
        $letters = chr(65 + intdiv($index - 1, 26) % 26).chr(65 + ($index - 1) % 26);

        return 'AN-'.$letters.' '.$r->getInt(100, 999);
    }

    /** Zufällige Zeichen ohne I, O und Q (so verlangt es die Fahrgestellnummer; für die Führerscheinnummer genauso brauchbar). */
    private function chars(Randomizer $r, int $length): string
    {
        $alphabet = '0123456789ABCDEFGHJKLMNPRSTUVWXYZ';
        $s = '';
        for ($i = 0; $i < $length; $i++) {
            $s .= $alphabet[$r->getInt(0, strlen($alphabet) - 1)];
        }

        return $s;
    }

    /**
     * Fälligkeitsdatum: mit $forcedDays fest in n Tagen ab heute (negativ = abgelaufen, bis 30 = bald fällig),
     * sonst zufällig mit gutem Abstand (mindestens 45 Tage), damit nichts ungewollt in der Warnung landet.
     */
    private function deadline(Randomizer $r, ?int $forcedDays, int $maxDays = 540): string
    {
        $days = $forcedDays ?? $r->getInt(45, max(60, $maxDays));

        return $this->today->addDays($days)->toDateString();
    }

    /** @param array<string, int> $weights Schlüssel => Gewicht */
    private function weighted(Randomizer $r, array $weights): string
    {
        $pick = $r->getInt(1, array_sum($weights));
        foreach ($weights as $key => $weight) {
            if (($pick -= $weight) <= 0) {
                return (string) $key;
            }
        }

        return (string) array_key_first($weights);
    }
}
