## Konventionen (planstack, serverseitig gepflegt)

Verbindliche, projektübergreifende Anweisungen für den allgemeinen `planstack`-Skill. Serverseitig gepflegt: bei Drift (`X-Planstack-Skill-Revision`) über `GET /config` → `skill_instructions` nachladen.

- **PR-Titel:** Beim Erstellen eines Pull Requests **immer** den Task-Namen als Titel-Prefix setzen: `<TASK>: <Kurzbeschreibung>` (z. B. `C27: PseudoPropertyBinding-Fallback`). `<TASK>` ist der Kurzname des Tasks (Feld `name`), nicht die numerische id. Gilt für beide Modi.

## Lokale Einstellungen (`/planstack settings`)

Der Skill kennt lokale Einstellungen, die **ausschließlich auf diesem Rechner** in `${CLAUDE_SKILL_DIR}/settings.json` (neben `config.json`) gespeichert werden — sie werden **nie** an den Server übertragen. Fehlt die Datei oder ein einzelner Schlüssel, gilt der jeweilige Default.

**Aufruf `/planstack settings`** (erstes Argument ist `settings`, kein Projekt-Alias): die Einstellungen als **editierbare, interaktive Auswahl** präsentieren — wie `claude /settings`, **nicht** nacheinander abfragen. Nutze ein interaktives Auswahl-Formular (mehrere Fragen auf einmal, je eine pro Einstellung) mit **deutschen Labels** und deutschen Werten; der **aktuelle Wert** ist jeweils vorausgewählt. Nach der Auswahl alle Werte gesammelt nach `settings.json` schreiben und die aktualisierte Übersicht (deutsche Labels) zeigen.

**Anzeige immer deutsch**, in `settings.json` aber die stabilen Schlüssel/Werte speichern (Mapping unten):

| Einstellung (Label) | Schlüssel | Werte (Anzeige → gespeichert) | Default |
|---|---|---|---|
| Lokale Tests ausführen | `local_tests` | Ja→`yes` · Nein→`no` · Bei jeder Aufgabe fragen→`ask` | Ja |
| PHPStan-Prüfung (lokal) | `local_phpstan` | Ja→`yes` · Nein→`no` · Bei jeder Aufgabe fragen→`ask` | Ja |
| PHPCS-Formatierung (lokal) | `local_phpcs` | Ja→`yes` · Nein→`no` · Bei jeder Aufgabe fragen→`ask` | Ja |
| PRs betreuen (Babysit) | `babysit_prs` | Ja→`yes` · Nein→`no` · Bei jeder Aufgabe fragen→`ask` | Bei jeder Aufgabe fragen |
| Review-Ergebnis speichern | `review_results` | Nur im Task→`task_only` · Im Task und am PR→`task_and_pr` | Nur im Task |
| Review-Empfehlung setzen | `review_auto_status` | Manuell bestätigen→`manual` · Automatisch→`auto` | Manuell bestätigen |
| Ausgabe-Umfang | `verbosity` | Standard→`default` · Knapp→`minimal` · Ausführlich→`maximal` | Standard |

Der **Ausgabe-Umfang** (`verbosity`) steuert, wie viel Claude während der Abarbeitung ausgibt: `minimal` = nur das Nötigste (kurze Statusmeldungen, Ergebnisse), `default` = normale Berichterstattung, `maximal` = ausführlich (Schritte, Begründungen, Details).

`settings.json` (Beispiel mit den Defaults; gespeichert werden die Schlüssel/Werte, nicht die Labels):

```json
{
  "local_tests": "yes",
  "local_phpstan": "yes",
  "local_phpcs": "yes",
  "babysit_prs": "ask",
  "review_results": "task_only",
  "review_auto_status": "manual",
  "verbosity": "default"
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

## Fix (`/planstack fix [<PROJECT>] <TASK|PR-NUMMER>`)

Bringt einen offenen PR wieder in mergefähigen Zustand — alles über `gh`/`git` am PR (nichts serverseitig). `<TASK|PR-NUMMER>` ist **erforderlich** (kein Auto-Pick).

1. **PR bestimmen** (Argument ist Pflicht):
   - Argument ist **numerisch** → diese PR-Nummer (im Repo des Projekts).
   - Argument ist ein **Task-Name** → `GET $BASE/projects/$PROJ/tasks/$TASK` → dessen `pr_number`.
   - **Ohne `<PROJECT>`**: das Ziel projektübergreifend auflösen — `GET $BASE/projects` durchgehen und das Projekt finden, dessen Task den Namen trägt bzw. dessen Repo die PR-Nummer enthält.
2. **Merge-Konflikte zum Ziel-Branch:** Hat der PR Konflikte mit seinem Target-/Base-Branch, den Head-Branch auschecken, den Target-Branch ziehen und einmergen (`git fetch` + `git merge origin/<base>`), Konflikte auflösen, committen und pushen.
3. **Kommentare UND Review-Kommentare** — beide Arten abarbeiten:
   - **PR-/Issue-Kommentare** (Konversation, `gh pr view --comments` bzw. `gh api repos/{owner}/{repo}/issues/{pr}/comments`): jeden fachlich beantworten und, wo nötig, den Code fixen.
   - **Review-Kommentare** (inline an Codezeilen / Review-Threads, `gh api repos/{owner}/{repo}/pulls/{pr}/comments`): jeden beantworten, den Code entsprechend fixen und den Thread **auflösen** (resolve, z. B. GraphQL `resolveReviewThread`).
   Grundsatz: alles Offene beantworten + fixen; Review-Threads zusätzlich resolven.
4. **Fehlschlagende CI:** `gh pr checks` prüfen; rote Checks lokal reproduzieren, korrigieren, committen und pushen, bis die CI grün ist.

Danach ggf. via `/planstack review` erneut prüfen.
