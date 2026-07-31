## Auto-Modus (`/planstack auto <PROJECT>`)

Arbeitet das Board von `<PROJECT>` **dauerhaft und unbeaufsichtigt** ab. `auto` steht in der **Sub-Kommando-Position** (erstes Argument, wie `review`/`fix`/`settings`), gefolgt vom Projekt — es ist **kein** Task namens „auto". Der Haupt-Agent ist nur **Supervisor**: er startet in einer Endlosschleife nacheinander **Auto-Runs** (je ein eigener Subagent mit frischem Kontext) und entscheidet nur anhand deren Ergebnisbericht. Der Modus endet erst auf Nutzer-Abbruch.

**Supervisor-Schleife:**

1. **Einen Auto-Run als Subagent starten** (Agent-Tool, `subagent_type: general-purpose`, **synchron** — `run_in_background: false`, weil das Ergebnis die nächste Entscheidung bestimmt). Prompt = die „Auto-Run"-Anweisung unten mit fest eingesetztem `<PROJECT>` und dem Pfad `~/.claude/planstack-status-<session_id>.txt` **seiner** Session (der Subagent hat keine eigene `session_id`, die die Statusline lesen würde).
2. **Ergebnisbericht lesen:** `{ "action": "review|fix|finish|pick|concern|idle", "task": "<Name|null>", "detail": "<kurz>" }` (Zuordnung zu den `action`-Werten des Servers: siehe Tabelle unter „Auto-Run").
3. **Verzweigen:** `action` ≠ `idle` → **sofort** den nächsten Auto-Run starten, ohne Pause. `action` = `idle` → **5 Minuten** (300 s) echt pausieren, dann weiter. Kommt vorher Nutzer-Input, diesen bevorzugt behandeln.
4. Endlos wiederholen.

Der Supervisor setzt die Statuszeile vor dem Start eines Auto-Runs (`⏳ Auto (Wähle) <PROJECT> · …`), während der Pause (`⏳ Auto (Idle) …`) und beim Anhalten (`⏳ Auto (Pause) …`). Kurz nach dem Start dem Nutzer einmal bestätigen, dass der Auto-Modus läuft; danach je Auto-Run knapp berichten (eine Zeile: Aktion + Task) gemäß `verbosity`.

**Auto-Run (ein Subagent, genau eine Arbeitseinheit):** Er lässt sich die Arbeit **vom Server** zuweisen, ruft dafür das passende bestehende `/planstack`-Sub-Kommando auf — jeweils mit **explizitem** `<PROJECT>` **und** `<TASK>`, kein Auto-Pick —, führt es vollständig aus, meldet das Ergebnis und beendet sich; er startet **keine** weiteren Auto-Runs.

**Die Wahl trifft `POST $BASE/projects/$PROJ/next-action` in einem Aufruf.** Die Priorität ist **serverseitig** hinterlegt (`fix` → `review` → `work`, also Blockiertes zuerst freiräumen) — sie **nicht** clientseitig nachbauen und **nicht** umsortieren. Das Board dafür zu lesen (`GET /board`, `GET /tasks`, `review-next`) ist überflüssig und liefert im Zweifel eine andere Reihenfolge als der Server.

Antwort: `action` (`fix` · `review` · `work` · `none`), `reason` (Kurzbegründung, z. B. `CI FAILURE`, `3 unresolved threads`) und der Task unter `data` (dekoriert, `pr_number`/`pr_url` **immer** dabei). Zuordnung zum Sub-Kommando und zum Ergebnisbericht:

| `action` | Sub-Kommando | Bericht |
|---|---|---|
| `fix` | `/planstack fix <PROJECT> <TASK>` | `fix` |
| `review` | `/planstack review <PROJECT> <TASK>` | `review` |
| `work` | `/planstack work <PROJECT> <TASK>` | `pick` (bzw. `finish`, wenn der Task schon in Arbeit war) |
| `none` | keins | `idle` |

**Der Endpunkt reserviert den Task bereits atomar** — `fix` per ablaufendem Lease, `review` per `reviewed_by`, `work` per Claim samt `CLAIMED`-Statuswechsel und Board-Broadcast. Also **nicht** erneut claimen: das Sub-Kommando setzt den Zyklus ab dem aktuellen Status fort. Weil das Fix-Lease abläuft, blockiert ein abgebrochener Auto-Run den Task nicht dauerhaft. Parallele Worker kollidieren nicht (bedingte UPDATEs — genau einer gewinnt).

Fehlt der Endpunkt (`404`, älterer Server), ersatzweise selbst bestimmen, **in derselben Reihenfolge**: (1) offener PR mit rotem CI, offenen Review-Threads oder angeforderten Änderungen → `fix`; (2) Task im Review-Pool mit PR, ohne Reviewer, nicht selbst beansprucht → `review`; (3) bester pickbarer Task (höchste `unlocks`) → `work`; (4) sonst `idle`.

Sobald die Arbeit feststeht, schreibt der Auto-Run die Statuszeile in die vom Supervisor übergebene Datei und benennt dabei **beide** Ebenen (`Auto › Work`, `Auto › Review`, `Auto › Fix`), damit sichtbar bleibt, dass die Schleife lebt — inklusive PR-Nummer als Link, sobald `pr_url` bekannt ist. Das Sub-Kommando bringt seinen Zyklus, seine lokalen Checks und seine Selbst-Update-Prüfung selbst mit — der Auto-Run baut nichts davon nach. Meldet die Umsetzung einen **Concern**, gilt der Auto-Run als „hat etwas getan" (`action: "concern"`, nicht `idle`). Nicht pickbare Tasks nie erzwingen.
