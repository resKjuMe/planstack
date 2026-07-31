<?php

namespace App\Support;

use App\Models\Project;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Datenbasis der Aktivitäts-Heatmap auf der Performance-Unterseite: wie viele
 * Statusupdates es je Kalendertag und Stunde gab — auf Wunsch je Mitarbeiter.
 *
 * Wie die Zeitachse ein eigener, schlanker Endpunkt statt einer Ableitung aus dem
 * geteilten Tasks-Store: der Task kennt nur seinen jetzigen Status, die ZEITPUNKTE
 * der Wechsel stehen im Änderungsprotokoll (siehe {@see TaskStatusHistory}).
 *
 * Gebucketet wird SERVERSEITIG in der Zeitzone des Betrachters (Query-Parameter
 * `tz`): „14 Uhr" soll die Uhrzeit sein, zu der die Person gearbeitet hat, nicht die
 * UTC-Zeit der Datenbank. Nur die fertigen Zähler gehen über die Leitung — der
 * Client rendert das Raster, ohne selbst Zeitzonen zu rechnen (halbstündige
 * Zonen-Offsets wie +05:30 fielen bei einer Umrechnung im Client zwischen zwei
 * Stundenkästchen).
 *
 * Der Verursacher kommt als ID je Kästchen mit, nicht als eigenes Raster je Person:
 * so kann der Client sowohl summieren (Vorgabe) als auch auf eine Person filtern,
 * ohne nachzuladen. Updates OHNE protokollierten Verursacher (Konsole, Automationen)
 * tragen `actor: null` — sie zählen in der Summe mit, gehören aber zu niemandem.
 *
 * Ebenso mit der Status-FAMILIE des gesetzten Ziel-Status (`group`), aus der der
 * Client den Farbton des Kästchens wählt. Gruppiert wird über `kind` der
 * Org-Status-Konfiguration, nicht über eine Liste von Schlüsseln: eigene Status einer
 * Organisation („Polishing", „Request Changes") fallen damit automatisch in die
 * richtige Familie, ohne dass hier jemand nachpflegt.
 *
 * Die Antwort ist SPARSE: nur Stunden mit mindestens einem Update. Ein voller
 * 26-Wochen-Raster hätte 4.368 Kästchen, davon fast alle leer.
 */
class StatusActivityPresenter
{
    /** Vorgabe-Fenster: so weit zurück, wie die Ansicht maximal zeigen kann. */
    public const DEFAULT_DAYS = 182;

    private const MIN_DAYS = 7;

    private const MAX_DAYS = 366;

    /**
     * Status-Familie je `kind` der Org-Status-Konfiguration. Alles Übrige (wartend,
     * fertig, Ausnahme, unbekannter Status) ist „sonstiges" — der Farbton sagt dann
     * neutral „hier ist etwas passiert, aber weder Bearbeitung noch Review".
     *
     * @var array<string, string>
     */
    private const GROUP_BY_KIND = [
        'active' => 'work',
        'review' => 'review',
    ];

    public function __construct(private readonly TaskStatusHistory $history) {}

    /**
     * @return array{from: string, to: string, days: int, timezone: string, total: int, people: array<int, array{id: int, name: string, count: int}>, buckets: array<int, array{date: string, hour: int, actor: ?int, group: string, count: int}>}
     */
    public function payload(Project $project, int $days = self::DEFAULT_DAYS, ?string $timezone = null): array
    {
        $days = max(self::MIN_DAYS, min(self::MAX_DAYS, $days));
        $tz = $this->resolveTimezone($timezone);

        // Das Fenster endet JETZT und beginnt am Tagesanfang vor `days - 1` Tagen —
        // die letzte Spalte ist damit immer der heutige (laufende) Tag, gerechnet in
        // der Zone des Betrachters.
        $now = CarbonImmutable::now($tz);
        $from = $now->subDays($days - 1)->startOfDay();

        // Status-ID → Familie. Einmal je Antwort statt je Ereignis, und über `kind`
        // statt über Schlüssel-Listen (siehe GROUP_BY_KIND).
        $groupByStatus = $project->organization->statuses()->get()
            ->mapWithKeys(fn ($status) => [$status->id => self::GROUP_BY_KIND[$status->kind] ?? 'other']);

        /** @var array<string, array{date: string, hour: int, actor: ?int, group: string, count: int}> $buckets */
        $buckets = [];
        /** @var array<int, int> $perPerson */
        $perPerson = [];
        $total = 0;

        foreach ($this->history->changeEvents($project->tasks()->pluck('id'), $from) as $event) {
            $local = $event['at']->setTimezone($tz);
            $date = $local->format('Y-m-d');
            $actor = $event['actor_id'];
            // Ein inzwischen gelöschter Status ist nicht mehr einzuordnen — das
            // Update gab es trotzdem, es zählt also als „sonstiges" mit.
            $group = $groupByStatus[$event['status_id']] ?? 'other';

            $key = $date.'|'.$local->hour.'|'.($actor ?? '-').'|'.$group;
            $buckets[$key] ??= ['date' => $date, 'hour' => $local->hour, 'actor' => $actor, 'group' => $group, 'count' => 0];
            $buckets[$key]['count']++;

            if ($actor !== null) {
                $perPerson[$actor] = ($perPerson[$actor] ?? 0) + 1;
            }
            $total++;
        }

        return [
            'from' => $from->toIso8601String(),
            'to' => $now->toIso8601String(),
            'days' => $days,
            'timezone' => $tz,
            'total' => $total,
            'people' => $this->people($perPerson),
            'buckets' => array_values($buckets),
        ];
    }

    /**
     * Die Filterliste: wer im Fenster Statusupdates verursacht hat, der aktivste
     * zuerst. Ein inzwischen gelöschter Nutzer behält seine Zeile (die Updates gab
     * es), nur mit ID statt Namen — sonst verschwände Aktivität aus der Auswahl,
     * die in der Summe weiterhin steckt.
     *
     * @param  array<int, int>  $perPerson  actor-id → Anzahl
     * @return array<int, array{id: int, name: string, count: int}>
     */
    private function people(array $perPerson): array
    {
        if ($perPerson === []) {
            return [];
        }

        $names = User::whereKey(array_keys($perPerson))->pluck('name', 'id');

        $people = [];
        foreach ($perPerson as $id => $count) {
            $people[] = ['id' => $id, 'name' => (string) ($names[$id] ?? '#'.$id), 'count' => $count];
        }

        usort($people, fn (array $a, array $b) => [$b['count'], $a['name']] <=> [$a['count'], $b['name']]);

        return $people;
    }

    /**
     * Zeitzone des Betrachters, wenn sie eine gültige Kennung ist — sonst die der
     * App. Ein Client soll sich an einem Tippfehler nicht einen 500er einfangen.
     */
    private function resolveTimezone(?string $timezone): string
    {
        $timezone = trim((string) $timezone);

        if ($timezone === '' || ! in_array($timezone, timezone_identifiers_list(), true)) {
            return (string) config('app.timezone', 'UTC');
        }

        return $timezone;
    }
}
