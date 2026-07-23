<?php

namespace App\Concerns;

use App\Support\NotificationBroadcaster;

/**
 * Generisches Entity-Broadcasting: jedes Modell, das diesen Trait nutzt, meldet
 * created/updated/deleted über Pusher an den Organisations-Channel, worauf der
 * geteilte React-Store die betroffene Entity partiell nachlädt. Ein Modell
 * beschreibt über {@see entityChangeScope()}, ALS WAS es gemeldet wird (Typ + id,
 * den der Client nachladen kann) und in welchem Projekt/welcher Organisation.
 *
 * Abhängige Modelle (z. B. Concern, PR) melden sich bewusst als ihr Eltern-Task,
 * sodass der Client einfach den Task neu lädt (der die Relation mitbringt) — der
 * Store kennt nur wenige Top-Level-Typen (task, phase, project).
 *
 * "Best effort": Fehler im Broadcast brechen die Anfrage nie ab (siehe
 * NotificationBroadcaster). Ist Pusher nicht konfiguriert, passiert nichts.
 */
trait BroadcastsEntityChange
{
    public static function bootBroadcastsEntityChange(): void
    {
        static::created(fn ($model) => $model->emitEntityChange('insert'));
        static::updated(fn ($model) => $model->emitEntityChange('update'));
        static::deleted(fn ($model) => $model->emitEntityChange('delete'));
    }

    public function emitEntityChange(string $action): void
    {
        $scope = $this->entityChangeScope();

        if ($scope === null || ($scope['organization_id'] ?? null) === null) {
            return;
        }

        app(NotificationBroadcaster::class)->broadcastEntity($scope['organization_id'], [
            'type' => 'entity-changed',
            'entity' => $scope['entity'],
            'id' => $scope['id'],
            'action' => $action,
            'project_id' => $scope['project_id'] ?? null,
            'project_alias' => $scope['project_alias'] ?? null,
            'organization_id' => $scope['organization_id'],
        ]);
    }

    /**
     * Beschreibt, als welche Entity (Typ + nachladbare id) und in welchem
     * Projekt/welcher Organisation diese Änderung gemeldet wird. null = nicht
     * broadcasten (z. B. wenn das Projekt/die Organisation nicht auflösbar ist).
     *
     * @return array{entity: string, id: int|string, organization_id: int|null, project_id?: int|null, project_alias?: string|null}|null
     */
    abstract public function entityChangeScope(): ?array;
}
