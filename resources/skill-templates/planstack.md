---
name: {{alias}}
description: >
  Arbeite ein Planstack-Board (Roadmap aus
  Tasks/Phasen/Gates) vollständig über die Planstack-REST-API ab: Board lesen,
  Task picken, claimen, analysieren, umsetzen oder Concern melden, PR setzen,
  mergen. KEINE Schreibzugriffe auf Markdown/DIAGRAM-Dateien — der einzige
  Zustandsspeicher ist die API. Nutze diesen Skill, wenn ein Planstack-Board
  (z. B. Alias „{{alias}}") ferngesteuert über die API bearbeitet werden soll.
---

# {{name}} — Planstack (Remote)

Ein Planstack-Board wird ausschließlich über die **Planstack-REST-API**
abgearbeitet: Board lesen, Task picken, claimen, analysieren, umsetzen oder
Concern melden, PR setzen, mergen. Der Board-Zustand (pickable, Gate, Stacking,
Unlocks, PR, Status) wird von der API **live berechnet** — es gibt keine lokalen
Zustandsdateien.

## 1. Konfiguration

Die Zugangsdaten liegen in `config.json` neben dieser Datei (im Skill-Archiv
bereits enthalten):

- `base_url` — Basis-URL inkl. `/api`, z. B. `https://planstack.eskju.net/api`
- `token` — Personal-Access-Token (Sanctum)
- `project` — Projekt-Alias, z. B. `{{alias}}`

Jeder Request trägt den Header `Authorization: Bearer <token>` und
`Accept: application/json`. Ohne gültiges Token → `401`.

Werte in Shell-Variablen laden (für alle folgenden Beispiele). `config.json`
liegt immer **neben dieser SKILL.md**; Claude Code stellt deren Verzeichnis in
`CLAUDE_SKILL_DIR` bereit — egal ob der Skill global, projektlokal oder als
Plugin installiert ist:

```bash
CFG="${CLAUDE_SKILL_DIR:-.}/config.json"
BASE=$(python3 -c "import json;print(json.load(open('$CFG'))['base_url'])")
PROJ=$(python3 -c "import json;print(json.load(open('$CFG'))['project'])")
TOKEN=$(python3 -c "import json;print(json.load(open('$CFG'))['token'])")
AUTH=(-H "Authorization: Bearer $TOKEN" -H "Accept: application/json" -H "Content-Type: application/json")
```

## 2. Der Zyklus

```
Board lesen → besten Pick wählen → claim → analyze
   → (Concern melden)  ODER  (umsetzen → in_progress → PR setzen → done/in_review → merge)
```

### 2.1 Board lesen (Einstieg)

```bash
curl -s "${AUTH[@]}" "$BASE/projects/$PROJ/board"
```

Antwort: `totals` (Fortschritt/SP/pickable-Anzahl), `phases` (Aggregate je Phase)
und `pickable` — die **pickbaren Tasks, absteigend nach `unlocks` sortiert**. Der
erste Eintrag ist der „beste Pick" (schaltet die meisten Folge-Tasks frei). Ein
Task ist genau dann pickbar, wenn **alle** Gates erfüllt sind — ein Gate gilt als
erfüllt, sobald der Vorgänger einen **PR** hat (ein offener PR genügt) oder
gemergt ist —, er nicht beansprucht ist, selbst noch keinen PR hat und nicht
gemergt ist.

Vollständige Task-Liste inkl. berechneter Felder:

```bash
curl -s "${AUTH[@]}" "$BASE/projects/$PROJ/tasks"
```

### 2.2 Picken & claimen

Task-Name (z. B. `C27`) oder id aus der pickable-Liste. Claim ist atomar:

```bash
curl -s "${AUTH[@]}" -X POST "$BASE/projects/$PROJ/tasks/$ID/claim"
```

- `200` → beansprucht (`status=CLAIMED`, `claimed_by` = Token-User).
- `409` → bereits von jemand anderem beansprucht → anderen Task wählen.

Freigeben (wenn doch nicht bearbeitet):

```bash
curl -s "${AUTH[@]}" -X POST "$BASE/projects/$PROJ/tasks/$ID/release"
```

### 2.3 Analysieren

```bash
curl -s "${AUTH[@]}" -X POST "$BASE/projects/$PROJ/tasks/$ID/status" -d '{"status":"analyze"}'
```

Setzt `ANALYZING`. Lies dazu die Task-Details (`summary`, `acceptance_criteria`,
`prerequisites`) via `GET .../tasks/$ID`.

### 2.4a Concern (Blocker/Missverständnis/Entscheidung)

Wenn die Aufgabe so nicht umsetzbar ist:

```bash
curl -s "${AUTH[@]}" -X POST "$BASE/projects/$PROJ/tasks/$ID/concern" -d '{
  "summary": "Kurzbeschreibung",
  "context": "…", "blocker": "…", "misconception": "…", "decisions": "…"
}'
```

Setzt `CONCERNED` + legt/aktualisiert den Concern. Auflösen (zurück nach
pickbar/beansprucht):

```bash
curl -s "${AUTH[@]}" -X DELETE "$BASE/projects/$PROJ/tasks/$ID/concern"
```

### 2.4b Umsetzen

1. In Arbeit setzen:
   ```bash
   curl -s "${AUTH[@]}" -X POST "$BASE/projects/$PROJ/tasks/$ID/status" -d '{"status":"in_progress"}'
   ```
2. Arbeit erledigen (Code, Tests …).
3. PR-Nummer eintragen (nur Ziffern; `pr_url` kommt automatisch, wenn für den
   Projekt-Alias ein GitHub-Repo konfiguriert ist). Der PR-Eintrag selbst ändert
   den Status nicht, schaltet aber ab jetzt abhängige Tasks frei (Gate erfüllt):
   ```bash
   curl -s "${AUTH[@]}" -X POST "$BASE/projects/$PROJ/tasks/$ID/pr" -d '{"pr_number":7890}'
   ```
4. Arbeit als fertig melden — mit gesetztem PR wandert der Task nach `IN_REVIEW`
   („in Review": PR offen, wartet auf Merge). Ohne PR bliebe er `IN_PROGRESS`:
   ```bash
   curl -s "${AUTH[@]}" -X POST "$BASE/projects/$PROJ/tasks/$ID/status" -d '{"status":"done"}'
   ```
5. Nach dem Merge:
   ```bash
   curl -s "${AUTH[@]}" -X POST "$BASE/projects/$PROJ/tasks/$ID/merge"
   ```
   `merged` ist idempotent — `merged_at` wird nur beim ersten Übergang gesetzt.
   Erst der Merge nimmt den Task vom Board (Status `MERGED`).

> **Ein PR (offen oder gemergt) erfüllt das Gate** und schaltet abhängige Tasks
> frei (erneut Board lesen). Der Task selbst wird mit `status=done` (oder
> `in_review`) zu `IN_REVIEW`, sobald ein PR gesetzt ist — ohne PR bleibt `done`
> aus Kompatibilität auf `IN_PROGRESS`. Ein PR allein macht ihn nicht „erledigt";
> COMPLETED entsteht nur per Split, MERGED erst per `/merge`.

### 2.5 Gate & Split (Board pflegen)

Requirements/Gate eines Tasks ersetzen (Liste aus Task-Namen und/oder ids,
projekt-gescoped, kein Self-Gate):

```bash
curl -s "${AUTH[@]}" -X POST "$BASE/projects/$PROJ/tasks/$ID/gate" -d '{"gate":["C21","C25"]}'
```

Task splitten (Parent → `COMPLETED`, N Kinder in derselben Phase, atomar):

```bash
curl -s "${AUTH[@]}" -X POST "$BASE/projects/$PROJ/tasks/$ID/split" -d '{"children":[
  {"name":"C27a","summary":"…","effort_story_points":3,"gate":["C25"]},
  {"name":"C27b","summary":"…","effort_story_points":2}
]}'
```

### 2.6 Neue Tasks / Phasen anlegen

```bash
curl -s "${AUTH[@]}" -X POST "$BASE/projects/$PROJ/phases" -d '{"name":"P6 · Cleanup","position":6}'
curl -s "${AUTH[@]}" -X POST "$BASE/projects/$PROJ/tasks" -d '{
  "name":"C40","summary":"…","phase_id":6,"effort_story_points":3,"gate":["C39"]
}'
```

## 3. Regeln

- **Nur die API** ist Wahrheit — es gibt keine lokalen Board-/Statusdateien.
- Vor jeder Aktion Board neu lesen (der Zustand ändert sich durch andere Worker).
- `409` beim Claim = Kollision → nicht überschreiben, anderen Task nehmen.
- Ungültige Referenzen/Werte liefern `422` mit `errors` — Meldung lesen, korrigieren.
- Fortschritt/Pickbarkeit/Farben werden **serverseitig** berechnet; nicht lokal
  nachbilden.

## 4. Fehlerbilder

| Code | Bedeutung | Reaktion |
|------|-----------|----------|
| 401  | Kein/ungültiges Token | Token/`PLANSTACK_API_TOKEN` prüfen |
| 403  | Kein Zugriff aufs Projekt | Team-/Owner-Zugriff prüfen |
| 404  | Projekt/Task nicht gefunden | Alias/id prüfen |
| 409  | Task bereits beansprucht | anderen Task wählen |
| 422  | Validierung (Gate/Status/…) | `errors` lesen, Eingabe korrigieren |
