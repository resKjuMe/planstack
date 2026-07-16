<?php

// Nutzer-Changelog der Website (verständlich, untechnisch). Neueste Version
// zuerst — der erste Eintrag bestimmt auch die Versionsnummer in der Hauptnavi.
// Bei jedem sichtbaren Website-Update hier oben einen neuen Block ergänzen.
return [
    'releases' => [
        [
            'version' => '1.1.0',
            'date' => '2026-07-17',
            'tldr' => ['Farbige Fortschrittsbalken', 'Hover-Details', 'Teams je Projekt', 'Changelog-Hinweis'],
            'changes' => [
                'Fortschrittsbalken zeigen jetzt farbige Segmente je Status (gemerged, in Review, in Arbeit …) — auf der Projekte-Übersicht und in der Phasen-Summary.',
                'Beim Überfahren eines Balken-Segments erscheinen Status, Anzahl Tasks und SP-Anteil in der passenden Farbe.',
                'Projektkacheln zeigen unter der Owner:in die zugeordneten Teams.',
                'Neue Updates werden in der Navigation mit einem Symbol angekündigt und beim Öffnen dieser Seite hervorgehoben.',
            ],
        ],
        [
            'version' => '1.0.0',
            'date' => '2026-07-16',
            // TL;DR: nur Stichwörter (werden fett dargestellt).
            'tldr' => ['CI-Status im Diagramm', 'Velocity', 'Teams umbenennen', 'Changelog'],
            'changes' => [
                'Neu: CI-/Merge-Status jedes Pull Requests direkt im Projekt-Diagramm — auf einen Blick sehen, was grün ist, was fehlschlägt und was bereit zum Mergen ist. Einrichtung über den Menüpunkt „TamperMonkey Script".',
                'Neu: Velocity in der Kalibrierung — wie viele Story Points pro Tag tatsächlich fertig werden (gemessen vom ersten Claim bis zum letzten Merge).',
                'Neu: Teams lassen sich jetzt umbenennen.',
                'Neu: Diese Änderungsübersicht in der oberen Navigation.',
            ],
        ],
    ],
];
