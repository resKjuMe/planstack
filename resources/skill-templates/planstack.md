---
name: planstack
description: Planstack-Boards über die REST-API abarbeiten — projektübergreifend. Aufruf „/planstack <PROJECT>" (ganzes Board) oder „/planstack <PROJECT> <TASK>" (ein Task). Das Projekt kommt aus dem Argument, der Zugang aus config.json. Einziger Zustandsspeicher ist die API.
---

# Planstack (Remote, projektübergreifend)

Ein Planstack-Board wird über die **REST-API** abgearbeitet: Board lesen, Task picken, claimen, analysieren, umsetzen oder Concern melden, PR setzen, mergen. Der Board-Zustand (pickable, Gate, Stacking, Unlocks, PR, Status) wird **serverseitig live berechnet** — es gibt keine lokalen Zustandsdateien.

## Aufruf

- `/planstack <PROJECT>` — das Board von `<PROJECT>` abarbeiten (besten Pick wählen, Zyklus s. u.).
- `/planstack <PROJECT> <TASK>` — gezielt **einen** Task (`<TASK>` = Task-Name, z. B. `C27`) dieses Projekts abarbeiten.
- `/planstack review [<PROJECT>] [<TASK>]` — in-review Task(s) mit PR reviewen (übernimmt Review, führt den Review-Skill aus, erfasst das Ergebnis; ohne Argumente projektübergreifend; siehe „Review").
- `/planstack fix [<PROJECT>] [<TASK|PR-NUMMER>]` — offenen PR reparieren: Merge-Konflikte auflösen, Review-Kommentare beantworten/fixen/resolven, rote CI korrigieren (siehe „Fix").
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

# Aufruf: /planstack <PROJECT> [<TASK>]  →  PROJ = erstes Argument, TASK = optionales zweites.
read -r PROJ TASK <<<"$ARGUMENTS"
```

Alle Endpunkte laufen unter `$BASE/projects/$PROJ` (siehe Betriebshandbuch). Fehler: `401` Token · `403` kein Zugriff aufs Projekt · `404` unbekannter Alias.

## Zwei Modi

**A — ganzes Board (`/planstack <PROJECT>`):** dem Zyklus des Betriebshandbuchs folgen. Pro Runde `POST $BASE/projects/$PROJ/claim-next` → das wählt den besten pickbaren Task (höchste `unlocks`) und claimt ihn atomar in einem Aufruf; die Antwort ist der geclaimte Task mit Arbeitsdetails (spart `GET /board` + `claim` + `GET /task`). Dann `analyze` → umsetzen bzw. `concern` → PR → `done` → `merge`. Kommt `{"claimed": null}` zurück, ist nichts (mehr) pickbar → fertig bzw. warten.

**B — ein Task (`/planstack <PROJECT> <TASK>`):** Der Task ist direkt per Name ansprechbar (Pfadsegment akzeptiert Name **oder** id) — kein name→id-Lookup nötig: `POST $BASE/projects/$PROJ/tasks/$TASK/claim`, dann `GET .../tasks/$TASK` für die Details (falls `claim.return_details` aus ist), und denselben Zyklus **nur für diesen Task** (analyze → umsetzen/concern → PR → done → merge). Ist der Task nicht pickbar (Gate offen, bereits beansprucht oder schon mit PR), das melden statt es zu erzwingen.

## Selbst-Update

**Sync-at-start:** Zu Beginn **jedes** `/planstack`-Aufrufs die Aktualität prüfen, **bevor** die eigentliche Arbeit beginnt. Modi mit Projekt-Call (Board/Task): Die erste Antwort (z. B. `claim-next`/`board`/Task) trägt `X-Planstack-Skill-Revision` — weicht sie von `$SKILLREV` ab, sofort `/config` nachziehen und die neuen Inhalte übernehmen, dann erst arbeiten. `settings` ist rein lokal (kein Sync nötig); `update-config` ist der explizite Sync.

Jede Board-Antwort trägt `X-Planstack-Config-Version` und `X-Planstack-Skill-Revision`. Weicht `X-Planstack-Skill-Revision` von `$SKILLREV` ab: `GET $BASE/projects/$PROJ/config` lesen und `operating_manual` + `status_rules` + `skill_instructions` von dort befolgen (Vorrang vor den Snapshots unten) — `skill_instructions` sind die verbindlichen, projektübergreifenden Anweisungen dieses Skills (z. B. die PR-Titel-Konvention).

**Selbstheilung (neue Kommandos):** Wird ein Sub-Kommando aufgerufen, das in dieser SKILL.md **nicht** beschrieben ist (z. B. ein später ergänztes), zuerst `GET $BASE/projects/$P/config` lesen (mit einem zugänglichen Projekt `$P` aus `GET $BASE/projects`) und `skill_instructions` befolgen — dort steht die **aktuelle Kommandoliste**. So stehen neue Features auch ohne Neu-Download bereit, sobald diese Selbstheilungs-Regel einmal installiert ist. Die **projektspezifische** Board-Konfiguration (Verhaltens-Hinweise wie `execution.mode`, `run.mode`, `parallelism.max_workers` …) liefert das Board bei Bedarf als `client_hints`-Block mit — separat je `<PROJECT>`, nichts davon ist fest im Skill hinterlegt.
