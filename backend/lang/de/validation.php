<?php

/*
 * Deutsche Fehlermeldungen der Eingabeprüfung.
 *
 * Aufbau der Sätze: Sie beginnen mit dem Feldnamen (:attribute), damit sie zu jedem Feld passen,
 * egal ob der Name männlich, weiblich oder sächlich ist. Die Feldnamen stehen unten in "attributes".
 * Vollständigkeit gegenüber Laravels Vorlage prüft tests/Feature/LocalizationTest.php.
 */

return [

    'accepted' => ':attribute muss akzeptiert werden.',
    'accepted_if' => ':attribute muss akzeptiert werden, wenn :other :value ist.',
    'active_url' => ':attribute muss eine gültige Internetadresse sein.',
    'after' => ':attribute muss ein Datum nach dem :date sein.',
    'after_or_equal' => ':attribute muss ein Datum am oder nach dem :date sein.',
    'alpha' => ':attribute darf nur Buchstaben enthalten.',
    'alpha_dash' => ':attribute darf nur Buchstaben, Ziffern, Binde- und Unterstriche enthalten.',
    'alpha_num' => ':attribute darf nur Buchstaben und Ziffern enthalten.',
    'any_of' => ':attribute ist ungültig.',
    'array' => ':attribute muss eine Liste sein.',
    'array_keys' => ':attribute darf nur diese Schlüssel enthalten: :values.',
    'ascii' => ':attribute darf nur einfache Buchstaben, Ziffern und Zeichen enthalten.',
    'base64' => ':attribute muss eine gültige Base64-Zeichenfolge sein.',
    'before' => ':attribute muss ein Datum vor dem :date sein.',
    'before_or_equal' => ':attribute muss ein Datum am oder vor dem :date sein.',
    'between' => [
        'array' => ':attribute muss zwischen :min und :max Einträge enthalten.',
        'file' => ':attribute muss zwischen :min und :max Kilobyte groß sein.',
        'numeric' => ':attribute muss zwischen :min und :max liegen.',
        'string' => ':attribute muss zwischen :min und :max Zeichen lang sein.',
    ],
    'boolean' => ':attribute muss „ja“ oder „nein“ sein.',
    'can' => ':attribute enthält einen nicht erlaubten Wert.',
    'confirmed' => 'Die Bestätigung für :attribute stimmt nicht überein.',
    'contains' => ':attribute fehlt ein erforderlicher Wert.',
    'current_password' => 'Das Passwort ist nicht korrekt.',
    'date' => ':attribute muss ein gültiges Datum sein.',
    'date_equals' => ':attribute muss das Datum :date sein.',
    'date_format' => ':attribute muss dem Format :format entsprechen.',
    'decimal' => ':attribute muss :decimal Nachkommastellen haben.',
    'declined' => ':attribute muss abgelehnt werden.',
    'declined_if' => ':attribute muss abgelehnt werden, wenn :other :value ist.',
    'different' => ':attribute und :other müssen sich unterscheiden.',
    'digits' => ':attribute muss aus genau :digits Ziffern bestehen.',
    'digits_between' => ':attribute muss aus :min bis :max Ziffern bestehen.',
    'dimensions' => 'Die Bildgröße von :attribute ist ungültig.',
    'distinct' => ':attribute enthält einen doppelten Wert.',
    'doesnt_contain' => ':attribute darf nichts von Folgendem enthalten: :values.',
    'doesnt_end_with' => ':attribute darf nicht mit einem der folgenden Werte enden: :values.',
    'doesnt_start_with' => ':attribute darf nicht mit einem der folgenden Werte beginnen: :values.',
    'email' => ':attribute muss eine gültige E-Mail-Adresse sein.',
    'encoding' => ':attribute muss in :encoding kodiert sein.',
    'ends_with' => ':attribute muss mit einem der folgenden Werte enden: :values.',
    'enum' => 'Die Auswahl für :attribute ist ungültig.',
    'exists' => 'Die Auswahl für :attribute ist ungültig.',
    'extensions' => ':attribute muss eine dieser Dateiendungen haben: :values.',
    'file' => ':attribute muss eine Datei sein.',
    'filled' => ':attribute darf nicht leer sein.',
    'gt' => [
        'array' => ':attribute muss mehr als :value Einträge enthalten.',
        'file' => ':attribute muss größer als :value Kilobyte sein.',
        'numeric' => ':attribute muss größer als :value sein.',
        'string' => ':attribute muss länger als :value Zeichen sein.',
    ],
    'gte' => [
        'array' => ':attribute muss mindestens :value Einträge enthalten.',
        'file' => ':attribute muss mindestens :value Kilobyte groß sein.',
        'numeric' => ':attribute muss mindestens :value betragen.',
        'string' => ':attribute muss mindestens :value Zeichen lang sein.',
    ],
    'hex_color' => ':attribute muss ein gültiger Farbcode sein, zum Beispiel #1d4e89.',
    'image' => ':attribute muss ein Bild sein.',
    'in' => 'Die Auswahl für :attribute ist ungültig.',
    'in_array' => ':attribute muss in :other vorkommen.',
    'in_array_keys' => ':attribute muss mindestens einen dieser Schlüssel enthalten: :values.',
    'integer' => ':attribute muss eine ganze Zahl sein.',
    'ip' => ':attribute muss eine gültige IP-Adresse sein.',
    'ipv4' => ':attribute muss eine gültige IPv4-Adresse sein.',
    'ipv6' => ':attribute muss eine gültige IPv6-Adresse sein.',
    'json' => ':attribute muss ein gültiger JSON-Text sein.',
    'list' => ':attribute muss eine Liste sein.',
    'lowercase' => ':attribute muss in Kleinbuchstaben geschrieben sein.',
    'lt' => [
        'array' => ':attribute muss weniger als :value Einträge enthalten.',
        'file' => ':attribute muss kleiner als :value Kilobyte sein.',
        'numeric' => ':attribute muss kleiner als :value sein.',
        'string' => ':attribute muss kürzer als :value Zeichen sein.',
    ],
    'lte' => [
        'array' => ':attribute darf höchstens :value Einträge enthalten.',
        'file' => ':attribute darf höchstens :value Kilobyte groß sein.',
        'numeric' => ':attribute darf höchstens :value betragen.',
        'string' => ':attribute darf höchstens :value Zeichen lang sein.',
    ],
    'mac_address' => ':attribute muss eine gültige MAC-Adresse sein.',
    'max' => [
        'array' => ':attribute darf höchstens :max Einträge enthalten.',
        'file' => ':attribute darf höchstens :max Kilobyte groß sein.',
        'numeric' => ':attribute darf höchstens :max betragen.',
        'string' => ':attribute darf höchstens :max Zeichen lang sein.',
    ],
    'max_digits' => ':attribute darf höchstens :max Ziffern haben.',
    'mimes' => ':attribute muss eine Datei des Typs :values sein.',
    'mimetypes' => ':attribute muss eine Datei des Typs :values sein.',
    'min' => [
        'array' => ':attribute muss mindestens :min Einträge enthalten.',
        'file' => ':attribute muss mindestens :min Kilobyte groß sein.',
        'numeric' => ':attribute muss mindestens :min betragen.',
        'string' => ':attribute muss mindestens :min Zeichen lang sein.',
    ],
    'min_digits' => ':attribute muss mindestens :min Ziffern haben.',
    'missing' => ':attribute darf nicht angegeben werden.',
    'missing_if' => ':attribute darf nicht angegeben werden, wenn :other :value ist.',
    'missing_unless' => ':attribute darf nicht angegeben werden, außer :other ist :value.',
    'missing_with' => ':attribute darf nicht angegeben werden, wenn :values angegeben ist.',
    'missing_with_all' => ':attribute darf nicht angegeben werden, wenn :values angegeben sind.',
    'multiple_of' => ':attribute muss ein Vielfaches von :value sein.',
    'not_in' => 'Die Auswahl für :attribute ist ungültig.',
    'not_regex' => 'Das Format von :attribute ist ungültig.',
    'numeric' => ':attribute muss eine Zahl sein.',
    'password' => [
        'letters' => ':attribute muss mindestens einen Buchstaben enthalten.',
        'mixed' => ':attribute muss mindestens einen Groß- und einen Kleinbuchstaben enthalten.',
        'numbers' => ':attribute muss mindestens eine Ziffer enthalten.',
        'symbols' => ':attribute muss mindestens ein Sonderzeichen enthalten.',
        'uncompromised' => 'Dieses :attribute ist in einem Datenleck aufgetaucht. Bitte wählen Sie ein anderes.',
    ],
    'present' => ':attribute muss vorhanden sein.',
    'present_if' => ':attribute muss vorhanden sein, wenn :other :value ist.',
    'present_unless' => ':attribute muss vorhanden sein, außer :other ist :value.',
    'present_with' => ':attribute muss vorhanden sein, wenn :values angegeben ist.',
    'present_with_all' => ':attribute muss vorhanden sein, wenn :values angegeben sind.',
    'prohibited' => ':attribute darf nicht angegeben werden.',
    'prohibited_if' => ':attribute darf nicht angegeben werden, wenn :other :value ist.',
    'prohibited_if_accepted' => ':attribute darf nicht angegeben werden, wenn :other akzeptiert wurde.',
    'prohibited_if_declined' => ':attribute darf nicht angegeben werden, wenn :other abgelehnt wurde.',
    'prohibited_unless' => ':attribute darf nicht angegeben werden, außer :other ist in :values enthalten.',
    'prohibits' => ':attribute erlaubt nicht, dass :other angegeben wird.',
    'regex' => 'Das Format von :attribute ist ungültig.',
    'required' => ':attribute ist erforderlich.',
    'required_array_keys' => ':attribute muss Einträge für diese Schlüssel enthalten: :values.',
    'required_if' => ':attribute ist erforderlich, wenn :other :value ist.',
    'required_if_accepted' => ':attribute ist erforderlich, wenn :other akzeptiert wurde.',
    'required_if_declined' => ':attribute ist erforderlich, wenn :other abgelehnt wurde.',
    'required_unless' => ':attribute ist erforderlich, außer :other ist in :values enthalten.',
    'required_with' => ':attribute ist erforderlich, wenn :values angegeben ist.',
    'required_with_all' => ':attribute ist erforderlich, wenn :values angegeben sind.',
    'required_without' => ':attribute ist erforderlich, wenn :values nicht angegeben ist.',
    'required_without_all' => ':attribute ist erforderlich, wenn keiner der Werte :values angegeben ist.',
    'same' => ':attribute und :other müssen übereinstimmen.',
    'size' => [
        'array' => ':attribute muss genau :size Einträge enthalten.',
        'file' => ':attribute muss genau :size Kilobyte groß sein.',
        'numeric' => ':attribute muss genau :size sein.',
        'string' => ':attribute muss genau :size Zeichen lang sein.',
    ],
    'starts_with' => ':attribute muss mit einem der folgenden Werte beginnen: :values.',
    'string' => ':attribute muss ein Text sein.',
    'timezone' => ':attribute muss eine gültige Zeitzone sein.',
    'unique' => ':attribute ist bereits vergeben.',
    'uploaded' => 'Das Hochladen von :attribute ist fehlgeschlagen.',
    'uppercase' => ':attribute muss in Großbuchstaben geschrieben sein.',
    'url' => ':attribute muss eine gültige Internetadresse sein.',
    'ulid' => ':attribute muss eine gültige ULID sein.',
    'uuid' => ':attribute muss eine gültige UUID sein.',

    /*
    |--------------------------------------------------------------------------
    | Eigene Meldungen je Feld
    |--------------------------------------------------------------------------
    | Format "feld.regel". Nur dort, wo der Standardsatz zum Feld nicht gut passt.
    */

    'custom' => [
        'email' => [
            'email' => 'Das ist keine gültige E-Mail-Adresse.',
            'unique' => 'Diese E-Mail-Adresse wird bereits verwendet.',
        ],
        'password' => [
            'confirmed' => 'Die beiden Passwörter stimmen nicht überein.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Feldnamen, wie sie in den Meldungen erscheinen
    |--------------------------------------------------------------------------
    | Die Namen entsprechen den Beschriftungen in der Oberfläche.
    */

    'attributes' => [
        // Benutzer und Anmeldung
        'name' => 'Name',
        'email' => 'E-Mail-Adresse',
        'password' => 'Passwort',
        'password_confirmation' => 'Passwort-Wiederholung',
        'current_password' => 'Aktuelles Passwort',
        'role' => 'Rolle',
        'active' => 'Status',

        // Kunden und Ansprechpartner
        'type' => 'Art',
        'company_name' => 'Firmenname',
        'salutation' => 'Anrede',
        'first_name' => 'Vorname',
        'last_name' => 'Nachname',
        'position' => 'Position',
        'phone' => 'Telefon',
        'mobile' => 'Mobil',
        'website' => 'Webseite',
        'street' => 'Straße',
        'postal_code' => 'PLZ',
        'city' => 'Ort',
        'country' => 'Land',
        'vat_id' => 'USt-IdNr.',
        'payment_term_days' => 'Zahlungsziel',
        'notes' => 'Notizen',
        'label' => 'Bezeichnung',
        'is_primary' => 'Hauptansprechpartner',

        // Firmendaten und Erscheinungsbild
        'legal_form' => 'Rechtsform',
        'tax_number' => 'Steuernummer',
        'commercial_register' => 'Handelsregister',
        'managing_director' => 'Geschäftsführer',
        'bank_name' => 'Bank',
        'iban' => 'IBAN',
        'bic' => 'BIC',
        'letterhead_note' => 'Zusatz im Briefkopf',
        'footer_text' => 'Fußzeile',
        'brand_color' => 'Markenfarbe',
        'logo' => 'Logo',

        // Steuersätze und Nummernkreise
        'rate' => 'Steuersatz',
        'prefix' => 'Präfix',
        'include_year' => 'Jahr in der Nummer',
        'padding' => 'Stellen',
        'reset_yearly' => 'Jährlich neu beginnen',
        'next_number' => 'Nächste Nummer',

        // Suche und Listen
        'q' => 'Suchbegriff',
        'archived' => 'Archivierte',
        'page' => 'Seite',
    ],

];
