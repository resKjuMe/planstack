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
| `GET /config` | Konfig + `operating_manual` + `status_rules` |

In Task-Pfaden ist `{id}` **auch per Task-Name** ansprechbar (z. B. `.../tasks/C27/claim`) — kein separater name→id-Lookup nötig.

Beim Anlegen (`POST /tasks`) **immer** `affected_files` (geschätzte Dateianzahl) mitgeben — verbindliche Konvention, serverseitig aber **nicht** validiert (nur ein Hinweis).

Fehler: `401` Token · `403` Zugriff · `404` fehlt · `409` Kollision · `422` `errors` lesen. **Nur die API ist die Wahrheit** — keine lokalen Zustandsdateien.
