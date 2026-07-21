# Event API

## Definition

`POST /api/events`
```json
{
    "task_id": 123,
    "event": "{EVENT_NAME}"
}
```

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

## Organisations-Verwaltung

Je Event kann ein Status definiert werden, in den die Aufgabe geschoben wird. 
Bleibt die Auswahl leer, verändert sich der Status nicht.
Zusätzlich gibt es eine Auswahl, welche Status, die die Aufgabe gerade hat, überschrieben werden können.

Außerdem können je Event beliebig viele Felder der Aufgabe befüllt werden.
Die Automatisierungen der gewählten Spalte werden hier als readonly angezeigt.
