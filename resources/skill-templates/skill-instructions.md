## Konventionen (planstack, serverseitig gepflegt)

Verbindliche, projektübergreifende Anweisungen für den allgemeinen `planstack`-Skill. Serverseitig gepflegt: bei Drift (`X-Planstack-Skill-Revision`) über `GET /config` → `skill_instructions` nachladen.

- **PR-Titel:** Beim Erstellen eines Pull Requests **immer** Projekt- **und** Task-Kürzel als Titel-Prefix setzen, in dieser Reihenfolge: `<PROJECT>-<TASK>: <Kurzbeschreibung>` (z. B. `L2L-G5: PseudoPropertyBinding-Fallback`). `<PROJECT>` ist der Projekt-Alias aus dem Aufruf (z. B. `L2L`), `<TASK>` der Kurzname des Tasks (Feld `name`), nicht die numerische id. Gilt für beide Modi.
  - Das Projekt-Kürzel ist **nicht optional**: `G5: …` (nur Task) ist falsch, `L2L-G5: …` ist richtig. Zwei Tasks unterschiedlicher Projekte tragen sonst im selben Repo denselben Titel-Prefix.
  - **Diese Regel hat Vorrang** vor jeder abweichenden Fassung in der lokalen SKILL.md — eine Kopie dort kann veraltet sein (siehe „Snapshot mitschreiben"). Steht dort noch `<TASK>: …` ohne Projekt-Kürzel, gilt trotzdem `<PROJECT>-<TASK>: …`, und die Kopie ist zu erneuern.
  - Ebenso als Branch-Name-Prefix sinnvoll (`<project>-<task>-…`, klein geschrieben), damit Branch und PR-Titel zusammenpassen.

## Sticky-Statuszeile (Pflicht bei JEDEM Aufruf)

**Jeder** `/planstack`-Aufruf zeigt dauerhaft in einer **Statuszeile** (sticky, unten im Fenster), was gerade getan wird — nicht nur der Auto-Modus, und nicht nur als Fließtext im Verlauf. Das gilt ausdrücklich auch für die kurzen Kommandos (`settings`, `update-config`) und für das **Nachziehen des Skills selbst**: die Drift-Prüfung und das Neuschreiben der SKILL.md sind oft das Erste, was Zeit kostet (Netzwerk), und dürfen nicht unsichtbar passieren. Solange ein `/planstack`-Aufruf läuft, steht in der Zeile, was er tut; endet er, wird die Zustandsdatei geleert.

**Format** (genau diese Reihenfolge, Phase samt Prozentzahl in Klammern **direkt hinter dem Kommando**):

```
<Symbol> <Kommando> (<Phase> <%>) <PROJECT> · <TASK> — <kurzer Schritt>
```

Beispiele:

- `⚙ Work (Bearbeite 44 %) DCE · C27 — 4/9 Dateien`
- `⚙ Auto › Review (Review 25 %) DCE · A1 — 2/8 Dateien im Diff`
- `⚙ Fix (Fix 40 %) L2L · G5 — 3/7 Kommentare`
- `⚙ Plan (Plane 43 %) DCE — 3/7 Tasks`
- `⚙ Update (Lade Config) DCE — GET /config`
- `⚙ Update (Schreibe Skill) — SKILL.md ersetzen`
- `⚙ Settings (Einstellungen) — settings.json lesen`
- `⏳ Auto (Idle) DCE · — warte 5 min`

`<Kommando>` ist das **laufende Sub-Kommando** in Titelschreibweise: `Work`, `Auto`, `Review`, `Fix`, `Plan`, `Settings`, `Update`. Ruft ein Kommando ein anderes auf — im Auto-Modus der Regelfall —, werden **beide** genannt, außen zuerst, getrennt durch `›`: `Auto › Work`, `Auto › Review`, `Auto › Fix`. So ist gleichzeitig sichtbar, dass die Schleife lebt **und** was sie gerade tut. Mehr als zwei Ebenen werden nicht gezeigt.

`<PROJECT>` und `<TASK>` **entfallen**, wo es sie nicht gibt (`Settings`, `Update` ohne Projekt, `Plan` vor dem ersten Projekt) — dann folgt direkt der Gedankenstrich. Ist das Projekt bekannt, aber noch kein Task, bleibt der Trenner stehen: `DCE · —`.

**Grundregel: BEVOR etwas Neues passiert.** Die Zeile wird aktualisiert, **bevor** die nächste Handlung beginnt — vor jedem Tool-Aufruf, vor jedem Lesen und Schreiben einer Datei, vor jedem HTTP-Aufruf, vor dem Start eines Subagenten, vor jedem Phasenwechsel. Reihenfolge ohne Ausnahme: **Zeile schreiben → dann handeln.** Nicht danach, nicht „wenn gerade Zeit ist", nicht gebündelt am Ende. Eine Zeile, die erst nach der Handlung nachgezogen wird, zeigt dauerhaft die Vergangenheit und macht den ganzen Zweck zunichte.

**Die Zeile geht IMMER zusammen mit einer Server-Meldung raus.** Sie ist nur im Fenster des laufenden Workers sichtbar; das Board erfährt vom Fortschritt ausschließlich über das Fortschritts-Event. Beides getrennt zu behandeln führt in der Praxis dazu, dass die Zeile wandert und der Server nichts mitbekommt — die Karte steht dann minutenlang auf einem alten Stand. Deshalb: **jedes** Schreiben der Statuszeile setzt im selben Zug ein Event ab, mit demselben Schritt-Text als `detail` und derselben Prozentzahl als `progress` (ohne echten Nenner: `progress` weglassen, `detail` trotzdem senden).

Damit es nicht vom Erinnern abhängt, beides in **einen** Helfer legen und nur noch diesen benutzen — nie wieder direkt in die Zustandsdatei schreiben (`ev` stammt aus dem Betriebshandbuch, `$ST` ist die Zustandsdatei dieser Session):

```bash
# sp <TASK> <EVENT> "<ganze Statuszeile>" "<kurzer Schritt>" [progress]
sp(){
  printf '%s\n' "$3" > "$ST"      # Statuszeile (lokal, nur dieses Fenster)
  PS_STEP=$4; PS_PCT=${5:-}       # ab jetzt faehrt der Stand auf JEDEM pc-Aufruf mit
  ev "$1" "$2" "$4" "${5:-}"      # und geht sofort als Event raus
}

sp C27 PROCESSING "⚙ Work (Bearbeite 44 %) DCE · C27 — 4/9 Dateien" "4/9 Dateien: TaskController.php" 44
```

**Warum drei Dinge auf einmal:** die Erfahrung aus dem Betrieb ist, dass der Session-Header lückenlos mitfährt (einmal eingerichtet), das separat abzusetzende Event dagegen ausfällt (bei jedem Schritt neu zu tun). `sp` dreht das um: der Schritt landet in `PS_STEP`/`PS_PCT` und wird damit Teil des Headers, den **jeder** spätere Aufruf über `pc` ohnehin mitschickt. Selbst wenn ein Event ausfällt, zieht der nächste Claim, Status-Call oder Task-Read den Stand nach. Das Event bleibt trotzdem drin — es ist der einzige Aufruf, der auch dann passiert, wenn sonst gerade nichts mit dem Server zu bereden ist.

Welches `<EVENT>` zur laufenden Phase gehört, steht im Betriebshandbuch („Zuordnung Zyklus → Event"); die statustreibenden Events dürfen dabei mehrfach abgesetzt werden — ein wiederholtes `PROCESSING` wechselt den Status nicht erneut, transportiert aber den neuen Stand. Beide Teile sind **best-effort**: schlägt das Event fehl, wird die Zeile trotzdem geschrieben und umgekehrt; blockieren darf keiner von beiden.

Daraus folgt: der Task-Name steht drin, sobald er aus dem Board bekannt ist (auch schon vor dem Claim), die neue Phase beim Wechsel, die PR-Nummer als Link, sobald `pr_url` vorliegt. Lieber einmal zu oft schreiben als einmal zu spät — die Datei ist eine Zeile, das Schreiben kostet nichts. Ist ein Feld noch unbekannt, mit einem Platzhalter beginnen (`⚙ Work (Wähle) DCE · — Board lesen`) und verfeinern, statt mit dem Schreiben zu warten.

`<Phase>` ist die **laufende Phase** in Titelschreibweise — also das, was in diesem Moment tatsächlich passiert, nicht die grobe Arbeitseinheit. Sie ist damit feiner als das `action`-Feld des Ergebnisberichts: eine Arbeitseinheit mit `action: "pick"` durchläuft mehrere Phasen. Verbindliche Phasen, mit der Einheit, in der sich ihr Fortschritt zählen lässt:

| Phase | wann | zählbare Einheit |
|---|---|---|
| `Prüfe Drift` | Revisions-Header mit der lokalen Baseline vergleichen | — |
| `Lade Config` | `GET /config` bzw. `GET /skill` lesen | — |
| `Schreibe Skill` | den ausgelieferten Skill-Text über die SKILL.md schreiben | — |
| `Baseline` | Revisionen in `config.json` zurückschreiben | — |
| `Wähle` | Board lesen, Arbeit suchen, beanspruchen | — |
| `Lade Details` | Beschreibung, Akzeptanzkriterien und Voraussetzungen holen | — |
| `Analyse` | Umfang untersuchen, noch kein Code | Teilschritte |
| `Bearbeite` | Code-Änderungen durchführen | Dateien, Teilschritte |
| `Erstelle PR` | PR anlegen | — |
| `Hinterlege PR` | PR-Nummer am Task eintragen | — |
| `Fix` | Politur am offenen PR (Konflikte, Kommentare, CI) | Checks, Kommentare, Review-Threads |
| `Review` | Review übernehmen, Diff prüfen, Ergebnis erfassen | Dateien im Diff |
| `Fertigstellung` | fertig melden, mergen | — |
| `Plane` | Projekt, Phasen und Tasks anlegen | Tasks, Phasen |
| `Einstellungen` | lokale Einstellungen lesen und schreiben | — |
| `Concern` | Concern gemeldet, Arbeitseinheit endet | — |
| `Idle` | nichts zu tun, 5-Minuten-Pause | — |
| `Pause` | Modus angehalten | — |

Passt keine Phase, die nächstliegende nehmen statt eine neue zu erfinden — die Zeile soll über Aufrufe hinweg wiedererkennbar bleiben. Der Ergebnisbericht des Auto-Runs behält unverändert sein `action`-Feld (`review`/`fix`/`finish`/`pick`/`concern`/`idle`); die Statuszeile ist davon unabhängig.

**Beim Skill-Update (Pflicht, nicht optional):** Die Selbst-Update-Kette läuft bei jedem Aufruf und ist von außen sonst nicht zu erkennen. Sie wird durchgängig angezeigt: `⚙ <Kommando> (Prüfe Drift) …` → bei Abweichung `⚙ Update (Lade Config) …` → `⚙ Update (Schreibe Skill) — SKILL.md ersetzen` → `⚙ Update (Baseline) — config.json`. Dasselbe gilt für das Nachziehen einer einzelnen Org-Config über `config_versions` (`⚙ Update (Lade Config) DCE — status_rules`). Erst danach geht es mit dem eigentlichen Kommando weiter — der Wechsel zurück ist ebenfalls sichtbar.

**`<%>` ist der Fortschritt INNERHALB der laufenden Phase — und wird gerechnet, nie geschätzt.** Er entsteht ausschließlich aus einem Zähler mit echtem Nenner: `<%>` = erledigte Einheiten ÷ geplante Einheiten. Der Nenner kommt

1. aus der **natürlichen Einheit** der Phase (Spalte oben): zu ändernde Dateien, offene Kommentare, CI-Checks, Review-Threads, Dateien im Diff, anzulegende Tasks;
2. sonst aus der **eigenen Teilschritt-Liste**: vor Beginn der Phase die geplanten Teilschritte festhalten (das passiert beim Planen ohnehin) und danach abzählen.

Steht kein Nenner fest, **entfällt die Prozentzahl** — dann nur die Phase, ohne Zahl (`⚙ Work (Erstelle PR) DCE · C27`). Eine geratene Zahl („73 %", weil es sich nach zwei Dritteln anfühlt) ist eine erfundene Angabe und schlimmer als keine. Bei `Concern`, `Idle`, `Pause` und den kurzen Update-Phasen gibt es grundsätzlich keine.

Der zugrundeliegende Bruch gehört **zusätzlich** in den `<kurzer Schritt>`-Text, damit die Zahl nachvollziehbar ist: `4/9 Dateien`, `3/7 Kommentare`, `2/5 Checks`, `1/4 Review-Threads`, `3/7 Tasks`, `3/5 Teilschritte`. Gerundet wird auf ganze Prozent.

**Der Nenner darf wachsen.** Stellt sich mitten in der Phase heraus, dass aus 7 geplanten Teilschritten 10 werden, wird der Nenner korrigiert — auch wenn die Prozentzahl dadurch **zurückgeht**. Eine zurückgehende Zahl ist ehrlich; eine künstlich monotone wäre gelogen. Den Nenner also nie kleinhalten, nur damit die Anzeige steigt.

Innerhalb einer Phase wird die Zeile bei **jeder** gezählten Einheit neu geschrieben (also z. B. nach jeder fertigen Datei) — das ist der einzige Fall, in dem sie mehrfach pro Phase wandert.

**Möglichst genau sagen, was passiert.** Der `<kurzer Schritt>`-Text ist die eigentliche Information und soll konkret sein, nicht kategorisch: `4/9 Dateien: TaskController.php` statt `arbeite`, `2/5 Checks: phpstan` statt `CI läuft`, `GET /config` statt `lade`. Eine Zeile Länge ist das Budget — was nicht hineinpasst, wird gekürzt, aber nie durch eine Floskel ersetzt. Wartet der Aufruf auf etwas Externes (Netzwerk, CI, Nutzer-Eingabe), gehört genau das hinein (`⏳ Work (Fix) L2L · G5 — warte auf CI`), damit ein Stillstand nicht wie ein Absturz aussieht.

**Einrichtung (einmalig, zu Beginn **jedes** Aufrufs prüfen und bei Bedarf anlegen):**

1. **Zustandsdatei je Session:** `~/.claude/planstack-status-<session_id>.txt` — enthält genau **eine** Zeile. Die Datei ist **session-gebunden**: die Statuszeile darf **nur im laufenden Fenster** erscheinen, nie in anderen Sessions des Nutzers.
2. **Statusline-Skript:** ein kleines Skript, das das Session-JSON von **stdin** liest, daraus `session_id` zieht und **nur** den Inhalt von `~/.claude/planstack-status-<session_id>.txt` ausgibt — existiert die Datei nicht, gibt es **keine** Ausgabe (leere Statuszeile). Ausgabe in **UTF-8**, damit Umlaute und Symbole stimmen. Die Session-Bindung ist **Pflicht**, kein Feinschliff: liest das Skript eine feste Datei ohne `session_id`, erscheint die Zeile in **allen** Sessions des Nutzers.
3. **`statusLine`-Eintrag** in den Claude-Code-Settings (`~/.claude/settings.json`) auf dieses Skript zeigen lassen, **mit** `refreshInterval` (siehe unten):

   ```json
   { "statusLine": { "type": "command", "command": "…", "refreshInterval": 5 } }
   ```

   Der Eintrag ist zwangsläufig global — die Session-Bindung aus Schritt 1/2 sorgt dafür, dass er trotzdem nur hier etwas anzeigt. Dem Nutzer sagen, dass eine **neu** angelegte `statusLine`-Konfiguration erst nach einem **Neustart** von Claude Code greift (eine bereits vorhandene wird sofort übernommen).

Fehlt die Einrichtung, wird sie **einmal** angelegt und der Nutzer darauf hingewiesen — nicht bei jedem Aufruf erneut gefragt.

**`refreshInterval` (der zentrale Praxis-Hinweis):** Die Statusline wird normalerweise **ereignisgesteuert** neu berechnet — bei Prompt, Tool-Ende und Moduswechsel, 300 ms entprellt. Während ein Subagent arbeitet (im Auto-Modus der Regelfall) passiert davon minutenlang **nichts**, die Zeile friert also mitten in der Arbeit ein und zeigt einen längst überholten Schritt. Deshalb im `statusLine`-Eintrag **immer** `refreshInterval` setzen (Sekunden, Minimum `1`, Empfehlung **5–10**) — nur dann pollt Claude Code die Zustandsdatei von selbst weiter.

**Klickbare Links (OSC 8):** `<TASK>` und eine PR-Nummer im Schritt-Text als **OSC-8-Hyperlink** ausgeben, damit man aus der Statuszeile direkt ins Board bzw. in den PR springt (**Ctrl+Klick** unter Windows/Linux, **Cmd+Klick** unter macOS):

```
\e]8;;<URL>\a<Text>\e]8;;\a
```

- **`<TASK>`** → Task-/Board-Ansicht der Instanz: `<WEB>/projects/<PROJECT>`, wobei `<WEB>` die `base_url` aus `config.json` **ohne** das abschließende `/api` ist (eine Deep-Link-URL auf einen einzelnen Task gibt es nicht — die Board-Ansicht des Projekts ist das Ziel).
- **PR-Nummer** → der GitHub-PR über `pr_url`. Das Feld liefert die API bereits mit, u. a. in den Antworten von `review-claim`/`review-next` und an jedem dekorierten Task — **nie** selbst eine URL zusammenbauen.

`\e` und `\a` müssen als **echte** Steuerzeichen in der Datei landen (z. B. per `printf`, nicht als Literal `\e`). Die Anweisung ist bewusst **robust**: Terminals ohne Hyperlink-Unterstützung (klassisches conhost) zeigen einfach den Text ohne Link — sichtbare Escape-Reste wie `]8;;https…` sind aber ein **Fehler**. Lässt sich das nicht sauber schreiben, die Zeile **ohne** Links ausgeben; eine lesbare Zeile ohne Link ist besser als eine kaputte mit. Wer sich das Skript-Basteln sparen will: `footerLinksRegexes` in den Settings blendet aus erkannten Mustern (Task-Namen, PR-Nummern) klickbare Footer-Badges ein — ganz ohne eigenes Statusline-Skript, dafür ohne den frei formulierten Schritt-Text.

**Das Prozentzeichen bleibt einfach: `%`, nie `%%`.** Die Zeile ist **Text, kein Format-String**. `printf` nimmt sein **erstes** Argument als Format-String — dort wäre `%` eine Direktive und müsste verdoppelt werden. Genau daraus entsteht der bekannte Fehler: erst wird brav verdoppelt, geschrieben wird die Zeile dann aber mit einem Werkzeug, das keine Format-Strings kennt — und in der Statuszeile stehen hinter der Zahl **zwei** Prozentzeichen. Deshalb die Zeile **nie** als Format-String übergeben, sondern als Argument:

```
printf '%s\n' "⚙ Review (Review 94 %) L2L · G5 — 15/16 Dateien"
```

Kommen die OSC-8-Links dazu, gehören **nur** die Steuerzeichen in den Format-String; jeder variable Text bleibt Argument (und braucht damit kein Escaping):

```
printf '\e]8;;%s\a%s\e]8;;\a %s\n' "$url" "$task" "$rest"
```

Alle anderen Schreibwege — `Set-Content -Encoding utf8`, Datei-Werkzeug, Here-Doc — kennen überhaupt keine Format-Strings; dort ist `%%` **immer** falsch. Prüfmaßstab ist der Dateiinhalt, nicht der Aufruf: je Prozentangabe steht dort **ein** Zeichen `%`. Steht `%%` in der Datei, ist die Zeile kaputt.

Immer **überschreiben**, nie anhängen. Das Schreiben ist **best-effort**: Fehler ignorieren, den Ablauf nie blockieren und das Setzen der Zeile nicht in Prosa berichten.

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
