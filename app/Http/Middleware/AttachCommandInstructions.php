<?php

namespace App\Http\Middleware;

use App\Support\SkillTemplate;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Haengt die Anleitung eines Sub-Kommandos an die Antwort des Aufrufs, ohne den das
 * Kommando nicht stattfinden kann (`review-next`/`review-claim` fuer review,
 * `next-action` fuer auto, der Task-Read fuer fix).
 *
 * Warum am Pflicht-Call und nicht in der SKILL.md:
 *  - Token: ein Lauf zahlt nur die Anleitung, die er ausfuehrt. `review` laedt nicht
 *    den Auto-Modus mit, `work` nicht die Review-Prozedur.
 *  - Verlaesslichkeit: eine lokale Datei kann uebersprungen werden, dieser Aufruf
 *    nicht — er IST das Kommando. Damit gibt es keinen Pfad, auf dem die Anleitung
 *    fehlt, anders als bei nachzulesenden Referenzdateien.
 *  - Drift: der Server schickt immer den aktuellen Text. Fuer diese Abschnitte kann
 *    es keine veraltete Kopie mehr geben (die Fehlerklasse „PR-Titel ohne
 *    Projekt-Kuerzel" ist hier strukturell ausgeschlossen).
 *
 * Ausgeloest wird es vom Header `X-Planstack-Session`, dessen erstes Wort das
 * laufende Kommando nennt (`review DCE/A1`). Nur der allgemeine planstack-Skill
 * sendet ihn — die projektgebundenen Skills (L2LR/LOG) rufen dieselben Endpunkte
 * ohne Header auf und bekommen dadurch nichts zusaetzlich in die Antwort.
 */
class AttachCommandInstructions
{
    /** Feld, unter dem die Anleitung in der JSON-Antwort landet. */
    public const FIELD = 'command_instructions';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $command = self::commandFrom($request);

        if ($command === null || ! $response instanceof JsonResponse) {
            return $response;
        }

        $instructions = SkillTemplate::commandInstructions($command);

        if ($instructions === '') {
            return $response;
        }

        $payload = $response->getData(true);

        // Nur bei Objekt-Antworten: eine Listen-Antwort wuerde durch einen
        // Zeichenketten-Schluessel ihre Form verlieren.
        if (! is_array($payload) || array_is_list($payload)) {
            return $response;
        }

        $payload[self::FIELD] = $instructions;
        $response->setData($payload);

        return $response;
    }

    /**
     * Das laufende Sub-Kommando aus dem Session-Header (erstes Wort, klein
     * geschrieben), oder null wenn der Header fehlt bzw. kein bekanntes Kommando
     * nennt.
     */
    private static function commandFrom(Request $request): ?string
    {
        $header = trim((string) $request->header(TrackClaimSession::HEADER, ''));

        if ($header === '') {
            return null;
        }

        $command = strtolower(strtok($header, " \t") ?: '');

        return in_array($command, SkillTemplate::COMMANDS, true) ? $command : null;
    }
}
