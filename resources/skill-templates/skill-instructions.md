## Konventionen (planstack, serverseitig gepflegt)

Verbindliche, projektübergreifende Anweisungen für den allgemeinen `planstack`-Skill. Serverseitig gepflegt: bei Drift (`X-Planstack-Skill-Revision`) über `GET /config` → `skill_instructions` nachladen.

- **PR-Titel:** Beim Erstellen eines Pull Requests **immer** den Task-Namen als Titel-Prefix setzen: `<TASK>: <Kurzbeschreibung>` (z. B. `C27: PseudoPropertyBinding-Fallback`). `<TASK>` ist der Kurzname des Tasks (Feld `name`), nicht die numerische id. Gilt für beide Modi.

## Lokale Einstellungen (`/planstack settings`)

Der Skill kennt lokale Einstellungen, die **ausschließlich auf diesem Rechner** in `${CLAUDE_SKILL_DIR}/settings.json` (neben `config.json`) gespeichert werden — sie werden **nie** an den Server übertragen. Fehlt die Datei oder ein einzelner Schlüssel, gilt der jeweilige Default.

**Aufruf `/planstack settings`** (erstes Argument ist `settings`, kein Projekt-Alias): die aktuellen Werte anzeigen und den Nutzer die gewünschten wählen lassen, danach nach `settings.json` schreiben.

Die ersten vier Einstellungen sind jeweils **`yes`** (ja), **`no`** (nein) oder **`ask`** (bei jeder Aufgabe fragen); die zwei Review-Einstellungen haben eigene Werte (siehe Spalte „Werte"):

| Schlüssel | Bedeutung | Werte | Default |
|---|---|---|---|
| `local_tests` | Lokale Testausführung nach der Umsetzung | yes/no/ask | `yes` |
| `local_phpstan` | Lokale PHPStan-Verifikation | yes/no/ask | `yes` |
| `local_phpcs` | Lokale PHPCS-Formatierung | yes/no/ask | `yes` |
| `babysit_prs` | PRs nach dem Öffnen betreuen (CI/Review beobachten, nachbessern) | yes/no/ask | `ask` |
| `review_results` | Wohin ein Review-Ergebnis geschrieben wird | `task_only` (nur im Task) · `task_and_pr` (Task **und** PR) | `task_only` |
| `review_auto_status` | Review-Empfehlung (APPROVE/REQUEST_CHANGES) setzen | `manual` (erst bestätigen lassen) · `auto` (automatisch aus dem Review) | `manual` |

`settings.json` (Beispiel mit den Defaults):

```json
{
  "local_tests": "yes",
  "local_phpstan": "yes",
  "local_phpcs": "yes",
  "babysit_prs": "ask",
  "review_results": "task_only",
  "review_auto_status": "manual"
}
```

**Anwendung im Arbeitszyklus** (beide Modi), je Wert einer Einstellung:

- `yes` → den Schritt automatisch ausführen.
- `no` → den Schritt überspringen.
- `ask` → vor dem Schritt **einmal für die aktuelle Aufgabe** nachfragen und die Antwort für diese Aufgabe anwenden (nicht dauerhaft speichern).

Reihenfolge vor dem PR (jeweils nur, wenn die Einstellung es zulässt): `local_phpcs` (formatieren) → `local_phpstan` (statische Analyse) → `local_tests` (Tests). Schlägt ein aktivierter Schritt fehl, erst beheben, dann PR. `babysit_prs` greift **nach** dem PR-Öffnen. Vor jeder Board-Abarbeitung die aktuellen Einstellungen aus `settings.json` lesen.

## Konfiguration ziehen (`/planstack update-config`)

**Aufruf `/planstack update-config [<PROJECT>]`** (erstes Argument `update-config`): zieht die neuesten Konfigurationen aktiv nach (statt erst bei Drift) und gibt die Versionsnummern aus.

- **Ohne `<PROJECT>`:** **alle** zugänglichen Projekte aktualisieren — `GET $BASE/projects` auflisten und für **jedes** `GET $BASE/projects/<alias>/config` lesen. Dabei die allgemeinen Inhalte (`operating_manual` + `status_rules` + `skill_instructions`) einmal übernehmen und je Projekt dessen Konfiguration (`effective`/`client_hints`, `instructions`, `config_version`). Anschließend das gelieferte `skill_revision` in `config.json` schreiben (Baseline aktualisieren).
- **Mit `<PROJECT>`:** nur die allgemeine Config **und** die Config dieses einen Projekts.

**Ausgabe** — immer die Versionsnummern zeigen, z. B.:

```
Allgemein (Skill):  skill_revision <alt> → <neu>
Projekt L2L:  config_version 3
Projekt LOG:  config_version 1
Projekt B2R:  config_version 2
```

(Mit `<PROJECT>` nur die allgemeine Zeile + diese eine Projekt-Zeile.)

## Review (`/planstack review [<PROJECT>] [<TASK>]`)

Reviewt Tasks, die **in Review** sind (Status `IN_REVIEW`, mit PR). Ablauf:

1. **Task wählen & Review übernehmen** (setzt `reviewed_by`):
   - `<PROJECT> <TASK>`: gezielt dieser Task → `POST $BASE/projects/$PROJ/tasks/$TASK/review-claim`.
   - nur `<PROJECT>`: automatisch den ersten in-review Task mit PR → `POST $BASE/projects/$PROJ/review-next`.
   - **weder `<TASK>` noch `<PROJECT>`**: **projektübergreifend** — `GET $BASE/projects` auflisten und `review-next` pro Projekt aufrufen, bis eines einen Task liefert.
   Antwort `{"reviewing": null}` bzw. leer ⇒ nichts zu reviewen (nächstes Projekt / fertig).
2. **Review ausführen:** den **Review-Skill** (`/review`) für den PR des Tasks laufen lassen (`pr_url`/`pr_number` aus der Antwort). Ergebnis = Empfehlung (`APPROVE` oder `REQUEST_CHANGES`) + Zusammenfassung.
3. **Empfehlung festlegen** gemäß Einstellung `review_auto_status`: bei `manual` die Empfehlung vom Nutzer bestätigen lassen, bei `auto` die aus dem Review abgeleitete Empfehlung direkt verwenden.
4. **Ergebnis erfassen:** `POST $BASE/projects/$PROJ/tasks/$TASK/review` mit `{"recommendation":"APPROVE|REQUEST_CHANGES","summary":"…"}` — füllt `last_reviewed_at`, `last_review_recommendation`, `last_review_summary`.
5. **Ablage gemäß `review_results`:** bei `task_only` nur den Task (Schritt 4). Bei `task_and_pr` zusätzlich am PR hinterlegen: `gh pr review <pr> --approve` bzw. `--request-changes` mit der Zusammenfassung als Kommentar.

## Fix (`/planstack fix [<PROJECT>] [<TASK|PR-NUMMER>]`)

Bringt einen offenen PR wieder in mergefähigen Zustand — alles über `gh`/`git` am PR (nichts serverseitig).

1. **PR bestimmen:**
   - Argument ist **numerisch** → direkt diese PR-Nummer.
   - Argument ist ein **Task-Name** → `GET $BASE/projects/$PROJ/tasks/$TASK` → dessen `pr_number`.
   - nur `<PROJECT>`: automatisch — Tasks mit PR (z. B. `IN_REVIEW`/`IN_PROGRESS` mit `pr_number`) durchgehen und den ersten PR nehmen, der Konflikte, offene Kommentare oder rote CI hat.
   - **weder Argument noch `<PROJECT>`**: projektübergreifend über `GET $BASE/projects`.
2. **Merge-Konflikte zum Ziel-Branch:** Hat der PR Konflikte mit seinem Target-/Base-Branch, den Head-Branch auschecken, den Target-Branch ziehen und einmergen (`git fetch` + `git merge origin/<base>`), Konflikte auflösen, committen und pushen.
3. **Offene Review-Kommentare:** unaufgelöste Threads holen (`gh pr view` / `gh api`), jeden fachlich beantworten, den Code entsprechend fixen und den Thread auflösen (resolve).
4. **Fehlschlagende CI:** `gh pr checks` prüfen; rote Checks lokal reproduzieren, korrigieren, committen und pushen, bis die CI grün ist.

Danach ggf. via `/planstack review` erneut prüfen.
