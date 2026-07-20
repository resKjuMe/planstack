<?php

// Nutzer-Changelog der Website (verständlich, untechnisch). Neueste Version
// zuerst — der erste Eintrag bestimmt auch die Versionsnummer in der Hauptnavi.
// Bei jedem sichtbaren Website-Update hier oben einen neuen Block ergänzen.
return [
    'releases' => [
        [
            'version' => '1.9.0',
            'date' => '2026-07-20',
            'tldr' => ['Organisationen', 'Phasen-Verwaltung', 'Projekte archivieren', 'Projekte abschließen', 'Fortschritt zählt nur Erledigtes'],
            'changes' => [
                'Neu: Organisationen (Menüpunkt „Organisation" oben rechts). Jeder kann eine Organisation gründen oder einer bestehenden per Einladungscode beitreten; jeder gehört höchstens einer Organisation an. Die Seite zeigt Mitglieder, den Einladungscode zum Weitergeben sowie das Austreten bzw. (für Gründer) das Löschen. Der Gründer kann zudem Einladungslinks per E-Mail versenden — wer sich darüber registriert, wird automatisch der Organisation zugeordnet.',
                'Neu: Vollständige Phasen-Verwaltung je Projekt (Tab „Phasen" in der Projektbearbeitung) — Phasen anlegen, umbenennen, per Pfeilen umsortieren und löschen. Beim Löschen bleiben die enthaltenen Tasks erhalten und verlieren nur die Phasenzuordnung.',
                'Neu: Projekte lassen sich als „abgeschlossen" markieren (Schalter auf der Bearbeiten-Seite). Abgeschlossene Projekte tragen in der Übersicht das Badge „Abgeschlossen" und sind über die gleichnamige Filter-Pill filterbar; sie bleiben normal in der Liste.',
                'Neu: Projekte lassen sich archivieren (Schalter auf der Bearbeiten-Seite). Archivierte Projekte verschwinden aus der Projektliste und sind nur noch über die neue Filter-Pill „Archiviert" sichtbar; die Kennzahlen oben zählen nur aktive Projekte.',
                'Der Fortschritt zählt jetzt einheitlich nur noch erledigte bzw. gemergte Tasks — in der Projektübersicht, in der Summary (KPI-Kacheln und Phasen-Balken) und im Phasenfilter des Diagramms. Ein bloß offener Pull Request gilt nicht mehr als Fortschritt.',
            ],
        ],
        [
            'version' => '1.8.0',
            'date' => '2026-07-17',
            'tldr' => ['Neue Task-Detailseite', 'Abhakbare Checklisten', '„/planstack plan"', 'IST/SOLL & Testanleitung'],
            'changes' => [
                'Überarbeitete Task-Detailseite: aufgeräumter Kopf mit Status- und Review-Kennzeichnung nebeneinander, zweispaltiges Layout mit mitlaufender Seitenleiste, ein offener Concern erscheint als auffälliger Warnhinweis direkt oben, dazu ein Verlauf (Timeline) und eine übersichtliche Review-Zusammenfassung mit Kurzfazit.',
                'Neu: Abhakbare Checklisten für Akzeptanzkriterien und Testschritte — mit Fortschrittsanzeige (x/n) und sofortigem Speichern beim Anhaken. Bestehende Freitext-Kriterien lassen sich per Klick in eine Checkliste umwandeln.',
                'Neu: „/planstack plan" — legt Projekte, Phasen und Tasks an. Für die Textfelder gibt es jetzt klare Formatvorgaben, damit Inhalte einheitlich und ansprechend dargestellt werden.',
                'Neue Task-Felder: „IST/SOLL-Vergleich" (Verhalten vorher/nachher, als Gegenüberstellung dargestellt) und „Testanleitung" (nummerierte, abhakbare Prüfschritte).',
                'Neues Task-Feld „Kritikalität" (unkritisch / mittel / hoch / kritisch) — als farbiges Badge in der Detailansicht, im Formular wählbar und über die API setzbar.',
                'Der „Ausgabe-Umfang" (knapp / standard / ausführlich) wird jetzt konsequent angewendet — „knapp" hält Claude während der Abarbeitung spürbar wortkarger.',
                'Pull-Request-Titel tragen jetzt Projekt- und Task-Kürzel (z. B. „L2L-G5: …").',
                '„Freigeben" ist bei einem offenen Concern gesperrt (mit Hinweis), damit ein problematischer Task nicht versehentlich freigegeben wird.',
                'Der Task-Update-Endpunkt der API ist jetzt ein Teil-Update: Es werden nur die tatsächlich mitgeschickten Felder geändert.',
            ],
        ],
        [
            'version' => '1.7.0',
            'date' => '2026-07-17',
            'tldr' => ['PR-Review per Skill', 'PR reparieren', 'Review-Ergebnis am Task & im Diagramm', 'Mehr Skill-Einstellungen'],
            'changes' => [
                'Neu: „/planstack review" — übernimmt Tasks, die „in Review" sind (mit PR), führt den Review-Skill aus und erfasst das Ergebnis. Ohne Task-Angabe wird automatisch ein Task des Projekts gewählt, ohne Projekt wird projektübergreifend gereviewt. Eigene Tasks (selbst umgesetzt) können nicht reviewt werden.',
                'Neu: „/planstack fix <Task/PR>" — bringt einen offenen PR wieder in Ordnung: löst Merge-Konflikte mit dem Ziel-Branch, beantwortet und behebt sowohl normale Kommentare als auch Review-Kommentare (Review-Threads werden zusätzlich aufgelöst) und korrigiert fehlschlagende CI. Der Task bzw. die PR-Nummer ist dabei anzugeben.',
                'Tasks haben jetzt ein Review-Ergebnis: „zuletzt reviewt am", „Empfehlung" (genehmigt / Änderungen erforderlich) und eine ausführliche „Review-Analyse" (mit vorangestelltem TLDR und einem Kopf, der Strenge, Gründlichkeit sowie das verwendete Modell/den Aufwand dokumentiert). Die Felder werden beim Abschluss eines Reviews befüllt und sind im Task-Formular bearbeitbar, solange der Task „in Review" ist.',
                'Das Review-Ergebnis wird jetzt auch angezeigt: in der Task-Detailansicht sowie als Eck-Symbol im Diagramm (grünes Häkchen = genehmigt, Warndreieck = Änderungen erforderlich).',
                'Erweiterte Skill-Einstellungen unter „/planstack settings", jetzt als editierbare Tabelle mit verständlichen deutschen Bezeichnern: Review-Ergebnis (nur im Task / im Task und am PR), Review-Status (manuell bestätigen / automatisch), Review-Strenge (locker / standard / streng), Review-Genauigkeit (lässig / standard / akribisch) und Ausgabe-Umfang (knapp / standard / ausführlich).',
                'Beim Anlegen eines Tasks sollte die geschätzte Dateianzahl immer angegeben werden (Hinweis/Konvention, keine Pflichtvalidierung).',
                'Fehlerbehebung: Der Statuswechsel eines Tasks über die API meldete fälschlich einen Serverfehler (500), obwohl der Wechsel ausgeführt wurde — behoben (betraf ebenso das Melden und Auflösen von Concerns).',
            ],
        ],
        [
            'version' => '1.6.0',
            'date' => '2026-07-17',
            'tldr' => ['Skill-Einstellungen', 'Konfiguration ziehen', 'Neue Kommandos ohne Neu-Download'],
            'changes' => [
                'Neu: „/planstack settings" — lokale Einstellungen für Tests, PHPStan, PHPCS und das Betreuen von PRs, je wählbar als „ja", „nein" oder „bei jeder Aufgabe fragen". Diese Einstellungen werden ausschließlich lokal gespeichert und nie an den Server übertragen.',
                'Neu: „/planstack update-config" — zieht die neueste allgemeine und (optional) Projekt-Konfiguration und zeigt die jeweiligen Versionsnummern an.',
                'Der Skill holt sich neu hinzugekommene Kommandos künftig selbst (Selbstheilung): Ist ein aufgerufenes Kommando noch unbekannt, lädt er die aktuelle Kommandoliste vom Server — neue Features stehen so ohne erneuten Download bereit (einmalige Aktualisierung des Skills vorausgesetzt).',
            ],
        ],
        [
            'version' => '1.5.1',
            'date' => '2026-07-17',
            'tldr' => ['Weniger Anfragen beim Abarbeiten', 'Task per Kürzel ansprechbar'],
            'changes' => [
                'Der Skill übernimmt den nächsten passenden Task jetzt in einem einzigen Schritt (Auswählen und Beanspruchen zusammengefasst) — das spart Anfragen und Tokens beim Abarbeiten eines Boards und vermeidet Doppel-Beanspruchungen bei mehreren Workern.',
                'Ein einzelner Task lässt sich direkt über sein Kürzel ansprechen (z. B. bei „/planstack <PROJEKT> <TASK>") — ohne vorherige Suche nach der internen ID.',
            ],
        ],
        [
            'version' => '1.5.0',
            'date' => '2026-07-17',
            'tldr' => ['Planstack-Skill in der Hauptnavi', 'Ein Skill für alle Projekte', 'PR-Titel mit Task-Kürzel'],
            'changes' => [
                'Neu: Eigener Menüpunkt „Planstack-Skill" in der Hauptnavigation mit einer Info-Seite (Erklärung, Installation, Benutzung) und dem Skill-Download — nicht mehr pro Projekt.',
                'Ein einziger Skill bedient alle Projekte: Das Projekt wird beim Aufruf angegeben — „/planstack <PROJEKT>" arbeitet das ganze Board ab, „/planstack <PROJEKT> <TASK>" gezielt einen einzelnen Task.',
                'Die mitgelieferte Konfiguration enthält kein festes Projekt mehr (es ist dynamisch); der eingebettete Zugang gilt für alle Projekte, auf die du Zugriff hast.',
                'Pull Requests bekommen jetzt einheitlich das Task-Kürzel als Titel-Prefix (z. B. „C27: …").',
                'Die allgemeinen Skill-Anweisungen (z. B. die PR-Titel-Konvention) werden serverseitig gepflegt und vom Skill bei Änderungen automatisch übernommen — ohne erneuten Download.',
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
