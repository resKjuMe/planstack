## Auto-Modus (`/planstack auto <PROJECT>`)

Arbeitet das Board von `<PROJECT>` **dauerhaft und unbeaufsichtigt** ab. `auto` steht in der **Sub-Kommando-Position** (erstes Argument, wie `review`/`fix`/`settings`), gefolgt vom Projekt — es ist **kein** Task namens „auto". Der Haupt-Agent ist nur **Supervisor**: er startet Auto-Runs (je ein eigener Subagent mit frischem Kontext) und entscheidet nur anhand deren Ergebnisbericht. Der Modus endet erst auf Nutzer-Abbruch.

**Der Supervisor arbeitet mit einem Pool aus `N` Workern**, die **gleichzeitig** laufen. `N = 1` ist der Normalfall und verhält sich wie ein rein sequenzieller Lauf; ab `N > 1` gelten zusätzlich die Regeln unter „Parallelbetrieb", und die sind **nicht optional** — sie sind der Unterschied zwischen mehr Durchsatz und zwei Workern, die sich gegenseitig die Arbeit zerschießen.

### Wie viele Worker (`N`)

Zwei Angaben, beide werden gelesen, die kleinere gewinnt:

1. **Lokale Einstellung `auto_workers`** (`${CLAUDE_SKILL_DIR}/settings.json`, siehe „Lokale Einstellungen") — wie viele Worker **diese Maschine** fahren soll. `auto` (Default) = keine eigene Vorgabe.
2. **Projekt-Knopf `parallelism.max_workers`** — wie viele Worker **dieses Board** verträgt. Er steht in der Antwort von `next-actions` als `max_workers`; ein separater Config-Abruf ist dafür nicht nötig.

Daraus: `auto_workers = auto` → `N = max_workers`. Sonst `N = min(auto_workers, max_workers)`. Ist `auto_workers` größer als `max_workers`, **einmal** beim Start sagen, mit welchem Wert gefahren wird und wo der Deckel sitzt (Projekt-Konfiguration → „Max. Worker"); danach nicht mehr erwähnen.

### Supervisor-Schleife

1. **Arbeit für die freien Slots holen — ein Aufruf, nicht `N`:**
   `POST $BASE/projects/$PROJ/next-actions` mit `{"count": <Anzahl freier Slots>}`. Die Antwort trägt unter `data` bis zu `count` **Arbeitseinheiten**, jede mit `action` (`fix` · `review` · `work`), `reason`, `session` (s. u.) und dem dekorierten Task unter `task` (`pr_number`/`pr_url` immer dabei). Dazu `workers` (vergeben), `requested` und `max_workers`.
   Der Server garantiert: **jede Einheit auf einem anderen Task**, jede atomar reserviert, Priorität `fix` → `review` → `work` (Blockiertes zuerst freiräumen) — serverseitig hinterlegt, **nicht** nachbauen und **nicht** umsortieren. Das Board dafür zu lesen (`GET /board`, `GET /tasks`, `review-next`) ist überflüssig und liefert im Zweifel eine andere Reihenfolge.
   Leeres `data` ⇒ nichts zu tun.
2. **Je Einheit einen Auto-Run starten** (Agent-Tool, `subagent_type: general-purpose`). Bei `N = 1` synchron (`run_in_background: false`), bei `N > 1` **alle gleichzeitig** (`run_in_background: true`) — sonst wartet der Pool auf den langsamsten. Der Prompt ist die „Auto-Run"-Anweisung unten mit fest eingesetztem `<PROJECT>`, `<TASK>`, `action`, dem **`session`-Label aus der Antwort** und dem Pfad seiner Statusdatei.
3. **Fertige Worker einsammeln, sofort nachbesetzen.** Jeder Auto-Run meldet `{ "action": "review|fix|finish|pick|concern|idle", "task": "<Name|null>", "detail": "<kurz>" }` (Zuordnung s. Tabelle unter „Auto-Run"). Sobald **einer** fertig ist, wird sein Slot frei → zurück zu Schritt 1 mit der Anzahl der **jetzt** freien Slots. Nicht auf alle warten: ein Stapel-Ende als Sperre verschenkt genau die Zeit, die der Pool gewinnen soll.
4. **Leerlauf:** Erst wenn **kein** Worker mehr läuft **und** der letzte Aufruf nichts geliefert hat, **5 Minuten** (300 s) echt pausieren, dann weiter. Liefert der Aufruf weniger Einheiten als Slots frei sind, laufen eben weniger Worker — das ist **kein** Leerlauf, es wird nicht pausiert.
5. Endlos wiederholen. Kommt Nutzer-Input, diesen bevorzugt behandeln.

Der Supervisor setzt die Statuszeile vor dem Holen der Arbeit, beim Start und Einsammeln von Workern, während der Pause (`⏳ Auto (Idle) …`) und beim Anhalten (`⏳ Auto (Pause) …`). Kurz nach dem Start dem Nutzer einmal bestätigen, dass der Auto-Modus läuft (mit `N`); danach je fertigem Auto-Run knapp berichten (eine Zeile: Aktion + Task) gemäß `verbosity`.

### Parallelbetrieb — vier Regeln, die Kollisionen verhindern

1. **Das `session`-Label kommt vom Server und wird wörtlich übernommen.** Jede Einheit bringt `session` mit (z. B. `work MN/C27 #2`): damit ist die Reservierung schon gestempelt. Genau diesen Wert sendet der Worker in **jedem** seiner Aufrufe als `X-Planstack-Session` — nicht das Label des Supervisors, nichts Selbstgebautes. Nur so frischt sein Heartbeat sein **eigenes** Lease auf (Claim-Lease und Fix-Lease), und das Board zeigt, welcher Slot an welchem Task sitzt. Ein falsches Label heißt: das Board hält den Task für verwaist und der Server gibt ihn nach Ablauf des Leases an den nächsten Worker — mitten in der Arbeit.
2. **Ein eigenes Arbeitsverzeichnis je Worker.** Zwei Worker im **selben** Checkout überschreiben sich Branch, Index und Arbeitskopie — das ist der teuerste Fehler in diesem Modus. Also je Worker ein eigener `git worktree` neben dem Repo, benannt nach Projekt und Task, und darin arbeiten:

   ```bash
   git -C <repo> worktree add ../wt-<project>-<task> -b <project>-<task>-<kurz>
   # … arbeiten, committen, pushen, PR …
   git -C <repo> worktree remove ../wt-<project>-<task>   # nach dem Merge
   ```

   Gilt für `work` und `fix` (beide schreiben). Ein `review`, das nur `gh pr diff`/`gh api` liest, braucht keinen Checkout — braucht es doch einen, dann ebenfalls einen eigenen.
3. **Lokale Prüfläufe werden serialisiert, nicht parallelisiert.** PHPStan-/Test-Caches, Build-Ordner und Test-Datenbanken sind gemeinsame Ressourcen; zwei gleichzeitige Läufe liefern falsche Ergebnisse oder überschreiben sich. Sind `local_phpcs`/`local_phpstan`/`local_tests` aktiv, läuft davon **immer nur einer** — über eine Sperrdatei (z. B. `~/.claude/planstack-checks.lock`), die der Worker vor dem Prüflauf holt und danach freigibt. Wer warten muss, schreibt das in die Statuszeile (`⏳ … — warte auf Prüf-Slot`), statt trotzdem zu starten.
4. **Kein eigenes Picken, kein Zurückgeben an einen zweiten Worker.** Der Server verteilt; ein Worker claimt oder sucht **nichts** selbst und gibt seinen Task nicht an einen anderen Worker weiter. Was ein Worker liegen lässt (Concern, Abbruch), holt die nächste Runde von `next-actions` — und ein Task, an dem sichtbar noch gearbeitet wird, wird dabei **übersprungen**, auch wenn sein Lease bereits abgelaufen ist. Ein hart abgebrochener Worker blockiert seinen Task deshalb höchstens für die Dauer der Session-TTL (Standard 10 Minuten), nicht dauerhaft.

### Statuszeile im Pool

Eine Zeile, mehrere Worker: der Supervisor schreibt den **Kopf** in seine Zustandsdatei, jeder Worker seinen **Abschnitt** in eine eigene Slot-Datei; das Statusline-Skript setzt daraus eine Zeile zusammen (Aufbau und Dateinamen: „Sticky-Statuszeile" → „Mehrere Worker"). Der Worker schreibt also **nicht** in die Datei des Supervisors — sonst überschreiben sich `N` Worker gegenseitig im Sekundentakt. Endet ein Worker, löscht er seine Slot-Datei; hinterlässt ein abgebrochener Worker eine, räumt sie der Supervisor beim Einsammeln weg.

Das Melden auf den Server bleibt unverändert: **jeder** Worker setzt seine Fortschritts-Events selbst ab (`sp`-Helfer), jeder an seinem eigenen Task. Da die Tasks verschieden sind, kommen sich die Meldungen nicht in die Quere.

### Auto-Run (ein Worker, genau eine Arbeitseinheit)

Er bekommt seine Arbeit **vorgegeben** (Aktion, Projekt, Task, `session`-Label, Slot-Datei), ruft dafür das passende bestehende `/planstack`-Sub-Kommando auf — mit **explizitem** `<PROJECT>` **und** `<TASK>`, kein Auto-Pick —, führt es vollständig aus, meldet das Ergebnis und beendet sich; er startet **keine** weiteren Auto-Runs und holt sich **keine** zweite Einheit.

| `action` | Sub-Kommando | Bericht |
|---|---|---|
| `fix` | `/planstack fix <PROJECT> <TASK>` | `fix` |
| `review` | `/planstack review <PROJECT> <TASK>` | `review` |
| `work` | `/planstack work <PROJECT> <TASK>` | `pick` (bzw. `finish`, wenn der Task schon in Arbeit war) |

**Der Task ist bereits reserviert** — `fix` per Lease, `review` per `reviewed_by`, `work` per Claim samt `CLAIMED`-Statuswechsel und Board-Broadcast. Also **nicht** erneut claimen: das Sub-Kommando setzt den Zyklus ab dem aktuellen Status fort. Sobald die Arbeit läuft, schreibt der Auto-Run seine Statuszeile und benennt dabei **beide** Ebenen (`Auto › Work`, `Auto › Review`, `Auto › Fix`), inklusive PR-Nummer als Link, sobald `pr_url` bekannt ist. Das Sub-Kommando bringt seinen Zyklus, seine lokalen Checks und seine Selbst-Update-Prüfung selbst mit — der Auto-Run baut nichts davon nach. Meldet die Umsetzung einen **Concern**, gilt der Auto-Run als „hat etwas getan" (`action: "concern"`, nicht `idle`). Nicht pickbare Tasks nie erzwingen.

**Älterer Server (`404` auf `next-actions`):** ersatzweise `POST .../next-action` (Einzahl) — bei `N > 1` einmal **pro freiem Slot**, und dabei selbst darauf achten, keinen Task doppelt zu vergeben (die Antwort nennt den Task; ein bereits laufender Task wird übersprungen). Fehlt auch dieser Endpunkt, selbst bestimmen, **in derselben Reihenfolge**: (1) offener PR mit rotem CI, offenen Review-Threads oder angeforderten Änderungen → `fix`; (2) Task im Review-Pool mit PR, ohne Reviewer, nicht selbst beansprucht → `review`; (3) bester pickbarer Task (höchste `unlocks`) → `work`; (4) sonst `idle`. Liefert die Antwort kein `session`-Feld, das Label selbst nach demselben Muster bilden (`<aktion> <PROJECT>/<TASK> #<slot>`).
