<?php

return [
    'nav' => 'Benutzerdefinierte Felder',
    'title' => 'Benutzerdefinierte Felder',
    'intro' => 'Definiere eigene Felder für Tasks. Jedes Feld hat einen stabilen Schlüssel (API-Feldname), Bezeichnungen (DE/EN), einen Datentyp und eine optionale Laravel-Validierungsregel. Die Werte werden per API an Tasks befüllt (Feld :field im Objekt custom_fields).',
    'field_placeholder' => 'custom_fields',

    'col_key' => 'Schlüssel',
    'col_label' => 'Bezeichnung (DE)',
    'col_label_en' => 'Bezeichnung (EN)',
    'col_type' => 'Typ',
    'col_validation' => 'Validierung (Laravel)',
    'col_actions' => '',

    'validation_placeholder' => 'z. B. required|max:100',
    'save' => 'Speichern',
    'saved_all' => 'Felder gespeichert.',
    'add_field' => 'Feld anlegen',
    'created' => 'Feld „:label" angelegt.',
    'delete' => 'Löschen',
    'delete_confirm' => 'Dieses Feld wirklich löschen? Bereits gesetzte Werte bleiben an den Tasks bestehen.',
    'deleted' => 'Feld gelöscht.',
    'no_fields' => 'Noch keine benutzerdefinierten Felder.',

    // Typ-Bezeichnungen
    'type_string' => 'Text (kurz)',
    'type_text' => 'Text (lang)',
    'type_integer' => 'Ganzzahl',
    'type_decimal' => 'Dezimalzahl',
    'type_boolean' => 'Ja/Nein',
    'type_date' => 'Datum',
    'type_datetime' => 'Datum + Zeit',
    'type_url' => 'URL',
    'type_email' => 'E-Mail',
];
