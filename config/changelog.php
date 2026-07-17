<?php

// Nutzer-Changelog der Website (verständlich, untechnisch). Neueste Version
// zuerst — der erste Eintrag bestimmt auch die Versionsnummer in der Hauptnavi.
// Bei jedem sichtbaren Website-Update hier oben einen neuen Block ergänzen.
return [
    'releases' => [
        [
            'version' => '1.5.0',
            'date' => '2026-07-17',
            'tldr' => ['Planstack-Skill in der Hauptnavi', 'Ein Skill für alle Projekte', 'PR-Titel mit Task-Kürzel'],
            'changes' => [
                'Neu: Der Planstack-Skill für Claude Code wird jetzt zentral über die Hauptnavigation heruntergeladen („Planstack-Skill") — nicht mehr pro Projekt.',
                'Ein einziger Skill bedient alle Projekte: Das Projekt wird beim Aufruf angegeben — „/planstack <PROJEKT>" arbeitet das ganze Board ab, „/planstack <PROJEKT> <TASK>" gezielt einen einzelnen Task.',
                'Die mitgelieferte Konfiguration enthält kein festes Projekt mehr (es ist dynamisch); der eingebettete Zugang gilt für alle Projekte, auf die du Zugriff hast.',
                'Pull Requests bekommen jetzt einheitlich das Task-Kürzel als Titel-Prefix (z. B. „C27: …").',
            ],
        ],
        [
            'version' => '1.4.0',
            'date' => '2026-07-17',
            'tldr' => ['Detaillierte Claude-Einstellungen', 'Umgang mit Concerns', 'Zugriff beim Bearbeiten', 'Selbst-aktualisierender Skill'],
            'changes' => [
                'Neu: Detaillierte Einstellungsmöglichkeiten für Claude — unter „Projekt bearbeiten → Claude" lässt sich pro Projekt genau steuern, wie sparsam und wie Claude das Board abarbeitet (Presets von „economy" bis „rich", einzeln überschreibbar, mit Erklärung und Token-Schätzung je Option).',
                'Neu: Einstellung „Umgang mit Concerns" (kritisch / ausgewogen / mutig) auf der Claude-Seite — sie steuert, wie bereitwillig der Skill einen Concern meldet, statt mit eigenen Annahmen weiterzuarbeiten.',
                'Der Skilltext wird jetzt auf der „Claude"-Unterseite gepflegt (statt auf der allgemeinen Bearbeiten-Seite).',
                '„Zugriff" ist jetzt Teil der Projekt-Bearbeitung — als Reiter neben „Allgemein" und „Claude".',
                'Der heruntergeladene Skill enthält jetzt kompakt die aktuelle Konfiguration, das Betriebshandbuch und die verbindlichen Statusregeln — und aktualisiert sich bei Änderungen selbst, ohne erneuten Download (ein einmaliges Neu-Laden vorausgesetzt).',
                'Die Skilldatei wurde deutlich verschlankt (spürbar weniger Tokens) bei gleichem Funktionsumfang.',
            ],
        ],
        [
            'version' => '1.3.0',
            'date' => '2026-07-17',
            'tldr' => ['Claude-Konfiguration', 'Token sparen', 'Presets', 'Live-Schätzung'],
            'changes' => [
                'Neu: Unterseite „Claude" beim Projekt-Bearbeiten — hier stellst du ein, wie sparsam Claude das Board abarbeitet (weniger Tokens pro Aufgabe): von „economy" (maximal sparsam) über den empfohlenen Standard bis „rich" (ausführlich, wie bisher).',
                'Vier Presets inkl. dem neuen Standard „Claude-recommended"; einzelne Optionen lassen sich als Pills gezielt überschreiben, Max. Worker per Schieberegler.',
                'Zu jeder Option und jedem Preset gibt es eine ausführliche Erklärung mit Pro und Contra sowie eine Kennzeichnung der Token-Last (grün/gelb/rot).',
                'Eine Live-Anzeige schätzt den Tokenverbrauch im Vergleich zur sparsamsten Einstellung, während du die Optionen änderst.',
                'Nach einmaligem Neu-Download des Skills werden künftige Konfigurationsänderungen automatisch übernommen (der Skill erkennt sie an einer Versionskennung in jeder Board-Antwort). Manche Einstellungen wirken sogar ohne Neu-Download, weil der Server die Antworten direkt entsprechend liefert.',
            ],
        ],
        [
            'version' => '1.2.0',
            'date' => '2026-07-17',
            'tldr' => ['Zugriff-Verwaltung', 'Kalibrierung überarbeitet', 'Hilfe auf jeder Seite', 'Einheitliches Design'],
            'changes' => [
                'Neu: Eigener Menüpunkt „Zugriff" (ganz rechts) — hier verwaltest du zugewiesene Teams und Rollen (Mitarbeiter, Architekt, Administrator) übersichtlich an einer Stelle, mit Erklärungen zu jedem Begriff.',
                'Die Kalibrierung wurde überarbeitet: klarere Kennzahlen-Kacheln, ein Diagramm „geschätzt vs. tatsächlich", Treffsicherheit nach Story Points sowie Filter und Sortierung der Tasks.',
                'Jede Projekt-Unterseite hat jetzt eine eigene Überschrift und einen „?"-Button mit einer kurzen Erklärung aller Begriffe und Kennzahlen der Seite.',
                'Einheitlicheres Aussehen über alle Seiten hinweg — z. B. die PR-Sequenz mit Kennzahlen als Kacheln, aufgeräumten Filtern und der Liste als Karte.',
            ],
        ],
        [
            'version' => '1.1.0',
            'date' => '2026-07-17',
            'tldr' => ['Farbige Fortschrittsbalken', 'Hover-Details', 'Teams je Projekt', 'Changelog-Hinweis'],
            'changes' => [
                'Fortschrittsbalken zeigen jetzt farbige Segmente je Status (gemerged, in Review, in Arbeit …) — auf der Projekte-Übersicht und in der Phasen-Summary.',
                'Beim Überfahren eines Balken-Segments erscheinen Status, Anzahl Tasks und SP-Anteil in der passenden Farbe.',
                'Projektkacheln zeigen unter dem Projektgründer die zugeordneten Teams.',
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
