## Betriebshandbuch (serverseitig gepflegt, gilt für alle Projekte)

**Zyklus:** `claim-next` (bester Pick **und** Claim in einem, spart Roundtrips/Tokens) → `analyze` → (`concern`) | (`in_progress` → PR → `done` → `merge`). Board neu lesen gemäß `reread.policy`. Alternativ manuell: `GET /board` → bester Pick → `POST /claim`.

Endpunkte unter `$BASE/projects/$PROJ`, Aufruf mit `curl -s "${AUTH[@]}"`:

| Methode / Pfad | Zweck |
|---|---|
| `POST /claim-next` | besten pickbaren Task atomar beanspruchen; Antwort = geclaimter Task (nach `task.fields`), `{"claimed":null}` wenn nichts pickbar |
| `POST /next-action` | **nächste sinnvolle Arbeit vom Server bestimmen lassen** und atomar reservieren, in einem Aufruf: `action` (`fix` · `review` · `work` · `none`), `reason`, Task unter `data` (`pr_number`/`pr_url` immer dabei). Priorität `fix` → `review` → `work` ist serverseitig hinterlegt — nicht nachbauen |
| `POST /review-next` | nächsten zum Review bereiten Task mit PR (Pool `REVIEWBAR`, ersatzweise noch nicht übernommener `IN_REVIEW`) zum Review übernehmen (setzt `reviewed_by`); **ein bereits selbst übernommener, noch nicht abgeschlossener Review kommt zuerst** (wird fortgesetzt, nicht neu gestempelt; mit erfasstem Ergebnis nicht mehr); `{"reviewing":null}` wenn nichts ansteht |
| `POST /tasks/{id}/review-claim` · `/review {recommendation,summary}` | Review übernehmen · Ergebnis erfassen (`APPROVE`/`REQUEST_CHANGES`) |
| `GET /board` | pickable nach `unlocks`; Antwort trägt die Versions-Header |
| `GET /tasks` · `/tasks/{id}` | Details (`summary`, `acceptance_criteria`, `prerequisites`) |
| `GET /tasks/by-name/{name}` · `/tasks/by-pr/{pr}` | einen Task gezielt über Namen bzw. **PR-Nummer** finden — statt Task-Listen zu durchsuchen |
| `POST /tasks/{id}/claim` · `/release` | beanspruchen (`409`→anderen) · freigeben |
| `POST /tasks/{id}/status {status}` | `analyze\|in_progress\|in_review\|done` |
| `POST /tasks/{id}/pr {pr_number}` · `/merge` | PR setzen · mergen (idempotent) |
| `POST /tasks/{id}/complete {pr_number?,merge?}` | gebündelt (bei `actions.bundling`) |
| `POST /tasks/{id}/concern {summary,…}` · `DELETE …/concern` | melden · auflösen |
| `POST /tasks/{id}/gate {gate}` · `/split {children}` | Gate setzen · splitten |
| `POST /phases` · `POST /tasks` | anlegen |
| `POST /tasks/{id}/events {event}` | Fortschritts-Event melden (projekt-gebunden, `{id}` per Name **oder** id). Alternativ top-level `POST $BASE/events {task_id,event}` (`task_id` numerisch) |
| `GET /config` | Konfig + `operating_manual` + `status_rules`; `?parts=<a>,<b>` liefert nur die genannten Blöcke (die Versions-/Drift-Felder immer) |

In Task-Pfaden ist `{id}` **auch per Task-Name** ansprechbar (z. B. `.../tasks/C27/claim`) — kein separater name→id-Lookup nötig.

**Unverändert? Dann keinen Body holen.** `GET /board` und `GET /config` liefern einen `ETag`. Diesen mitschicken (`-H "If-None-Match: <etag>"`) beantwortet der Server mit `304` und **leerem Body**, wenn sich nichts geändert hat — die Versions-Header kommen trotzdem mit. Lohnt überall, wo derselbe Stand mehrfach gelesen wird (Warteschleifen, Polling).

**Session-Kennung (`X-Planstack-Session`, empfohlen bei jedem Aufruf):** Reservierungen hängen am **Nutzer** (`claimed_by_id`), nicht an der Session — arbeiten mehrere Worker unter demselben Token, sind sie im Board nicht unterscheidbar. Ein sprechendes Label (≤ 60 Zeichen, z. B. `work MN/C27`) im Header zeigt auf der Karte, **welche** Session den Task hält. Der Header wirkt zweifach: beim Claim wird das Label auf den Task gestempelt, und **jeder** weitere Zugriff dieser Session auf den Task frischt ihren Heartbeat auf. Bleibt der aus (Worker hart beendet), markiert das Board den Claim nach `claim_session_ttl_minutes` als **verwaist** — der Claim selbst bleibt bestehen, bis ihn jemand per `release` freigibt. Ohne Header verhält sich alles wie bisher. Der Header ist reine Anzeige-Information und ersetzt **keine** Autorisierung.

**Fortschritts-Events (best-effort, nicht blockierend):** Während der Abarbeitung den Fortschritt melden — projekt-gebunden `POST $BASE/projects/$PROJ/tasks/{id}/events {event}` (`{id}` per Name **oder** id) **oder** top-level `POST $BASE/events {task_id,event}` (`task_id` numerisch). Beide wirken identisch; die projekt-gebundene Variante ist über **jedes** Projekt per REST erreichbar (kein an ein einzelnes Projekt gebundener MCP-Server nötig) und nimmt den Task-Namen, den man ohnehin kennt. Zweck: die Organisation kann je Event einen Statuswechsel und/oder Feld-Automationen hinterlegen; ohne Konfiguration ist es eine reine Meldung. **Fire-and-forget gegenüber Fehlern** — Netzwerk-/HTTP-Fehler ignorieren, den Ablauf nie blockieren, das Absetzen nicht in Prosa berichten. Bequemer Helfer (`$BASE`/`$PROJ`/`AUTH` stammen aus dem Zugang):

```bash
# ev <task> <EVENT> [detail] [progress]
ev(){ curl -s "${AUTH[@]}" -X POST "$BASE/projects/$PROJ/tasks/$1/events" \
  -d "$(python3 -c 'import json,sys
d={"event":sys.argv[1]}
if len(sys.argv)>2 and sys.argv[2]: d["detail"]=sys.argv[2]
if len(sys.argv)>3 and sys.argv[3]: d["progress"]=int(sys.argv[3])
print(json.dumps(d))' "$2" "${3:-}" "${4:-}")" 2>/dev/null || true; }
```

**Fortschritt mitgeben (`detail`, `progress`) — nicht nur das Event.** Beide Felder sind optional: `detail` ist derselbe kurze Freitext wie in der Statuszeile (≤ 200 Zeichen, z. B. `4/9 Dateien: TaskController.php`), `progress` die **gerechnete** Prozentzahl (0–100) innerhalb des Events. Damit steht der Fortschritt **auf dem Board** — dauerhaft, für alle sichtbar, auch nachdem der Worker weg ist —, statt nur in der lokalen Statuszeile des einen Fensters. Wer die Statuszeile ohnehin bei jeder gezählten Einheit schreibt, meldet denselben Text hier mit:

```bash
ev C27 PROCESSING "4/9 Dateien: TaskController.php" 44
```

Ein Event **ohne** die Felder lässt den zuletzt gemeldeten Stand unberührt (er wird nicht geleert) — es ist also unschädlich, sie nur dort mitzugeben, wo es einen echten Zähler gibt. Ohne echten Nenner `progress` **weglassen**, nie schätzen: dieselbe Regel wie für die Prozentzahl der Statuszeile.

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
