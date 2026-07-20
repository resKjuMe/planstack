<?php

// Nutzer-Changelog der Website (verständlich, untechnisch). Neueste Version
// zuerst — der erste Eintrag bestimmt auch die Versionsnummer in der Hauptnavi.
// Bei jedem sichtbaren Website-Update hier oben einen neuen Block ergänzen.
return [
    'releases' => [
        [
            'version' => '2.0.0',
            'date' => '2026-07-20',
            'tldr' => [
                'de' => ['Englische Oberfläche', 'Organisationen'],
                'en' => ['English UI', 'Organizations'],
            ],
            'changes' => [
                'de' => [
                    'Neu: Englische Oberfläche — die Sprache lässt sich im Profil auswählen (Standard Deutsch, optional Englisch (US)) und wird pro Nutzer gespeichert; die gesamte Oberfläche inkl. Meldungen und Changelog ist übersetzt.',
                    'Neu: Organisationen (Menüpunkt „Organisation" oben rechts). Jeder kann eine Organisation gründen; jeder gehört höchstens einer Organisation an. Der Gründer lädt Mitglieder per E-Mail ein (mit optionaler Team-Zuordnung): Existiert bereits ein Konto mit der Adresse, wird es direkt der Organisation und den gewählten Teams zugeordnet — sonst geht ein persönlicher, einmaliger Registrierungslink raus, über den die Zuordnung nach der Registrierung automatisch erfolgt. Bereits registrierte Personen können alternativ selbst den Code aus der E-Mail auf der Organisationsseite eingeben. Ohne Organisation ist die App gesperrt: erreichbar sind dann nur Profil, Organisation und Logout.',
                    'Projekte und Teams sind jetzt organisationsgebunden: Sie gehören der Organisation ihres Erstellers, und man sieht ausschließlich Projekte/Teams der eigenen Organisation. Der Organisationsgründer sieht alle Projekte seiner Organisation und hat volle Rechte darauf – ebenso auf alle Teams der Organisation. Team-Mitglieder werden aus einer Liste der Organisations-User ausgewählt (statt E-Mail-Eingabe).',
                    'Audit-Log-Einträge tragen jetzt die Organisation des handelnden Users (in den Metadaten) – vorbereitet für spätere organisationsbezogene Auswertungen.',
                ],
                'en' => [
                    'New: English interface — the language can be selected in your profile (default German, optionally English (US)) and is stored per user; the entire UI including messages and this changelog is translated.',
                    'New: Organizations (the "Organization" menu item at the top right). Anyone can found an organization; each person belongs to at most one organization. The founder invites members by email (with an optional team assignment): if an account with that address already exists, it is added directly to the organization and the selected teams — otherwise a personal, one-time registration link is sent, through which the assignment happens automatically after registration. People who are already registered can alternatively enter the code from the email themselves on the organization page. Without an organization the app is locked: only Profile, Organization and Logout remain accessible.',
                    'Projects and teams are now tied to an organization: they belong to their creator\'s organization, and you only see projects/teams of your own organization. The organization founder sees all projects of their organization and has full rights to them — as well as to all teams of the organization. Team members are picked from a list of the organization\'s users (instead of entering an email address).',
                    'Audit log entries now carry the organization of the acting user (in the metadata) — laying the groundwork for later organization-based analytics.',
                ],
            ],
        ],
        [
            'version' => '1.9.0',
            'date' => '2026-07-20',
            'tldr' => [
                'de' => ['Phasen-Verwaltung', 'Projekte archivieren', 'Projekte abschließen', 'Fortschritt zählt nur Erledigtes'],
                'en' => ['Phase management', 'Archive projects', 'Complete projects', 'Progress counts only what is done'],
            ],
            'changes' => [
                'de' => [
                    'Neu: Vollständige Phasen-Verwaltung je Projekt (Tab „Phasen" in der Projektbearbeitung) — Phasen anlegen, umbenennen, per Pfeilen umsortieren und löschen. Beim Löschen bleiben die enthaltenen Tasks erhalten und verlieren nur die Phasenzuordnung.',
                    'Neu: Projekte lassen sich als „abgeschlossen" markieren (Schalter auf der Bearbeiten-Seite). Abgeschlossene Projekte tragen in der Übersicht das Badge „Abgeschlossen" und sind über die gleichnamige Filter-Pill filterbar; sie bleiben normal in der Liste.',
                    'Neu: Projekte lassen sich archivieren (Schalter auf der Bearbeiten-Seite). Archivierte Projekte verschwinden aus der Projektliste und sind nur noch über die neue Filter-Pill „Archiviert" sichtbar; die Kennzahlen oben zählen nur aktive Projekte.',
                    'Der Fortschritt zählt jetzt einheitlich nur noch erledigte bzw. gemergte Tasks — in der Projektübersicht, in der Summary (KPI-Kacheln und Phasen-Balken) und im Phasenfilter des Diagramms. Ein bloß offener Pull Request gilt nicht mehr als Fortschritt.',
                ],
                'en' => [
                    'New: Full phase management per project (the "Phases" tab in the project editor) — create, rename, reorder (via arrows) and delete phases. When a phase is deleted, its tasks are kept and only lose their phase assignment.',
                    'New: Projects can be marked as "completed" (a toggle on the edit page). Completed projects show a "Completed" badge in the overview and can be filtered via the filter pill of the same name; they stay in the list as usual.',
                    'New: Projects can be archived (a toggle on the edit page). Archived projects disappear from the project list and are only visible via the new "Archived" filter pill; the metrics at the top count only active projects.',
                    'Progress now consistently counts only completed or merged tasks — in the project overview, in the summary (KPI tiles and phase bars) and in the phase filter of the diagram. A merely open pull request no longer counts as progress.',
                ],
            ],
        ],
        [
            'version' => '1.8.0',
            'date' => '2026-07-17',
            'tldr' => [
                'de' => ['Neue Task-Detailseite', 'Abhakbare Checklisten', '„/planstack plan"', 'IST/SOLL & Testanleitung'],
                'en' => ['New task detail page', 'Checkable checklists', '"/planstack plan"', 'Actual/Target & test guide'],
            ],
            'changes' => [
                'de' => [
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
                'en' => [
                    'Redesigned task detail page: a tidy header with status and review labels side by side, a two-column layout with a sticky sidebar, an open concern shown as a prominent warning right at the top, plus a timeline and a clear review summary with a short verdict.',
                    'New: Checkable checklists for acceptance criteria and test steps — with a progress indicator (x/n) and instant saving when you check an item. Existing free-text criteria can be turned into a checklist with a click.',
                    'New: "/planstack plan" — creates projects, phases and tasks. The text fields now have clear formatting conventions so content is presented consistently and attractively.',
                    'New task fields: "Actual/Target comparison" (behavior before/after, shown side by side) and "Test guide" (numbered, checkable verification steps).',
                    'New task field "Criticality" (non-critical / medium / high / critical) — shown as a colored badge in the detail view, selectable in the form and settable via the API.',
                    'The "output verbosity" (concise / standard / detailed) is now applied consistently — "concise" keeps Claude noticeably more terse while working through the board.',
                    'Pull request titles now carry the project and task codes (e.g. "L2L-G5: …").',
                    '"Approve" is blocked while a concern is open (with a note) so a problematic task is not approved by accident.',
                    'The API task update endpoint is now a partial update: only the fields actually sent are changed.',
                ],
            ],
        ],
        [
            'version' => '1.7.0',
            'date' => '2026-07-17',
            'tldr' => [
                'de' => ['PR-Review per Skill', 'PR reparieren', 'Review-Ergebnis am Task & im Diagramm', 'Mehr Skill-Einstellungen'],
                'en' => ['PR review via skill', 'Fix a PR', 'Review result on task & in diagram', 'More skill settings'],
            ],
            'changes' => [
                'de' => [
                    'Neu: „/planstack review" — übernimmt Tasks, die „in Review" sind (mit PR), führt den Review-Skill aus und erfasst das Ergebnis. Ohne Task-Angabe wird automatisch ein Task des Projekts gewählt, ohne Projekt wird projektübergreifend gereviewt. Eigene Tasks (selbst umgesetzt) können nicht reviewt werden.',
                    'Neu: „/planstack fix <Task/PR>" — bringt einen offenen PR wieder in Ordnung: löst Merge-Konflikte mit dem Ziel-Branch, beantwortet und behebt sowohl normale Kommentare als auch Review-Kommentare (Review-Threads werden zusätzlich aufgelöst) und korrigiert fehlschlagende CI. Der Task bzw. die PR-Nummer ist dabei anzugeben.',
                    'Tasks haben jetzt ein Review-Ergebnis: „zuletzt reviewt am", „Empfehlung" (genehmigt / Änderungen erforderlich) und eine ausführliche „Review-Analyse" (mit vorangestelltem TLDR und einem Kopf, der Strenge, Gründlichkeit sowie das verwendete Modell/den Aufwand dokumentiert). Die Felder werden beim Abschluss eines Reviews befüllt und sind im Task-Formular bearbeitbar, solange der Task „in Review" ist.',
                    'Das Review-Ergebnis wird jetzt auch angezeigt: in der Task-Detailansicht sowie als Eck-Symbol im Diagramm (grünes Häkchen = genehmigt, Warndreieck = Änderungen erforderlich).',
                    'Erweiterte Skill-Einstellungen unter „/planstack settings", jetzt als editierbare Tabelle mit verständlichen deutschen Bezeichnern: Review-Ergebnis (nur im Task / im Task und am PR), Review-Status (manuell bestätigen / automatisch), Review-Strenge (locker / standard / streng), Review-Genauigkeit (lässig / standard / akribisch) und Ausgabe-Umfang (knapp / standard / ausführlich).',
                    'Beim Anlegen eines Tasks sollte die geschätzte Dateianzahl immer angegeben werden (Hinweis/Konvention, keine Pflichtvalidierung).',
                    'Fehlerbehebung: Der Statuswechsel eines Tasks über die API meldete fälschlich einen Serverfehler (500), obwohl der Wechsel ausgeführt wurde — behoben (betraf ebenso das Melden und Auflösen von Concerns).',
                ],
                'en' => [
                    'New: "/planstack review" — picks up tasks that are "in review" (with a PR), runs the review skill and records the result. Without a task specified, a task of the project is chosen automatically; without a project, reviewing happens across projects. Your own tasks (implemented by yourself) cannot be reviewed.',
                    'New: "/planstack fix <task/PR>" — gets an open PR back in shape: resolves merge conflicts with the target branch, answers and addresses both regular comments and review comments (review threads are additionally resolved) and fixes failing CI. The task or PR number must be provided.',
                    'Tasks now have a review result: "last reviewed on", "recommendation" (approved / changes required) and a detailed "review analysis" (with a leading TLDR and a header documenting strictness, thoroughness and the model/effort used). The fields are filled in when a review is completed and remain editable in the task form as long as the task is "in review".',
                    'The review result is now also shown: in the task detail view as well as a corner icon in the diagram (green check = approved, warning triangle = changes required).',
                    'Extended skill settings under "/planstack settings", now as an editable table with clear labels: review result (task only / task and PR), review status (confirm manually / automatic), review strictness (relaxed / standard / strict), review accuracy (casual / standard / meticulous) and output verbosity (concise / standard / detailed).',
                    'When creating a task, the estimated number of files should always be provided (a convention/hint, not a required validation).',
                    'Bug fix: changing a task\'s status via the API wrongly reported a server error (500) even though the change went through — fixed (this also affected reporting and resolving concerns).',
                ],
            ],
        ],
        [
            'version' => '1.6.0',
            'date' => '2026-07-17',
            'tldr' => [
                'de' => ['Skill-Einstellungen', 'Konfiguration ziehen', 'Neue Kommandos ohne Neu-Download'],
                'en' => ['Skill settings', 'Pull configuration', 'New commands without re-downloading'],
            ],
            'changes' => [
                'de' => [
                    'Neu: „/planstack settings" — lokale Einstellungen für Tests, PHPStan, PHPCS und das Betreuen von PRs, je wählbar als „ja", „nein" oder „bei jeder Aufgabe fragen". Diese Einstellungen werden ausschließlich lokal gespeichert und nie an den Server übertragen.',
                    'Neu: „/planstack update-config" — zieht die neueste allgemeine und (optional) Projekt-Konfiguration und zeigt die jeweiligen Versionsnummern an.',
                    'Der Skill holt sich neu hinzugekommene Kommandos künftig selbst (Selbstheilung): Ist ein aufgerufenes Kommando noch unbekannt, lädt er die aktuelle Kommandoliste vom Server — neue Features stehen so ohne erneuten Download bereit (einmalige Aktualisierung des Skills vorausgesetzt).',
                ],
                'en' => [
                    'New: "/planstack settings" — local settings for tests, PHPStan, PHPCS and babysitting PRs, each selectable as "yes", "no" or "ask on every task". These settings are stored locally only and never sent to the server.',
                    'New: "/planstack update-config" — pulls the latest general and (optionally) project configuration and shows the respective version numbers.',
                    'The skill now fetches newly added commands by itself (self-healing): if a called command is still unknown, it loads the current command list from the server — so new features are available without re-downloading (one-time update of the skill assumed).',
                ],
            ],
        ],
        [
            'version' => '1.5.1',
            'date' => '2026-07-17',
            'tldr' => [
                'de' => ['Weniger Anfragen beim Abarbeiten', 'Task per Kürzel ansprechbar'],
                'en' => ['Fewer requests while working', 'Address a task by its code'],
            ],
            'changes' => [
                'de' => [
                    'Der Skill übernimmt den nächsten passenden Task jetzt in einem einzigen Schritt (Auswählen und Beanspruchen zusammengefasst) — das spart Anfragen und Tokens beim Abarbeiten eines Boards und vermeidet Doppel-Beanspruchungen bei mehreren Workern.',
                    'Ein einzelner Task lässt sich direkt über sein Kürzel ansprechen (z. B. bei „/planstack <PROJEKT> <TASK>") — ohne vorherige Suche nach der internen ID.',
                ],
                'en' => [
                    'The skill now takes the next matching task in a single step (selecting and claiming combined) — saving requests and tokens while working through a board and avoiding double claims when several workers are active.',
                    'A single task can be addressed directly via its code (e.g. with "/planstack <PROJECT> <TASK>") — without first looking up the internal ID.',
                ],
            ],
        ],
        [
            'version' => '1.5.0',
            'date' => '2026-07-17',
            'tldr' => [
                'de' => ['Planstack-Skill in der Hauptnavi', 'Ein Skill für alle Projekte', 'PR-Titel mit Task-Kürzel'],
                'en' => ['Planstack skill in the main nav', 'One skill for all projects', 'PR titles with task code'],
            ],
            'changes' => [
                'de' => [
                    'Neu: Eigener Menüpunkt „Planstack-Skill" in der Hauptnavigation mit einer Info-Seite (Erklärung, Installation, Benutzung) und dem Skill-Download — nicht mehr pro Projekt.',
                    'Ein einziger Skill bedient alle Projekte: Das Projekt wird beim Aufruf angegeben — „/planstack <PROJEKT>" arbeitet das ganze Board ab, „/planstack <PROJEKT> <TASK>" gezielt einen einzelnen Task.',
                    'Die mitgelieferte Konfiguration enthält kein festes Projekt mehr (es ist dynamisch); der eingebettete Zugang gilt für alle Projekte, auf die du Zugriff hast.',
                    'Pull Requests bekommen jetzt einheitlich das Task-Kürzel als Titel-Prefix (z. B. „C27: …").',
                    'Die allgemeinen Skill-Anweisungen (z. B. die PR-Titel-Konvention) werden serverseitig gepflegt und vom Skill bei Änderungen automatisch übernommen — ohne erneuten Download.',
                ],
                'en' => [
                    'New: A dedicated "Planstack skill" menu item in the main navigation with an info page (explanation, installation, usage) and the skill download — no longer per project.',
                    'A single skill serves all projects: the project is specified at call time — "/planstack <PROJECT>" works through the whole board, "/planstack <PROJECT> <TASK>" targets a single task.',
                    'The bundled configuration no longer contains a fixed project (it is dynamic); the embedded access applies to all projects you have access to.',
                    'Pull requests now consistently get the task code as a title prefix (e.g. "C27: …").',
                    'The general skill instructions (e.g. the PR title convention) are maintained server-side and adopted automatically by the skill on changes — without re-downloading.',
                ],
            ],
        ],
        [
            'version' => '1.4.0',
            'date' => '2026-07-17',
            'tldr' => [
                'de' => ['Detaillierte Claude-Einstellungen', 'Umgang mit Concerns', 'Zugriff beim Bearbeiten', 'Selbst-aktualisierender Skill'],
                'en' => ['Detailed Claude settings', 'Handling concerns', 'Access in the editor', 'Self-updating skill'],
            ],
            'changes' => [
                'de' => [
                    'Neu: Detaillierte Einstellungsmöglichkeiten für Claude — unter „Projekt bearbeiten → Claude" lässt sich pro Projekt genau steuern, wie sparsam und wie Claude das Board abarbeitet (Presets von „economy" bis „rich", einzeln überschreibbar, mit Erklärung und Token-Schätzung je Option).',
                    'Neu: Einstellung „Umgang mit Concerns" (kritisch / ausgewogen / mutig) auf der Claude-Seite — sie steuert, wie bereitwillig der Skill einen Concern meldet, statt mit eigenen Annahmen weiterzuarbeiten.',
                    'Der Skilltext wird jetzt auf der „Claude"-Unterseite gepflegt (statt auf der allgemeinen Bearbeiten-Seite).',
                    '„Zugriff" ist jetzt Teil der Projekt-Bearbeitung — als Reiter neben „Allgemein" und „Claude".',
                    'Der heruntergeladene Skill enthält jetzt kompakt die aktuelle Konfiguration, das Betriebshandbuch und die verbindlichen Statusregeln — und aktualisiert sich bei Änderungen selbst, ohne erneuten Download (ein einmaliges Neu-Laden vorausgesetzt).',
                    'Die Skilldatei wurde deutlich verschlankt (spürbar weniger Tokens) bei gleichem Funktionsumfang.',
                ],
                'en' => [
                    'New: Detailed settings for Claude — under "Edit project → Claude" you can control per project exactly how frugally and how Claude works through the board (presets from "economy" to "rich", individually overridable, with an explanation and token estimate per option).',
                    'New: A "Handling concerns" setting (critical / balanced / bold) on the Claude page — it controls how readily the skill reports a concern instead of proceeding with its own assumptions.',
                    'The skill text is now maintained on the "Claude" subpage (instead of the general edit page).',
                    '"Access" is now part of project editing — as a tab next to "General" and "Claude".',
                    'The downloaded skill now compactly contains the current configuration, the operations manual and the binding status rules — and updates itself on changes without re-downloading (a one-time reload assumed).',
                    'The skill file has been slimmed down considerably (noticeably fewer tokens) with the same functionality.',
                ],
            ],
        ],
        [
            'version' => '1.3.0',
            'date' => '2026-07-17',
            'tldr' => [
                'de' => ['Claude-Konfiguration', 'Token sparen', 'Presets', 'Live-Schätzung'],
                'en' => ['Claude configuration', 'Save tokens', 'Presets', 'Live estimate'],
            ],
            'changes' => [
                'de' => [
                    'Neu: Unterseite „Claude" beim Projekt-Bearbeiten — hier stellst du ein, wie sparsam Claude das Board abarbeitet (weniger Tokens pro Aufgabe): von „economy" (maximal sparsam) über den empfohlenen Standard bis „rich" (ausführlich, wie bisher).',
                    'Vier Presets inkl. dem neuen Standard „Claude-recommended"; einzelne Optionen lassen sich als Pills gezielt überschreiben, Max. Worker per Schieberegler.',
                    'Zu jeder Option und jedem Preset gibt es eine ausführliche Erklärung mit Pro und Contra sowie eine Kennzeichnung der Token-Last (grün/gelb/rot).',
                    'Eine Live-Anzeige schätzt den Tokenverbrauch im Vergleich zur sparsamsten Einstellung, während du die Optionen änderst.',
                    'Nach einmaligem Neu-Download des Skills werden künftige Konfigurationsänderungen automatisch übernommen (der Skill erkennt sie an einer Versionskennung in jeder Board-Antwort). Manche Einstellungen wirken sogar ohne Neu-Download, weil der Server die Antworten direkt entsprechend liefert.',
                ],
                'en' => [
                    'New: A "Claude" subpage in the project editor — here you set how frugally Claude works through the board (fewer tokens per task): from "economy" (maximally frugal) through the recommended standard to "rich" (detailed, as before).',
                    'Four presets including the new default "Claude-recommended"; individual options can be selectively overridden as pills, max. workers via a slider.',
                    'Every option and every preset comes with a detailed explanation including pros and cons as well as a label for the token load (green/yellow/red).',
                    'A live display estimates token consumption compared to the most frugal setting while you change the options.',
                    'After a one-time re-download of the skill, future configuration changes are adopted automatically (the skill detects them via a version marker in every board response). Some settings even take effect without a re-download because the server delivers the responses accordingly.',
                ],
            ],
        ],
        [
            'version' => '1.2.0',
            'date' => '2026-07-17',
            'tldr' => [
                'de' => ['Zugriff-Verwaltung', 'Kalibrierung überarbeitet', 'Hilfe auf jeder Seite', 'Einheitliches Design'],
                'en' => ['Access management', 'Reworked calibration', 'Help on every page', 'Consistent design'],
            ],
            'changes' => [
                'de' => [
                    'Neu: Eigener Menüpunkt „Zugriff" (ganz rechts) — hier verwaltest du zugewiesene Teams und Rollen (Mitarbeiter, Architekt, Administrator) übersichtlich an einer Stelle, mit Erklärungen zu jedem Begriff.',
                    'Die Kalibrierung wurde überarbeitet: klarere Kennzahlen-Kacheln, ein Diagramm „geschätzt vs. tatsächlich", Treffsicherheit nach Story Points sowie Filter und Sortierung der Tasks.',
                    'Jede Projekt-Unterseite hat jetzt eine eigene Überschrift und einen „?"-Button mit einer kurzen Erklärung aller Begriffe und Kennzahlen der Seite.',
                    'Einheitlicheres Aussehen über alle Seiten hinweg — z. B. die PR-Sequenz mit Kennzahlen als Kacheln, aufgeräumten Filtern und der Liste als Karte.',
                ],
                'en' => [
                    'New: A dedicated "Access" menu item (far right) — here you manage assigned teams and roles (member, architect, administrator) clearly in one place, with explanations for each term.',
                    'Calibration has been reworked: clearer metric tiles, an "estimated vs. actual" chart, accuracy by story points as well as task filtering and sorting.',
                    'Every project subpage now has its own heading and a "?" button with a short explanation of all terms and metrics on the page.',
                    'A more consistent look across all pages — e.g. the PR sequence with metrics as tiles, tidied-up filters and the list as a card.',
                ],
            ],
        ],
        [
            'version' => '1.1.0',
            'date' => '2026-07-17',
            'tldr' => [
                'de' => ['Farbige Fortschrittsbalken', 'Hover-Details', 'Teams je Projekt', 'Changelog-Hinweis'],
                'en' => ['Colored progress bars', 'Hover details', 'Teams per project', 'Changelog notice'],
            ],
            'changes' => [
                'de' => [
                    'Fortschrittsbalken zeigen jetzt farbige Segmente je Status (gemerged, in Review, in Arbeit …) — auf der Projekte-Übersicht und in der Phasen-Summary.',
                    'Beim Überfahren eines Balken-Segments erscheinen Status, Anzahl Tasks und SP-Anteil in der passenden Farbe.',
                    'Projektkacheln zeigen unter dem Projektgründer die zugeordneten Teams.',
                    'Neue Updates werden in der Navigation mit einem Symbol angekündigt und beim Öffnen dieser Seite hervorgehoben.',
                ],
                'en' => [
                    'Progress bars now show colored segments per status (merged, in review, in progress …) — on the projects overview and in the phase summary.',
                    'Hovering over a bar segment reveals the status, number of tasks and SP share in the matching color.',
                    'Project tiles show the assigned teams below the project founder.',
                    'New updates are announced in the navigation with an icon and highlighted when this page is opened.',
                ],
            ],
        ],
        [
            'version' => '1.0.0',
            'date' => '2026-07-16',
            // TL;DR: nur Stichwörter (werden fett dargestellt).
            'tldr' => [
                'de' => ['CI-Status im Diagramm', 'Velocity', 'Teams umbenennen', 'Changelog'],
                'en' => ['CI status in the diagram', 'Velocity', 'Rename teams', 'Changelog'],
            ],
            'changes' => [
                'de' => [
                    'Neu: CI-/Merge-Status jedes Pull Requests direkt im Projekt-Diagramm — auf einen Blick sehen, was grün ist, was fehlschlägt und was bereit zum Mergen ist. Einrichtung über den Menüpunkt „TamperMonkey Script".',
                    'Neu: Velocity in der Kalibrierung — wie viele Story Points pro Tag tatsächlich fertig werden (gemessen vom ersten Claim bis zum letzten Merge).',
                    'Neu: Teams lassen sich jetzt umbenennen.',
                    'Neu: Diese Änderungsübersicht in der oberen Navigation.',
                ],
                'en' => [
                    'New: CI/merge status of every pull request directly in the project diagram — see at a glance what is green, what is failing and what is ready to merge. Set up via the "TamperMonkey Script" menu item.',
                    'New: Velocity in calibration — how many story points are actually completed per day (measured from the first claim to the last merge).',
                    'New: Teams can now be renamed.',
                    'New: This change overview in the top navigation.',
                ],
            ],
        ],
    ],
];
