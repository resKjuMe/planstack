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

**Fortschritts-Events (best-effort, nicht blockierend):** Während der Abarbeitung den Fortschritt melden — `POST $BASE/events {task_id,event}` (top-level, **nicht** unter `/projects`). Zweck: die Organisation kann je Event einen Statuswechsel und/oder Feld-Automationen hinterlegen; ohne Konfiguration ist es eine reine Meldung. **Fire-and-forget gegenüber Fehlern** — Netzwerk-/HTTP-Fehler ignorieren, den Ablauf nie blockieren, das Absetzen nicht in Prosa berichten. `task_id` ist die **numerische** id (aus der Claim-/Task-Antwort). Bequemer Helfer (`$BASE`/`AUTH` stammen aus dem Zugang):

```bash
ev(){ curl -s "${AUTH[@]}" -X POST "$BASE/events" -d "{\"task_id\":$1,\"event\":\"$2\"}" 2>/dev/null || true; }
```

**Die Antwort ist maßgeblich (nicht selbst herleiten):** `POST /events` liefert `{configured, status_changed, status, applied_fields}` zurück. Liegt eine Antwort vor, ist ihr `status` der **tatsächliche** Status des Tasks nach dem Event — den Status **niemals** aus dem Event-Namen erraten. `status_changed:false` bei einem statustreibenden Event bedeutet nicht „Fehler", sondern dass der Guard nicht passte (der aktuelle Status stand nicht in der Override-Menge, s. `status_rules` → „Ereignis-gesteuerte Status-Zuweisung") — meist, weil ein vorheriges Event fehlte oder die Reihenfolge nicht stimmte. In dem Fall den zurückgemeldeten `status` akzeptieren, **nicht** dagegen anarbeiten. `configured:false` heißt: für dieses Event ist in der Org keine Automation hinterlegt — reine Meldung, kein Statuswechsel zu erwarten.

Zuordnung Zyklus → Event (jeweils sobald die id bekannt ist). **[S]** = treibt (bei Standard-Config) den Status, **[i]** = rein informativ/Log (kein Statuswechsel):

- vor dem Claim `CLAIMING` **[i]** (nur wenn die id schon bekannt ist, z. B. Task-Modus), nach dem Claim `CLAIMED` **[S]**
- Analyse: `ANALYZING` **[S]** (Start) → `ANALYZED` **[i]** (Ende)
- Umsetzung: `PROCESSING` **[S]** (Start) → `PROCESSED` **[i]** (Ende)
- PR erstellen: `PUBLISHING` **[i]**
- Politur/Fix (CI grün, Kommentare beantwortet): `POLISHING` **[i]** (Start) → `POLISHED` **[S]** (→ reviewbar)
- Concern gemeldet: `CONCERNED` **[S]**

Welche Events in **dieser** Organisation tatsächlich einen Status setzen (und unter welchem Guard), steht verbindlich im `status_rules`-Abschnitt „Ereignis-gesteuerte Status-Zuweisung" — die **[S]**/**[i]**-Marken hier sind nur die Standard-Config. Der `MERGED`-Event wird **nicht** vom Skill gemeldet, sondern serverseitig beim „Sync". Über MCP: Tool `emit_event {task,event}` (nimmt Task-Name **oder** id).

**Ereignisgesteuerter Status (org-abhängig):** Hat die Organisation Fortschritts-Events mit einer Status-Zuweisung hinterlegt (erkennbar am Abschnitt „Ereignis-gesteuerte Status-Zuweisung" in `status_rules`), setzt der Server den Status **aus diesen Events**. In diesem Fall die verdrahteten direkten Statuswechsel `POST /tasks/{id}/status` (`analyze`/`in_progress`/`in_review`/`done`) **weglassen** — sie würden den per Event zugewiesenen Status wieder überschreiben. `claim`/`claim-next`, `pr`, `merge`, `concern` und `split` bleiben unverändert. Ohne solche Automationen gilt der normale Zyklus mit direkten Statuswechseln.

**Feldumfang gezielt erzwingen:** An jeden Task-Read lässt sich `?fields=full` (oder `minimal`/`standard`) hängen — das überschreibt für **diese eine Anfrage** den Projekt-Knopf `task.fields`. So bekommt man die **vollen Details** eines Tasks (z. B. `GET /tasks/C27?fields=full`), auch wenn das Projekt sonst sparsam liefert.

Beim Anlegen (`POST /tasks`) **immer** `affected_files` (geschätzte Dateianzahl) mitgeben — verbindliche Konvention, serverseitig aber **nicht** validiert (nur ein Hinweis).

Fehler: `401` Token · `403` Zugriff · `404` fehlt · `409` Kollision · `422` `errors` lesen. **Nur die API ist die Wahrheit** — keine lokalen Zustandsdateien.
