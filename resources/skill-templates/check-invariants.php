<?php

declare(strict_types=1);

/**
 * Prueft die Skill-Vorlagen gegen die Invarianten, die tests/Feature/Api/SkillEndpointTest.php
 * am ausgelieferten Text festnagelt — ohne Laravel, Datenbank oder HTTP.
 *
 * Zweck: Die Vorlagen werden regelmaessig gekuerzt und umsortiert. Faellt dabei eine
 * Direktive heraus, merkt man das sonst erst in der CI (oder, schlimmer, im naechsten
 * Auto-Lauf). Dieses Skript laeuft ueberall, wo PHP vorhanden ist:
 *
 *   php resources/skill-templates/check-invariants.php
 *
 * Geprueft wird alles, was den Client erreicht: die ausgelieferte SKILL.md (wie
 * SkillTemplate::composed()) UND die kommandospezifischen Anleitungen unter
 * commands/, die zur Laufzeit an den jeweiligen Pflicht-Call gehaengt werden. Wo eine
 * Direktive steht, ist damit frei; DASS sie ankommt, ist die Zusicherung. Zusaetzlich
 * wird der umgekehrte Fall geprueft — Endpunkte, die der Text nennt, muessen in
 * routes/api.php wirklich existieren.
 */
$dir = __DIR__;

// Was der Client insgesamt bekommt: die ausgelieferte SKILL.md (wie
// SkillTemplate::composed()) PLUS die kommandospezifischen Anleitungen, die zur
// Laufzeit an den Pflicht-Call gehaengt werden. Geprueft wird die Vereinigung —
// entscheidend ist, DASS eine Direktive ankommt, nicht wo sie steht.
$parts = ['planstack.md', 'operating-manual.md', 'status-rules.md', 'skill-instructions.md'];
$commands = ['commands/review.md', 'commands/fix.md', 'commands/auto.md'];

$bundle = '';
$composed = '';

foreach ([...$parts, ...$commands] as $file) {
    $path = $dir.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $file);

    if (! is_file($path)) {
        fwrite(STDERR, "FEHLT: {$file}\n");
        exit(1);
    }

    $content = rtrim((string) file_get_contents($path))."\n\n";
    $bundle .= $content;

    if (! in_array($file, $commands, true)) {
        $composed .= $content;
    }
}

/** @var list<array{0: string, 1: string}> Literale, die im Buendel vorkommen MUESSEN. */
$required = [
    // PR-Titel-Konvention: das Projekt-Kuerzel ist schon einmal aus einer veralteten
    // Kopie gefallen, seither ist es festgenagelt.
    ['<PROJECT>-<TASK>: <Kurzbeschreibung>', 'PR-Titel-Konvention'],
    ['Snapshot mitschreiben', 'Snapshot-Pflicht beim Baseline-Schreiben'],

    // Statuszeile: ohne diese vier ist der Auto-Modus blind bzw. die Zeile kaputt.
    ['Sticky-Statuszeile', 'Statuszeilen-Kapitel'],
    ['<Symbol> <Kommando> (<Phase> <%>) <PROJECT> · <TASK> — <kurzer Schritt>', 'Statuszeilen-Format'],
    ['planstack-status-<session_id>.txt', 'session-gebundene Zustandsdatei'],
    ['refreshInterval', 'refreshInterval-Hinweis'],
    ['OSC 8', 'OSC-8-Links'],

    // Prozentzeichen-Regel samt sicherem Schreibweg.
    ['nie `%%`', 'Regel gegen das doppelte Prozentzeichen'],
    ["printf '%s\\n'", 'sicherer Schreibweg fuer die Statuszeile'],

    // Parallele Worker: jede dieser vier Angaben verhindert eine Kollisionsart.
    // Faellt eine heraus, laufen die Worker sich gegenseitig ins Messer — und zwar
    // still, das Board sieht dabei bis zum Schluss plausibel aus.
    ['auto_workers', 'Einstellung fuer die Worker-Anzahl'],
    ['planstack-status-<session_id>.w<k>.txt', 'Slot-Datei je Worker'],
    ['git -C <repo> worktree add', 'eigenes Arbeitsverzeichnis je Worker'],
    ['next-actions', 'Stapel-Endpunkt fuer mehrere Worker'],
];

$errors = [];

foreach ($required as [$needle, $label]) {
    if (! str_contains($bundle, $needle)) {
        $errors[] = "fehlt: {$label} — erwartetes Literal: {$needle}";
    }
}

// Der Anleitungstext selbst darf keine Prozentangabe verdoppeln: die Beispielzeilen
// werden abgeschrieben, ein "44 %%" darin lehrt genau den Fehler, den die Regel verbietet.
if (preg_match('/\d\s*%%/', $bundle, $hit) === 1) {
    $errors[] = 'verdoppeltes Prozentzeichen in einer Beispielzeile: '.$hit[0];
}

// Die kommandospezifischen Anleitungen duerfen NICHT zusaetzlich in der
// ausgelieferten SKILL.md stehen: dann waere die Verlagerung wirkungslos (der Text
// zaehlt weiter zum Prolog jedes Aufrufs) und es gaebe wieder zwei Fassungen, die
// auseinanderlaufen koennen.
foreach (['## Review (`/planstack review', '## Fix (`/planstack fix', '## Auto-Modus (`/planstack auto'] as $heading) {
    if (str_contains($composed, $heading)) {
        $errors[] = 'Kommando-Abschnitt steht doppelt (auch in der SKILL.md): '.$heading;
    }
}

// Und der Bootstrap-Teil muss erklaeren, woher sie stattdessen kommen — sonst weiss
// ein Lauf nicht, dass er auf das Feld warten bzw. es nachladen kann.
foreach (['command_instructions', '?parts=skill_instructions'] as $needle) {
    if (! str_contains($composed, $needle)) {
        $errors[] = 'Bootstrap erklaert die Laufzeit-Anleitung nicht: '.$needle.' fehlt in der SKILL.md';
    }
}

// ---------------------------------------------------------------------------
// Endpunkt-Abgleich: jeder im Skill-Text genannte API-Pfad muss es wirklich geben.
//
// Der Skill ist Prosa — ein erfundener Pfad ("POST /next-work") faellt sonst erst
// zur Laufzeit als 404 auf, und der Skill baut die Funktion clientseitig nach.
// Geprueft wird gegen routes/api.php, ohne Laravel zu booten: die Route-Strings
// stehen dort literal.
// ---------------------------------------------------------------------------
$routesFile = dirname($dir, 2).DIRECTORY_SEPARATOR.'routes'.DIRECTORY_SEPARATOR.'api.php';
$routes = is_file($routesFile) ? (string) file_get_contents($routesFile) : '';

if ($routes === '') {
    fwrite(STDERR, "FEHLT: routes/api.php — Endpunkt-Abgleich nicht moeglich\n");
    exit(1);
}

// Alle Route-Definitionen: Route::post('projects/{project}/next-action', …)
preg_match_all(
    "/Route::(?:get|post|put|patch|delete|match)\s*\(\s*(?:\[[^\]]*\]\s*,\s*)?'([^']+)'/",
    $routes,
    $routeHits,
);

// Alle festen Segmente, die in irgendeiner Route vorkommen (projects, board,
// claim-next, by-pr …). Daraus leitet sich ab, was ein Platzhalter ist: jedes
// Segment, das NICHT hier steht, ist ein Wert — egal ob als `{task}`, `$TASK`,
// `<pr>` oder als konkretes Beispiel wie `C27` geschrieben. So bleibt die Pruefung
// automatisch aktuell, wenn Routen dazukommen.
$literalSegments = [];
foreach ($routeHits[1] as $uri) {
    foreach (explode('/', trim($uri, '/')) as $segment) {
        if ($segment !== '' && ! str_starts_with($segment, '{')) {
            $literalSegments[$segment] = true;
        }
    }
}

/** Pfad auf ein vergleichbares Muster bringen: jeder Wert wird zu {}. */
$normalise = static function (string $path) use ($literalSegments): string {
    $path = (string) preg_replace('/\?.*$/', '', $path);
    $out = [];

    foreach (explode('/', trim($path, '/')) as $segment) {
        if ($segment === '') {
            continue;
        }

        $out[] = isset($literalSegments[$segment]) ? $segment : '{}';
    }

    return '/'.implode('/', $out);
};

$known = [];
foreach ($routeHits[1] as $uri) {
    $known[$normalise($uri)] = true;
}

// Pfade, die im Skill-Text auftauchen — ueber den GESAMTEN Text, nicht nur ueber
// Backtick-Spannen: die Paarung `([^`]+)` verschiebt sich an jedem Code-Fence (```)
// und verschluckt dann genau die Treffer, die geprueft werden sollen. Der Prefix
// (/projects, /tasks …) ist eng genug, dass Prosa keine Treffer erzeugt.
$referenced = [];

preg_match_all(
    '#(?:\$BASE|\$\{BASE\})?(/(?:projects|tasks|events|skill|user|status-config)[A-Za-z0-9_{}<>/$.-]*)#',
    $bundle,
    $paths,
);

foreach ($paths[1] as $path) {
    $path = rtrim($path, '.,;:');
    $normalised = $normalise($path);

    if ($normalised === '' || $normalised === '/') {
        continue;
    }

    $referenced[$normalised][] = $path;
}

foreach ($referenced as $normalised => $originals) {
    if (isset($known[$normalised])) {
        continue;
    }

    // Relative Task-Pfade im Betriebshandbuch (`/tasks/{id}/claim`) sind als
    // "unter $BASE/projects/$PROJ" dokumentiert — auch so pruefen.
    if (isset($known[$normalise('projects/{}'.$normalised)])) {
        continue;
    }

    $errors[] = 'unbekannter Endpunkt im Skill-Text: '.$originals[0]
        .' (normalisiert '.$normalised.') — in routes/api.php nicht gefunden';
}

$bytes = strlen($bundle);
$approxTokens = (int) round($bytes / 4);

if ($errors !== []) {
    fwrite(STDERR, "Skill-Invarianten verletzt:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, "  - {$error}\n");
    }
    exit(1);
}

printf(
    'Skill-Invarianten ok (%d Pruefungen) — Buendel %s Bytes, ~%s Token%s',
    count($required) + 1,
    number_format($bytes, 0, ',', '.'),
    number_format($approxTokens, 0, ',', '.'),
    PHP_EOL,
);
