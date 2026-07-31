## Konventionen (planstack, serverseitig gepflegt)

Verbindliche, projektübergreifende Anweisungen für den allgemeinen `planstack`-Skill. Serverseitig gepflegt: bei Drift (`X-Planstack-Skill-Revision`) über `GET /config` → `skill_instructions` nachladen.

- **PR-Titel:** Beim Erstellen eines Pull Requests **immer** Projekt- **und** Task-Kürzel als Titel-Prefix setzen, in dieser Reihenfolge: `<PROJECT>-<TASK>: <Kurzbeschreibung>` (z. B. `L2L-G5: PseudoPropertyBinding-Fallback`). `<PROJECT>` ist der Projekt-Alias aus dem Aufruf (z. B. `L2L`), `<TASK>` der Kurzname des Tasks (Feld `name`), nicht die numerische id. Gilt für beide Modi.
  - Das Projekt-Kürzel ist **nicht optional**: `G5: …` (nur Task) ist falsch, `L2L-G5: …` ist richtig. Zwei Tasks unterschiedlicher Projekte tragen sonst im selben Repo denselben Titel-Prefix.
  - **Diese Regel hat Vorrang** vor jeder abweichenden Fassung in der lokalen SKILL.md — eine Kopie dort kann veraltet sein (siehe „Snapshot mitschreiben"). Steht dort noch `<TASK>: …` ohne Projekt-Kürzel, gilt trotzdem `<PROJECT>-<TASK>: …`, und die Kopie ist zu erneuern.
  - Ebenso als Branch-Name-Prefix sinnvoll (`<project>-<task>-…`, klein geschrieben), damit Branch und PR-Titel zusammenpassen.

## Sticky-Statuszeile (Pflicht bei JEDEM Aufruf)

**Jeder** `/planstack`-Aufruf zeigt in einer **Statuszeile** (sticky, unten im Fenster), was gerade getan wird — auch die kurzen Kommandos (`settings`, `update-config`) und das **Nachziehen des Skills selbst**, das als Erstes Zeit kostet und nicht unsichtbar passieren darf. Endet der Aufruf, wird die Zustandsdatei geleert.

**Format** (genau diese Reihenfolge, Phase samt Prozentzahl in Klammern **direkt hinter dem Kommando**):

```
<Symbol> <Kommando> (<Phase> <%>) <PROJECT> · <TASK> — <kurzer Schritt>
```

Beispiele: `⚙ Work (Bearbeite 44 %) DCE · C27 — 4/9 Dateien` · `⚙ Auto › Review (Review 25 %) DCE · A1 — 2/8 Dateien im Diff` · `⚙ Update (Schreibe Skill) — SKILL.md ersetzen` · `⏳ Auto (Idle) DCE · — warte 5 min`

`<Kommando>` ist das laufende Sub-Kommando in Titelschreibweise (`Work`, `Auto`, `Review`, `Fix`, `Plan`, `Settings`, `Update`). Ruft ein Kommando ein anderes auf — im Auto-Modus der Regelfall —, werden **beide** genannt, außen zuerst, getrennt durch `›` (`Auto › Work`); mehr als zwei Ebenen nicht. `<PROJECT>`/`<TASK>` **entfallen**, wo es sie nicht gibt; ist das Projekt bekannt, aber noch kein Task, bleibt der Trenner stehen (`DCE · —`).

**Wann geschrieben wird:** **bei jedem Phasenwechsel** und **bei jeder gezählten Einheit** innerhalb einer Phase (z. B. nach jeder fertigen Datei), jeweils **bevor** der nächste Schritt beginnt. Ein Schreibvorgang vor *jedem* einzelnen Tool-Aufruf ist **nicht** nötig — das verdoppelt die Roundtrips, ohne mehr zu zeigen. Wo die Zeile neben einem Shell-Aufruf steht, gehört sie **in denselben Aufruf** (`printf … > "$ST"; curl …`) und kostet damit keinen eigenen Roundtrip. Der Task-Name steht drin, sobald er bekannt ist (auch vor dem Claim), die PR-Nummer als Link, sobald `pr_url` vorliegt; ist ein Feld noch unbekannt, mit Platzhalter beginnen (`⚙ Work (Wähle) DCE · — Board lesen`) und verfeinern, statt mit dem Schreiben zu warten.

`<Phase>` ist die **laufende Phase** in Titelschreibweise — was in diesem Moment tatsächlich passiert, nicht die grobe Arbeitseinheit (eine Einheit mit `action: "pick"` durchläuft mehrere Phasen). Verbindliche Phasen mit ihrer zählbaren Einheit:

| Phase | zählbare Einheit |
|---|---|
| `Prüfe Drift` · `Lade Config` · `Schreibe Skill` · `Baseline` | — |
| `Wähle` · `Lade Details` | — |
| `Analyse` | Teilschritte |
| `Bearbeite` | Dateien, Teilschritte |
| `Erstelle PR` · `Hinterlege PR` · `Fertigstellung` | — |
| `Fix` | Checks, Kommentare, Review-Threads |
| `Review` | Dateien im Diff |
| `Plane` | Tasks, Phasen |
| `Einstellungen` · `Concern` · `Idle` · `Pause` | — |

Passt keine Phase, die nächstliegende nehmen statt eine neue zu erfinden — die Zeile soll über Aufrufe hinweg wiedererkennbar bleiben. Der Ergebnisbericht des Auto-Runs behält sein `action`-Feld; die Statuszeile ist davon unabhängig.

**Die Selbst-Update-Kette wird angezeigt** (sie ist von außen sonst nicht zu erkennen): `⚙ <Kommando> (Prüfe Drift) …` → bei Abweichung `⚙ Update (Lade Config) …` → `⚙ Update (Schreibe Skill) — SKILL.md ersetzen` → `⚙ Update (Baseline) — config.json`, dann zurück zum eigentlichen Kommando.

**`<%>` ist der Fortschritt INNERHALB der Phase — gerechnet, nie geschätzt:** erledigte ÷ geplante Einheiten, gerundet auf ganze Prozent. Der Nenner kommt aus der natürlichen Einheit der Phase (Tabelle oben) oder aus der eigenen Teilschritt-Liste; er **darf wachsen** — werden aus 7 geplanten Teilschritten 10, wird korrigiert, auch wenn die Zahl dadurch zurückgeht (ehrlich statt künstlich monoton). Steht kein Nenner fest, **entfällt die Prozentzahl** (`⚙ Work (Erstelle PR) DCE · C27`): eine geratene Zahl ist erfunden und schlimmer als keine. Der Bruch gehört **zusätzlich** in den Schritt-Text (`4/9 Dateien`, `2/5 Checks`, `3/7 Tasks`).

Der Schritt-Text ist die eigentliche Information und soll **konkret** sein: `4/9 Dateien: TaskController.php` statt `arbeite`, `2/5 Checks: phpstan` statt `CI läuft`, `GET /config` statt `lade`. Eine Zeile ist das Budget — kürzen ja, durch Floskeln ersetzen nein. Wartet der Aufruf auf etwas Externes, gehört genau das hinein (`⏳ Work (Fix) L2L · G5 — warte auf CI`), damit Stillstand nicht wie ein Absturz aussieht.

**Einrichtung (zu Beginn jedes Aufrufs prüfen, bei Bedarf einmal anlegen):**

1. **Zustandsdatei je Session:** `~/.claude/planstack-status-<session_id>.txt`, genau **eine** Zeile.
2. **Statusline-Skript:** liest das Session-JSON von **stdin**, zieht `session_id`, gibt **nur** den Inhalt dieser Datei aus (fehlt sie: keine Ausgabe), in **UTF-8**. Die Bindung an die `session_id` ist **Pflicht**: liest das Skript eine feste Datei ohne sie, erscheint die Zeile in **allen** Sessions des Nutzers.
3. **`statusLine`-Eintrag** in `~/.claude/settings.json` auf dieses Skript zeigen lassen, **mit** `refreshInterval`:

   ```json
   { "statusLine": { "type": "command", "command": "…", "refreshInterval": 5 } }
   ```

Der Eintrag ist zwangsläufig global — die Session-Bindung sorgt dafür, dass er nur hier etwas anzeigt. Eine **neu** angelegte `statusLine`-Konfiguration greift erst nach einem **Neustart** von Claude Code, eine vorhandene sofort. Fehlt die Einrichtung, einmal anlegen und den Nutzer hinweisen — nicht bei jedem Aufruf erneut fragen.

**`refreshInterval` ist der zentrale Praxis-Hinweis** (Sekunden, Minimum `1`, Empfehlung **5–10**): sonst wird die Statusline nur **ereignisgesteuert** neu berechnet (Prompt, Tool-Ende, Moduswechsel). Während ein Subagent arbeitet — im Auto-Modus der Regelfall — passiert davon minutenlang nichts, und die Zeile friert mit einem überholten Schritt ein.

**Klickbare Links (OSC 8):** `<TASK>` und PR-Nummer als OSC-8-Hyperlink ausgeben (`\e]8;;<URL>\a<Text>\e]8;;\a`, Ctrl+Klick bzw. Cmd+Klick). `<TASK>` → `<WEB>/projects/<PROJECT>`, wobei `<WEB>` die `base_url` **ohne** abschließendes `/api` ist (einen Deep-Link auf einen einzelnen Task gibt es nicht). PR-Nummer → `pr_url` aus der API (u. a. in `review-claim`/`review-next` und an jedem dekorierten Task) — **nie** selbst eine URL bauen. `\e`/`\a` müssen **echte** Steuerzeichen sein (per `printf`, nicht als Literal `\e`); Terminals ohne Hyperlink-Unterstützung zeigen nur den Text, sichtbare Escape-Reste wie `]8;;https…` sind dagegen ein **Fehler**. Geht es nicht sauber, die Zeile **ohne** Links schreiben — lesbar ohne Link ist besser als kaputt mit. Alternative ohne eigenes Skript: `footerLinksRegexes` in den Settings macht aus erkannten Mustern klickbare Footer-Badges, dafür ohne freien Schritt-Text.

**Das Prozentzeichen bleibt einfach: `%`, nie `%%`.** Die Zeile ist **Text, kein Format-String**. Nur `printf` behandelt sein **erstes** Argument als Format-String; wer dort reflexhaft verdoppelt und die Zeile dann mit einem Werkzeug ohne Format-Strings schreibt, hat zwei Prozentzeichen hinter der Zahl. Deshalb die Zeile immer als **Argument** übergeben:

```
printf '%s\n' "⚙ Review (Review 94 %) L2L · G5 — 15/16 Dateien"
```

Mit OSC-8-Links gehören **nur** die Steuerzeichen in den Format-String, variabler Text bleibt Argument:

```
printf '\e]8;;%s\a%s\e]8;;\a %s\n' "$url" "$task" "$rest"
```

`Set-Content -Encoding utf8`, Datei-Werkzeuge und Here-Docs kennen gar keine Format-Strings — dort ist die Verdopplung **immer** falsch. Prüfmaßstab ist der Dateiinhalt: je Prozentangabe steht dort **ein** Zeichen `%`.

Immer **überschreiben**, nie anhängen. Das Schreiben ist **best-effort**: Fehler ignorieren, den Ablauf nie blockieren, das Setzen der Zeile nicht in Prosa berichten.

## Feingranulare Config-Aktualisierung (`config_versions`)

**Drift-Marken auf dem Hot-Path (Header jeder Board-/Task-Antwort):**

- `X-Planstack-Config-Version` — **Projektconfig** (Profil/Overrides).
- `X-Planstack-Skill-Revision` — geteilte **Datei**-Inhalte (Betriebshandbuch + Statusregeln + `skill_instructions`). Abweichung → diese Inhalte neu befolgen und den lokalen Snapshot erneuern (siehe „Selbst-Update" im Bootstrap-Teil).
- `X-Planstack-Status-Config-Version` — **org-weite Status-Config** (Status, Spalten, Übergänge, Status-/Event-Automationen). Diese Marke — **nicht** die Skill-Revision — steigt, wenn eine Organisation ihren Workflow ändert.

`GET /config` liefert dazu `status_config_version` (dieselbe Marke wie der Header) und `config_versions` — je Org-Config-Tabelle den jüngsten `updated_at` (ISO-8601 oder `null`):

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

Diese Werte je Projekt **lokal** als Baseline in `${CLAUDE_SKILL_DIR}/config.json` unter `projects.<PROJECT>` speichern (`status_config_version` **und** `config_versions`; rein lokal, nie an den Server). Beim Sync-at-start genügt der Vergleich des Headers mit der lokalen `status_config_version` — sind sie gleich, ist nichts nachzuziehen (kein `GET /config` nötig). Weichen sie ab (oder fehlt die Baseline), `GET /config` lesen — dabei **nur die betroffenen Teile** anfordern (`?parts=status_rules`, siehe unten) — und je `config_versions`-Eintrag prüfen: geändert → **nur diese** Config neu übernehmen, nicht das ganze Skill-Dokument.

- `statuses` · `status_groups` · `transitions` · `status_automations` · `event_automations` → den `status_rules`-Block neu übernehmen (Abschnitt „Status dieser Organisation").
- `custom_fields` → nur die benutzerdefinierten Task-Felder (relevant für `/planstack plan`).

Danach die neue `status_config_version` **und** die `config_versions` als lokale Baseline zurückschreiben. `null` bleibt `null`.

**Teilweise abrufen (`?parts=`):** `GET /config?parts=<a>,<b>` liefert nur die genannten Blöcke statt der vollen Antwort — erlaubt sind `status_rules`, `operating_manual`, `skill_instructions`, `plan_instructions`, `config`, `catalog`. Die Versions-/Drift-Felder (`config_version`, `skill_revision`, `status_config_version`, `config_versions`, `plan_revision`) sind **immer** enthalten, damit der Aufruf allein zum Baseline-Vergleich genügt. Ohne `parts` verhält sich der Endpunkt unverändert. Die volle Antwort ist ~68 KB — wer nur `status_rules` braucht, holt mit `?parts=status_rules` ein Zwanzigstel davon. `GET /config` unterstützt außerdem `ETag`/`If-None-Match` (`304` ohne Body), wenn sich seit dem letzten Abruf nichts geändert hat.

**Snapshot mitschreiben (gilt für `skill_revision`):** Die Regeln in der lokalen SKILL.md sind nur eine **Kopie** dieses Textes. Wird eine neue `skill_revision` als Baseline geschrieben, **ohne** die Kopie zu erneuern, folgt der Skill ab dann dauerhaft dem alten Text — die Drift-Prüfung schlägt nie wieder an, weil lokale Baseline und Server-Header übereinstimmen. Genau so entstehen z. B. PR-Titel ohne Projekt-Kürzel. Deshalb gilt bei **jedem** Nachziehen (Drift **und** `update-config`): **erst** die SKILL.md aus `GET $BASE/skill` ersetzen, **dann** die Baseline setzen — nie die Baseline allein. Der verbindliche Ablauf samt Schreibbefehl steht im Abschnitt „Selbst-Update" des Bootstrap-Teils. Antwortet `GET $BASE/skill` mit `404` (älterer Server), die Baseline **nicht** anheben — dann bleibt die Drift-Prüfung wirksam.

Ereignisgesteuerter Status: gilt wie im Betriebshandbuch und in den Statusregeln beschrieben — trägt `status_rules` den Abschnitt „Ereignis-gesteuerte Status-Zuweisung", folgt der Status ausschließlich den Fortschritts-Events, und direkte `POST /tasks/{id}/status`-Calls bleiben serverseitig ohne Wirkung.

## Lokale Einstellungen (`/planstack settings`)

Lokale Einstellungen liegen **ausschließlich auf diesem Rechner** in `${CLAUDE_SKILL_DIR}/settings.json` (neben `config.json`) und werden **nie** an den Server übertragen. Fehlt die Datei oder ein Schlüssel, gilt der Default.

**Aufruf `/planstack settings`** (erstes Argument ist `settings`, kein Projekt-Alias): die Einstellungen als **editierbare, interaktive Auswahl** präsentieren — wie `claude /settings`, **nicht** nacheinander abfragen. Ein Auswahl-Formular mit mehreren Fragen auf einmal (je eine pro Einstellung), **deutsche** Labels und Werte, der aktuelle Wert vorausgewählt. Danach alles gesammelt nach `settings.json` schreiben und die aktualisierte Übersicht zeigen. **Anzeige immer deutsch**, gespeichert werden die stabilen Schlüssel/Werte:

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
| Lauf-Metriken | `metrics` | An→`on` · Aus→`off` | An |

- **`verbosity`** steuert **verbindlich** (keine Empfehlung), wie viel Fließtext während der ganzen Abarbeitung ausgegeben wird, ab dem ersten Satz: `minimal` = nur das Nötigste — keine Vorreden, keine Ankündigungen, keine Zwischenerklärungen, keine Zusammenfassung des eben Getanen; pro Task maximal eine knappe Zeile je abgeschlossenem Schritt (`C27: PR #123 geöffnet`) und am Ende das Ergebnis; Tool-Aufrufe sprechen für sich und werden nicht zusätzlich in Prosa beschrieben. `default` = knappe Orientierung + Ergebnisse. `maximal` = Schritte, Begründungen, Abwägungen offenlegen. Explizit angeforderte Inhalte (Review-`summary`, Metriken, direkte Nutzerfragen) sind davon unberührt.
- **`review_strictness`** = wie hart bewertet wird: `lenient` nur echte Blocker (im Zweifel `APPROVE`), `default` normal, `strict` auch kleinere Mängel, Stil, Edge-Cases (eher `REQUEST_CHANGES`).
- **`review_thoroughness`** = wie gründlich geschaut wird: `relaxed` schneller Überblick, `default` normal, `meticulous` jede Datei/Zeile und alle Edge-Cases.
- **`metrics`** = Lauf-Metriken erfassen und am Ende ausgeben (siehe „Metriken") oder nicht.

**Anwendung im Arbeitszyklus** (beide Modi): `yes` → Schritt ausführen · `no` → überspringen · `ask` → **einmal für die aktuelle Aufgabe** nachfragen und die Antwort nur dafür anwenden (nicht speichern). Reihenfolge vor dem PR, jeweils nur wenn erlaubt: `local_phpcs` (formatieren) → `local_phpstan` → `local_tests`. Schlägt ein aktivierter Schritt fehl, erst beheben, dann PR. `babysit_prs` greift **nach** dem PR-Öffnen. Vor jeder Abarbeitung die aktuellen Einstellungen lesen.

Unbekannte Schlüssel in `settings.json` werden **ignoriert**, nicht entfernt — so überlebt eine Datei den Wechsel zwischen Skill-Versionen.

## Metriken (Einstellung `metrics`)

Ist `metrics` = `on` (Default), wird je Planstack-Step erfasst, was **tatsächlich messbar** ist, und am Ende des Laufs als Tabelle ausgegeben; bei `off` entfallen Erfassung und Tabelle.

**Nur zählen, nicht schätzen.** Exaktes Token-Accounting steht dem Skill zur Laufzeit **nicht** zur Verfügung — eine Token-Spalte wäre eine erfundene Zahl im Gewand einer Messung und ist deshalb **nicht** auszugeben. Erfasst werden je Step ausschließlich abzählbare Größen:

- **API-Calls** — Anzahl der Planstack-Requests in diesem Step.
- **Tool-Calls** — Anzahl der übrigen Werkzeug-Aufrufe (Datei, Shell, Subagent).
- **Dauer** — Wall-Clock des Steps (Sekunden, aus der Uhr, nicht geschätzt).
- **Diff** — bei Steps mit Code-Änderung geänderte Dateien und Zeilen (`git diff --shortstat`).

**Steps** sind die Schritte des Arbeitszyklus, je Task getrennt: `claim-next`/`claim`, `analyze`, `umsetzen` (inkl. lokaler Checks), `PR`, `done`, `merge` — bzw. `concern`. Im Board-Modus je Task gruppieren.

```
Lauf-Metriken (gezählt)

Task C27
| Step       | API | Tools | Dauer | Diff        |
|------------|-----|-------|-------|-------------|
| claim-next |   1 |     0 |    2s | —           |
| analyze    |   2 |     7 |   48s | —           |
| umsetzen   |   3 |    31 |  6m12s| 9 D, +214/-38 |
| PR         |   2 |     4 |   35s | —           |
| merge      |   2 |     1 |    6s | —           |
| Summe C27  |  10 |    43 |  7m43s| 9 D, +214/-38 |
```

Im Einzel-Task-Modus genügen die eine Task-Tabelle und die Summenzeile.

## Konfiguration ziehen (`/planstack update-config`)

**Aufruf `/planstack update-config [<PROJECT>]`**: zieht die neuesten Konfigurationen aktiv nach (statt erst bei Drift) und gibt die Versionsnummern aus.

- **Ohne `<PROJECT>`:** alle zugänglichen Projekte — `GET $BASE/projects` auflisten, je Projekt `GET $BASE/projects/<alias>/config` lesen. Die allgemeinen Inhalte einmal übernehmen, je Projekt dessen Konfiguration (`effective`/`client_hints`, `instructions`, `config_version`). Anschließend die lokale SKILL.md aus `GET $BASE/skill` ersetzen (siehe „Selbst-Update") und **erst dann** `skill_revision` als Baseline schreiben, dazu je Projekt die `config_versions`.
- **Mit `<PROJECT>`:** nur die allgemeine Config und die dieses einen Projekts (das Ersetzen der SKILL.md gehört zur allgemeinen Config und passiert auch hier).

**Ausgabe** — immer die Versionsnummern und **ob die SKILL.md neu geschrieben wurde**:

```
Allgemein (Skill):  skill_revision <alt> → <neu>  (SKILL.md neu geschrieben)
Projekt L2L:  config_version 3
Projekt LOG:  config_version 1
```
