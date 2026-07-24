---
name: planstack
description: Planstack-Boards über die REST-API abarbeiten — projektübergreifend. Aufruf „/planstack <PROJECT>" (ganzes Board) oder „/planstack <PROJECT> <TASK>" (ein Task). Das Projekt kommt aus dem Argument, der Zugang aus config.json. Einziger Zustandsspeicher ist die API.
argument-hint: <project> [task] · auto <project> · do <project> [task] · review [project] [task] · fix [project] <task|pr> · plan [project] · settings · update-config [project]
---

# Planstack (Remote, projektübergreifend)

Ein Planstack-Board wird über die **REST-API** abgearbeitet: Board lesen, Task picken, claimen, analysieren, umsetzen oder Concern melden, PR setzen, mergen. Der Board-Zustand (pickable, Gate, Stacking, Unlocks, PR, Status) wird **serverseitig live berechnet** — es gibt keine lokalen Zustandsdateien.

## Aufruf

- `/planstack <PROJECT>` — das Board von `<PROJECT>` abarbeiten (besten Pick wählen, Zyklus s. u.).
- `/planstack <PROJECT> <TASK>` — gezielt **einen** Task (`<TASK>` = Task-Name, z. B. `C27`) dieses Projekts abarbeiten.
- `/planstack auto <PROJECT>` — **Auto-Modus**: das Board von `<PROJECT>` dauerhaft und unbeaufsichtigt abarbeiten (reviewen → eigene Tasks fertigstellen → pickbaren Task umsetzen; bei Leerlauf 5 min warten, dann weiter). `auto` steht in der **Sub-Kommando-Position** (erstes Argument), gefolgt vom Projekt. Die ausführliche Anleitung ist serverseitig gepflegt (siehe „Auto-Modus").
- `/planstack do <PROJECT> [<TASK>]` — **Alias** für die beiden Formen oben: erzwingt den Abarbeitungs-Modus (ganzes Board bzw. ein Task). Nützlich, wenn ein Projekt-Alias mit einem reservierten Sub-Kommando (`auto`, `review`, `fix`, `settings`, `update-config`, `plan`) kollidiert. `<PROJECT>` = Alias **oder** id, `<TASK>` = Name **oder** id (optional).
- `/planstack review [<PROJECT>] [<TASK>]` — in-review Task(s) mit PR reviewen (übernimmt Review, führt den Review-Skill aus, erfasst das Ergebnis; ohne Argumente projektübergreifend; siehe „Review").
- `/planstack fix [<PROJECT>] <TASK|PR-NUMMER>` — offenen PR reparieren (Task/PR erforderlich): Merge-Konflikte auflösen, Kommentare + Review-Kommentare beantworten/fixen/resolven, rote CI korrigieren (siehe „Fix").
- `/planstack plan [<PROJECT>]` — Projekte, Phasen und Tasks anlegen (Planung). Die Anleitung dazu ist serverseitig gepflegt und wird bei **jedem** Aufruf frisch geladen (siehe „Plan").
- `/planstack settings` — lokale Einstellungen (Tests, PHPStan, PHPCS, Babysit-PRs) anzeigen/ändern (nur lokal gespeichert; siehe „Lokale Einstellungen").
- `/planstack update-config [<PROJECT>]` — neueste allgemeine (+ Projekt-)Konfiguration ziehen und die Versionsnummern anzeigen (siehe „Konfiguration ziehen").

`<PROJECT>` ist der Projekt-Alias (z. B. `L2L`, `LOG`). Dasselbe installierte Skill bedient **alle** Projekte, auf die dein Token Zugriff hat.

## Zugang

`config.json` liegt neben dieser SKILL.md; Claude Code stellt deren Verzeichnis in `CLAUDE_SKILL_DIR` bereit. Sie enthält nur `base_url` + `token` (user-gebunden, gilt projektübergreifend) und `skill_revision` — **kein** Projekt: das kommt aus dem Aufruf.

```bash
CFG="${CLAUDE_SKILL_DIR:-.}/config.json"
j(){ python3 -c "import json;print(json.load(open('$CFG')).get('$1',''))"; }
BASE=$(j base_url); TOKEN=$(j token); SKILLREV=$(j skill_revision)
AUTH=(-H "Authorization: Bearer $TOKEN" -H "Accept: application/json" -H "Content-Type: application/json")

# Aufruf: /planstack [do] <PROJECT> [<TASK>]  →  ein optionales fuehrendes "do" ist
# ein Alias fuer den Abarbeitungs-Modus und wird verworfen; PROJ = erstes echtes
# Argument (Alias oder id), TASK = optionales zweites (Name oder id).
# /planstack auto <PROJECT>  →  Auto-Modus, PROJ = zweites Argument.
read -r A1 A2 A3 <<<"$ARGUMENTS"
if [ "$A1" = "do" ]; then PROJ=$A2; TASK=$A3
elif [ "$A1" = "auto" ]; then MODE=auto; PROJ=$A2
else PROJ=$A1; TASK=$A2; fi
```

Alle Endpunkte laufen unter `$BASE/projects/$PROJ` (siehe Betriebshandbuch). Fehler: `401` Token · `403` kein Zugriff aufs Projekt · `404` unbekannter Alias.

## Zwei Modi

**A — ganzes Board (`/planstack <PROJECT>`):** dem Zyklus des Betriebshandbuchs folgen. Pro Runde `POST $BASE/projects/$PROJ/claim-next` → das wählt den besten pickbaren Task (höchste `unlocks`) und claimt ihn atomar in einem Aufruf; die Antwort ist der geclaimte Task mit Arbeitsdetails (spart `GET /board` + `claim` + `GET /task`). Dann `analyze` → umsetzen bzw. `concern` → PR → `done` → `merge`. Kommt `{"claimed": null}` zurück, ist nichts (mehr) pickbar → fertig bzw. warten.

**B — ein Task (`/planstack <PROJECT> <TASK>`):** Der Task ist direkt per Name ansprechbar (Pfadsegment akzeptiert Name **oder** id) — kein name→id-Lookup nötig: `POST $BASE/projects/$PROJ/tasks/$TASK/claim`, dann `GET .../tasks/$TASK` für die Details (falls `claim.return_details` aus ist), und denselben Zyklus **nur für diesen Task** (analyze → umsetzen/concern → PR → done → merge). Ist der Task nicht pickbar (Gate offen, bereits beansprucht oder schon mit PR), das melden statt es zu erzwingen.

## Auto-Modus (`/planstack auto <PROJECT>`)

Arbeitet das Board von `<PROJECT>` **dauerhaft und unbeaufsichtigt** ab (`auto` in der **Sub-Kommando-Position**, erstes Argument, gefolgt vom Projekt; kein Task namens „auto"). Der Haupt-Agent wirkt als **Supervisor** und startet in einer Endlosschleife nacheinander **Auto-Runs**, jeder als **eigener Subagent** (synchron). Ein Auto-Run erledigt genau **eine** Arbeitseinheit, indem er das passende bestehende Sub-Kommando (mit explizitem `<PROJECT>`/`<TASK>`) aufruft: (1) ersten reviewbaren Task via `/planstack review <PROJECT> <TASK>`, sonst (2) ersten eigenen offenen Task via `/planstack <PROJECT> <TASK>` (bzw. `/planstack fix <PROJECT> <TASK>`, wenn der PR nur noch Politur braucht) bis zum polierten PR, sonst (3) besten pickbaren Task via `/planstack <PROJECT> <TASK>` bis zum erstellten PR, sonst nichts (`idle`). Hat der Auto-Run etwas getan, startet sofort der nächste; war er `idle`, wird **5 Minuten** gewartet und dann weitergemacht. Der Modus endet erst auf Nutzer-Abbruch.

Die vollständige, verbindliche Anleitung (Supervisor-Schleife, Ergebnisbericht, Priorität) wird **serverseitig gepflegt** (`skill_instructions`, Abschnitt „Auto-Modus") und bei Drift (`X-Planstack-Skill-Revision`) frisch nachgeladen.

## Plan (`/planstack plan [<PROJECT>]`)

Legt **Projekte, Phasen und Tasks** an (Planungsmodus statt Abarbeitung). Die vollständige, verbindliche Anleitung wird **serverseitig gepflegt** und ist bewusst **nicht** in dieser SKILL.md eingebacken, damit sie sich ohne Neu-Download aktualisiert.

**Self-updating (bei jedem Aufruf):** Zu Beginn von `/planstack plan` **immer zuerst** `GET $BASE/projects/<P>/config` lesen und den Abschnitt **`plan_instructions`** befolgen — er ist eigenständig versioniert (`plan_revision`) und beschreibt Ablauf, Endpunkte und den Feld-für-Feld-Leitfaden für Tasks (inkl. IST/SOLL-Vergleich und Testanleitung). `<P>` ist der übergebene `<PROJECT>` bzw. — wenn ein **neues** Projekt angelegt werden soll und noch kein Alias existiert — ein beliebiges zugängliches Projekt aus `GET $BASE/projects` (nur um `plan_instructions` zu ziehen). Erst danach mit der Planung beginnen.

## Selbst-Update

**Sync-at-start:** Zu Beginn **jedes** `/planstack`-Aufrufs die Aktualität prüfen, **bevor** die eigentliche Arbeit beginnt. Modi mit Projekt-Call (Board/Task): Die erste Antwort (z. B. `claim-next`/`board`/Task) trägt `X-Planstack-Skill-Revision` — weicht sie von `$SKILLREV` ab, sofort `/config` nachziehen und die neuen Inhalte übernehmen, dann erst arbeiten. `settings` ist rein lokal (kein Sync nötig); `update-config` ist der explizite Sync.

Jede Board-Antwort trägt `X-Planstack-Config-Version` und `X-Planstack-Skill-Revision`. Weicht `X-Planstack-Skill-Revision` von `$SKILLREV` ab: `GET $BASE/projects/$PROJ/config` lesen und `operating_manual` + `status_rules` + `skill_instructions` von dort befolgen (Vorrang vor den Snapshots unten) — `skill_instructions` sind die verbindlichen, projektübergreifenden Anweisungen dieses Skills (z. B. die PR-Titel-Konvention).

**Selbstheilung (neue Kommandos):** Wird ein Sub-Kommando aufgerufen, das in dieser SKILL.md **nicht** beschrieben ist (z. B. ein später ergänztes), zuerst `GET $BASE/projects/$P/config` lesen (mit einem zugänglichen Projekt `$P` aus `GET $BASE/projects`) und `skill_instructions` befolgen — dort steht die **aktuelle Kommandoliste**. So stehen neue Features auch ohne Neu-Download bereit, sobald diese Selbstheilungs-Regel einmal installiert ist. Die **projektspezifische** Board-Konfiguration (Verhaltens-Hinweise wie `execution.mode`, `run.mode`, `parallelism.max_workers` …) liefert das Board bei Bedarf als `client_hints`-Block mit — separat je `<PROJECT>`, nichts davon ist fest im Skill hinterlegt.
