## Konventionen (planstack, serverseitig gepflegt)

Verbindliche, projektübergreifende Anweisungen für den allgemeinen `planstack`-Skill. Serverseitig gepflegt: bei Drift (`X-Planstack-Skill-Revision`) über `GET /config` → `skill_instructions` nachladen.

- **PR-Titel:** Beim Erstellen eines Pull Requests **immer** Projekt- **und** Task-Kürzel als Titel-Prefix setzen, in dieser Reihenfolge: `<PROJECT>-<TASK>: <Kurzbeschreibung>` (z. B. `L2L-G5: PseudoPropertyBinding-Fallback`). `<PROJECT>` ist der Projekt-Alias aus dem Aufruf (z. B. `L2L`), `<TASK>` der Kurzname des Tasks (Feld `name`), nicht die numerische id. Gilt für beide Modi.

## Feingranulare Config-Aktualisierung (`config_versions`)

**Drift-Marken auf dem Hot-Path (Header jeder Board-/Task-Antwort):**

- `X-Planstack-Config-Version` — **Projektconfig** (Profil/Overrides). Logik **unverändert**.
- `X-Planstack-Skill-Revision` — geteilte **Datei**-Inhalte (Betriebshandbuch + Statusregeln + skill_instructions). Abweichung → `operating_manual`/`status_rules`/`skill_instructions` neu befolgen.
- `X-Planstack-Status-Config-Version` — **org-weite Status-Config** (Status, Spalten, Übergänge, Status-/Event-Automationen). Diese Marke — **nicht** die Skill-Revision — steigt, wenn eine Organisation ihren Workflow ändert (z. B. eine neue ereignisgesteuerte Status-Zuweisung). Weicht sie vom lokal gespeicherten Stand ab → `GET /config` lesen und über `config_versions` (unten) ermitteln, **welche** Tabelle sich geändert hat.

`GET /config` liefert zusätzlich zu `config_version`/`skill_revision` (Projektconfig-Logik **unverändert**) `status_config_version` (dieselbe Marke wie der Header) und einen Block `config_versions` — je Org-Config-Tabelle den jüngsten `updated_at` (ISO-8601 oder `null`):

```json
"config_versions": {
  "statuses": "2026-07-21T10:00:00+00:00",
  "status_groups": null,
  "transitions": "2026-07-21T10:00:00+00:00",
  "status_automations": null,
  "event_automations": "2026-07-21T11:30:00+00:00",
  "custom_fields": null
}
```

Diese Werte je Projekt **lokal** als Baseline in `${CLAUDE_SKILL_DIR}/config.json` unter `projects.<PROJECT>` speichern (`status_config_version` **und** `config_versions`; rein lokal, nie an den Server). Beim **Sync-at-start** genügt der Vergleich des Headers `X-Planstack-Status-Config-Version` mit der lokalen `status_config_version` — sind sie gleich, ist nichts nachzuziehen (kein `GET /config` nötig). Weichen sie ab (oder fehlt die Baseline), `GET /config` lesen und je `config_versions`-Eintrag prüfen: hat sich ein einzelner Eintrag gegenüber der lokalen Baseline geändert, **nur die betroffene Config** neu übernehmen — **nicht** das ganze Skill-Dokument. Zuordnung Eintrag → nachzuziehender Inhalt:

- `statuses` · `status_groups` · `transitions` · `status_automations` · `event_automations` → den `status_rules`-Block neu übernehmen (Abschnitt „Status dieser Organisation": Spalten, Rollen, erlaubte Übergänge, Feld-Automationen **und** die ereignisgesteuerten Status-Zuweisungen).
- `custom_fields` → nur die benutzerdefinierten Task-Felder (relevant fürs Anlegen/Befüllen von Tasks, `/planstack plan`).

Nach dem Übernehmen die neue `status_config_version` **und** die `config_versions` als lokale Baseline zurückschreiben. `null` bleibt `null` (Tabelle leer → nichts nachzuziehen).

**Wichtig — ereignisgesteuerter Status:** Enthält der `status_rules`-Block den Abschnitt „Ereignis-gesteuerte Status-Zuweisung", treibt der Server den Status **aus den Fortschritts-Events**. Direkte `POST /tasks/{id}/status`-Calls (`analyze`/`in_progress`/`in_review`/`done`) sind dann überflüssig — der **Server ignoriert sie in diesem Modus serverseitig** (sie können den per Event zugewiesenen Status nicht mehr überschreiben und lösen auch keinen Übergangs-Konflikt aus, sondern liefern den unveränderten Status zurück). Der Schutz gilt unabhängig davon, ob dieser Skill die Config-Änderung schon nachgezogen hat. Nur `claim`/`claim-next`, `pr`, `merge`, `concern`, `split` bleiben wirksam; der Status folgt ausschließlich den Events.

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
| Review-Strenge | `review_strictness` | Locker→`lenient` · Standard→`default` · Streng→`strict` | Standard |
| Review-Genauigkeit | `review_thoroughness` | Lässig→`relaxed` · Standard→`default` · Akribisch→`meticulous` | Standard |
| Metriken (Token-Verbrauch) | `metrics` | An→`on` · Aus→`off` | An |

Der **Ausgabe-Umfang** (`verbosity`) steuert **verbindlich**, wie viel Fließtext Claude während der gesamten Abarbeitung (beide Modi, alle Kommandos) ausgibt. Er ist **keine Empfehlung**, sondern eine harte Vorgabe und gilt ab dem ersten Satz der Antwort:

- `minimal` — **nur das Nötigste.** Keine Vorreden, keine Ankündigungen („Ich schaue mir jetzt …", „Als Nächstes …"), keine Zwischenerklärungen, keine Begründungen, keine Zusammenfassung des eben Getanen. Pro Task **maximal eine** knappe Statuszeile je abgeschlossenem Schritt (z. B. `C27: PR #123 geöffnet`) und am Ende das Ergebnis. Werkzeug-/Tool-Aufrufe sprechen für sich — sie **nicht** zusätzlich in Prosa beschreiben. Im Zweifel weglassen.
- `default` — normale Berichterstattung: knappe Orientierung wo sinnvoll, Ergebnisse, keine ausufernden Details.
- `maximal` — ausführlich: Schritte, Begründungen, Abwägungen und Details offenlegen.

Der Wert gilt für die **normale Arbeitsausgabe**. Explizit angeforderte Inhalte (z. B. der Review-`summary`, `metrics`-Ausgaben, direkte Nutzerfragen) sind davon unberührt und werden vollständig geliefert.

Die **Review-Strenge** (`review_strictness`) steuert, wie streng `/planstack review` urteilt: `lenient` = nur echte Blocker/kritische Punkte bemängeln (im Zweifel `APPROVE`), `default` = normale Prüfung, `strict` = auch kleinere Mängel, Stil und Edge-Cases bemängeln (eher `REQUEST_CHANGES`).

Die **Review-Genauigkeit** (`review_thoroughness`) steuert, wie tief/gründlich geprüft wird: `relaxed` = schneller Überblick, nur offensichtliche Stellen, `default` = normale Prüftiefe, `meticulous` = jede Datei/Zeile, Edge-Cases und Details akribisch durchgehen. (Strenge = wie hart bewertet wird; Genauigkeit = wie gründlich geschaut wird.)

Die **Metriken** (`metrics`) steuern die Token-Erfassung je Planstack-Step: `on` = während der Abarbeitung je Step den Token-Verbrauch erfassen und am Ende als Tabelle ausgeben (siehe „Metriken"), `off` = keine Erfassung, keine Tabelle.

`settings.json` (Beispiel mit den Defaults; gespeichert werden die Schlüssel/Werte, nicht die Labels):

```json
{
  "local_tests": "yes",
  "local_phpstan": "yes",
  "local_phpcs": "yes",
  "babysit_prs": "ask",
  "review_results": "task_only",
  "review_auto_status": "manual",
  "verbosity": "default",
  "review_strictness": "default",
  "review_thoroughness": "default",
  "metrics": "on"
}
```

**Anwendung im Arbeitszyklus** (beide Modi), je Wert einer Einstellung:

- `yes` → den Schritt automatisch ausführen.
- `no` → den Schritt überspringen.
- `ask` → vor dem Schritt **einmal für die aktuelle Aufgabe** nachfragen und die Antwort für diese Aufgabe anwenden (nicht dauerhaft speichern).

Reihenfolge vor dem PR (jeweils nur, wenn die Einstellung es zulässt): `local_phpcs` (formatieren) → `local_phpstan` (statische Analyse) → `local_tests` (Tests). Schlägt ein aktivierter Schritt fehl, erst beheben, dann PR. `babysit_prs` greift **nach** dem PR-Öffnen. Vor jeder Board-Abarbeitung die aktuellen Einstellungen aus `settings.json` lesen.

## Metriken (Einstellung `metrics`)

Ist `metrics` = `on` (Default), wird während der Abarbeitung **je Planstack-Step** der Token-Verbrauch erfasst und am **Ende** des Laufs als Tabelle ausgegeben. Ist `metrics` = `off`, entfällt Erfassung und Tabelle vollständig.

**Steps** sind die Schritte des Arbeitszyklus, je bearbeitetem Task getrennt — typischerweise: `claim-next`/`claim`, `analyze`, `umsetzen` (die eigentliche Implementierung inkl. lokaler Checks), `PR`, `done`, `merge` (bzw. `concern`, falls statt Umsetzung ein Concern gemeldet wird). Im Board-Modus die Steps pro Task gruppieren.

Je Step **zwei** Werte getrennt erfassen:

- **a) Planstack-Calls** — Tokens für die Interaktion mit der Planstack-API/dem MCP-Server selbst (Request-Aufbau, Antwort-Verarbeitung), also der Steuerungs-Overhead des Steps.
- **b) Aufgaben-Ausführung** — Tokens für die eigentliche fachliche Arbeit dieses Steps (Code lesen/schreiben, Analyse, Reasoning, Tool-Calls außerhalb von Planstack).

Die Werte sind eine **Best-Effort-Schätzung** aus dem tatsächlichen Verlauf (exaktes Token-Accounting steht dem Skill zur Laufzeit nicht zur Verfügung) — als solche kennzeichnen, nicht als exakte Abrechnung ausgeben.

**Ausgabe am Ende** (Beispielformat; eine Zeile je Step, Summenzeile je Task und Gesamtsumme):

```
Token-Metriken (Schätzung)

Task C27
| Step        | Planstack-Calls | Aufgaben-Ausführung | Summe  |
|-------------|-----------------|---------------------|--------|
| claim-next  |             420 |                   0 |    420 |
| analyze     |             180 |               3 200 |  3 380 |
| umsetzen    |             150 |              18 400 | 18 550 |
| PR          |             120 |               2 100 |  2 220 |
| done        |             110 |                   0 |    110 |
| merge       |             110 |                   0 |    110 |
| Summe C27   |           1 090 |              23 700 | 24 790 |

Gesamt: Planstack-Calls 1 090 · Aufgaben-Ausführung 23 700 · Summe 24 790
```

Im Einzel-Task-Modus (`/planstack work <PROJECT> <TASK>`) genügen die eine Task-Tabelle und die Gesamtsumme.

## Konfiguration ziehen (`/planstack update-config`)

**Aufruf `/planstack update-config [<PROJECT>]`** (erstes Argument `update-config`): zieht die neuesten Konfigurationen aktiv nach (statt erst bei Drift) und gibt die Versionsnummern aus.

- **Ohne `<PROJECT>`:** **alle** zugänglichen Projekte aktualisieren — `GET $BASE/projects` auflisten und für **jedes** `GET $BASE/projects/<alias>/config` lesen. Dabei die allgemeinen Inhalte (`operating_manual` + `status_rules` + `skill_instructions`) einmal übernehmen und je Projekt dessen Konfiguration (`effective`/`client_hints`, `instructions`, `config_version`). Anschließend das gelieferte `skill_revision` in `config.json` schreiben (Baseline aktualisieren) **und** je Projekt die `config_versions` als lokale Baseline (`projects.<alias>.config_versions`).
- **Mit `<PROJECT>`:** nur die allgemeine Config **und** die Config dieses einen Projekts.

**Ausgabe** — immer die Versionsnummern zeigen, z. B.:

```
Allgemein (Skill):  skill_revision <alt> → <neu>
Projekt L2L:  config_version 3
Projekt LOG:  config_version 1
Projekt B2R:  config_version 2
```

(Mit `<PROJECT>` nur die allgemeine Zeile + diese eine Projekt-Zeile.)

## Auto-Modus (`/planstack auto <PROJECT>`)

Arbeitet das Board von `<PROJECT>` **dauerhaft und unbeaufsichtigt** ab. `auto` steht in der **Sub-Kommando-Position** (erstes Argument, wie `review`/`fix`/`settings`/`update-config`), gefolgt vom Projekt: `/planstack auto <PROJECT>`. Es ist **kein** Task namens „auto". Der Haupt-Agent ist dabei nur **Supervisor**: Er startet in einer Endlosschleife nacheinander **Auto-Runs**, jeder Auto-Run läuft als **eigener Subagent** (frischer Kontext), und der Supervisor entscheidet nur anhand von dessen Ergebnisbericht, wie es weitergeht. Der Modus endet nicht von selbst — er läuft, bis der Nutzer ihn abbricht.

**Supervisor-Schleife** (Haupt-Agent):

1. **Einen Auto-Run als Subagent starten** (Agent-Tool, `subagent_type: general-purpose`, **synchron** — `run_in_background: false`, weil das Ergebnis die nächste Entscheidung bestimmt). Prompt = die „Auto-Run"-Anweisung unten, mit `<PROJECT>` fest eingesetzt.
2. **Ergebnisbericht lesen.** Der Subagent liefert strukturiert zurück: `{ "action": "review|finish|pick|concern|idle", "task": "<Name|null>", "detail": "<kurz>" }`.
3. **Verzweigen:**
   - `action` ≠ `idle` (der Auto-Run hat etwas erledigt) → **sofort** den nächsten Auto-Run starten (zurück zu 1), ohne Pause.
   - `action` = `idle` (nichts zu tun gefunden) → **5 Minuten warten**, dann den nächsten Auto-Run starten (zurück zu 1).
4. Endlos wiederholen.

Kurz nach dem Start dem Nutzer einmal bestätigen, dass der Auto-Modus für `<PROJECT>` läuft; danach je Auto-Run knapp berichten (eine Zeile: Aktion + Task) gemäß Einstellung `verbosity`.

**Warten (5 Minuten):** Nach einem `idle`-Auto-Run 300 s echt pausieren (nicht mit Arbeit „totlaufen"), bevor der nächste startet. Kommt vorher ein Nutzer-Input, diesen bevorzugt behandeln.

**Auto-Run (ein Subagent, genau eine Arbeitseinheit):** Der Subagent **wählt** anhand des Boards die erste zutreffende Arbeit und **ruft dafür das passende bestehende `/planstack`-Sub-Kommando** auf — jeweils mit **explizitem** `<PROJECT>` **und** `<TASK>` (kein Auto-Pick im Sub-Kommando) —, führt es vollständig aus, meldet das Ergebnis zurück und beendet sich; er startet **keine** weiteren Auto-Runs (das macht der Supervisor). Priorität:

1. **Reviewbar?** Liegt mindestens ein Task zum Review bereit (`REVIEWBAR`-Pool bzw. noch nicht übernommener `IN_REVIEW`, mit PR, nicht selbst umgesetzt), den **ersten** davon per **`/planstack review <PROJECT> <TASK>`** reviewen. → `action: "review"`.
2. **Sonst: eigene offene Tasks?** Gibt es Tasks, die **ich selbst** beansprucht habe und die noch in Arbeit sind (Status *beansprucht / in Analyse / in Arbeit / in Bereinigung*), den **ersten** davon **bis zu einem polierten PR** fertigstellen:
   - hat er bereits einen offenen PR, der noch Politur braucht (rote CI oder offene/ungelöste Kommentare) → **`/planstack fix <PROJECT> <TASK>`**. → `action: "fix"`.
   - sonst → **`/planstack work <PROJECT> <TASK>`** (der Ein-Task-Modus führt den Zyklus ab dem aktuellen Status weiter, bis ein polierter PR steht). → `action: "finish"`.
3. **Sonst: pickbar?** Ist ein Task pickbar, den **besten** (höchste `unlocks`) bestimmen und per **`/planstack work <PROJECT> <TASK>`** bis zum erstellten PR umsetzen. → `action: "pick"`.
4. **Sonst:** nichts zu tun, kein Sub-Kommando aufrufen. → `action: "idle"`.

Der Subagent ermittelt den konkreten `<TASK>` (Name) zuerst aus dem Board bzw. `GET /tasks` (Schritt 2 gefiltert auf die eigene Beanspruchung — Identität = der Board-Nutzer dieses Tokens — und einen Arbeits-Status) und ruft das Sub-Kommando dann gezielt mit diesem Namen auf. Das jeweilige Sub-Kommando bringt seinen eigenen (ereignisgesteuerten) Zyklus, seine lokalen Checks/Einstellungen und seine Selbst-Update-Prüfung selbst mit — der Auto-Run baut nichts davon nach. Meldet die Umsetzung einen **Concern** statt einer Änderung, gilt der Auto-Run als „hat etwas getan" (`action: "concern"`, nicht `idle`). Nicht pickbare/übernehmbare Tasks nie erzwingen.

## Review (`/planstack review [<PROJECT>] [<TASK>]`)

Reviewt Tasks, die **zum Review bereitliegen**: im Pool-Status `REVIEWBAR` (die Spalte *vor* `IN_REVIEW`) oder in einem noch nicht übernommenen `IN_REVIEW` — jeweils mit PR. **Eigene Tasks (selbst beansprucht/umgesetzt) sind nicht reviewbar** — `review-next` überspringt sie, ein gezielter Aufruf darauf wird abgelehnt. Das Übernehmen setzt nur `reviewed_by`; die Verschiebung nach `IN_REVIEW` löst das `REVIEWING`-Event über die Org-Automation aus (die Endpunkte verschieben nicht selbst). Ablauf:

1. **Task wählen & Review übernehmen** (setzt `reviewed_by`):
   - `<PROJECT> <TASK>`: gezielt dieser Task → `POST $BASE/projects/$PROJ/tasks/$TASK/review-claim`.
   - nur `<PROJECT>`: automatisch den ersten zum Review bereiten Task mit PR aus dem `REVIEWBAR`-Pool → `POST $BASE/projects/$PROJ/review-next`.
   - **weder `<TASK>` noch `<PROJECT>`**: **projektübergreifend** — `GET $BASE/projects` auflisten und `review-next` pro Projekt aufrufen, bis eines einen Task liefert.
   Antwort `{"reviewing": null}` bzw. leer ⇒ nichts zu reviewen (nächstes Projekt / fertig). Nach dem Übernehmen `ev <id> REVIEWING` melden (best-effort, `<id>` aus der Antwort).
2. **Review ausführen:** den **Review-Skill** (`/review`) für den PR des Tasks laufen lassen — mit Strenge gemäß `review_strictness` und Prüftiefe gemäß `review_thoroughness`. Die Antwort aus Schritt 1 trägt **immer** `pr_number` (und `pr_url`, sofern Repo konfiguriert) — unabhängig von den Board-/`task.fields`-Einstellungen. Ergebnis = Empfehlung (`APPROVE` oder `REQUEST_CHANGES`) + die ausführliche Review-Analyse.
3. **Review vorlegen & Empfehlung festlegen** gemäß Einstellung `review_auto_status`:
   - `auto`: die aus dem Review abgeleitete Empfehlung direkt verwenden — **keine** Rückfrage nötig.
   - `manual` **oder nicht gesetzt** (Default): dem Nutzer **zuerst die vollständige Review anzeigen** (Review-Konfiguration + TLDR + ausführliche Analyse, Aufbau wie in Schritt 4) und **erst danach** die Empfehlung (`APPROVE`/`REQUEST_CHANGES`) bestätigen lassen. **Grundsatz: nie nach der Entscheidung fragen, ohne die Review vorher gezeigt zu haben.**

   Solange die Settings das automatische Bestätigen bzw. Ablegen/Posten **nicht** aktiv festlegen — also `review_auto_status`≠`auto` **oder** `review_results` schreibt nicht automatisch nach Task/PR — wird also erst die Review angezeigt und die Bestätigung des Nutzers eingeholt, **bevor** irgendetwas in Task oder PR geschrieben wird (Schritte 4–5). Bei `auto` laufen die Schritte 4–5 direkt.
4. **Ergebnis erfassen** (nach Bestätigung bzw. direkt bei `auto`): `POST $BASE/projects/$PROJ/tasks/$TASK/review` mit `{"recommendation":"APPROVE|REQUEST_CHANGES","summary":"…"}` — füllt `last_reviewed_at`, `last_review_recommendation`, `last_review_summary`. Das Feld `summary` ist **keine Kurzbeschreibung**, sondern die **ausführliche Review-Analyse**. Aufbau (in dieser Reihenfolge):
   1. **Review-Konfiguration** (vorab, damit das Review für andere nachvollziehbar ist) — eine Zeile: `Review-Konfiguration: Strenge=<review_strictness>, Gründlichkeit=<review_thoroughness>, Modell=<tatsächlich genutztes Claude-Modell>, Effort=<Reasoning-Aufwand>`.
   2. **TLDR** — eine Zeile: `TLDR: <Kernaussage in 1–3 Sätzen>`.
   3. **Ausführliche Analyse** — Befunde je Datei/Aspekt, Begründungen, Risiken, Vorschläge.
5. **Ablage gemäß `review_results`:** bei `task_only` nur den Task (Schritt 4). Bei `task_and_pr` zusätzlich am PR hinterlegen: `gh pr review <pr> --approve` bzw. `--request-changes` mit der Zusammenfassung als Kommentar.

**Fortschritts-Events (best-effort, nicht blockierend):** nach Schritt 4 `ev <id> REVIEWED`, danach je nach Empfehlung `ev <id> APPROVED` bzw. `ev <id> CHANGES_REQUESTED` (siehe „Fortschritts-Events" im Betriebshandbuch).

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

**Fortschritts-Events (best-effort, nicht blockierend):** zu Beginn der Politur `ev <id> POLISHING`, nach grüner CI + beantworteten/aufgelösten Kommentaren `ev <id> POLISHED` (`<id>` = numerische Task-id; siehe „Fortschritts-Events" im Betriebshandbuch).

Danach ggf. via `/planstack review` erneut prüfen.
