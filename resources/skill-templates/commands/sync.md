## Sync (`/planstack sync <PROJECT> [--refresh] [--yes]`)

Spiegelt das Board in ein **Jira-Epic**: jede **Phase** wird eine **Aufgabe** des Epics, jeder **Task** dieser Phase eine **Unteraufgabe** dieser Aufgabe. Alles landet im **Sprint des Epics**. Planstack bleibt die Quelle; Jira bekommt eine für den PO lesbare Fassung. Die Verknüpfung steht danach in Planstack: Projekt `jira_epic_key`, Phase `jira_key`, Task `jira_key`.

Statuszeile: `⚙ Sync (<Phase> <%>) <PROJECT> — <Schritt>` mit den Phasen `Lade`, `Plane`, `Lege an`, `Aktualisiere`. `sync` claimt keinen Task — es gibt also kein Task-Event; die Zeile direkt nach `$ST` schreiben.

### 1. Daten holen

`GET $BASE/projects/$PROJ/jira-sync` (Session-Header `sync <PROJECT>`) liefert `project` (inkl. `jira_epic_key`), `phases[]` (je `id`, `name`, `jira_key`, `tasks[]`) und `unphased_tasks[]`; jeder Task trägt `summary`, `description`, `acceptance_criteria`, `target_actual`, `test_cases`, `criticality`, `effort`, `gate` (Task-Namen) und `jira_key`.

- **Kein `jira_epic_key`** → abbrechen und melden: im Projekt unter „Bearbeiten → Jira-Epic" setzen (oder `PATCH $BASE/projects/$PROJ {"jira_epic_key":"MN-1234"}`). Nicht raten.
- **`unphased_tasks`** werden **nicht** synchronisiert — am Ende auflisten.

### 2. Jira-Zugang

Jira Cloud REST v3 mit Basic-Auth. Zugangsdaten **nur lokal**: `jira_base_url` (z. B. `https://firma.atlassian.net`) und `jira_email` aus `${CLAUDE_SKILL_DIR}/settings.json`, das Token aus der Umgebungsvariable `JIRA_API_TOKEN`. Fehlt `jira_base_url`/`jira_email`, einmal nachfragen und in `settings.json` speichern; fehlt das Token, abbrechen und darauf hinweisen. Nie an Planstack senden.

```bash
JB=$(jq -r .jira_base_url "$SD/settings.json"); JE=$(jq -r .jira_email "$SD/settings.json")
jc(){ curl -s -u "$JE:$JIRA_API_TOKEN" -H 'Content-Type: application/json' -H 'Accept: application/json' "$@"; }
```

Einmal je Lauf ermitteln:

1. **Epic:** `jc "$JB/rest/api/3/issue/$EPIC?fields=project,summary,status"` → Projekt-Key. `404` → Key falsch, abbrechen.
2. **Sprint-Feld:** `jc "$JB/rest/api/3/field"` → Feld mit `schema.custom == "com.pyxis.greenhopper.jira:gh-sprint"` (meist `customfield_10020`).
3. **Sprint des Epics:** `jc "$JB/rest/api/3/issue/$EPIC?fields=<sprintfeld>"` → aus der Liste den Sprint mit `state` `active`, sonst `future`; nur `closed` oder leer → **ohne Sprint** weiter und das im Ergebnis melden.
4. **Vorgangstypen:** `jc "$JB/rest/api/3/issue/createmeta/<PROJEKT>/issuetypes"` → Aufgabe = `subtask:false` und `hierarchyLevel:0`, Name „Aufgabe"/„Task" bevorzugt; Unteraufgabe = `subtask:true`.

### 3. Plan zeigen und bestätigen lassen

Vor dem ersten Schreiben eine Tabelle zeigen: je Phase/Task die Aktion **anlegen** (kein `jira_key`), **prüfen** (Key vorhanden) bzw. mit `--refresh` **Texte aktualisieren**, dazu Epic, Sprint und die übersprungenen Tasks ohne Phase. Dann **einmal** bestätigen lassen (Tickets anlegen ist nach außen sichtbar). Mit `--yes` entfällt die Rückfrage.

### 4. Anlegen / prüfen — Phase für Phase

Reihenfolge: Phasen nach `position`, je Phase erst die Aufgabe, dann ihre Unteraufgaben (Task-Namen natürlich sortiert).

**Ohne `jira_key` — vorher Dubletten ausschließen:** ein früherer Lauf kann angelegt, aber den Key nicht mehr zurückgeschrieben haben. Daher erst suchen (`POST $JB/rest/api/3/search/jql` mit `{"jql":"parent = <EPIC bzw. Aufgabe> ORDER BY created","fields":["summary"]}`); gibt es dort schon ein Ticket mit identischem Titel, dieses übernehmen statt neu anzulegen.

**Aufgabe (Phase) anlegen:**

```json
POST $JB/rest/api/3/issue
{"fields":{"project":{"key":"<PROJEKT>"},"issuetype":{"id":"<Aufgabe>"},
  "parent":{"key":"<EPIC>"},"summary":"<Titel>","description":<ADF>,
  "<sprintfeld>":<sprint-id>}}
```

Lehnt Jira das Sprint-Feld ab (nicht auf der Maske), ohne anlegen und danach `POST $JB/rest/agile/1.0/sprint/<id>/issue {"issues":["<KEY>"]}`.

**Unteraufgabe (Task) anlegen:** wie oben, aber `issuetype` = Unteraufgabe, `parent` = Key der Aufgabe, **ohne** Sprint-Feld — Unteraufgaben laufen in Jira automatisch im Sprint ihrer Aufgabe.

**Sofort zurückschreiben**, nach **jedem** angelegten Ticket, nicht gesammelt am Ende (sonst verliert ein Abbruch die Zuordnung):

- Phase: `PATCH $BASE/projects/$PROJ/phases/<id>` `{"jira_key":"<KEY>"}`
- Task: `PATCH $BASE/projects/$PROJ/tasks/<id>` `{"jira_key":"<KEY>"}`

**Mit `jira_key` (prüfen):** `GET $JB/rest/api/3/issue/<KEY>?fields=parent,<sprintfeld>,summary`. Falscher `parent` → melden, **nicht** umhängen. Aufgabe nicht im Epic-Sprint → per Agile-API einplanen. `404` → melden (Key in Planstack prüfen), nicht neu anlegen. Texte bleiben unverändert — außer mit `--refresh`: dann `PUT $JB/rest/api/3/issue/<KEY>` mit neuem `summary` + `description`.

### 5. Texte — so, als kämen sie vom PO

Fachlich, **kurz und präzise, aber vollständig**: jedes Detail aus Planstack muss drinstehen, nichts dazuerfinden. Keine Planstack-Interna (Gates, Claim, Status, Unlocks, Token), keine PR-Nummern; Code-/Dateinamen nur, wenn sie für das Verständnis nötig sind. Deutsch mit echten Umlauten. Leere Abschnitte weglassen.

**Titel:** Aufgabe = Phasenname, sprechend formuliert (ohne „Phase 1"-Präfix, wenn der Name sonst nichts sagt, ergänzen, worum es geht). Unteraufgabe = `<Task-Name>: <summary>` (max. 255 Zeichen) — der Name hält die Zuordnung zu Planstack sichtbar.

**Beschreibung Aufgabe (Phase):**

- **Ziel** — ein, zwei Sätze: was nach dieser Phase für Nutzer bzw. Produkt anders ist.
- **Umfang** — Aufzählung der Unteraufgaben, je ein Satz (Task-Name + Kern).
- **Reihenfolge/Abhängigkeiten** — nur wenn Tasks aufeinander aufbauen oder die Phase eine andere voraussetzt.
- **Fertig, wenn** — die Phase ist abgeschlossen, sobald … (aus den Akzeptanzkriterien der Tasks verdichtet).

**Beschreibung Unteraufgabe (Task):**

- **Worum geht es** — Problem/Anlass und Ziel in zwei, drei Sätzen.
- **IST → SOLL** — aus `target_actual`, als Gegenüberstellung.
- **Anforderungen** — alle fachlichen Details aus `description` als knappe Punkte (Regeln, Grenzwerte, Sonderfälle, betroffene Bereiche).
- **Akzeptanzkriterien** — aus `acceptance_criteria`, je ein prüfbarer Punkt.
- **Testhinweise** — aus `test_cases`, als Schritte.
- **Abhängigkeiten** — `gate` als Jira-Keys der jeweiligen Unteraufgaben (sofern vorhanden, sonst Task-Name).
- Fußzeile: `Aufwand: <man_days> PT / <story_points> SP · Kritikalität: <criticality>` (nur gesetzte Werte).

**Format:** Jira v3 erwartet **ADF**. Abschnittstitel als `heading` (Level 3), Fließtext als `paragraph`, Punkte als `bulletList`/`listItem`, Schritte als `orderedList`, Code-/Dateinamen als Text mit `code`-Mark.

### 6. Ergebnis

Tabelle: Phase/Task → Jira-Key (als Link `<JB>/browse/<KEY>`) → Aktion (angelegt / übernommen / geprüft / aktualisiert / Fehler), darunter Epic, Sprint (oder „ohne Sprint" mit Grund), übersprungene Tasks ohne Phase und alle Abweichungen (falscher Parent, `404`). Danach die Statuszeile leeren.
