## Betriebshandbuch (serverseitig gepflegt, gilt für alle Projekte)

**Zyklus:** Board → bester Pick → `claim` → `analyze` → (`concern`) | (`in_progress` → PR → `done` → `merge`). Board neu lesen gemäß `reread.policy`.

Endpunkte unter `$BASE/projects/$PROJ`, Aufruf mit `curl -s "${AUTH[@]}"`:

| Methode / Pfad | Zweck |
|---|---|
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

Fehler: `401` Token · `403` Zugriff · `404` fehlt · `409` Kollision · `422` `errors` lesen. **Nur die API ist die Wahrheit** — keine lokalen Zustandsdateien.
