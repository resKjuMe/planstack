<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Projekt bearbeiten – <span class="font-mono">{{ $project->alias }}</span>
        </h2>
    </x-slot>

    <x-slot name="subheader">
        <x-project-edit-tabs :project="$project" active="claude" />
    </x-slot>

    @php
        // Token-Last je Option: g = niedrig, y = mittel, r = hoch, n = neutral.
        $badge = fn ($t) => ['g' => '🟢', 'y' => '🟡', 'r' => '🔴'][$t] ?? '⚪';
        $badgeWord = fn ($t) => ['g' => 'niedrig', 'y' => 'mittel', 'r' => 'hoch'][$t] ?? 'neutral';
        $boolKey = fn ($v) => ($v === true || $v === '1' || $v === 1) ? '1' : '0';

        // Gruppierte Einstellungen: Label, Kurzhilfe, ausführliche Beschreibung und
        // je Option Token-Last (token) + Pro/Contra (pro/con) für das Hilfe-Icon.
        $groups = [
            'Board-Ausgabe' => [
                ['key' => 'board.scope', 'type' => 'enum', 'label' => 'Umfang',
                 'desc' => 'Steuert, wie viele Tasks das Board liefert. Weniger Tasks = weniger Tokens pro Board-Abruf.',
                 'options' => [
                    'next_only' => ['label' => 'Nur bester Pick', 'token' => 'g',
                        'pro' => 'Minimaler Kontext, ein klarer Fokus je Durchlauf.',
                        'con' => 'Kein Überblick über Alternativen; bei Kollision neuer Abruf nötig.'],
                    'pickable' => ['label' => 'Pickbare Liste', 'token' => 'y',
                        'pro' => 'Alle aktuell startbaren Tasks sichtbar, freie Wahl.',
                        'con' => 'Größere Antwort als ein einzelner Pick.'],
                    'all' => ['label' => 'Alle Tasks', 'token' => 'r',
                        'pro' => 'Vollständiger Überblick inkl. blockierter Tasks.',
                        'con' => 'Teuerste Antwort, viel Ballast pro Abruf.'],
                 ]],
                ['key' => 'board.format', 'type' => 'enum', 'label' => 'Format',
                 'desc' => 'Bestimmt das Antwortformat des Boards – von kompaktem Text bis zu vollem JSON.',
                 'options' => [
                    'terse' => ['label' => 'Text (terse)', 'token' => 'g',
                        'pro' => 'Kleinste Antwort, eine Zeile je Task.',
                        'con' => 'Nur Kerninfos, kein strukturiertes JSON.'],
                    'lean' => ['label' => 'Kompaktes JSON', 'token' => 'y',
                        'pro' => 'Maschinenlesbar mit kurzen Schlüsseln, ohne Ballast.',
                        'con' => 'Etwas größer als reiner Text.'],
                    'full' => ['label' => 'Volles JSON', 'token' => 'r',
                        'pro' => 'Alle berechneten Felder, keine zweite Abfrage nötig.',
                        'con' => 'Größte Antwort.'],
                 ]],
                ['key' => 'board.aggregates', 'type' => 'bool', 'label' => 'Aggregate',
                 'desc' => 'Fortschritts-Summen und Phasen-Aggregate mitliefern.',
                 'options' => [
                    '0' => ['label' => 'Aus', 'token' => 'g',
                        'pro' => 'Spart Summen-/Phasen-Ballast.',
                        'con' => 'Kein Fortschrittsüberblick in der Antwort.'],
                    '1' => ['label' => 'An', 'token' => 'y',
                        'pro' => 'Fortschritt und Phasen auf einen Blick.',
                        'con' => 'Zusätzliche Tokens pro Abruf.'],
                 ]],
                ['key' => 'board.diff_mode', 'type' => 'enum', 'label' => 'Diff-Modus',
                 'desc' => 'Ob ein unverändertes Board als „304 Not Modified" beantwortet wird.',
                 'options' => [
                    'etag' => ['label' => 'ETag (304)', 'token' => 'g',
                        'pro' => 'Unverändertes Board = 304, nichts Neues im Kontext.',
                        'con' => 'Client muss den ETag mitschicken.'],
                    'off' => ['label' => 'Immer voll', 'token' => 'r',
                        'pro' => 'Immer vollständige Daten, simpel.',
                        'con' => 'Wiederholt denselben Inhalt bei jedem Abruf.'],
                 ]],
            ],
            'Task-Details' => [
                ['key' => 'task.fields', 'type' => 'enum', 'label' => 'Feldumfang',
                 'desc' => 'Welche Felder ein Task-Objekt trägt.',
                 'options' => [
                    'minimal' => ['label' => 'Minimal', 'token' => 'g',
                        'pro' => 'Nur das Nötigste zum Picken/Bearbeiten – sehr sparsam.',
                        'con' => 'Fehlende Details müssen ggf. nachgeladen werden.'],
                    'standard' => ['label' => 'Standard', 'token' => 'y',
                        'pro' => 'Guter Mittelweg (inkl. Phase, Aufwand, PR).',
                        'con' => 'Mehr als minimal.'],
                    'full' => ['label' => 'Voll', 'token' => 'r',
                        'pro' => 'Alles inkl. Zeitstempel und Verlauf.',
                        'con' => 'Größte Task-Objekte.'],
                 ]],
                ['key' => 'claim.return_details', 'type' => 'bool', 'label' => 'Details bei Aktionen',
                 'desc' => 'Ob Schreib-Aktionen den vollen Task oder nur eine kurze Bestätigung zurückgeben.',
                 'options' => [
                    '0' => ['label' => 'Kurzer Ack', 'token' => 'g',
                        'pro' => 'Winzige Schreib-Antworten (id, Name, Status).',
                        'con' => 'Für Details muss das Board neu gelesen werden.'],
                    '1' => ['label' => 'Voller Task', 'token' => 'y',
                        'pro' => 'Direkt alle Daten nach der Aktion.',
                        'con' => 'Jede Aktion trägt den vollen Task zurück.'],
                 ]],
            ],
            'Roundtrips & Aktionen' => [
                ['key' => 'actions.bundling', 'type' => 'bool', 'label' => 'Aktion bündeln',
                 'desc' => 'PR setzen + fertig melden (+ optional mergen) in einem Aufruf.',
                 'options' => [
                    '1' => ['label' => 'Gebündelt', 'token' => 'g',
                        'pro' => 'Ein Aufruf statt drei – weniger Roundtrips im Kontext.',
                        'con' => 'Weniger granulare Zwischenschritte.'],
                    '0' => ['label' => 'Einzeln', 'token' => 'r',
                        'pro' => 'Feingranulare Kontrolle je Schritt.',
                        'con' => 'Mehr Aufrufe, mehr Kontext.'],
                 ]],
                ['key' => 'response.errors', 'type' => 'enum', 'label' => 'Fehlerausgabe',
                 'desc' => 'Ausführlichkeit von Fehlerantworten.',
                 'options' => [
                    'minimal' => ['label' => 'Nur Code', 'token' => 'g',
                        'pro' => 'Kleinste Fehlerantwort (HTTP-Status trägt die Bedeutung).',
                        'con' => 'Keine Klartext-Begründung.'],
                    'standard' => ['label' => 'Code + Meldung', 'token' => 'y',
                        'pro' => 'Kurze, verständliche Meldung.',
                        'con' => 'Etwas mehr Text.'],
                    'verbose' => ['label' => 'Ausführlich', 'token' => 'r',
                        'pro' => 'Volle Fehlerdetails zum Debuggen.',
                        'con' => 'Am meisten Text.'],
                 ]],
                ['key' => 'reread.policy', 'type' => 'enum', 'label' => 'Board-Neulesen',
                 'desc' => 'Wann der Client das Board erneut liest (Client-Hint).',
                 'options' => [
                    'on_conflict' => ['label' => 'Nur bei Konflikt', 'token' => 'g',
                        'pro' => 'Minimale Board-Abrufe (verlässt sich auf 409).',
                        'con' => 'Stände können kurz veraltet sein.'],
                    'once_per_pick' => ['label' => 'Einmal pro Pick', 'token' => 'y',
                        'pro' => 'Aktueller Stand je Task.',
                        'con' => 'Ein zusätzlicher Abruf je Task.'],
                    'before_every_action' => ['label' => 'Vor jeder Aktion', 'token' => 'r',
                        'pro' => 'Immer topaktuell.',
                        'con' => 'Vervielfacht die Board-Abrufe – teuer.'],
                 ]],
            ],
            'Instruktionen & Konventionen' => [
                ['key' => 'instructions.delivery', 'type' => 'enum', 'label' => 'Logik-Auslieferung',
                 'desc' => 'Wie Statuslogik und Regeln zum Client gelangen (Client-Hint).',
                 'options' => [
                    'server_enforced' => ['label' => 'Server erzwingt', 'token' => 'g',
                        'pro' => 'Regeln stecken serverseitig – nicht im Kontext.',
                        'con' => 'Client kennt die Regeldetails nicht explizit.'],
                    'changelog' => ['label' => 'Changelog-Delta', 'token' => 'y',
                        'pro' => 'Bei Versionssprung nur die Änderung.',
                        'con' => 'Client braucht das Basiswissen bereits.'],
                    'full_doc' => ['label' => 'Volldokument', 'token' => 'r',
                        'pro' => 'Alle Regeln direkt verfügbar.',
                        'con' => 'Großer Kontextverbrauch.'],
                 ]],
                ['key' => 'conventions.delivery', 'type' => 'enum', 'label' => 'Konventionen',
                 'desc' => 'Wie Coding-Standards/PR-Template ausgeliefert werden (Client-Hint).',
                 'options' => [
                    'server_enforced' => ['label' => 'Server erzwingt', 'token' => 'g',
                        'pro' => 'CI/Lint sichert Standards – kein Kontext nötig.',
                        'con' => 'Feedback erst nach dem Lauf.'],
                    'snippet' => ['label' => 'Ausschnitt', 'token' => 'y',
                        'pro' => 'Nur der für den Task relevante Teil.',
                        'con' => 'Ggf. fehlt Randwissen.'],
                    'full_prose' => ['label' => 'Volltext', 'token' => 'r',
                        'pro' => 'Alle Konventionen präsent.',
                        'con' => 'Viel Prosa je Task.'],
                 ]],
            ],
            'Ausführung (Client-Hint)' => [
                ['key' => 'execution.mode', 'type' => 'enum', 'label' => 'Ausführungsmodell',
                 'desc' => 'Wie der Client Tasks abarbeitet – der größte Token-Hebel.',
                 'options' => [
                    'headless' => ['label' => 'Headless-Loop', 'token' => 'g',
                        'pro' => 'Frischer Prozess je Task, null Alt-Ballast.',
                        'con' => 'Kein geteilter Kontext über Tasks hinweg.'],
                    'subagent' => ['label' => 'Subagent je Task', 'token' => 'g',
                        'pro' => 'Isolierter, kleiner Kontext; Orchestrator bleibt winzig.',
                        'con' => 'Etwas Overhead je Subagent.'],
                    'single_session' => ['label' => 'Einzel-Session', 'token' => 'r',
                        'pro' => 'Durchgehender Kontext, einfachster Ablauf.',
                        'con' => 'Historie wächst stark – teuerste Variante.'],
                 ]],
                ['key' => 'context.between_tasks', 'type' => 'enum', 'label' => 'Kontext zwischen Tasks',
                 'desc' => 'Nach jedem Task anhalten (Kontext leerbar) oder durchlaufen.',
                 'options' => [
                    'stop' => ['label' => 'Nach Task stoppen', 'token' => 'g',
                        'pro' => 'Kontext leerbar (/clear), bleibt konstant klein.',
                        'con' => 'Neustart je Task nötig.'],
                    'continue' => ['label' => 'Durchlaufen', 'token' => 'r',
                        'pro' => 'Ohne Unterbrechung.',
                        'con' => 'Wachsende Historie.'],
                 ]],
                ['key' => 'parallelism.max_workers', 'type' => 'int', 'label' => 'Max. Worker',
                 'desc' => 'Anzahl paralleler Worker (1–32). Beeinflusst den Durchsatz, nicht die Tokens pro Task – die Gesamtkosten skalieren mit der Anzahl.',
                 'options' => []],
            ],
            'Arbeitsweise (Client-Hint)' => [
                ['key' => 'concerns.attitude', 'type' => 'enum', 'label' => 'Umgang mit Concerns',
                 'desc' => 'Wie bereitwillig der Worker einen Concern meldet (Blocker, Missverständnis, offene Entscheidung), statt mit eigenen Annahmen weiterzuarbeiten.',
                 'options' => [
                    'kritisch' => ['label' => 'kritisch', 'token' => 'n',
                        'pro' => 'Höchste Sicherheit – Unklarheiten und Risiken werden früh gemeldet, bevor Code entsteht.',
                        'con' => 'Mehr Rückfragen/Concerns und Unterbrechungen; langsamerer Durchsatz.'],
                    'ausgewogen' => ['label' => 'ausgewogen', 'token' => 'n',
                        'pro' => 'Guter Mittelweg – Concern nur bei echten Blockern/Unklarheiten, sonst wird mit vernünftigen Annahmen weitergearbeitet.',
                        'con' => 'Gelegentlich ein unnötiger oder ein fehlender Concern.'],
                    'mutig' => ['label' => 'mutig', 'token' => 'n',
                        'pro' => 'Schnellster Durchsatz – der Worker trifft eigenständig vernünftige Annahmen und meldet nur harte Blocker.',
                        'con' => 'Höheres Risiko falscher Annahmen und dadurch mehr Nacharbeit.'],
                 ]],
            ],
            'Claude-Ausführung (Client-Hint)' => [
                ['key' => 'run.mode', 'type' => 'enum', 'label' => 'Berechtigungsmodus',
                 'desc' => 'In welchem Modus Claude läuft. „Client-Standard" übernimmt die Einstellung der lokalen Claude-Umgebung (kein projektweiter Override).',
                 'options' => [
                    'client' => ['label' => 'Client-Standard', 'token' => 'n',
                        'pro' => 'Übernimmt die lokale Einstellung; keine projektweite Vorgabe.',
                        'con' => 'Verhalten hängt von der jeweiligen Umgebung ab.'],
                    'manual' => ['label' => 'Manuell', 'token' => 'n',
                        'pro' => 'Maximale Kontrolle – jede Aktion wird bestätigt.',
                        'con' => 'Viele Rückfragen, langsamer.'],
                    'accept_edits' => ['label' => 'Edits automatisch', 'token' => 'n',
                        'pro' => 'Dateiänderungen ohne Rückfrage – zügiger.',
                        'con' => 'Weniger Kontrolle über einzelne Edits.'],
                    'plan' => ['label' => 'Plan-Modus', 'token' => 'n',
                        'pro' => 'Erst Plan, dann Umsetzung – gut zur Abstimmung.',
                        'con' => 'Zusätzlicher Schritt vor der Arbeit.'],
                    'auto' => ['label' => 'Auto', 'token' => 'n',
                        'pro' => 'Arbeitet autonom durch, keine Unterbrechungen.',
                        'con' => 'Wenig Kontrolle – nur mit Vertrauen/Absicherung.'],
                 ]],
                ['key' => 'run.model', 'type' => 'enum', 'label' => 'Modell',
                 'desc' => 'Mit welchem Modell Claude arbeitet. „Client-Standard" nutzt das lokal gewählte Modell.',
                 'options' => [
                    'client' => ['label' => 'Client-Standard', 'token' => 'n',
                        'pro' => 'Nutzt das lokal gewählte Modell.',
                        'con' => 'Keine projektweite Vorgabe.'],
                    'opus' => ['label' => 'Opus', 'token' => 'n',
                        'pro' => 'Stärkste Qualität für schwierige Aufgaben.',
                        'con' => 'Höchste Kosten.'],
                    'sonnet' => ['label' => 'Sonnet', 'token' => 'n',
                        'pro' => 'Ausgewogen zwischen Qualität und Kosten.',
                        'con' => 'Bei sehr schweren Aufgaben schwächer als Opus.'],
                    'haiku' => ['label' => 'Haiku', 'token' => 'n',
                        'pro' => 'Schnell und günstig.',
                        'con' => 'Geringere Qualität bei komplexen Aufgaben.'],
                    'fable' => ['label' => 'Fable', 'token' => 'n',
                        'pro' => 'Modell der Claude-5-Familie.',
                        'con' => 'Eignung je nach Aufgabe abwägen.'],
                 ]],
                ['key' => 'run.effort', 'type' => 'enum', 'label' => 'Reasoning-Aufwand',
                 'desc' => 'Wie viel Nachdenk-Aufwand Claude investiert. „Client-Standard" nutzt die lokale Voreinstellung.',
                 'options' => [
                    'client' => ['label' => 'Client-Standard', 'token' => 'n',
                        'pro' => 'Nutzt die lokale Voreinstellung.',
                        'con' => 'Keine projektweite Vorgabe.'],
                    'low' => ['label' => 'niedrig', 'token' => 'n',
                        'pro' => 'Schnell und günstig.',
                        'con' => 'Weniger gründlich.'],
                    'medium' => ['label' => 'mittel', 'token' => 'n',
                        'pro' => 'Ausgewogener Standard-Kompromiss.',
                        'con' => '—'],
                    'high' => ['label' => 'hoch', 'token' => 'n',
                        'pro' => 'Gründlicher bei schwierigen Aufgaben.',
                        'con' => 'Mehr Tokens und Zeit.'],
                    'xhigh' => ['label' => 'sehr hoch', 'token' => 'n',
                        'pro' => 'Sehr gründlich.',
                        'con' => 'Deutlich mehr Tokens und Zeit.'],
                    'max' => ['label' => 'maximal', 'token' => 'n',
                        'pro' => 'Maximale Gründlichkeit.',
                        'con' => 'Höchster Token- und Zeitaufwand.'],
                 ]],
            ],
        ];

        // Daten für die Live-Aktualisierung beim Preset-Wechsel (Alpine).
        $jsMeta = [];
        foreach ($groups as $settings) {
            foreach ($settings as $s) {
                $opts = [];
                foreach ($s['options'] as $val => $opt) {
                    $opts[(string) $val] = ['label' => $opt['label'], 'token' => $opt['token']];
                }
                $jsMeta[$s['key']] = ['type' => $s['type'], 'options' => $opts];
            }
        }
        // Initiale (explizite) Auswahl je Feld: '' = Profil-Standard.
        $initialValues = [];
        foreach ($jsMeta as $mKey => $m) {
            if (! array_key_exists($mKey, $overrides)) {
                $initialValues[$mKey] = '';
            } elseif ($m['type'] === 'bool') {
                $initialValues[$mKey] = $boolKey($overrides[$mKey]);
            } else {
                $initialValues[$mKey] = (string) $overrides[$mKey];
            }
        }

        // Grobe relative Token-Gewichte je Option (nur für die Schätzung). Der
        // größte Hebel ist das Ausführungsmodell/der Kontext zwischen Tasks.
        $tokenCost = [
            'board.scope' => ['next_only' => 1, 'pickable' => 4, 'all' => 12],
            'board.format' => ['terse' => 1, 'lean' => 3, 'full' => 6],
            'board.aggregates' => ['0' => 0, '1' => 3],
            'board.diff_mode' => ['etag' => 0, 'off' => 4],
            'task.fields' => ['minimal' => 1, 'standard' => 3, 'full' => 6],
            'claim.return_details' => ['0' => 0, '1' => 3],
            'actions.bundling' => ['1' => 0, '0' => 3],
            'response.errors' => ['minimal' => 0, 'standard' => 1, 'verbose' => 3],
            'reread.policy' => ['on_conflict' => 1, 'once_per_pick' => 4, 'before_every_action' => 10],
            'instructions.delivery' => ['server_enforced' => 0, 'changelog' => 4, 'full_doc' => 20],
            'conventions.delivery' => ['server_enforced' => 0, 'snippet' => 5, 'full_prose' => 25],
            'execution.mode' => ['headless' => 0, 'subagent' => 5, 'single_session' => 120],
            'context.between_tasks' => ['stop' => 0, 'continue' => 40],
        ];

        $alpineInit = [
            'profile' => $profile ?: '',
            'presets' => \App\Support\ProjectConfig::PROFILES,
            'defaults' => \App\Support\ProjectConfig::DEFAULTS,
            'meta' => $jsMeta,
            'values' => $initialValues,
            'costs' => $tokenCost,
            'hintKeys' => array_values(\App\Support\ProjectConfig::CLIENT_HINT_KEYS),
        ];

        // Farbiger Token-Punkt (Tailwind) je Token-Stufe.
        $dot = fn ($t) => ['g' => 'bg-green-500', 'y' => 'bg-amber-500', 'r' => 'bg-red-500'][$t] ?? 'bg-gray-400';

        // Kurzbeschreibung je Gruppe (unter dem Kartentitel).
        $groupDescriptions = [
            'Board-Ausgabe' => 'Wie schlank die Board-Antwort ausfällt – Umfang, Format und Caching.',
            'Task-Details' => 'Wie viele Felder ein einzelner Task und die Antworten von Schreib-Aktionen tragen.',
            'Roundtrips & Aktionen' => 'Aufrufe bündeln und Antworten knapp halten, um Roundtrips zu sparen.',
            'Instruktionen & Konventionen' => 'Wie Regeln und Standards zum Client gelangen – möglichst ohne Kontext-Ballast.',
            'Ausführung (Client-Hint)' => 'Wie der Client Tasks abarbeitet – der größte Token-Hebel.',
            'Arbeitsweise (Client-Hint)' => 'Wie der Worker mit Unklarheiten und Entscheidungen umgeht.',
            'Claude-Ausführung (Client-Hint)' => 'Modus, Modell und Reasoning-Aufwand, mit denen Claude läuft (Client-Standard = lokale Einstellung übernehmen).',
        ];
    @endphp

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-flash />

            <div class="bg-white rounded-lg shadow p-6 space-y-3">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="font-semibold text-gray-800">Claude-Konfiguration</h3>
                        <p class="text-sm text-gray-500 mt-1">
                            Token-sparende Schalter für das Board-Protokoll. Der Skill zieht Änderungen
                            automatisch über die Version <span class="font-mono">v{{ $project->config_version }}</span>
                            (Header <span class="font-mono">X-Planstack-Config-Version</span>) — ohne Extra-Aufruf.
                        </p>
                    </div>
                    <span class="shrink-0 inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-mono text-gray-600">v{{ $project->config_version }}</span>
                </div>
                <p class="text-xs text-gray-400">
                    Token-Last je Option: {{ $badge('g') }} niedrig · {{ $badge('y') }} mittel · {{ $badge('r') }} hoch.
                </p>
            </div>

            <form method="POST" action="{{ route('projects.claude.update', $project) }}" class="space-y-6"
                  x-data="claudeConfig(@js($alpineInit))">
                @csrf
                @method('PUT')

                @php
                    $profilePills = [
                        'recommended' => ['label' => 'Claude-recommended', 'dot' => 'bg-indigo-500',
                            'desc' => 'Claudes Empfehlung und der Standard für neue Projekte – die sinnvollste Balance zwischen Sparsamkeit und Praxistauglichkeit. Konkret: Board und Tasks kommen als kompaktes JSON mit Standard-Feldern (genug zum Arbeiten, ohne Zeitstempel/Verlauf), unveränderte Boards werden per ETag als „304" abgekürzt, Schreib-Aktionen antworten mit kurzem Ack, und die drei Aktionen PR/fertig/merge laufen gebündelt. Regeln und Konventionen werden serverseitig erzwungen (nichts davon im Kontext), das Board wird einmal pro Pick gelesen, und jeder Task läuft in einem isolierten Subagenten-Kontext, der nach dem Task endet.',
                            'pro' => 'Rund eine Größenordnung weniger Protokoll-Tokens als „rich", ohne an Übersicht, strukturierten Daten oder Fehlermeldungen zu sparen. Sicherer Allrounder für den Regelbetrieb.',
                            'con' => 'Nicht ganz so sparsam wie „economy". Der Subagent-Modus entfaltet seinen Vorteil nur, wenn der ausführende Worker Tasks tatsächlich isoliert (ein Task pro frischem Kontext) abarbeitet.'],
                        'economy' => ['label' => 'economy', 'dot' => 'bg-green-500',
                            'desc' => 'Das sparsamste Paket – jeder Schalter steht auf der tokenärmsten Stufe: nur der beste Pick als reine Textzeile je Task, keine Aggregate, ETag-Caching, minimale Task-Felder, kurze Acks, gebündelte Aktionen, minimale Fehlerausgabe, Board-Neulesen nur bei Konflikt (409), server-seitig erzwungene Regeln/Konventionen, Headless-Ausführung (frischer Prozess je Task) und Stopp nach jedem Task.',
                            'pro' => 'Minimaler Tokenverbrauch pro Iteration; ideal für lange, vollautomatisierte Board-Läufe mit vielen kleinen Tasks.',
                            'con' => 'Antworten enthalten nur das Nötigste (Text statt JSON, keine Meldungstexte bei Fehlern). Der Nutzen hängt stark davon ab, dass der Worker die Hinweise befolgt (Headless, frischer Kontext je Task); zum Debuggen/Einarbeiten wenig geeignet.'],
                        'balanced' => ['label' => 'balanced', 'dot' => 'bg-amber-500',
                            'desc' => 'Der Mittelweg – kompaktes JSON mit der vollständigen pickbaren Liste (statt nur einem Pick), Standard-Feldern und vollständigen Schreib-Antworten, ETag-Caching, gebündelte Aktionen, Fehlermeldungen im Klartext. Logik kommt als Changelog-Delta, Konventionen als Ausschnitt, ein Subagent je Task, Board-Neulesen einmal pro Pick.',
                            'pro' => 'Guter Kompromiss aus Sparsamkeit und voller Übersicht/Details; der Worker sieht alle Alternativen und bekommt vollständige Rückmeldungen.',
                            'con' => 'Spürbar mehr Tokens als „recommended" und „economy" (volle Listen, volle Schreib-Antworten, Deltas statt server-erzwungener Regeln).'],
                        'rich' => ['label' => 'rich', 'dot' => 'bg-red-500',
                            'desc' => 'Die ausführlichste Variante und zugleich das bisherige Verhalten des L2LR-Skills (vor der Konfiguration): volles JSON, alle Task-Felder inkl. Zeitstempel/Verlauf, Totals und Phasen-Aggregate, kein ETag, vollständige Schreib-Antworten, Einzelaktionen statt Bündelung, ausführliche Fehler, Board-Neulesen vor jeder Aktion, ganze Regel-/Konventionsdokumente im Kontext und eine durchgehende Session. Entspricht den globalen Standardwerten.',
                            'pro' => 'Maximale Transparenz – alles liegt direkt vor; gut zum Debuggen, Nachvollziehen und Einarbeiten.',
                            'con' => 'Höchster Tokenverbrauch: die durchgehende Session lässt die Historie stark wachsen, und Volldokumente/volle Antworten belasten jeden Schritt.'],
                    ];
                @endphp
                <div class="bg-white rounded-lg shadow p-6" x-data="{ help: false }">
                    <input type="hidden" name="profile" :value="profile" />
                    <div class="flex items-center gap-4">
                        <label class="w-52 shrink-0 text-sm font-medium text-gray-700">Profil (Preset)</label>
                        <div class="flex-1 flex flex-wrap items-center justify-end gap-2">
                            @foreach ($profilePills as $val => $p)
                                <button type="button" @click="profile = '{{ $val }}'"
                                        :class="profile === '{{ $val }}'
                                            ? 'border-indigo-500 bg-indigo-50 text-indigo-700 ring-1 ring-indigo-500'
                                            : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'"
                                        class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium">
                                    <span class="h-2 w-2 rounded-full {{ $p['dot'] }}"></span>
                                    {{ $p['label'] }}
                                </button>
                            @endforeach
                        </div>
                        <button type="button" @click="help = ! help" :aria-expanded="help"
                                aria-label="Erklärung zu den Presets" title="Erklärung ein-/ausblenden"
                                class="shrink-0 text-gray-400 hover:text-indigo-600 focus:outline-none">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg>
                        </button>
                    </div>

                    <div x-show="help" style="display:none"
                         class="mt-3 rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm leading-relaxed text-gray-600">
                        <p>Ein Preset setzt die Basiswerte aller Einstellungen. Einzelne Felder unten überschreiben das gewählte Preset gezielt.</p>
                        <ul class="mt-3 space-y-2.5">
                            @foreach ($profilePills as $val => $p)
                                <li>
                                    <div class="flex items-center gap-1.5 font-medium text-gray-800">
                                        <span class="h-2 w-2 rounded-full {{ $p['dot'] }}"></span>
                                        {{ $p['label'] }}
                                    </div>
                                    <div class="ms-3.5">{{ $p['desc'] }}</div>
                                    <div class="ms-3.5"><span class="font-medium text-green-700">Pro:</span> {{ $p['pro'] }}</div>
                                    <div class="ms-3.5"><span class="font-medium text-rose-700">Contra:</span> {{ $p['con'] }}</div>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <x-input-error :messages="$errors->get('profile')" class="mt-2" />
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-baseline justify-between gap-4">
                        <h4 class="font-semibold text-gray-700">Geschätzter Tokenverbrauch im Vergleich zur Minimalconfig</h4>
                        <span class="shrink-0 text-lg font-bold text-gray-800" x-text="'× ' + tokenRatio().toFixed(1)"></span>
                    </div>
                    <div class="mt-3 h-3 w-full overflow-hidden rounded-full bg-gray-100">
                        <div class="h-full rounded-full transition-all duration-300"
                             :class="tokenBarClass()" :style="'width: ' + Math.max(2, tokenPct()) + '%'"></div>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-xs text-gray-400">
                        <span>Minimal (×1,0)</span>
                        <span>Maximal</span>
                    </div>
                    <p class="mt-2 text-xs text-gray-400">
                        Grobe Schätzung des Board-/Task-Protokoll-Anteils pro Iteration relativ zur sparsamsten
                        Einstellung (≙ Profil „economy" = ×1,0). Der eigentliche Code-Aufwand je Task ist nicht enthalten.
                        Größter Hebel: <span class="font-medium">Ausführungsmodell</span> und <span class="font-medium">Kontext zwischen Tasks</span>.
                    </p>
                </div>

                @foreach ($groups as $groupTitle => $settings)
                    <div class="bg-white rounded-lg shadow p-6">
                        <h4 class="font-semibold text-gray-700">{{ $groupTitle }}</h4>
                        <p class="text-sm text-gray-500 mt-0.5 mb-2">{{ $groupDescriptions[$groupTitle] ?? '' }}</p>
                        <div class="divide-y divide-gray-100">
                            @foreach ($settings as $s)
                                @php $key = $s['key']; @endphp
                                <div class="py-3" x-data="{ help: false }">
                                    <div class="flex items-center gap-4">
                                        <label for="f-{{ $key }}" class="w-52 shrink-0 text-sm font-medium text-gray-700">{{ $s['label'] }}</label>

                                        <div class="flex-1 flex flex-wrap items-center justify-end gap-3">
                                            @if ($s['type'] === 'int')
                                                <input type="hidden" name="overrides[{{ $key }}]" :value="values['{{ $key }}']" />
                                                {{-- Standard zurücksetzen --}}
                                                <button type="button" @click="select('{{ $key }}', '')"
                                                        :class="isSelected('{{ $key }}', '')
                                                            ? 'border-indigo-500 bg-indigo-50 text-indigo-700 ring-1 ring-indigo-500'
                                                            : 'border-gray-300 bg-white text-gray-500 hover:bg-gray-50'"
                                                        class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium">
                                                    <span x-text="'Standard: ' + defaultOptLabel('{{ $key }}')"></span>
                                                </button>
                                                <input id="f-{{ $key }}" type="range" min="1" max="32" step="1"
                                                       :value="values['{{ $key }}'] !== '' ? values['{{ $key }}'] : defaultVal('{{ $key }}')"
                                                       @input="values['{{ $key }}'] = $event.target.value"
                                                       class="w-48 accent-indigo-600" />
                                                <span class="w-7 text-right text-sm font-semibold text-gray-800"
                                                      x-text="values['{{ $key }}'] !== '' ? values['{{ $key }}'] : defaultVal('{{ $key }}')"></span>
                                            @else
                                                <input type="hidden" name="overrides[{{ $key }}]" :value="values['{{ $key }}']" />
                                                {{-- Profil-Standard --}}
                                                <button type="button" @click="select('{{ $key }}', '')"
                                                        :class="isSelected('{{ $key }}', '')
                                                            ? 'border-indigo-500 bg-indigo-50 text-indigo-700 ring-1 ring-indigo-500'
                                                            : 'border-gray-300 bg-white text-gray-500 hover:bg-gray-50'"
                                                        class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium">
                                                    <span class="h-2 w-2 rounded-full" :class="dotClass(defaultToken('{{ $key }}'))"></span>
                                                    <span x-text="'Standard: ' + defaultOptLabel('{{ $key }}')"></span>
                                                </button>
                                                {{-- Explizite Optionen --}}
                                                @foreach ($s['options'] as $val => $opt)
                                                    <button type="button" @click="select('{{ $key }}', '{{ $val }}')"
                                                            :class="isSelected('{{ $key }}', '{{ $val }}')
                                                                ? 'border-indigo-500 bg-indigo-50 text-indigo-700 ring-1 ring-indigo-500'
                                                                : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'"
                                                            class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium">
                                                        <span class="h-2 w-2 rounded-full {{ $dot($opt['token']) }}"></span>
                                                        {{ $opt['label'] }}
                                                    </button>
                                                @endforeach
                                            @endif
                                        </div>

                                        <button type="button" @click="help = ! help" :aria-expanded="help"
                                                aria-label="Erklärung zu {{ $s['label'] }}" title="Erklärung ein-/ausblenden"
                                                class="shrink-0 text-gray-400 hover:text-indigo-600 focus:outline-none">
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg>
                                        </button>
                                    </div>

                                    <div x-show="help" style="display:none"
                                         class="mt-3 rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm leading-relaxed text-gray-600">
                                        <p>{{ $s['desc'] }}</p>
                                        @if (! empty($s['options']))
                                            <ul class="mt-3 space-y-2.5">
                                                @foreach ($s['options'] as $val => $opt)
                                                    <li>
                                                        <div class="flex items-center gap-1.5 font-medium text-gray-800">
                                                            <span class="h-2 w-2 rounded-full {{ $dot($opt['token']) }}"></span>
                                                            {{ $opt['label'] }}
                                                            <span class="font-normal text-gray-400">· Token-Last {{ $badgeWord($opt['token']) }}</span>
                                                        </div>
                                                        <div class="ms-3.5"><span class="font-medium text-green-700">Pro:</span> {{ $opt['pro'] }}</div>
                                                        <div class="ms-3.5"><span class="font-medium text-rose-700">Contra:</span> {{ $opt['con'] }}</div>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </div>

                                    <x-input-error :messages="$errors->get('overrides.'.$key)" class="mt-1" />
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                <div class="bg-white rounded-lg shadow p-6">
                    <h4 class="font-semibold text-gray-700 mb-2">Aktive Client-Hinweise</h4>
                    <template x-if="liveHints().length">
                        <div>
                            <p class="text-sm text-gray-500 mb-2">Diese Abweichungen von den Standards übermittelt der Server dem Skill (er übernimmt sie bei Drift):</p>
                            <ul class="text-sm font-mono text-gray-600 space-y-1">
                                <template x-for="h in liveHints()" :key="h.key">
                                    <li x-text="h.key + ' = ' + h.value"></li>
                                </template>
                            </ul>
                        </div>
                    </template>
                    <template x-if="! liveHints().length">
                        <p class="text-sm text-gray-500">Keine — der Client nutzt seine eingebauten Standards (nichts Zusätzliches im Kontext).</p>
                    </template>

                    <div class="mt-5 border-t border-gray-100 pt-5">
                        <x-input-label for="skill_description" value="Skill-Anweisungen (SKILL.md)" />
                        <p class="mt-1 mb-2 text-xs text-gray-400">
                            Diese Anweisungen erhält der Skill. Änderungen erhöhen die Konfigurationsversion
                            (aktuell <span class="font-mono">v{{ $project->config_version }}</span>) und werden vom Skill bei Drift automatisch
                            nachgeladen (Endpunkt <span class="font-mono">/config → instructions</span>).
                            <span class="font-mono">@{{alias}}</span> und <span class="font-mono">@{{name}}</span> werden durch Kürzel und Name ersetzt.
                        </p>
                        <textarea id="skill_description" name="skill_description" rows="14" spellcheck="false"
                                  class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono text-xs"
                                  placeholder="# Skill&#10;&#10;Anweisungen für dieses Projekt …">{{ old('skill_description', $skillText) }}</textarea>
                        <x-input-error :messages="$errors->get('skill_description')" class="mt-2" />
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <a href="{{ route('projects.show', $project) }}" class="text-sm text-gray-500 hover:text-gray-700">Abbrechen</a>
                    <x-primary-button>Speichern</x-primary-button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Segmentierte Auswahl: hält die explizite Wahl je Feld ('' = Profil-Standard),
        // berechnet die live-aktualisierten Standardwerte beim Preset-Wechsel und den
        // geschätzten Tokenverbrauch relativ zur Minimalconfig.
        window.claudeConfig = function (init) {
            const dots = { g: 'bg-green-500', y: 'bg-amber-500', r: 'bg-red-500' };
            return {
                profile: init.profile,
                presets: init.presets,
                defaults: init.defaults,
                meta: init.meta,
                values: init.values,
                costs: init.costs,
                hintKeys: init.hintKeys,
                select(key, val) { this.values[key] = val; },
                isSelected(key, val) { return (this.values[key] ?? '') === val; },
                // Normalisierter Shipped-Default (ohne Profil) zum Vergleich.
                shippedNorm(key) {
                    const m = this.meta[key];
                    const v = this.defaults[key];
                    if (m && m.type === 'bool') return (v === true || v === '1' || v === 1) ? '1' : '0';
                    return String(v);
                },
                // Client-Hinweise = effektive Werte, die vom Shipped-Default abweichen.
                liveHints() {
                    const out = [];
                    for (const key of this.hintKeys) {
                        const eff = this.effNorm(key);
                        if (eff !== this.shippedNorm(key)) {
                            const m = this.meta[key];
                            const value = (m && m.type === 'bool') ? (eff === '1' ? 'true' : 'false') : eff;
                            out.push({ key, value });
                        }
                    }
                    return out;
                },
                // Effektiver (normalisierter) Wert eines Feldes: explizite Wahl,
                // sonst der Profil-/Default-Wert.
                effNorm(key) {
                    const v = this.values[key] ?? '';
                    return v !== '' ? String(v) : this.defaultNorm(key);
                },
                tokenIndex() {
                    let s = 0;
                    for (const k in this.costs) { s += (this.costs[k][this.effNorm(k)] ?? 0); }
                    return s;
                },
                tokenBounds() {
                    let mn = 0, mx = 0;
                    for (const k in this.costs) {
                        const vals = Object.values(this.costs[k]);
                        mn += Math.min(...vals); mx += Math.max(...vals);
                    }
                    return { mn, mx };
                },
                tokenRatio() {
                    const { mn } = this.tokenBounds();
                    return mn > 0 ? this.tokenIndex() / mn : 1;
                },
                tokenPct() {
                    const { mn, mx } = this.tokenBounds();
                    return mx > mn ? Math.round((this.tokenIndex() - mn) / (mx - mn) * 100) : 0;
                },
                tokenBarClass() {
                    const p = this.tokenPct();
                    return p < 20 ? 'bg-green-500' : (p < 55 ? 'bg-amber-500' : 'bg-red-500');
                },
                dotClass(token) { return dots[token] || 'bg-gray-400'; },
                defaultVal(key) {
                    const preset = this.presets[this.profile] || {};
                    return (preset[key] !== undefined) ? preset[key] : this.defaults[key];
                },
                defaultNorm(key) {
                    const m = this.meta[key];
                    const v = this.defaultVal(key);
                    if (!m || m.type === 'int') return String(v);
                    return m.type === 'bool'
                        ? ((v === true || v === '1' || v === 1) ? '1' : '0')
                        : String(v);
                },
                defaultOptLabel(key) {
                    const m = this.meta[key];
                    if (!m || m.type === 'int') return String(this.defaultVal(key));
                    const opt = m.options[this.defaultNorm(key)] || {};
                    return opt.label || String(this.defaultVal(key));
                },
                defaultToken(key) {
                    const m = this.meta[key];
                    if (!m || m.type === 'int') return 'n';
                    const opt = m.options[this.defaultNorm(key)] || {};
                    return opt.token || 'n';
                },
            };
        };
    </script>
</x-app-layout>
