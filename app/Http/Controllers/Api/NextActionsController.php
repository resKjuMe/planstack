<?php

namespace App\Http\Controllers\Api;

use App\Http\Middleware\AttachPlanstackConfig;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\Task;
use App\Support\NextActionResolver;
use App\Support\TaskBoardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * POST /api/projects/{project}/next-actions — mehrere Arbeitseinheiten für ebenso
 * viele PARALLELE Worker in einem Aufruf bestimmen und atomar reservieren. Die
 * Mehrzahl-Fassung von {@see NextActionController}: gleiche Priorität (fix → review
 * → work), gleiche Reservierungen, nur eben `count` Einheiten auf verschiedenen
 * Tasks.
 *
 * Warum ein eigener Endpunkt statt N Aufrufen von `next-action`:
 *  - **Tokens/Roundtrips:** ein Aufruf statt N, und die (mehrere KB große)
 *    Kommando-Anleitung kommt einmal mit statt N-mal.
 *  - **Keine Doppelvergabe:** der Ausschluss innerhalb des Stapels passiert
 *    serverseitig ({@see NextActionResolver::resolveMany()}); zwischen zwei
 *    Einzelaufrufen liegt dagegen immer ein Fenster, in dem der Supervisor selbst
 *    aufpassen müsste.
 *  - **Ein Board pro Stapel:** die teure Pickbarkeits-Rechnung läuft einmal für alle
 *    Einheiten (memoisiert im Resolver) statt N-mal.
 *
 * Die Antwortform ist bewusst eine Liste unter `data` — `next-action` (Einzahl)
 * bleibt unverändert, damit ältere Skills weiterlaufen.
 */
class NextActionsController extends ApiController
{
    /** Absolute Obergrenze — dieselbe Spanne, die die Projektconfig zulässt. */
    private const MAX = 32;

    public function __construct(
        private readonly NextActionResolver $resolver,
        private readonly TaskBoardService $board,
    ) {}

    public function __invoke(Request $request, Project $project): JsonResponse
    {
        $this->authorize('contribute', $project);

        $request->validate(['count' => 'sometimes|integer|min:1|max:'.self::MAX]);

        // Die Projektconfig ist die Obergrenze für DIESES Board (Knopf „Max. Worker"):
        // wie viele Worker eine Maschine fahren kann, weiß nur der Client — wie viele
        // dieses Projekt verträgt, nur das Projekt.
        $max = max(1, min(self::MAX, (int) AttachPlanstackConfig::value($request, 'parallelism.max_workers')));
        $requested = (int) $request->input('count', $max);
        $count = min($requested, $max);

        $units = $this->resolver->resolveMany($project, $request->user(), $count);

        // Einmal für den ganzen Stapel — die Board-Dekoration lädt alle Tasks des
        // Projekts samt Gates; das je Einheit zu wiederholen wäre der teuerste Teil
        // der Antwort. Nach den Reservierungen gelesen, also mit deren Wirkung drin.
        $board = $units === [] ? null : $this->board->board($project);

        return response()->json([
            'data' => array_map(
                fn (array $unit): array => [
                    'action' => $unit['action'],
                    'reason' => $unit['reason'],
                    // Das Label, das GENAU DIESER Worker in X-Planstack-Session senden
                    // muss: damit trifft sein Heartbeat sein eigenes Lease, und das
                    // Board zeigt von Anfang an den richtigen Slot am richtigen Task.
                    'session' => $unit['session'],
                    'task' => $this->taskPayload($request, $board, $unit['task']),
                ],
                $units,
            ),
            'workers' => count($units),
            'requested' => $requested,
            'max_workers' => $max,
        ]);
    }

    /**
     * Der Task mit derselben Board-Dekoration/Relationen wie bei den übrigen
     * Task-Endpunkten, damit ein Worker ohne Nachlesen starten kann — PR immer dabei
     * (der `fix`/`review`-Flow muss ihn adressieren können).
     *
     * @param  Collection<int, Task>|null  $board
     * @return array<string, mixed>
     */
    private function taskPayload(Request $request, ?Collection $board, Task $task): array
    {
        $decorated = $board?->firstWhere('id', $task->id) ?? $task;
        $decorated->loadMissing(['phase', 'claimer', 'concern', 'reviewer', 'prerequisites.orgStatus', 'pullRequests']);

        $resource = new TaskResource($decorated);
        $resource->alwaysIncludePr = true;

        return $resource->toArray($request);
    }
}
