# Event API

## Definition

Zwei gleichwertige Einstiege (identische Wirkung und Antwort):

`POST /api/events` — Task per numerischer id im Body:
```json
{
    "task_id": 123,
    "event": "{EVENT_NAME}"
}
```

`POST /api/projects/{project}/tasks/{task}/events` — projekt-gebunden, `{task}`
per Name **oder** id im Pfad (scopeBindings hält ihn aufs Projekt beschränkt):
```json
{
    "event": "{EVENT_NAME}"
}
```

Die projekt-gebundene Variante ist über **jedes** Projekt per REST erreichbar
(kein an ein einzelnes Projekt gebundener MCP-Server nötig) und nimmt den
Task-Namen, den der Client ohnehin kennt.

## Zweck

Der /planstack Skill sendet während der Abarbeitung Events an Planstack, um den Fortschritt der Abarbeitung zu informieren.

## Events

* CLAMING: Bevor Planstack eine Aufgabe beansprucht
* CLAIMED: Nachdem Planstack eine Aufgabe beansprucht hat
* ANALYZING: Die geclaimte Aufgabe wird analysiert
* ANALYZED: Die geclaimte Aufgabe wurde analysiert
* PROCESSING: Die geclaimte Aufgabe wird bearbeitet
* PROCESSED: Die geclaimte Aufgabe wurde bearbeitet
* PUBLISHING: Ein PR wird für die Aufgabe erstellt
* PUBLISHED: Ein PR wurde für die Aufgabe erstellt
* POLISHING: CI wird auf grün gebracht; unbeantwortete Kommentare/Review-Kommentare werden beantwortet, gefixt und anschließend resolved
* POLISHED: Die Aufgabe wurde poliert und ist nun reviewbar
* REVIEWING: Die Aufgabe wird gereviewed
* REVIEWED: Die Aufgabe wurde gereviewed
* APPROVED: Die Aufgabe wurde mit Approved markiert
* CHANGES_REQUESTED: Die Aufgabe wurde mit Changes Requested markiert
* MERGING_QUEUED: Die Aufgabe wird in den Merge-Queue eingereiht
* MERGING_FAILED: Bei der Merge-Operation ist ein Fehler aufgetreten
* MERGED: Die Aufgabe wurde gemerged
* DEPLOYED: Die Aufgabe wurde deployed
* CONCERNED: Die Aufgabe wurde mit Concerned markiert
* UNCLAIMING: Die Aufgabe wird von Planstack wieder freigegeben
* UNCLAIMED: Die Aufgabe wurde von Planstack wieder freigegeben

## Event-Propagation von /planstack

### Task Abarbeitung 
* CLAIMING
* CLAIMED
* ANALYZING
* ANALYZED
* PROCESSING
* PROCESSED
* PUBLISHING
* POLISHING
* POLISHED
* CONCERNED

### Review
* REVIEWING 
* REVIEWED
* APPROVED
* CHANGES_REQUESTED

## Event-Propagation vom "Sync"-Button
* MERGED
* APPROVED (später)
* CHANGES_REQUESTED (später)

## Antwort

`POST /api/events` liefert zurück, was das Event bewirkt hat — die Antwort ist maßgeblich, ein Client soll den Status **nie** aus dem Event-Namen herleiten:

```json
{
    "task_id": 123,
    "event": "POLISHED",
    "configured": true,
    "status_changed": true,
    "applied_fields": ["reviewed_by"],
    "status": "REVIEWBAR"
}
```

* `configured` — für das Event ist in der Org eine Automation hinterlegt (sonst reine Meldung/Log).
* `status_changed` — der Status wurde durch dieses Event geändert.
* `status` — der Status-Key **nach** dem Event (der tatsächliche aktuelle Status).
* `applied_fields` — welche Felder das Event zusätzlich befüllt hat.

`status_changed:false` bei einem statustreibenden Event ist **kein Fehler**, sondern heißt: der Guard (Override-Menge) passte nicht — der aktuelle Status stand nicht in der erlaubten Menge (typisch bei Events in falscher Reihenfolge oder fehlendem Vor-Event).

## Standard-Konfiguration (Guarded State Machine)

Eine neue Organisation startet mit dieser Event→Status-Zuordnung (`App\Support\DefaultTaskStatuses::EVENT_AUTOMATIONS`). Ein Event setzt seinen Zielstatus **nur**, wenn der aktuelle Status in der Guard-Menge liegt (`*` = aus jedem Status). Alle nicht aufgeführten Events (`CLAIMING`, `ANALYZED`, `PROCESSED`, `PUBLISHING`, `PUBLISHED`, `POLISHING`, `UNCLAIMING`, `REVIEWED`) haben **keinen** Zielstatus — sie sind reine Meldungen (No-op außer Log).

| Event | Zielstatus | Guard (nur wenn aktueller Status …) |
|---|---|---|
| `CLAIMED` | `CLAIMED` | `PICKABLE` |
| `ANALYZING` | `ANALYZING` | `CLAIMED` |
| `PROCESSING` | `IN_PROGRESS` | `ANALYZING` |
| `POLISHED` | `REVIEWBAR` | `IN_PROGRESS` |
| `REVIEWING` | `IN_REVIEW` | `REVIEWBAR` |
| `APPROVED` | `APPROVED` | `IN_REVIEW` |
| `CHANGES_REQUESTED` | `IN_PROGRESS` | `IN_REVIEW` |
| `MERGED` | `MERGED` | `APPROVED` |
| `DEPLOYED` | `COMPLETED` | `MERGED` |
| `CONCERNED` | `CONCERNED` | `*` |
| `UNCLAIMED` | `PICKABLE` | `*` |

## Organisations-Verwaltung

Je Event kann ein Status definiert werden, in den die Aufgabe geschoben wird. 
Bleibt die Auswahl leer, verändert sich der Status nicht.
Zusätzlich gibt es eine Auswahl, welche Status, die die Aufgabe gerade hat, überschrieben werden können (die Guard-Menge; leer = aus jedem Status).

Außerdem können je Event beliebig viele Felder der Aufgabe befüllt werden.
Die Automatisierungen der gewählten Spalte werden hier als readonly angezeigt.
