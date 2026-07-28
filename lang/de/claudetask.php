<?php

return [
    'title' => 'claudetask: einrichten',

    'intro_heading' => 'Was ist claudetask:?',
    'intro_1' => 'claudetask: ist ein Protokoll-Handler auf deinem Rechner. Klickst du im Board auf das Claude-Icon einer Kommando-Zeile, öffnet der Browser claudetask:<Prompt> — dein Rechner startet daraufhin eine Shell und ruft darin claude mit genau diesem Prompt auf.',
    'intro_2' => 'Ist kein Handler registriert, passiert beim Klick nichts. Planstack erkennt das an einem ausbleibenden Fokuswechsel, legt den Prompt in die Zwischenablage und verweist auf diese Seite. Die Erkennung ist eine Heuristik: startet die Shell sehr langsam oder im Hintergrund, kann der Hinweis auch fälschlich erscheinen.',
    'prerequisite' => 'Voraussetzung: Claude Code ist installiert und der Befehl claude liegt im PATH.',

    'windows_heading' => 'Windows (PowerShell, ohne Admin-Rechte)',
    'windows_step_1' => 'Handler-Skript anlegen — dieser Block schreibt %USERPROFILE%\planstack\claude-task.ps1:',
    'windows_step_2' => 'Protokoll registrieren (nur für den aktuellen Nutzer, deshalb kein Admin nötig):',
    'windows_step_3' => 'Testen: im Board auf das Claude-Icon klicken. Chrome fragt beim ersten Mal um Erlaubnis — dort „Immer erlauben" setzen. Alternativ die URI direkt in die Adressleiste tippen:',

    'auto_mode_heading' => 'Auto-Mode: PowerShell mit Claude ohne Rückfragen',
    'auto_mode_1' => 'Das Skript oben startet Claude interaktiv. Soll der Prompt unbeaufsichtigt durchlaufen, ersetze die Aufruf-Zeile durch --permission-mode auto:',
    'auto_mode_2' => 'Derselbe Aufruf direkt in einer PowerShell, ohne Handler:',
    'auto_mode_warn' => 'Im Auto-Mode arbeitet Claude Werkzeugaufrufe ohne Rückfrage ab. Nutze ihn nur für Prompts, denen du das zutraust.',

    'mac_heading' => 'macOS',
    'mac_step_1' => 'Handler-Skript anlegen (dekodiert den Prompt und öffnet ihn im Terminal, gleich im Auto-Mode):',
    'mac_step_2' => 'Automator öffnen → „Neues Dokument" → „Programm" → Aktion „AppleScript ausführen" mit diesem Inhalt, dann als /Applications/ClaudeTask.app speichern:',
    'mac_step_3' => 'Das Protokoll in der Info.plist der App eintragen und bei LaunchServices anmelden:',
    'mac_step_4' => 'Testen: im Board auf das Claude-Icon klicken. Beim ersten Mal fragt macOS, ob ClaudeTask geöffnet werden darf.',

    'troubleshoot_heading' => 'Wenn es nicht klappt',
    'troubleshoot_1' => 'Beim Klick passiert gar nichts und der Browser fragt nichts: der Handler ist nicht registriert — Schritt 2 (Windows) bzw. Schritt 3 (macOS) wiederholen.',
    'troubleshoot_2' => 'Ein Fenster öffnet sich und verschwindet sofort: claude liegt nicht im PATH der Shell. In der PowerShell prüfen mit (Get-Command claude).Source.',
    'troubleshoot_3' => 'Der Hinweis erscheint trotz funktionierendem Handler: die Erkennung wartet knapp eine Sekunde auf den Fokuswechsel. Der Prompt ist dann trotzdem gestartet — der Hinweis lässt sich ignorieren.',
    'troubleshoot_4' => 'Nach jedem erfolglosen Versuch liegt der Prompt in der Zwischenablage und kann direkt in eine laufende Claude-Sitzung eingefügt werden.',

    'copy' => 'Kopieren',
];
