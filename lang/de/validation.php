<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validierungs-Sprachzeilen
    |--------------------------------------------------------------------------
    |
    | Die folgenden Sprachzeilen enthalten die Standard-Fehlermeldungen der
    | Validator-Klasse. Einige Regeln haben mehrere Varianten, wie zum
    | Beispiel die Size-Regeln. Passe diese Meldungen hier gerne an.
    |
    */

    'accepted' => ':attribute muss akzeptiert werden.',
    'accepted_if' => ':attribute muss akzeptiert werden, wenn :other den Wert :value hat.',
    'active_url' => ':attribute muss eine gültige URL sein.',
    'after' => ':attribute muss ein Datum nach dem :date sein.',
    'after_or_equal' => ':attribute muss ein Datum nach oder gleich dem :date sein.',
    'alpha' => ':attribute darf nur Buchstaben enthalten.',
    'alpha_dash' => ':attribute darf nur Buchstaben, Zahlen, Binde- und Unterstriche enthalten.',
    'alpha_num' => ':attribute darf nur Buchstaben und Zahlen enthalten.',
    'any_of' => ':attribute ist ungültig.',
    'array' => ':attribute muss ein Array sein.',
    'ascii' => ':attribute darf nur alphanumerische Zeichen und Symbole im Ein-Byte-Format enthalten.',
    'before' => ':attribute muss ein Datum vor dem :date sein.',
    'before_or_equal' => ':attribute muss ein Datum vor oder gleich dem :date sein.',
    'between' => [
        'array' => ':attribute muss zwischen :min und :max Elemente enthalten.',
        'file' => ':attribute muss zwischen :min und :max Kilobyte groß sein.',
        'numeric' => ':attribute muss zwischen :min und :max liegen.',
        'string' => ':attribute muss zwischen :min und :max Zeichen lang sein.',
    ],
    'boolean' => ':attribute muss wahr oder falsch sein.',
    'can' => ':attribute enthält einen unzulässigen Wert.',
    'confirmed' => 'Die :attribute-Bestätigung stimmt nicht überein.',
    'contains' => ':attribute fehlt ein erforderlicher Wert.',
    'current_password' => 'Das Passwort ist falsch.',
    'date' => ':attribute muss ein gültiges Datum sein.',
    'date_equals' => ':attribute muss ein Datum gleich dem :date sein.',
    'date_format' => ':attribute muss dem Format :format entsprechen.',
    'decimal' => ':attribute muss :decimal Nachkommastellen haben.',
    'declined' => ':attribute muss abgelehnt werden.',
    'declined_if' => ':attribute muss abgelehnt werden, wenn :other den Wert :value hat.',
    'different' => ':attribute und :other müssen sich unterscheiden.',
    'digits' => ':attribute muss :digits Ziffern lang sein.',
    'digits_between' => ':attribute muss zwischen :min und :max Ziffern lang sein.',
    'dimensions' => ':attribute hat ungültige Bildabmessungen.',
    'distinct' => ':attribute enthält einen doppelten Wert.',
    'doesnt_contain' => ':attribute darf keinen der folgenden Werte enthalten: :values.',
    'doesnt_end_with' => ':attribute darf nicht mit einem der folgenden Werte enden: :values.',
    'doesnt_start_with' => ':attribute darf nicht mit einem der folgenden Werte beginnen: :values.',
    'email' => ':attribute muss eine gültige E-Mail-Adresse sein.',
    'encoding' => ':attribute muss in :encoding kodiert sein.',
    'ends_with' => ':attribute muss mit einem der folgenden Werte enden: :values.',
    'enum' => 'Der gewählte Wert für :attribute ist ungültig.',
    'exists' => 'Der gewählte Wert für :attribute ist ungültig.',
    'extensions' => ':attribute muss eine der folgenden Dateiendungen haben: :values.',
    'file' => ':attribute muss eine Datei sein.',
    'filled' => ':attribute muss einen Wert enthalten.',
    'gt' => [
        'array' => ':attribute muss mehr als :value Elemente enthalten.',
        'file' => ':attribute muss größer als :value Kilobyte sein.',
        'numeric' => ':attribute muss größer als :value sein.',
        'string' => ':attribute muss länger als :value Zeichen sein.',
    ],
    'gte' => [
        'array' => ':attribute muss :value oder mehr Elemente enthalten.',
        'file' => ':attribute muss größer oder gleich :value Kilobyte sein.',
        'numeric' => ':attribute muss größer oder gleich :value sein.',
        'string' => ':attribute muss mindestens :value Zeichen lang sein.',
    ],
    'hex_color' => ':attribute muss eine gültige Hexadezimalfarbe sein.',
    'image' => ':attribute muss ein Bild sein.',
    'in' => 'Der gewählte Wert für :attribute ist ungültig.',
    'in_array' => ':attribute muss in :other vorhanden sein.',
    'in_array_keys' => ':attribute muss mindestens einen der folgenden Schlüssel enthalten: :values.',
    'integer' => ':attribute muss eine ganze Zahl sein.',
    'ip' => ':attribute muss eine gültige IP-Adresse sein.',
    'ipv4' => ':attribute muss eine gültige IPv4-Adresse sein.',
    'ipv6' => ':attribute muss eine gültige IPv6-Adresse sein.',
    'json' => ':attribute muss ein gültiger JSON-String sein.',
    'list' => ':attribute muss eine Liste sein.',
    'lowercase' => ':attribute muss aus Kleinbuchstaben bestehen.',
    'lt' => [
        'array' => ':attribute muss weniger als :value Elemente enthalten.',
        'file' => ':attribute muss kleiner als :value Kilobyte sein.',
        'numeric' => ':attribute muss kleiner als :value sein.',
        'string' => ':attribute muss kürzer als :value Zeichen sein.',
    ],
    'lte' => [
        'array' => ':attribute darf nicht mehr als :value Elemente enthalten.',
        'file' => ':attribute muss kleiner oder gleich :value Kilobyte sein.',
        'numeric' => ':attribute muss kleiner oder gleich :value sein.',
        'string' => ':attribute darf höchstens :value Zeichen lang sein.',
    ],
    'mac_address' => ':attribute muss eine gültige MAC-Adresse sein.',
    'max' => [
        'array' => ':attribute darf nicht mehr als :max Elemente enthalten.',
        'file' => ':attribute darf nicht größer als :max Kilobyte sein.',
        'numeric' => ':attribute darf nicht größer als :max sein.',
        'string' => ':attribute darf nicht länger als :max Zeichen sein.',
    ],
    'max_digits' => ':attribute darf nicht mehr als :max Ziffern enthalten.',
    'mimes' => ':attribute muss eine Datei des folgenden Typs sein: :values.',
    'mimetypes' => ':attribute muss eine Datei des folgenden Typs sein: :values.',
    'min' => [
        'array' => ':attribute muss mindestens :min Elemente enthalten.',
        'file' => ':attribute muss mindestens :min Kilobyte groß sein.',
        'numeric' => ':attribute muss mindestens :min sein.',
        'string' => ':attribute muss mindestens :min Zeichen lang sein.',
    ],
    'min_digits' => ':attribute muss mindestens :min Ziffern enthalten.',
    'missing' => ':attribute darf nicht vorhanden sein.',
    'missing_if' => ':attribute darf nicht vorhanden sein, wenn :other den Wert :value hat.',
    'missing_unless' => ':attribute darf nicht vorhanden sein, außer :other hat den Wert :value.',
    'missing_with' => ':attribute darf nicht vorhanden sein, wenn :values vorhanden ist.',
    'missing_with_all' => ':attribute darf nicht vorhanden sein, wenn :values vorhanden sind.',
    'multiple_of' => ':attribute muss ein Vielfaches von :value sein.',
    'not_in' => 'Der gewählte Wert für :attribute ist ungültig.',
    'not_regex' => 'Das Format von :attribute ist ungültig.',
    'numeric' => ':attribute muss eine Zahl sein.',
    'password' => [
        'letters' => ':attribute muss mindestens einen Buchstaben enthalten.',
        'mixed' => ':attribute muss mindestens einen Groß- und einen Kleinbuchstaben enthalten.',
        'numbers' => ':attribute muss mindestens eine Zahl enthalten.',
        'symbols' => ':attribute muss mindestens ein Sonderzeichen enthalten.',
        'uncompromised' => ':attribute ist in einem Datenleck aufgetaucht. Bitte wähle einen anderen Wert für :attribute.',
    ],
    'present' => ':attribute muss vorhanden sein.',
    'present_if' => ':attribute muss vorhanden sein, wenn :other den Wert :value hat.',
    'present_unless' => ':attribute muss vorhanden sein, außer :other hat den Wert :value.',
    'present_with' => ':attribute muss vorhanden sein, wenn :values vorhanden ist.',
    'present_with_all' => ':attribute muss vorhanden sein, wenn :values vorhanden sind.',
    'prohibited' => ':attribute ist unzulässig.',
    'prohibited_if' => ':attribute ist unzulässig, wenn :other den Wert :value hat.',
    'prohibited_if_accepted' => ':attribute ist unzulässig, wenn :other akzeptiert wurde.',
    'prohibited_if_declined' => ':attribute ist unzulässig, wenn :other abgelehnt wurde.',
    'prohibited_unless' => ':attribute ist unzulässig, außer :other ist in :values enthalten.',
    'prohibits' => ':attribute verhindert, dass :other vorhanden sein darf.',
    'regex' => 'Das Format von :attribute ist ungültig.',
    'required' => ':attribute muss ausgefüllt werden.',
    'required_array_keys' => ':attribute muss Einträge für Folgendes enthalten: :values.',
    'required_if' => ':attribute muss ausgefüllt werden, wenn :other den Wert :value hat.',
    'required_if_accepted' => ':attribute muss ausgefüllt werden, wenn :other akzeptiert wurde.',
    'required_if_declined' => ':attribute muss ausgefüllt werden, wenn :other abgelehnt wurde.',
    'required_unless' => ':attribute muss ausgefüllt werden, außer :other ist in :values enthalten.',
    'required_with' => ':attribute muss ausgefüllt werden, wenn :values vorhanden ist.',
    'required_with_all' => ':attribute muss ausgefüllt werden, wenn :values vorhanden sind.',
    'required_without' => ':attribute muss ausgefüllt werden, wenn :values nicht vorhanden ist.',
    'required_without_all' => ':attribute muss ausgefüllt werden, wenn keiner der Werte :values vorhanden ist.',
    'same' => ':attribute muss mit :other übereinstimmen.',
    'size' => [
        'array' => ':attribute muss genau :size Elemente enthalten.',
        'file' => ':attribute muss :size Kilobyte groß sein.',
        'numeric' => ':attribute muss genau :size sein.',
        'string' => ':attribute muss genau :size Zeichen lang sein.',
    ],
    'starts_with' => ':attribute muss mit einem der folgenden Werte beginnen: :values.',
    'string' => ':attribute muss eine Zeichenkette sein.',
    'timezone' => ':attribute muss eine gültige Zeitzone sein.',
    'unique' => ':attribute ist bereits vergeben.',
    'uploaded' => ':attribute konnte nicht hochgeladen werden.',
    'uppercase' => ':attribute muss aus Großbuchstaben bestehen.',
    'url' => ':attribute muss eine gültige URL sein.',
    'ulid' => ':attribute muss eine gültige ULID sein.',
    'uuid' => ':attribute muss eine gültige UUID sein.',

    /*
    |--------------------------------------------------------------------------
    | Benutzerdefinierte Validierungs-Sprachzeilen
    |--------------------------------------------------------------------------
    |
    | Hier kannst du benutzerdefinierte Validierungsmeldungen für Attribute
    | festlegen. Verwende dazu die Konvention "attribute.rule", um die Zeilen
    | zu benennen. So lässt sich schnell eine bestimmte Meldung für eine
    | konkrete Attribut-Regel angeben.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Benutzerdefinierte Validierungs-Attribute
    |--------------------------------------------------------------------------
    |
    | Die folgenden Sprachzeilen ersetzen den Attribut-Platzhalter durch etwas
    | Lesefreundlicheres, zum Beispiel "E-Mail-Adresse" statt "email". Das
    | hilft dabei, unsere Meldungen ausdrucksstärker zu gestalten.
    |
    */

    'attributes' => [],

];
