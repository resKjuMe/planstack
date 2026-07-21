## Betriebshandbuch (serverseitig gepflegt, gilt für alle Projekte)

**Zyklus:** `claim-next` (bester Pick **und** Claim in einem, spart Roundtrips/Tokens) → `analyze` → (`concern`) | (`in_progress` → PR → `done` → `merge`). Board neu lesen gemäß `reread.policy`. Alternativ manuell: `GET /board` → bester Pick → `POST /claim`.

Endpunkte unter `$BASE/projects/$PROJ`, Aufruf mit `curl -s "${AUTH[@]}"`:

| Methode / Pfad | Zweck |
|---|---|
| `POST /claim-next` | besten pickbaren Task atomar beanspruchen; Antwort = geclaimter Task (nach `task.fields`), `{"claimed":null}` wenn nichts pickbar |
| `POST /review-next` | nächsten in-review Task mit PR zum Review übernehmen (setzt `reviewed_by`); `{"reviewing":null}` wenn nichts ansteht |
| `POST /tasks/{id}/review-claim` · `/review {recommendation,summary}` | Review übernehmen · Ergebnis erfassen (`APPROVE`/`REQUEST_CHANGES`) |
| `GET /board` | pickable nach `unlocks`; Antwort trägt die Versions-Header |
| `GET /tasks` · `/tasks/{id}` | Details (`summary`, `acceptance_criteria`, `prerequisites`) |
| `POST /tasks/{id}/claim` · `/release` | beanspruchen (`409`→anderen) · freigeben |
| `POST /tasks/{id}/status {status}` | `analyze\|in_progress\|in_review\|done` |
| `POST /tasks/{id}/pr {pr_number}` · `/merge` | PR setzen · mergen (idempotent) |
| `POST /tasks/{id}/complete {pr_number?,merge?}` | gebündelt (bei `actions.bundling`) |
| `POST /tasks/{id}/concern {summary,…}` · `DELETE …/concern` | melden · auflösen |
| `POST /tasks/{id}/gate {gate}` · `/split {children}` | Gate setzen · splitten |
| `POST /phases` · `POST /tasks` | anlegen |
| `POST $BASE/events {task_id,event}` | Fortschritts-Event melden (top-level, **nicht** unter `/projects`; `task_id` numerisch) |
| `GET /config` | Konfig + `operating_manual` + `status_rules` |

In Task-Pfaden ist `{id}` **auch per Task-Name** ansprechbar (z. B. `.../tasks/C27/claim`) — kein separater name→id-Lookup nötig.

**Fortschritts-Events (best-effort, nicht blockierend):** Während der Abarbeitung den Fortschritt melden — `POST $BASE/events {task_id,event}` (top-level, **nicht** unter `/projects`). Zweck: die Organisation kann je Event einen Statuswechsel und/oder Feld-Automationen hinterlegen; ohne Konfiguration ist es eine reine Meldung. **Immer fire-and-forget** — Fehler ignorieren, den Ablauf nie blockieren, nicht in Prosa berichten. `task_id` ist die **numerische** id (aus der Claim-/Task-Antwort). Bequemer Helfer (`$BASE`/`AUTH` stammen aus dem Zugang):

```bash
ev(){ curl -s "${AUTH[@]}" -X POST "$BASE/events" -d "{\"task_id\":$1,\"event\":\"$2\"}" >/dev/null 2>&1 || true; }
```

Zuordnung Zyklus → Event (jeweils sobald die id bekannt ist):

- vor dem Claim `CLAIMING` (nur wenn die id schon bekannt ist, z. B. Task-Modus), nach dem Claim `CLAIMED`
- Analyse: `ANALYZING` (Start) → `ANALYZED` (Ende)
- Umsetzung: `PROCESSING` (Start) → `PROCESSED` (Ende)
- PR erstellen: `PUBLISHING`
- Politur/Fix (CI grün, Kommentare beantwortet): `POLISHING` (Start) → `POLISHED` (reviewbar)
- Concern gemeldet: `CONCERNED`

Der `MERGED`-Event wird **nicht** vom Skill gemeldet, sondern serverseitig beim „Sync". Über MCP: Tool `emit_event {task,event}` (nimmt Task-Name **oder** id).

**Ereignisgesteuerter Status (org-abhängig):** Hat die Organisation Fortschritts-Events mit einer Status-Zuweisung hinterlegt (erkennbar am Abschnitt „Ereignis-gesteuerte Status-Zuweisung" in `status_rules`), setzt der Server den Status **aus diesen Events**. In diesem Fall die verdrahteten direkten Statuswechsel `POST /tasks/{id}/status` (`analyze`/`in_progress`/`in_review`/`done`) **weglassen** — sie würden den per Event zugewiesenen Status wieder überschreiben. `claim`/`claim-next`, `pr`, `merge`, `concern` und `split` bleiben unverändert. Ohne solche Automationen gilt der normale Zyklus mit direkten Statuswechseln.

**Feldumfang gezielt erzwingen:** An jeden Task-Read lässt sich `?fields=full` (oder `minimal`/`standard`) hängen — das überschreibt für **diese eine Anfrage** den Projekt-Knopf `task.fields`. So bekommt man die **vollen Details** eines Tasks (z. B. `GET /tasks/C27?fields=full`), auch wenn das Projekt sonst sparsam liefert.

Beim Anlegen (`POST /tasks`) **immer** `affected_files` (geschätzte Dateianzahl) mitgeben — verbindliche Konvention, serverseitig aber **nicht** validiert (nur ein Hinweis).

Fehler: `401` Token · `403` Zugriff · `404` fehlt · `409` Kollision · `422` `errors` lesen. **Nur die API ist die Wahrheit** — keine lokalen Zustandsdateien.
