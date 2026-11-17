<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Erzeugt realistische, aber erfundene Kundendaten für Schulungen, Messen und eigene Tests.
 *
 * Wiederholbar: Kunde Nr. 17 sieht bei jedem Lauf gleich aus (fester Startwert je Nummer), und wer
 * zuerst 200 und später 500 Kunden erzeugt, behält die ersten 200 unverändert.
 *
 * Alles ist erfunden: E-Mail-Adressen enden auf ".example" (reserviert, nie echt), Telefonnummern sind
 * Zufallsziffern. Ortsnamen, Postleitzahlen und Vorwahlen passen zusammen, damit es echt wirkt.
 */
class DemoDataGenerator
{
    /** Verteilung der Kundenarten in Prozent (Summe 100). Typisch für ein Busunternehmen. */
    private const TYPE_WEIGHTS = ['privat' => 30, 'verein' => 24, 'firma' => 22, 'schule' => 12, 'reisebuero' => 12];

    /** Ort => [Vorwahl, [Postleitzahlen]] */
    private const CITIES = [
        'München' => ['089', ['80331', '80333', '80469', '81369']], 'Augsburg' => ['0821', ['86150', '86152', '86159']],
        'Nürnberg' => ['0911', ['90402', '90403', '90409']], 'Regensburg' => ['0941', ['93047', '93049']],
        'Ingolstadt' => ['0841', ['85049', '85051']], 'Würzburg' => ['0931', ['97070', '97072']],
        'Passau' => ['0851', ['94032', '94034']], 'Bamberg' => ['0951', ['96047', '96049']],
        'Bayreuth' => ['0921', ['95444', '95445']], 'Kempten' => ['0831', ['87435', '87437']],
        'Rosenheim' => ['08031', ['83022', '83024']], 'Landshut' => ['0871', ['84028', '84030']],
        'Stuttgart' => ['0711', ['70173', '70176', '70190']], 'Ulm' => ['0731', ['89073', '89075']],
        'Karlsruhe' => ['0721', ['76133', '76135']], 'Freiburg' => ['0761', ['79098', '79100']],
        'Mannheim' => ['0621', ['68159', '68161']], 'Frankfurt' => ['069', ['60311', '60313', '60322']],
        'Wiesbaden' => ['0611', ['65183', '65185']], 'Mainz' => ['06131', ['55116', '55118']],
        'Kassel' => ['0561', ['34117', '34119']], 'Hannover' => ['0511', ['30159', '30161']],
        'Braunschweig' => ['0531', ['38100', '38102']], 'Bremen' => ['0421', ['28195', '28197']],
        'Hamburg' => ['040', ['20095', '20097', '22301']], 'Kiel' => ['0431', ['24103', '24105']],
        'Lübeck' => ['0451', ['23552', '23554']], 'Rostock' => ['0381', ['18055', '18057']],
        'Schwerin' => ['0385', ['19053', '19055']], 'Berlin' => ['030', ['10115', '10117', '10178', '12045']],
        'Potsdam' => ['0331', ['14467', '14469']], 'Leipzig' => ['0341', ['04109', '04103']],
        'Dresden' => ['0351', ['01067', '01069']], 'Erfurt' => ['0361', ['99084', '99086']],
        'Jena' => ['03641', ['07743', '07745']], 'Magdeburg' => ['0391', ['39104', '39106']],
        'Köln' => ['0221', ['50667', '50668', '50672']], 'Düsseldorf' => ['0211', ['40213', '40215']],
        'Essen' => ['0201', ['45127', '45128']], 'Dortmund' => ['0231', ['44135', '44137']],
        'Münster' => ['0251', ['48143', '48145']], 'Bielefeld' => ['0521', ['33602', '33604']],
        'Paderborn' => ['05251', ['33098', '33100']], 'Koblenz' => ['0261', ['56068', '56070']],
        'Trier' => ['0651', ['54290', '54292']], 'Saarbrücken' => ['0681', ['66111', '66113']],
    ];

    private const FEMALE = [
        'Anna', 'Maria', 'Sabine', 'Petra', 'Monika', 'Claudia', 'Birgit', 'Andrea', 'Susanne', 'Karin', 'Heike',
        'Gabriele', 'Ute', 'Renate', 'Ingrid', 'Brigitte', 'Christine', 'Martina', 'Stefanie', 'Katharina',
        'Julia', 'Sandra', 'Nicole', 'Barbara', 'Elisabeth', 'Angelika', 'Doris', 'Sigrid', 'Hannelore', 'Lena',
    ];

    private const MALE = [
        'Thomas', 'Michael', 'Andreas', 'Stefan', 'Peter', 'Klaus', 'Jürgen', 'Wolfgang', 'Hans', 'Manfred',
        'Dieter', 'Bernd', 'Uwe', 'Frank', 'Markus', 'Christian', 'Matthias', 'Martin', 'Werner', 'Helmut',
        'Rainer', 'Gerhard', 'Josef', 'Franz', 'Karl', 'Georg', 'Lukas', 'Jonas', 'Florian', 'Sebastian',
    ];

    private const LAST = [
        'Müller', 'Schmidt', 'Schneider', 'Fischer', 'Weber', 'Meyer', 'Wagner', 'Becker', 'Schulz', 'Hoffmann',
        'Schäfer', 'Koch', 'Bauer', 'Richter', 'Klein', 'Wolf', 'Schröder', 'Neumann', 'Schwarz', 'Zimmermann',
        'Braun', 'Krüger', 'Hofmann', 'Hartmann', 'Lange', 'Schmitt', 'Werner', 'Krause', 'Meier', 'Lehmann',
        'Schmid', 'Schulze', 'Maier', 'Köhler', 'Herrmann', 'König', 'Walter', 'Mayer', 'Huber', 'Kaiser',
        'Fuchs', 'Peters', 'Lang', 'Scholz', 'Möller', 'Weiß', 'Jung', 'Hahn', 'Schubert', 'Vogel',
        'Friedrich', 'Keller', 'Günther', 'Frank', 'Berger', 'Winkler', 'Roth', 'Beck', 'Lorenz', 'Baumann',
        'Franke', 'Albrecht', 'Schuster', 'Simon', 'Ludwig', 'Böhm', 'Winter', 'Kraus', 'Martin', 'Schumacher',
        'Krämer', 'Vogt', 'Stein', 'Jäger', 'Otto', 'Sommer', 'Groß', 'Seidel', 'Heinrich', 'Brandt',
    ];

    private const STREETS = [
        'Hauptstraße', 'Bahnhofstraße', 'Schulstraße', 'Gartenstraße', 'Dorfstraße', 'Bergstraße', 'Kirchplatz',
        'Lindenallee', 'Marktplatz', 'Ringstraße', 'Waldweg', 'Sonnenstraße', 'Mühlenweg', 'Rosenstraße',
        'Industriestraße', 'Am Anger', 'Poststraße', 'Goethestraße', 'Schillerstraße', 'Parkstraße', 'Wiesenweg',
        'Birkenweg', 'Eichenstraße', 'Brunnenstraße', 'Am Sportplatz', 'Hafenstraße', 'Jahnstraße', 'Mozartstraße',
        'Lessingstraße', 'Kantstraße', 'Gerberstraße', 'Brückenstraße', 'Neue Straße', 'Alte Landstraße',
        'Am Stadtpark', 'Uferweg', 'Feldstraße', 'Tannenweg', 'Kastanienallee', 'Rathausgasse',
    ];

    private const SCHOOL_TYPES = ['Grundschule', 'Mittelschule', 'Realschule', 'Gymnasium', 'Gesamtschule', 'Berufsschule', 'Förderzentrum'];

    private const PATRONS = [
        'Goethe', 'Schiller', 'Albert-Schweitzer', 'Kopernikus', 'Humboldt', 'Astrid-Lindgren', 'Pestalozzi',
        'Maria-Montessori', 'Erich-Kästner', 'Hermann-Hesse', 'Anne-Frank', 'Geschwister-Scholl', 'Max-Planck',
    ];

    private const CLUBS = [
        'TSV {c} {y} e.V.', 'FC {c} {y} e.V.', 'SV {c} {y} e.V.', 'Gesangverein {c} e.V.', 'Schützenverein {c} {y} e.V.',
        'Freiwillige Feuerwehr {c}', 'Kolpingfamilie {c}', 'Trachtenverein {c} e.V.', 'Wanderverein {c} e.V.',
        'Katholischer Frauenbund {c}', 'VdK Ortsverband {c}', 'Seniorenkreis {c}', 'Musikverein {c} {y} e.V.',
        'Heimat- und Geschichtsverein {c} e.V.', 'Radsportverein {c} {y} e.V.', 'Evangelische Kirchengemeinde {c}',
        'Landfrauenverein {c} e.V.', 'Tennisclub {c} e.V.', 'DLRG Ortsgruppe {c}', 'Chorgemeinschaft {c} e.V.',
    ];

    private const TRADES = [
        'Bau', 'Metallbau', 'Elektro', 'Sanitär', 'Transporte', 'Gartenbau', 'Druck', 'Immobilien', 'Maschinenbau',
        'Tischlerei', 'Dachdeckerei', 'Logistik', 'Haustechnik', 'Fliesen', 'Schreinerei',
    ];

    private const OFFICE_TYPES = ['Steuerberatung', 'Rechtsanwälte', 'Ingenieurbüro', 'Architekturbüro', 'Versicherungsmakler'];

    private const AGENCIES = [
        'Reisebüro {n}', 'Reisewelt {c}', 'Urlaubsparadies {n}', 'Fernweh {c} Reisen', '{n} Touristik',
        'Reisecenter {c}', 'Reisebüro am Markt {c}', '{c} Reisen GmbH', 'Sonnenland Reisen {c}', 'Weltenbummler {c}',
    ];

    private const SCHOOL_POSITIONS = ['Sekretariat', 'Schulleitung', 'Klassenleitung', 'Fachbetreuung Klassenfahrten', 'Elternbeirat'];

    private const CLUB_POSITIONS = ['1. Vorsitzende/r', 'Kassenwart', 'Schriftführer/in', 'Ausflugsleitung', 'Jugendwart'];

    private const FIRM_POSITIONS = ['Geschäftsführung', 'Assistenz der Geschäftsführung', 'Personalabteilung', 'Betriebsrat', 'Einkauf'];

    private const AGENCY_POSITIONS = ['Inhaber/in', 'Buchung', 'Gruppenreisen', 'Buchhaltung'];

    private const PICKUPS = [
        'Schulhof', 'Parkplatz Festhalle', 'Rathausplatz', 'Bahnhof Haupteingang', 'Parkplatz Sportheim',
        'Busbahnhof', 'Marktplatz', 'Haltestelle Kirche', 'Werkstor', 'Parkplatz Einkaufszentrum',
    ];

    private const NOTES = [
        'schule' => [
            'Abfahrt meist ab Schulhof, Ankunft 07:30 Uhr.', 'Rechnung bitte an den Förderverein.',
            'Jährliche Klassenfahrt im Mai, Gruppen von 25 bis 30 Kindern.', 'Bei Tagesfahrten bitte Begleitpersonen mitzählen.',
        ],
        'verein' => [
            'Jährliche Ausflugsfahrt im Herbst, meist 40 bis 50 Personen.', 'Rollstuhlfahrer dabei, Fahrzeug mit Hebebühne nötig.',
            'Zahlt zuverlässig per Überweisung.', 'Kontakt wechselt mit der Vorstandswahl, vor Angebot prüfen.',
        ],
        'firma' => [
            'Betriebsausflug im Sommer, Rechnung an die Buchhaltung.', 'Stammkunde seit mehreren Jahren.',
            'Angebote bitte immer mit Mehrwertsteuer ausweisen.', 'Vegetarisches Essen bei Mehrtagesfahrten.',
        ],
        'reisebuero' => [
            'Bucht regelmäßig Zubringerfahrten zu Flughäfen.', 'Provisionsvereinbarung vorhanden.',
            'Gruppenreisen meist mit Reisebegleitung.', 'Bevorzugt Angebote per E-Mail.',
        ],
        'privat' => [
            'Familienfeier, ca. 30 Personen.', 'Wünscht Abholung direkt am Haus.', 'Stammkunde, fährt jedes Jahr mit.',
            'Bitte telefonisch am Vormittag erreichen.',
        ],
    ];

    private const MOBILE_PREFIXES = ['0151', '0160', '0170', '0171', '0175', '0176', '0179'];

    private const FREEMAIL = ['mail.example', 'post.example', 'netz.example'];

    /** Bereits vergebene Namen. Hält die Demo-Kunden eindeutig, damit sie keine Dublettenwarnung auslösen. */
    private array $used = [];

    public function __construct(
        private readonly CarbonImmutable $today,
        private readonly int $seed = 20261119,
    ) {}

    /**
     * Kunde Nr. $index (ab 1). Muss der Reihe nach aufgerufen werden, weil die Eindeutigkeit der
     * Namen vom bisher Erzeugten abhängt. Dabei bleibt das Ergebnis je Nummer bei jedem Lauf gleich.
     *
     * @return array{customer: array, contacts: array[], addresses: array[], created_at: CarbonImmutable, updated_at: CarbonImmutable, archived: bool}
     */
    public function make(int $index): array
    {
        $r = new Randomizer(new Mt19937($this->seed + $index * 7919));

        $type = $this->weighted($r, self::TYPE_WEIGHTS);
        [$city, $zip, $areaCode] = $this->place($r);

        // Namen so lange neu würfeln, bis sie noch nicht vergeben sind.
        for ($attempt = 0; ; $attempt++) {
            $identity = $this->identity($r, $type, $city);
            $key = mb_strtolower(($identity['company_name'] ?? '').'|'.($identity['first_name'] ?? '').'|'.($identity['last_name'] ?? '').'|'.$zip);
            if (! isset($this->used[$key]) || $attempt >= 12) {
                break;
            }
        }
        if (isset($this->used[$key])) {
            // Sehr unwahrscheinlich: Notlösung mit Ortsteil, damit der Name eindeutig bleibt.
            $suffix = ' '.$this->pick($r, ['Nord', 'Süd', 'Ost', 'West', 'Mitte']);
            if ($identity['company_name']) {
                $identity['company_name'] .= $suffix;
            } else {
                $identity['last_name'] .= $suffix;
            }
            $key .= $suffix.$index;
        }
        $this->used[$key] = true;

        $isOrg = $type !== 'privat';
        $slug = $this->slug($isOrg ? $this->plainName($identity['company_name']) : ($identity['first_name'].'.'.$identity['last_name']), $isOrg ? '-' : '.');
        // Gekürzt auf 28 Zeichen. Ein Bindestrich am Ende (z. B. "…-.example") wäre keine gültige Domain.
        $domain = $isOrg ? rtrim(mb_substr($slug, 0, 28), '-').'.example' : $this->pick($r, self::FREEMAIL);

        $customer = [
            'type' => $type,
            'company_name' => $identity['company_name'],
            'salutation' => $identity['salutation'],
            'first_name' => $identity['first_name'],
            'last_name' => $identity['last_name'],
            'email' => $isOrg ? $this->pick($r, ['info', 'kontakt', 'buero', 'verwaltung']).'@'.$domain : $slug.'@'.$domain,
            'phone' => $this->phone($r, $areaCode),
            'mobile' => $type === 'privat' && $this->chance($r, 55) ? $this->mobile($r) : null,
            'website' => $isOrg && $this->chance($r, 40) ? 'www.'.$domain : null,
            'vat_id' => in_array($type, ['firma', 'reisebuero'], true) && $this->chance($r, $type === 'firma' ? 60 : 85)
                ? 'DE'.$r->getInt(1, 9).str_pad((string) $r->getInt(0, 99999999), 8, '0', STR_PAD_LEFT) : null,
            'street' => $this->street($r),
            'postal_code' => $zip,
            'city' => $city,
            'country' => 'DE',
            'payment_term_days' => $this->paymentTerm($r, $type),
            'notes' => $this->chance($r, 20) ? $this->pick($r, self::NOTES[$type]) : null,
        ];

        $created = $this->today->subDays($r->getInt(2, 700))->setTime($r->getInt(7, 17), $r->getInt(0, 59));
        $updated = $this->chance($r, 35) ? $created->addDays($r->getInt(1, max(1, (int) $created->diffInDays($this->today)))) : $created;

        return [
            'customer' => $customer,
            'contacts' => $this->contacts($r, $type, $domain, $areaCode),
            'addresses' => $this->addresses($r, $type, $city, $zip),
            'created_at' => $created,
            'updated_at' => $updated->greaterThan($this->today) ? $this->today : $updated,
            'archived' => $this->chance($r, 3),
        ];
    }

    // ---------- Namen ----------

    /** @return array{company_name: ?string, salutation: ?string, first_name: ?string, last_name: ?string} */
    private function identity(Randomizer $r, string $type, string $city): array
    {
        $none = ['company_name' => null, 'salutation' => null, 'first_name' => null, 'last_name' => null];

        if ($type === 'privat') {
            $female = $this->chance($r, 50);

            return ['salutation' => $female ? 'Frau' : 'Herr', 'first_name' => $this->pick($r, $female ? self::FEMALE : self::MALE), 'last_name' => $this->pick($r, self::LAST)] + $none;
        }

        $name = $this->pick($r, self::LAST);

        $company = match ($type) {
            'schule' => $this->chance($r, 55)
                ? $this->pick($r, self::SCHOOL_TYPES).' '.$city
                : $this->pick($r, self::PATRONS).'-'.$this->pick($r, ['Schule', 'Gymnasium', 'Realschule', 'Grundschule']).' '.$city,
            'verein' => strtr($this->pick($r, self::CLUBS), ['{c}' => $city, '{y}' => (string) $r->getInt(1875, 1998)]),
            'reisebuero' => strtr($this->pick($r, self::AGENCIES), ['{c}' => $city, '{n}' => $name]),
            default => $this->firm($r, $name, $city),
        };

        return ['company_name' => $company] + $none;
    }

    private function firm(Randomizer $r, string $name, string $city): string
    {
        return match ($r->getInt(1, 6)) {
            1 => $name.' '.$this->pick($r, self::TRADES).' GmbH',
            2 => 'Autohaus '.$name.' '.$city,
            3 => 'Bäckerei '.$name.' e.K.',
            4 => $name.' & Söhne '.$this->pick($r, ['GmbH', 'KG']),
            5 => $name.' '.$this->pick($r, self::OFFICE_TYPES),
            default => $city.' '.$this->pick($r, ['Pflegedienst', 'Seniorenresidenz', 'Wohnbau', 'Stadtwerke', 'Bürotechnik']).' GmbH',
        };
    }

    // ---------- Kontakte und Adressen ----------

    private function contacts(Randomizer $r, string $type, string $domain, string $areaCode): array
    {
        $count = match ($type) {
            'privat' => $this->chance($r, 8) ? 1 : 0,
            'reisebuero' => $r->getInt(1, 2),
            default => $r->getInt(1, 3),
        };
        $positions = match ($type) {
            'schule' => self::SCHOOL_POSITIONS, 'verein' => self::CLUB_POSITIONS, 'reisebuero' => self::AGENCY_POSITIONS, default => self::FIRM_POSITIONS,
        };

        $contacts = [];
        $usedPositions = [];
        for ($i = 0; $i < $count; $i++) {
            $female = $this->chance($r, 50);
            $first = $this->pick($r, $female ? self::FEMALE : self::MALE);
            $last = $this->pick($r, self::LAST);
            $position = $type === 'privat' ? null : $this->pick($r, array_values(array_diff($positions, $usedPositions)));
            $usedPositions[] = $position;

            $contacts[] = [
                'salutation' => $female ? 'Frau' : 'Herr',
                'first_name' => $first,
                'last_name' => $last,
                'position' => $position,
                'email' => $this->slug($first.'.'.$last, '.').'@'.($type === 'privat' ? $this->pick($r, self::FREEMAIL) : $domain),
                'phone' => $this->chance($r, 70) ? $this->phone($r, $areaCode) : null,
                'mobile' => $this->chance($r, 45) ? $this->mobile($r) : null,
                'is_primary' => $i === 0, // genau ein Hauptansprechpartner
                'notes' => null,
            ];
        }

        return $contacts;
    }

    private function addresses(Randomizer $r, string $type, string $city, string $zip): array
    {
        $count = match ($type) {
            'schule', 'verein' => $this->chance($r, 70) ? $r->getInt(1, 2) : 0,
            'firma' => $this->chance($r, 20) ? 1 : 0,
            'reisebuero' => $this->chance($r, 15) ? 1 : 0,
            default => 0,
        };

        $addresses = [];
        $labels = [];
        for ($i = 0; $i < $count; $i++) {
            $label = $this->pick($r, array_values(array_diff(self::PICKUPS, $labels)));
            $labels[] = $label;
            $addresses[] = [
                'type' => 'abholung', 'label' => $label, 'street' => $this->street($r),
                'postal_code' => $zip, 'city' => $city, 'country' => 'DE', 'notes' => null,
            ];
        }

        return $addresses;
    }

    // ---------- Bausteine ----------

    /** @return array{0: string, 1: string, 2: string} Ort, Postleitzahl, Vorwahl */
    private function place(Randomizer $r): array
    {
        $city = array_keys(self::CITIES)[$r->getInt(0, count(self::CITIES) - 1)];
        [$areaCode, $zips] = self::CITIES[$city];

        return [$city, $this->pick($r, $zips), $areaCode];
    }

    private function street(Randomizer $r): string
    {
        $number = $r->getInt(1, 149).($this->chance($r, 12) ? $this->pick($r, ['a', 'b']) : '');

        return $this->pick($r, self::STREETS).' '.$number;
    }

    private function phone(Randomizer $r, string $areaCode): string
    {
        return $areaCode.' '.$r->getInt(2, 9).str_pad((string) $r->getInt(0, 999999), $r->getInt(5, 6), '0', STR_PAD_LEFT);
    }

    private function mobile(Randomizer $r): string
    {
        return $this->pick($r, self::MOBILE_PREFIXES).' '.str_pad((string) $r->getInt(0, 9999999), 7, '0', STR_PAD_LEFT);
    }

    private function paymentTerm(Randomizer $r, string $type): ?int
    {
        $roll = $r->getInt(1, 100);
        if ($type === 'privat') {
            return $roll <= 15 ? 0 : null; // Privatkunden zahlen oft sofort, sonst gilt der Standard
        }

        return match (true) {
            $roll <= 55 => null, // Standard der Firma
            $roll <= 75 => 14,
            $roll <= 90 => 30,
            default => 7,
        };
    }

    /** Firmenname ohne Rechtsform und Zusätze, für E-Mail-Adresse und Webseite. */
    private function plainName(string $name): string
    {
        return trim(preg_replace('/\b(GmbH|KG|e\.K\.|e\.V\.|&)\b/u', '', $name));
    }

    private function slug(string $text, string $separator): string
    {
        $text = strtr(mb_strtolower($text), ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);

        return trim(preg_replace('/[^a-z0-9]+/', $separator, $text), $separator);
    }

    private function pick(Randomizer $r, array $list): mixed
    {
        return $list[$r->getInt(0, count($list) - 1)];
    }

    private function chance(Randomizer $r, int $percent): bool
    {
        return $r->getInt(1, 100) <= $percent;
    }

    private function weighted(Randomizer $r, array $weights): string
    {
        $roll = $r->getInt(1, array_sum($weights));
        foreach ($weights as $key => $weight) {
            if (($roll -= $weight) <= 0) {
                return $key;
            }
        }

        return array_key_first($weights);
    }
}
