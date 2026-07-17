---
name: planstack
description: Planstack-Boards über die REST-API abarbeiten — projektübergreifend. Aufruf „/planstack <PROJECT>" (ganzes Board) oder „/planstack <PROJECT> <TASK>" (ein Task). Das Projekt kommt aus dem Argument, der Zugang aus config.json. Einziger Zustandsspeicher ist die API.
---

# Planstack (Remote, projektübergreifend)

Ein Planstack-Board wird über die **REST-API** abgearbeitet: Board lesen, Task picken, claimen, analysieren, umsetzen oder Concern melden, PR setzen, mergen. Der Board-Zustand (pickable, Gate, Stacking, Unlocks, PR, Status) wird **serverseitig live berechnet** — es gibt keine lokalen Zustandsdateien.

## Aufruf

- `/planstack <PROJECT>` — das Board von `<PROJECT>` abarbeiten (besten Pick wählen, Zyklus s. u.).
- `/planstack <PROJECT> <TASK>` — gezielt **einen** Task (`<TASK>` = Task-Name, z. B. `C27`) dieses Projekts abarbeiten.

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

**A — ganzes Board (`/planstack <PROJECT>`):** dem Zyklus des Betriebshandbuchs folgen: `GET /board` → besten Pick (höchste `unlocks`) → `claim` → `analyze` → umsetzen bzw. `concern` → PR → `done` → `merge`; Board gemäß `reread.policy` neu lesen.

**B — ein Task (`/planstack <PROJECT> <TASK>`):** Der Task wird per **numerischer id** adressiert, `<TASK>` ist aber ein Name — daher zuerst auflösen: `GET $BASE/projects/$PROJ/tasks` lesen, den Eintrag mit `name == <TASK>` suchen, dessen `id` verwenden. Dann denselben Zyklus **nur für diesen Task** (claim → analyze → umsetzen/concern → PR → done → merge). Ist der Task nicht pickbar (Gate offen, bereits beansprucht oder schon mit PR), das melden statt es zu erzwingen.

## Selbst-Update

Jede Board-Antwort trägt `X-Planstack-Config-Version` und `X-Planstack-Skill-Revision`. Weicht `X-Planstack-Skill-Revision` von `$SKILLREV` ab: `GET $BASE/projects/$PROJ/config` lesen und `operating_manual` + `status_rules` + `skill_instructions` von dort befolgen (Vorrang vor den Snapshots unten) — `skill_instructions` sind die verbindlichen, projektübergreifenden Anweisungen dieses Skills (z. B. die PR-Titel-Konvention). Die **projektspezifische** Board-Konfiguration (Verhaltens-Hinweise wie `execution.mode`, `run.mode`, `parallelism.max_workers` …) liefert das Board bei Bedarf als `client_hints`-Block mit — separat je `<PROJECT>`, nichts davon ist fest im Skill hinterlegt.
