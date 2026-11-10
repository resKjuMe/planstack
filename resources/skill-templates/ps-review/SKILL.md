---
name: ps-review
description: Code-Review eines GitHub-PR mit Ablage in Planstack — reviewt den PR (inklusive Prüfung der Code-Kommentare auf Richtigkeit, Mehrwert und Knappheit), bewertet Kritikalität, KI-Prüfbarkeit und PO-Sichtung, sendet PR-Daten und Review samt Findings an die Planstack-API (sichtbar unter planstack.eskju.net/pull-requests), gibt die Review im Chat aus und schließt mit Findings-/Kritisch-/Wichtig-/Hinweis-/Empfehlung-Zeilen ab. Am PR wird nur nach ausdrücklicher Rückfrage gepostet. Mit „fix“ setzt er die in Planstack je Befund getroffenen Entscheidungen um, hält sie im PR-Body fest, pusht und reviewt neu; mit „resolve“ löst er den Merge-Konflikt, fixt rote CI und offene (Review-)Kommentare, pusht und reviewt neu; mit „convert“ übernimmt er die Changes-Requested-Review eines Kollegen als ps-review mit Befunden, beantwortet deren Kommentare mit dem Planstack-Link und löst die Threads auf. Nutze diesen Skill bei „ps-review 1234", „review PR 1234 nach Planstack", „/ps-review", „/ps-review fix 1234", „/ps-review resolve 1234", „/ps-review convert 1234 --from <login>".
version: 1.1.0
argument-hint: "[fix|resolve|convert] [<pr-nummer|pr-url|branch>] [--from <login>] [--lenient|--strict] [--relaxed|--meticulous] [--post|--comment|--no-post]"
---

**Nie** als Befund melden: reine Formatierung von Kommentaren — Zeilenumbruch mitten im Satz, Umbruchbreite, Einrückung, Leerzeilen, Satzzeichen. Das ist irrelevant, auch nicht als Hinweis.

# ps-review — Review mit Ablage in Planstack

Ein vollständiges Code-Review; das Ergebnis landet **nicht** als Datei, sondern in **Planstack**: gleich zu Beginn ein UPSERT des PR und der Start des Reviews (Planstack zeigt es dann als laufend), am Ende der Abschluss mit allen Findings. Die Review steht danach unter `https://planstack.eskju.net/pull-requests` **und** im Chat. Danach die Frage, ob die Review am PR gepostet werden soll.

## Harte Regeln (nicht verhandelbar)

1. **Nichts am PR posten, bevor der Nutzer zugestimmt hat.** Während des Reviews sind nur lesende `gh`-Aufrufe erlaubt (`gh pr view`, `gh pr diff`, `gh pr checks`, `gh api` mit `GET`). Geschrieben wird am PR erst in Schritt 8 — nach expliziter Zustimmung, und nur das, was der Nutzer in Schritt 7 ausgewählt hat. Ohne Antwort wird nichts gepostet; Schweigen ist kein Ja. Ausnahme: im Übernahme-Modus (`/ps-review convert`) ist der Klick auf den Button die Zustimmung zu den Antworten und dem Auflösen der übernommenen Threads (C4), im Fix-Modus einer übernommenen Review die Zustimmung zum Dismissen der ursprünglichen Review (F4).
2. **Planstack nur über drei Aufrufe**: `PUT /api/pull-requests` und `POST /api/pull-requests/{id}/reviews` (Start) in Schritt 1, `PUT /api/pull-requests/{id}/reviews/{review}` (Abschluss) in Schritt 5. Im Fix-Modus kommen `POST /api/pull-requests/{id}/fix/claim` und `PUT /api/pull-requests/{id}/reviews/{review}/fix` dazu. Keine Board-Aufrufe (`review-claim`, `review-next`, Events) — dieser Skill kennt kein Board.
3. **Keine lokale Report-Datei.** Die Payload-Dateien für `curl` liegen nur temporär (Scratchpad bzw. `mktemp -d`) und werden nach dem Senden gelöscht. Schlägt die API fehl, wird das gemeldet — nicht als Datei abgelegt.
4. **Nichts am Code ändern.** Das Review ist rein lesend: keine Edits, keine Commits, kein Push, kein Checkout, der lokale Änderungen gefährdet. Ausnahmen: der Nutzer wählt in Schritt 7 „Fixe Findings im gleichen PR“ — dann gilt Schritt 9; oder der Aufruf ist `/ps-review fix` bzw. `/ps-review resolve` — dann gilt der Fix- bzw. Konflikt-Modus. Der Übernahme-Modus (`/ps-review convert`) ändert keinen Code.
5. **Das Review wird immer abgeschlossen** — auch bei `APPROVE` ohne einen einzigen Befund. Ein gestartetes, nie abgeschlossenes Review zeigt Planstack zwei Stunden lang als laufend, danach als abgebrochen.

## Aufruf

`/ps-review [<ZIEL>] [<Schalter>]` · `/ps-review fix <ZIEL>` (Fix-Modus) · `/ps-review resolve <ZIEL>` (Konflikt-Modus) · `/ps-review convert <ZIEL> --from <login>` (Übernahme-Modus), siehe unten

Das Ziel muss sich zu einem **GitHub-PR** auflösen, denn Planstack speichert Reviews je PR:

| Argument | Ziel |
|---|---|
| Zahl (`9050`) oder PR-URL | dieser GitHub-PR |
| Branch-Name (`feature/xyz`) | der offene PR dieses Branch |
| *leer* | der offene PR des aktuellen Branch |

Lässt sich kein PR finden (lokaler Diff, Commit-Range, Branch ohne PR), **abbrechen** mit dem Hinweis, dass Planstack Reviews nur je PR speichert (für lokale Stände ggf. `/sq-review`, falls installiert). Ist das Ziel mehrdeutig, **einmal** nachfragen statt zu raten.

Schalter überschreiben für diesen Lauf die Einstellungen (s. u.): `--lenient` / `--strict`, `--relaxed` / `--meticulous`. `--post` / `--comment` / `--no-post` entscheiden das Posten vorab und überspringen die Rückfrage aus Schritt 7.

## Einstellungen und Zugang

`settings.json` liegt neben dieser SKILL.md (Verzeichnis in `CLAUDE_SKILL_DIR`); fehlt sie, gelten die Defaults:

| Schlüssel | Werte | Default | Bedeutung |
|---|---|---|---|
| `review_strictness` | `lenient` · `default` · `strict` | `strict` | Wie hart bewertet wird: `lenient` nur echte Blocker (im Zweifel `APPROVE`), `default` normal, `strict` auch kleinere Mängel, Stil, Edge-Cases (eher `REQUEST_CHANGES`). |
| `review_thoroughness` | `relaxed` · `default` · `meticulous` | `meticulous` | Wie gründlich geschaut wird: `relaxed` schneller Überblick, `default` normal, `meticulous` jede Datei/Zeile und alle Edge-Cases. |
| `verbosity` | `minimal` · `default` · `maximal` | `minimal` | Wie viel Fließtext während des Laufs im Chat landet. `minimal` = keine Vorreden, keine Ankündigungen, keine Zwischenzusammenfassungen; die Review selbst, Link und PR-Nummer sowie die Abschlusszeilen (Schritt 6) sind davon unberührt und werden nie gekürzt. |
| `planstack_config` | Pfad | `~/.claude/skills/planstack/config.json` | Woher `base_url` und `token` kommen, wenn neben dieser SKILL.md keine eigene `config.json` liegt — dieselbe Datei wie beim Skill `planstack`. |

Zugang: Liegt neben dieser SKILL.md eine `config.json` (so kommt der Skill aus dem Download unter Planstack → Pull Requests), gilt sie. Sonst die des Skills `planstack`.

```bash
SD="${CLAUDE_SKILL_DIR:-$HOME/.claude/skills/ps-review}"
s(){ python3 -c "import json,os,sys
p=os.path.join(sys.argv[1],'settings.json')
d=json.load(open(p,encoding='utf-8')) if os.path.exists(p) else {}
print(d.get(sys.argv[2], sys.argv[3]))" "$SD" "$1" "$2"; }
STRICT=$(s review_strictness strict)
THOROUGH=$(s review_thoroughness meticulous)
if [ -f "$SD/config.json" ]; then PSCFG="$SD/config.json"
else PSCFG=$(s planstack_config "$HOME/.claude/skills/planstack/config.json"); fi
case "$PSCFG" in "~/"*) PSCFG="$HOME/${PSCFG#\~/}" ;; esac
# Windows-Python versteht /c/... nicht
command -v cygpath >/dev/null && PSCFG=$(cygpath -w "$PSCFG")
c(){ python3 -c "import json,sys;print(json.load(open(sys.argv[1],encoding='utf-8')).get(sys.argv[2],''))" "$PSCFG" "$1"; }
BASE=$(c base_url); TOKEN=$(c token)          # BASE endet auf /api
AUTH=(-H "Authorization: Bearer $TOKEN" -H "Accept: application/json" -H "Content-Type: application/json")
```

Fehlt `token`, sofort abbrechen und auf den API-Token unter Planstack → Profil verweisen.

## Ablauf

### 1. Ziel auflösen und Kontext holen

Rein lesend:

```bash
REPO=$(gh repo view --json nameWithOwner -q .nameWithOwner)   # bzw. aus der PR-URL
gh api "repos/$REPO/pulls/<pr>"   # alle PR-Kennzahlen für Schritt 5 (Zuordnung dort)
# Approvals der Kollegen und Pflicht-Approvals (Stufe des pr-bot):
gh api --paginate "repos/$REPO/pulls/<pr>/reviews" \
  --jq '.[] | {login: .user.login, state, submitted_at, commit_id}'   # ein JSON-Objekt je Zeile
gh api "repos/$REPO/commits/<head.sha>/check-runs?check_name=pr-bot%20%2F%20Review%20Requirement" \
  --jq '.check_runs[0].output.summary // empty'
gh pr diff <pr>
gh pr checks <pr>                 # CI-Stand gehört in die Summary, wird aber nicht repariert
```

**Sofort danach — noch vor dem Lesen des Diffs — den Start in Planstack vermerken:** UPSERT des PR (Zuordnung in Schritt 5, „Aufruf 1“), dann den Review-Start:

```bash
T=$(mktemp -d)   # Payload-Dateien hier ablegen, am Ende von Schritt 5 löschen
# pr.json mit dem Write-Tool anlegen (Felder: Schritt 5, Aufruf 1), dann:
curl -s -w '\n%{http_code}\n' -X PUT "$BASE/pull-requests" "${AUTH[@]}" --data-binary @"$T/pr.json"
# 200/201; data.id = PRID
curl -s -w '\n%{http_code}\n' -X POST "$BASE/pull-requests/$PRID/reviews" "${AUTH[@]}" \
  --data-binary "{\"commit_sha\":\"<head.sha>\",\"strictness\":\"$STRICT\",\"thoroughness\":\"$THOROUGH\",\"model\":\"<Modell-ID>\",\"skill_version\":\"<version aus dem Frontmatter>\"}"
# 201; data.id = REVIEWID — für den Abschluss in Schritt 5 merken
```

Scheitert einer der beiden Aufrufe, den Fehler nennen und **trotzdem reviewen**; der Abschluss in Schritt 5 entfällt dann, und die Review steht nur im Chat. Bei 401/403 gleich zu Beginn melden, dass nichts in Planstack landet.

Den Diff **vollständig** lesen, nicht nur die Dateiliste. Die Zahl der geänderten Dateien ist der Nenner für den Fortschritt.

Bei `meticulous` zusätzlich den Kontext um jede Änderung herum lesen (die ganze Datei, nicht nur die Hunks) und die aufrufenden Stellen der geänderten Symbole suchen — ein Diff allein zeigt keine Regression, die erst beim Aufrufer entsteht.

**Verknüpftes Ticket:** Steht ein Jira-Key (`[A-Z]+-\d+`) in Titel, Branch oder Body, das Ticket lesen (REST `https://clickbits.atlassian.net/rest/api/3/issue/<KEY>?fields=summary,description,comment,attachment`, Auth `$JIRA_EMAIL` (Default `git config user.email`) + `$JIRA_API_TOKEN`; Ausgabe mit `PYTHONIOENCODING=utf-8`). Jeden Ticket-Punkt gegen den Diff abgleichen und notieren:

- **Weniger als verlangt:** Ticket-Punkte, die fehlen, nur teilweise oder bewusst abweichend umgesetzt sind (auch wenn der PR-Body das begründet). Bei „global prüfen“ selbst nach übersehenen Stellen suchen.
- **Mehr als verlangt:** Verhaltensänderungen, neue Regeln, Bugfixes und Refactorings, die das Ticket nicht verlangt.

Was davon eine Verhaltensänderung für Kunden ist, wird zusätzlich ein Befund (meist mit `requires_po_decision`). Nicht lesbare Anhänge (Screenshots) unter `## Nicht geprüft` nennen. Ohne Ticket oder ohne Jira-Zugang entfällt der Abgleich; dann unter `## Nicht geprüft` vermerken.

### 2. Review durchführen

Den vorhandenen Review-Skill (`/code-review`) auf das Ziel laufen lassen — **ohne** `--comment` und **ohne** `--fix`: `--comment` würde ungefragt am PR posten, `--fix` den Code ändern. Das Posten entscheidet allein Schritt 7. Effort-Stufe aus `review_thoroughness`: `relaxed` → `low`, `default` → `medium`, `meticulous` → `high`.

Das Ergebnis des Review-Skills ist der Rohbefund und **nicht** schon die Review: die Befunde werden anschließend selbst geprüft, eingeordnet, gewichtet und um das ergänzt, was die Checkliste unten verlangt.

Ist `/code-review` nicht verfügbar, das Review selbst durchführen — an derselben Checkliste entlang:

- **Korrektheit**: Logikfehler, falsche Bedingungen, Off-by-one, Null-/Leerfälle, Typ-Fallen, vertauschte Argumente.
- **Edge-Cases & Fehlerpfade**: leere Menge, ein Element, sehr große Menge, gleichzeitige Zugriffe, Teilausfall, Retry, Timeout, Transaktions-Rollback.
- **Regressionen**: geänderte Signaturen/Verträge gegen alle Aufrufer geprüft? Verhalten für Bestandsdaten unverändert?
- **Sicherheit**: Injection, fehlende Autorisierung, Mandanten-/Company-Grenzen, Secrets im Code oder Log, unsichere Deserialisierung.
- **Daten & Migrationen**: destruktive DDL (DROP/RENAME) braucht zwei Deploys; Indizes, Sperren, Laufzeit auf großen Tabellen.
- **Performance**: N+1, fehlende Indizes, unnötige Volllisten, Arbeit in der Schleife, die davor gehört.
- **Tests**: decken sie den geänderten Pfad? Fehlt der Negativfall? Nichtdeterministische Tests (Wanduhr-/Timing-Assertions) sind ein Befund.
- **Kommentare**: siehe eigener Abschnitt unten — jeder Kommentar im Diff wird einzeln geprüft.
- **Lesbarkeit & Konventionen**: Projektstil, Namen, tote Reste.

Bei `strict` zählen auch Stil- und Konventionsverstöße als Befund; bei `lenient` nur, was tatsächlich bricht.

#### Kommentare prüfen

Jeder im Diff **neue oder geänderte** Kommentar wird einzeln angeschaut — Zeilenkommentare, Blockkommentare, DocBlocks, TODOs. Zwei Fragen:

1. **Stimmt er?** Sagt der Kommentar etwas anderes als der Code darunter, ist das ein Befund — auch wenn der Code richtig ist. Ein falscher Kommentar ist schlimmer als keiner. Besonders auf Kommentare achten, die zum Code **daneben** noch passten, aber vom Diff überholt wurden.
2. **Bringt er Mehrwert?** Ein Kommentar ist gerechtfertigt, wenn er das **Warum** erklärt: die nicht offensichtliche Entscheidung, den Grund für einen Umweg, die Fallstricke einer Fremd-API, ein bewusst in Kauf genommenes Verhalten. Er ist überflüssig, wenn er nur das **Was** wiederholt, das der Code schon sagt.

Grundhaltung: **Kommentare sind die Ausnahme, nicht die Regel.** Wo ein besserer Name oder eine extrahierte Funktion den Kommentar erübrigt, ist das der bessere Vorschlag. Was bleibt, gehört so kurz und prägnant wie möglich formuliert.

Als Befund melden:

- **Falsch/veraltet** — widerspricht dem Code oder beschreibt einen früheren Stand.
- **Redundant** — paraphrasiert die Zeile darunter (`// Zähler erhöhen` über `$i++`).
- **KI-Slop** — generierter Füllton ohne Substanz: Kommentar über jeder Zeile, aufgeblähte DocBlocks, die nur Signatur und Typen wiederholen, Floskeln wie „Diese Methode ist dafür verantwortlich, …", „Wir prüfen hier, ob …", Abschnittsbanner (`// ---- Validierung ----`), erklärender Prosa-Absatz für dreizeilige Logik, Begründung des Offensichtlichen.
- **Zu lang** — drei Sätze, wo einer reicht.
- **Ticket-Referenzen** in Code oder DocBlock (`// MN-6556`, `@see JIRA-123`) — gehören in die Commit-Message, nicht in den Code.
- **Auskommentierter Code** — gehört gelöscht, die History hat ihn.
- **Fehlend** — die seltene Gegenrichtung: ein echter Trick ohne jede Erklärung.

Einstufung: Kommentar-Befunde sind **immer Hinweis** — auch falsche/veraltete (siehe Deckelung in Schritt 3). Bei `lenient` nur falsche Kommentare melden.

Im Vorschlag konkret werden: die gekürzte Fassung hinschreiben oder „streichen" sagen — nicht nur „Kommentar überarbeiten".

**Jeder Befund braucht ein konkretes Fehlszenario** — Eingabe/Zustand → falsches Ergebnis. Was sich nicht so formulieren lässt, ist kein Befund, sondern eine Anmerkung.

### 3. Befunde einstufen

| Schwere | API-Wert | Bedeutung |
|---|---|---|
| **Kritisch** | `CRITICAL` | Blocker: Datenverlust, Sicherheitslücke, falsches Ergebnis im Normalbetrieb, Deploy bricht |
| **Wichtig** | `IMPORTANT` | echter Mangel, der vor dem Merge gehört, aber nichts zerstört |
| **Hinweis** | `HINT` | Stil, Lesbarkeit, Nit, Verbesserungsidee |

**Deckelung — nie Wichtig oder Kritisch, höchstens Hinweis**, egal wie gravierend der Einzelfall wirkt:

- Code-Kommentare (falsch, veraltet, redundant, fehlend)
- `AGENTS.md` (fehlende oder veraltete Einträge, Verstöße gegen dessen Konventionen)
- PR-Beschreibung (unvollständig, veraltet, falsch)
- Merge-Konflikte mit dem Parent-/Base-Branch
- Tests (fehlend, lückenhaft, nichtdeterministisch, falsche Assertions)

Diese Befunde zählen nie zu `Wichtig`/`Kritisch` und beeinflussen damit die Empfehlung nicht.

**Konfidenz je Befund** (`confidence`, 0–100): wie sicher der Befund zutrifft — unabhängig von der Schwere. Dazu `confidence_reason`, ein Satz: was nachvollzogen wurde, was offen bleibt.

| Wert | Bedeutung |
|---|---|
| 90–100 | jeder Schritt des Fehlszenarios im Code nachvollzogen |
| 70–89 | Kern belegt, ein Schritt nur plausibel |
| 50–69 | plausibel, aber ein Schlüsselschritt ungeprüft oder hängt an der Fachregel |
| < 50 | zweifelhaft — vor dem Melden erneut prüfen; bleibt es so niedrig, ist es eine Anmerkung, kein Befund |

**Empfehlung** (`decision`) leitet sich daraus ab:

- `Kritisch ≥ 1` → **REQUEST_CHANGES**
- sonst bei `strict` und `Wichtig ≥ 3` → **REQUEST_CHANGES**
- sonst → **APPROVE** (offene *Hinweise* verhindern kein APPROVE)

Die Regel ist mechanisch: was in den Zahlen steht, muss zur Empfehlung passen. Weicht die Einschätzung davon ab, gehört die Begründung in die Summary — nicht eine stillschweigend andere Empfehlung.

### 4. Bewerten: Kritikalität, KI-Prüfbarkeit, PO-Sichtung, PO-Entscheidung, Typ

Jedes Review bekommt drei Werte von 0–100 mit je **einem Satz** Begründung, der die ausschlaggebenden Punkte nennt.

#### `criticality` — Risiko der Änderung

Wie viel steht auf dem Spiel, wenn diese Änderung einen Fehler enthält — **unabhängig von den gefundenen Befunden**. Ein fehlerfreier Migrations-PR ist trotzdem riskant; ein Tippfehler-Fix mit drei Hinweisen nicht.

| Band | Typische Änderung |
|---|---|
| 0–19 | Doku, Kommentare, reine Tests, Stil, Konfiguration ohne Laufzeitwirkung |
| 20–39 | lokal begrenzte Logik, UI-Text/Layout, neue isolierte Funktion (z. B. hinter Feature-Flag) |
| 40–59 | Geschäftslogik in einem Modul, neue Endpunkte, Queries auf mittelgroßen Tabellen, Minor-Updates von Abhängigkeiten |
| 60–79 | modulübergreifend, geänderte Verträge/Signaturen mit vielen Aufrufern, Hot-Path-Performance, Caching/Queues, nicht-destruktive Migration auf großen Tabellen |
| 80–100 | Auth/Rechte/Mandanten-/Company-Grenzen, Abrechnung/Geld, Datenlöschung, destruktive Migration (DROP/RENAME), Bestandsdaten-Migration, Sicherheit, Deploy-/Infra-Pipeline |

Innerhalb des Bands nach oben: großer Umfang, viele betroffene Aufrufer, kein einfacher Rollback, Wirkung auf alle Kunden statt auf wenige.

#### `ai_reviewability` — wie verlässlich kann die KI das allein beurteilen?

Niedrig heißt: **hier lohnt ein menschliches Review am meisten.** Start bei 100, dann abziehen:

| Abzug | Grund |
|---|---|
| −10 … −30 | Fachlogik, deren Richtigkeit nur mit Domänenwissen zu beurteilen ist |
| −10 … −25 | sichtbare UI/UX-Änderung (Layout, Texte, Interaktion), die nicht im Browser geprüft wurde |
| −10 … −25 | Abhängigkeit von Infrastruktur, Konfiguration, Deploy-Reihenfolge oder externen APIs, die nicht einsehbar sind |
| −5 … −15 | Nebenläufigkeit, Timing, Transaktionen, Cache-Invalidierung |
| −5 … −20 | großer Diff (über ~800 geänderte Zeilen) oder generierter Code |
| −5 … −15 | fehlende oder schwache Tests für den geänderten Pfad |
| −5 … −20 | „Nicht geprüft“ enthält Wesentliches (z. B. CI-Ergebnis fehlt, Fremdcode nicht einsehbar) |
| −10 je Befund | mit `requires_po_decision` (höchstens −30) |

Auf 0–100 begrenzen. Die Planstack-Seite sortiert nach `criticality × (100 − ai_reviewability)` — beide Werte ehrlich setzen, nicht auf die Mitte ausweichen.

#### `po_review_likelihood` — will der PO den PR vor dem GoLive sehen?

Wie wahrscheinlich der Product Owner diese Änderung vor dem GoLive selbst anschauen möchte — **unabhängig von Risiko und Befunden**. Maßstab ist, wie stark sich das Produkt für Kunden ändert. Ein riskanter Index-Umbau interessiert den PO nicht; eine geänderte Geschäftsregel schon.

Info: Dem PO sind User-Facing-Änderungen (UX/UI) besonders wichtig — das fließt in die Einordnung ein, ist aber kein Übergewicht gegenüber anderen fachlichen Änderungen.

| Band | Typische Änderung |
|---|---|
| 80–100 | neue Funktion oder neuer Ablauf für Kunden, geänderte Geschäftsregel oder Berechnung, Breaking Change für Kunden |
| 60–79 | geändertes Verhalten einer bestehenden Funktion: Rechte, Defaults, Export-/Import-Format, sichtbare UI-/Text-Änderung, E-Mails/Benachrichtigungen |
| 40–59 | kleine sichtbare Anpassung (Abstand, Farbe, Fehlermeldung, Sortierung), interne Admin-/Support-Oberfläche, Freischaltung eines Feature-Flags |
| 20–39 | Verhalten ändert sich nur in Randfällen; für Nutzer spürbare Performance |
| 0–19 | rein technisch: Refactoring, Tests, Infra, Abhängigkeiten, Doku, Bugfix ohne sichtbare Änderung |

Innerhalb des Bands nach oben: viele betroffene Kunden, prominente Stelle (Startseite, Zeiterfassung, Abrechnung), PR oder Ticket nennt Abnahme/Design. Je Befund mit `requires_po_decision` +10 (höchstens +30). Auf 0–100 begrenzen.

#### `requires_po_decision` je Befund

`true`, wenn sich der Befund **technisch nicht entscheiden** lässt, weil offen ist, welches Verhalten **fachlich gewollt** ist: Geschäftsregel, UX-Text, Rechte-Modell, Breaking Change für Kunden, Umgang mit Bestandsdaten. Ein reiner Bug („Null-Prüfung fehlt“) ist nie PO-Sache, auch wenn er kritisch ist.

#### `type` je Befund

- `INLINE` — der Befund hängt an einer Zeile, die **im Diff** vorkommt (Zeilennummer der neuen Datei). `file` und `line` sind Pflicht.
- `SUMMARY` — allgemeiner Befund, gelöschte Zeile oder Stelle außerhalb des Diffs (z. B. ein Aufrufer, der bricht). `file`/`line` setzen, wenn es eine konkrete Stelle gibt.

Dieselbe Einteilung gilt beim Posten (Schritt 8): `INLINE` geht an die Zeile, `SUMMARY` in den Body.

### 5. Review in Planstack abschließen

#### Sprache

Einfach und prägnant — in Planstack wie im Chat. Was der Skill selbst nicht dulden würde, schreibt er auch nicht:

- Kurze Sätze, aktive Formulierung, konkrete Substantive. Ein Gedanke pro Satz.
- Jeder Satz trägt Information. Keine Vorreden („Zunächst einmal fällt auf, dass …"), keine Wiederholung der Frage, keine Zusammenfassung dessen, was zwei Zeilen darüber steht.
- Kein Lob, keine Höflichkeitsfloskeln, keine Abschwächer („könnte eventuell unter Umständen"). Wenn etwas falsch ist: hinschreiben, dass es falsch ist.
- Keine Wertungswörter ohne Beleg („sauber", „elegant", „problematisch") — stattdessen sagen, was passiert.
- Datei und Zeile statt Umschreibung. Codeschnipsel statt Beschreibung eines Codeschnipsels.
- Keine Emojis, keine Banner, keine Aufzählung mit einem Punkt.

Ein Befund in drei Zeilen ist besser als derselbe Befund in zwölf.

#### Aufruf 1: PR anlegen/aktualisieren (UPSERT, schon in Schritt 1)

Zuordnung aus `gh api repos/$REPO/pulls/<pr>`:

| API-Feld | Quelle |
|---|---|
| `repository` | `$REPO` (`owner/name`) |
| `pull_request_id` | `.number` |
| `author_login` | `.user.login` — der Server ordnet `vorname-nachname_clockodo` dem Planstack-User zu |
| `title`, `description` | `.title`, `.body` |
| `branch`, `target_branch` | `.head.ref`, `.base.ref` |
| `last_commit` | `.head.sha` |
| `state` | `.merged == true` → `MERGED`, sonst `.state == "closed"` → `CLOSED`, sonst `OPEN` |
| `changed_files`, `additions`, `deletions`, `commits`, `comments`, `review_comments` | gleichnamige Felder |
| `opened_at`, `merged_at`, `closed_at` | `.created_at`, `.merged_at`, `.closed_at` |
| `github_reviews` | **alle** Reviews aus `pulls/<pr>/reviews` unverändert als `[{login, state, submitted_at, commit_id}]` — der Server ermittelt daraus die letzte Entscheidung je Kollege |
| `approval_check_summary` | `output.summary` des Checks „pr-bot / Review Requirement“ unverändert; **weglassen**, wenn es den Check nicht gibt (anderes Repo, Check noch nicht gelaufen) |

Die Approvals hält Planstack danach selbst aktuell (Sync alle 5 Minuten); der Skill wertet sie nicht aus und nennt sie nur in der Summary unter `## CI`, wenn Freigaben fehlen.

#### Aufruf 2: Review abschließen

Schließt das in Schritt 1 gestartete Review ab (`PUT …/reviews/$REVIEWID`); der Server setzt dabei `review_finished_at`. Der Aufruf kommt **vor** der Frage nach dem Posten (Schritt 7).

| API-Feld | Inhalt |
|---|---|
| `decision` | `APPROVE` oder `REQUEST_CHANGES` (Regel aus Schritt 3) |
| `criticality`, `criticality_reason` | aus Schritt 4 |
| `ai_reviewability`, `ai_reviewability_reason` | aus Schritt 4 |
| `po_review_likelihood`, `po_review_likelihood_reason` | aus Schritt 4 |
| `summary` | Markdown in dieser Reihenfolge: `## TLDR` · `## Ticket-Abgleich (<KEY>)` mit den Unterpunkten `**Weniger als verlangt:**` und `**Mehr als verlangt:**`, darunter **immer** je Punkt eine Aufzählungszeile (`- …`), auch bei nur einem Punkt (Pflicht, sobald ein Ticket verknüpft ist; ist nichts abweichend, steht direkt hinter dem Unterpunkt „nichts“) · `## Analyse je Datei` · `## Risiken & Nebenwirkungen` · `## Tests` · `## Kommentare` · `## Nicht geprüft` · `## CI` (Stand aus `gh pr checks`). Leere Abschnitte entfallen. **Keine** Befunde — die stehen in `findings`. |
| `commit_sha` | der geprüfte Head-SHA aus Schritt 1 |
| `strictness`, `thoroughness` | die tatsächlich verwendeten Werte |
| `model` | das tatsächlich genutzte Claude-Modell (Modell-ID) |
| `skill_version` | `version` aus dem Frontmatter dieser SKILL.md — Planstack zeigt bei älterer Version einen Update-Hinweis |
| `findings[]` | je Befund `level`, `type`, `requires_po_decision`, `file`, `line`, `title` (eine Zeile), `message`, `confidence` (0–100) und `confidence_reason` (ein Satz) |

`message` ist Markdown:

```markdown
**Was:** <was der Code tut>
**Warum falsch:** <Begründung>
**Fehlszenario:** <konkrete Eingabe/Zustand → falsches Ergebnis>
**Vorschlag (<n> %):** <konkreter Fix, ggf. mit Codeblock>
```

**Prozent am Vorschlag** (0–100): wie sinnvoll und sicher der vorgeschlagene Fix ist — getrennt von `confidence` (die bewertet, ob der Befund zutrifft). Hoch: Fix ist lokal, ohne Nebenwirkung, durch Tests absicherbar und deckt sich mit dem Ticket. Runter: berührt Aufrufer/Bestandsdaten, ändert Kundenverhalten, hängt an einer Fachregel oder **widerspricht bzw. überschreitet die Ticket-Anforderungen** (dann im Vorschlag kurz sagen, welcher Ticket-Punkt betroffen ist). Ist ein Ticket verknüpft, jeden Vorschlag gegen dessen Anforderungen prüfen.

Sobald es mehr als einen Vorschlag gibt (Alternativen oder mehrere Teil-Fixes), **immer** als A, B, C … nummerieren, jeden einzeln bewerten, den empfohlenen als A:

```markdown
**Vorschlag:**
- **A (80 %):** <Fix> — <Halbsatz warum>
- **B (40 %):** <Alternative> — <Halbsatz warum schwächer>
```

Die Prozentzahl gilt überall, wo ein Vorschlag erscheint (Planstack, Chat, Inline-Kommentar, `convert`-Befunde).

Die Zähler (`findings`, `findings_critical`, …) berechnet der Server aus `findings[]` — nicht mitschicken.

#### Senden

Die Payload mit dem Write-Tool als JSON-Datei in `$T` schreiben und mit `--data-binary @datei` senden — nie inline über die Kommandozeile (Umlaute, Backticks, Zeilenumbrüche):

```bash
# review.json mit dem Write-Tool in $T anlegen, dann:
curl -s -w '\n%{http_code}\n' -X PUT "$BASE/pull-requests/$PRID/reviews/$REVIEWID" "${AUTH[@]}" --data-binary @"$T/review.json"
# 200 erwartet; data.url ist der Link auf die Detailseite
rm -rf "$T"
```

- **422**: Die Antwort nennt das Feld. Payload korrigieren und **einmal** erneut senden.
- **409**: Das Review ist schon abgeschlossen (z. B. doppelt gesendet) — nicht wiederholen.
- **401/403**: Token ungültig, keine Organisation oder fremdes Review — melden, nicht wiederholen.
- **Andere Fehler/Timeout**: Status und Antwort im Chat melden. Die Review steht trotzdem vollständig im Chat; nichts lokal ablegen.

### 6. Review und Abschluss im Chat

Die Antwort hat drei Teile, in dieser Reihenfolge:

**1. Die Review**, inhaltlich identisch mit dem Gesendeten:

- `## TLDR`
- `## Bewertung` — drei Zeilen: `Kritikalität: <n> — <Begründung>`, `KI-Prüfbarkeit: <n> — <Begründung>` und `PO-Sichtung: <n> — <Begründung>`
- `## Befunde` — Übersichtstabelle (`#`, Schwere, Konfidenz, `Datei:Zeile`, Befund, PO), darunter je Befund der Abschnitt `### <n>. <Titel> — <Schwere>` mit `**Wo:** <Datei:Zeile>` und der `message`; bei `requires_po_decision` zusätzlich `**PO-Entscheidung nötig**`
- danach die übrigen Abschnitte der `summary` (Ticket-Abgleich, Analyse je Datei, Risiken & Nebenwirkungen, Tests, Kommentare, Nicht geprüft, CI)

Gibt es keine Befunde, bleibt `## Befunde` mit dem Satz `Keine Befunde.` stehen.

**Nicht kürzen und nicht umschreiben** — Chat und Planstack tragen dieselbe Review. Was zu lang für den Chat wirkt, war schon in der Payload zu lang: dann beides kürzen (siehe „Sprache"). Der Detailgrad folgt der Gründlichkeit, nicht dem Medium: bei `relaxed` ist die Review kurz.

**2. Link und PR** — immer beide, direkt untereinander: `data.url` aus Aufruf 2 und PR-Nummer und Titel, getrennt durch ` · ` (`.number` und `.title` aus Schritt 1). Ist Aufruf 2 gescheitert, steht statt der Planstack-URL der Fehler aus Schritt 5; die PR-Zeile bleibt.

```
Planstack: https://planstack.eskju.net/pull-requests/123
PR: #9050 · Fix overtime calculation for part-time users
```

**3. Die Abschlusszeilen** — **letzter** Block der Antwort, exakt in dieser Form und Reihenfolge:

```
Kritikalität: 45
KI-Prüfbarkeit: 55
PO-Sichtung: 80

Findings: 12
Kritisch: 2
Wichtig: 4
Hinweis: 6

Empfehlung: REQUEST_CHANGES
```

`Kritikalität`, `KI-Prüfbarkeit` und `PO-Sichtung` sind die Werte 0–100 aus Schritt 4 (ohne Begründung, die steht unter `## Bewertung`). `Findings` ist die **Gesamtzahl** aller Befunde (Kritisch + Wichtig + Hinweis), `Kritisch` die Teilmenge der Blocker, `Wichtig` und `Hinweis` die übrigen Schweregrade — die drei Teilzahlen ergeben zusammen `Findings`, `Empfehlung` genau `APPROVE` oder `REQUEST_CHANGES`. Zwischen `PO-Sichtung` und `Findings` sowie zwischen `Hinweis` und `Empfehlung` steht je genau eine Leerzeile. Keine weiteren Zeilen dahinter, keine Zusammenfassung des eben Getanen, kein Angebot, die Findings gleich zu fixen — die Review ist mit diesen acht Zeilen abgeschlossen. Einzige Ausnahme ist die Rückfrage aus Schritt 7; sie läuft über `AskUserQuestion` und nicht als weitere Textzeile.

### 7. Nachfragen, ob gepostet werden soll

Direkt nach den Abschlusszeilen fragen (`AskUserQuestion`, eine Frage, Einfachauswahl):

> Review an PR #<n> posten?

| Option | Wirkung |
|---|---|
| **Nicht posten** (Default) | nichts passiert; die Review liegt in Planstack |
| **Posten** | eine GitHub-Review: jeder `INLINE`-Befund als **Inline-Kommentar** an seiner Codezeile, die **Zusammenfassung** als Review-Body mit `APPROVE` bzw. `REQUEST_CHANGES` |
| **Nur Zusammenfassung** | ein einzelner PR-Kommentar ohne Inline-Kommentare und ohne Empfehlung |
| **Fixe Findings im gleichen PR** | nur anbieten, wenn `Findings ≥ 1`: nichts posten, stattdessen die Befunde auf dem PR-Branch beheben und pushen (Schritt 9) |

Die Frage kommt **nach** der vollständigen Review — nie vorher. Der Nutzer entscheidet über etwas, das er gelesen hat.

Gefragt wird **einmal**. Keine Antwort, Abbruch oder „Nicht posten" heißt: nichts posten, nicht nachhaken, kein zweites Angebot.

Sagt der Nutzer schon im Aufruf, was passieren soll, gilt das und die Frage entfällt: `--post` = Posten (inline + Zusammenfassung), `--comment` = nur Zusammenfassung, `--no-post` = nichts posten.

### 8. Posten (nur nach Zustimmung)

**Eine** Review, nicht viele Einzelkommentare: Befunde inline an ihren Zeilen, Zusammenfassung im Body, dazu die Empfehlung. Das geht in **einem** Aufruf — `gh api …/pulls/<pr>/reviews` mit `comments[]`. So erscheint alles auf einen Schlag und nicht als Tropfen einzelner Benachrichtigungen.

#### Was wohin gehört

| | Inhalt |
|---|---|
| **Inline-Kommentar** (je `INLINE`-Befund) | Schwere, was falsch ist, Fehlszenario, Vorschlag samt Prozent (je Option) — kurz, nur dieser eine Befund; bei `requires_po_decision` die Zeile `**PO-Entscheidung nötig**` |
| **Review-Body** | TLDR · Zahlen (Findings/Kritisch/Wichtig/Hinweis) · Einordnung · Risiken · Tests · Kommentare · Nicht geprüft — **keine Befunddetails** · dazu nur die `SUMMARY`-Befunde |
| **Nirgends** | die Bewertungszahlen und die Analyse je Datei — die stehen nur in Planstack |

**Der Body wiederholt keinen Befund, der inline steht.** Jeder Befund hat genau einen Ort. Steht er inline, taucht er im Body nicht mehr auf — weder als Absatz, noch als Liste, noch als Tabelle, noch als Einzeiler.

Verboten im Body:

- eine Befundliste oder -tabelle („1. Kritisch — `Foo.php:123` — Null-Prüfung fehlt")
- „Die wichtigsten Punkte:" mit Wiederholung der Inline-Kommentare
- Fehlszenarien, Vorschläge, Codeschnipsel zu Befunden, die inline hängen
- Dateinamen der inline kommentierten Stellen

Erlaubt ist die **Einordnung**: was die Änderung insgesamt tut, worin das Muster der Befunde besteht („drei der vier Kritischen betreffen dieselbe fehlende Mandantenprüfung"), was das für den Merge bedeutet.

**Einzige Ausnahme:** `SUMMARY`-Befunde. Die haben keinen inline-Ort, sonst gingen sie verloren — sie stehen unter einer eigenen Überschrift, mit Datei und Zeile im Text.

Body-Gerüst:

```markdown
<TLDR: 1–3 Sätze.>

**Findings:** 12 · **Kritisch:** 2 · **Wichtig:** 4 · **Hinweis:** 6
Details stehen an den Codezeilen.

<Einordnung: Muster, Zusammenhang, was das für den Merge heißt. Wenige Sätze.>

## Befunde außerhalb des Diffs
<nur SUMMARY-Befunde, je mit Datei:Zeile — entfällt, wenn es keine gibt>

## Risiken & Nebenwirkungen
## Tests
## Kommentare
## Nicht geprüft
```

Leere Abschnitte weglassen. Gibt es keinen Befund, ist der Body kurz: TLDR, Zahlen, und was geprüft wurde.

Format eines Inline-Kommentars:

````markdown
**Kritisch** — <Titel>

<Was falsch ist, ein bis zwei Sätze.>

**Fehlszenario:** <Eingabe/Zustand → falsches Ergebnis>

```suggestion
<der korrigierte Code, wenn es ein Einzeiler-Fix ist>
```
````

Den `suggestion`-Block nur setzen, wenn der Fix wirklich genau diese Zeilen ersetzt — ein unpassender Vorschlag, den jemand blind übernimmt, ist schlimmer als gar keiner. Sonst den Fix in Prosa.

#### Der Aufruf

JSON in eine Datei schreiben und mit `--input` übergeben — nie über die Kommandozeile (Umlaute, Backticks, Zeilenumbrüche):

```bash
# payload.json:
# {
#   "commit_id": "<Head-SHA aus Schritt 1>",
#   "event": "REQUEST_CHANGES",            # oder APPROVE / COMMENT
#   "body": "<Zusammenfassung>",
#   "comments": [
#     {"path":"src/Foo.php","line":123,"side":"RIGHT","body":"**Kritisch** — …"}
#   ]
# }
gh api "repos/$REPO/pulls/<pr>/reviews" --input payload.json
```

Für die Option **Nur Zusammenfassung**: `gh pr comment <pr> --body-file <Datei>` mit demselben Body.

#### Regeln für die Positionen

- `line` ist die Zeilennummer in der **neuen** Datei (`side: "RIGHT"`).
- Mehrzeilige Befunde: `start_line` + `line`, beide auf derselben Seite.
- **Nur Zeilen, die im Diff vorkommen.** GitHub weist alles andere mit `422` ab — solche Befunde sind `SUMMARY` und stehen im Body.
- `commit_id` ist der Head-SHA aus Schritt 1. Wird zwischendurch gepusht, schlägt der Aufruf fehl: dann den SHA neu holen und die Zeilen gegen den neuen Diff prüfen, nicht einfach wiederholen.

#### Wenn es klemmt

- **`422` auf einen einzelnen Kommentar**: GitHub lehnt die ganze Review ab. Den betroffenen Kommentar aus `comments[]` nehmen, seinen Inhalt unten an den Body hängen und erneut senden. Nicht die Review still ohne diesen Befund posten.
- **Eigener PR**: `APPROVE`/`REQUEST_CHANGES` lässt GitHub am eigenen PR nicht zu. Dann `"event": "COMMENT"` senden und im Chat sagen, dass die Empfehlung nicht gesetzt werden konnte.
- **Fehlschlag**: melden, nicht verschweigen. Die Review liegt in Planstack, es geht nichts verloren.

Danach eine Zeile mit der URL der Review und der Zahl der Inline-Kommentare:

```
Gepostet: https://github.com/<repo>/pull/9050#pullrequestreview-… — 7 inline, REQUEST_CHANGES
```

### 9. Findings fixen (nur nach Auswahl in Schritt 7)

- In einem eigenen Worktree auf dem Head-SHA des PR arbeiten (`git worktree add <pfad> <headRefOid>`), nie im Haupt-Checkout mit fremden lokalen Änderungen.
- Alle Befunde der Review beheben (Kritisch → Wichtig → Hinweis); einen Befund nur auslassen, wenn der Fix strittig ist oder `requires_po_decision` gesetzt ist — dann im Chat begründen.
- Repo-Konventionen beachten (Commit-Hooks, Tooling, Commit-Message-Stil, Attribution). Ein Commit, Message nennt die behobenen Befunde.
- Auf den PR-Branch pushen (`git push origin HEAD:<headRefName>`), kein Force-Push. Ist der Remote-Branch seit dem Review weitergelaufen: abbrechen und melden.
- **PR-Beschreibung aktuell halten:** Jede Aussage im Body, die ein Fix überholt (Verhalten, Tabellen, Einstufungen, Testliste, offene Punkte), im selben Durchgang anpassen — per `gh api …/pulls/<pr> --method PATCH --input <json>` mit dem vollständigen neuen Body (UTF-8-Datei). Das ist die einzige erlaubte Änderung am PR in diesem Schritt.
- Sonst nichts am PR posten. Abschluss im Chat: eine Zeile je Befund (behoben / ausgelassen mit Grund) und der neue Commit-SHA.

## Fix-Modus: `/ps-review fix <PR>`

Gestartet über den Button **Findings fixen** auf der Planstack-Detailseite (per `claudetask:`). Dort hat der Nutzer im Dialog je Befund entschieden: **Fixen wie vorgeschlagen** (ohne Anmerkung), **Fixen** oder **Nicht fixen**, mit Anmerkung; bei Befunden mit `requires_po_decision` ist die Anmerkung die **PO-Entscheidung** (Pflicht außer bei „wie vorgeschlagen“). Der Modus setzt diese Entscheidungen um, hält sie im PR-Body fest, pusht und reviewt danach neu. Er läuft unbeaufsichtigt: **keine Rückfragen**, auch nicht nach dem Posten.

#### F1. Fix abholen

PR auflösen und UPSERT wie in Schritt 1 (rein lesend, `PRID` aus der Antwort), dann:

```bash
curl -s -w '\n%{http_code}\n' -X POST "$BASE/pull-requests/$PRID/fix/claim" "${AUTH[@]}"
# 200: data.review_id, data.commit_sha (geprüfter Stand), data.requested_by, data.requested_at,
#      data.findings[] mit id, level, type, requires_po_decision, file, line, title, message, confidence, decision, note
#      (nur noch offene Entscheidungen — bei „Fixen“ an einem einzelnen Befund ggf. nur einer)
# 404: kein Fix angefordert → abbrechen und das melden
```

Planstack zeigt den Fix damit als laufend. Ein laufender Fix darf erneut abgeholt werden (Wiederaufnahme nach Abbruch).

#### F2. Umsetzen

- Worktree auf dem aktuellen Head des PR (`git worktree add <pfad> <headRefOid>`), nie im Haupt-Checkout.
- Ist der Head seit `data.commit_sha` weitergelaufen, jeden Befund gegen den aktuellen Code prüfen: schon erledigt → `FIXED` mit Hinweis auf den Commit, nicht doppelt ändern.
- `decision = FIX`: beheben (Kritisch → Wichtig → Hinweis). Die Anmerkung ist **verbindlich** und geht dem Vorschlag im Befund vor. Bei PO-Befunden genau die PO-Entscheidung umsetzen — nicht die eigene Lesart.
- Beginnt die Anmerkung mit `Vorschlag <X>:` bzw. `Vorschlag:` (im Dialog gewählte Option), genau diese Option aus dem Befund umsetzen; Text darunter ergänzt sie (bei PO-Befunden ist er die PO-Entscheidung).
- `decision = SUGGESTED`: beheben wie `FIX`, und zwar genau so, wie der **Vorschlag** im Befund (`message`) es beschreibt — keine eigene Alternative; bei mehreren Optionen die empfohlene (A). Bei PO-Befunden ist der Vorschlag die PO-Entscheidung.
- `decision = SKIP`: Code nicht anfassen. Ergebnis `SKIPPED`, Grund = Anmerkung (fehlt sie: „bewusst nicht umgesetzt“).
- Lässt sich ein `FIX` nicht sinnvoll umsetzen (Anmerkung widerspricht dem Code, Fix bräche etwas anderes): `SKIPPED` mit konkretem Grund — nicht raten.
- Repo-Konventionen wie in Schritt 9 (Hooks, Tooling, Commit-Message-Stil, Attribution). Ein Commit; die Message nennt die behobenen Befunde.
- Push auf den PR-Branch (`git push origin HEAD:<headRefName>`), kein Force-Push. Ist der Remote-Branch während des Fixes weitergelaufen: abbrechen und melden; der Fix bleibt in Planstack offen und kann neu gestartet werden.

#### F3. PR-Body aktualisieren

Mit dem Push den Body per `gh api repos/$REPO/pulls/<pr> --method PATCH --input <json>` ersetzen (vollständiger Body, UTF-8-Datei):

- Jede Aussage, die ein Fix überholt, anpassen (wie in Schritt 9).
- Den Abschnitt `## Review-Entscheidungen` anlegen bzw. ersetzen — vor einer abschließenden Attributionszeile, sonst ans Ende:

```markdown
## Review-Entscheidungen

Review vom <Datum> (<Planstack-URL>), umgesetzt in <sha7>.

| Befund | Schwere | Entscheidung | Ergebnis |
|---|---|---|---|
| <Titel> | Wichtig | Fixen: <Anmerkung> | Behoben |
| <Titel> | Wichtig | Fixen wie vorgeschlagen | Behoben |
| <Titel> | Hinweis | Nicht fixen: <Anmerkung> | Ausgelassen |

### PO-Entscheidungen

- **<Titel>:** <PO-Entscheidung> (<requested_by>, <Datum>)
```

Leere Anmerkung → nur „Fixen“ bzw. „Nicht fixen“. Ein PO-Befund mit `SUGGESTED` steht unter PO-Entscheidungen als „wie vorgeschlagen: <Vorschlag in einem Satz>“. `### PO-Entscheidungen` entfällt ohne PO-Befunde. Gab es den Abschnitt schon aus einem früheren Fix, bleiben dessen PO-Entscheidungen stehen; neue kommen dazu. Ein vorhandenes `### Nicht umgesetzt` (Planstack trägt dort einzeln verworfene Befunde samt Begründung ein) bleibt unverändert erhalten.

#### F4. Fix in Planstack abschließen

```bash
# fix.json mit dem Write-Tool in $T anlegen:
# {"commit_sha":"<neuer Head>","findings":[{"id":12,"outcome":"FIXED","note":"<wie, ein Satz>"},{"id":13,"outcome":"SKIPPED","note":"<Grund>"}]}
curl -s -w '\n%{http_code}\n' -X PUT "$BASE/pull-requests/$PRID/reviews/<data.review_id>/fix" "${AUTH[@]}" --data-binary @"$T/fix.json"
# 200; 409 = schon abgeschlossen, nicht wiederholen
```

Jeder Befund aus F1 bekommt genau ein Ergebnis.

**Übernommene Review dismissen:** Liefert die Antwort `converted_from_login` und `open_findings` = 0 (alle Befunde des übernommenen Reviews haben ein Ergebnis), die noch aktive `CHANGES_REQUESTED`-Review dieses Logins am PR als veraltet dismissen:

```bash
# letzte Review des Logins über alle Seiten: "<id> <state>"
read RID RSTATE < <(gh api --paginate "repos/$REPO/pulls/<pr>/reviews" \
  --jq '.[] | select(.user.login=="<converted_from_login>") | "\(.id) \(.state)"' | tail -1)
[ "$RSTATE" = CHANGES_REQUESTED ] && gh api -X PUT "repos/$REPO/pulls/<pr>/reviews/$RID/dismissals" \
  -f event=DISMISS -f message='Outdated: in Planstack übernommen und in <sha7> gefixt — <data.url>#review-<data.review_id>'
```

Ist die letzte Review des Logins keine `CHANGES_REQUESTED` mehr (z. B. inzwischen approved), entfällt das. Bei `open_findings` > 0 (Einzel-Fix) erst nach dem letzten Fix-Lauf dismissen. Scheitert das Dismissen (fehlende Rechte), im Chat melden, nicht abbrechen.

#### F5. Neues Review

Direkt danach ein vollständiges Review des neuen Head (Schritte 1–6, mit neuem Review-Start in Planstack) mit `--no-post`: Schritt 7 entfällt. Davor im Chat eine Zeile je Befund (behoben / ausgelassen mit Grund) und der Fix-Commit; die Antwort endet wie immer mit den Abschlusszeilen aus Schritt 6.

## Konflikt-Modus: `/ps-review resolve <PR>`

Gestartet über den Button **Merge-Konflikt lösen** / **CI fixen** / **Kommentare abarbeiten** auf der Planstack-Detailseite (per `claudetask:`), sichtbar solange der PR offen ist und GitHub einen Merge-Konflikt, rote CI (ohne ignorierte Checks) oder offene Review-Threads meldet. Alle drei Varianten durchlaufen R1–R6; was nicht zutrifft, entfällt. Der Modus bringt den PR wieder in einen mergebaren Zustand, pusht und reviewt danach neu. Er läuft unbeaufsichtigt: **keine Rückfragen**. In Planstack gibt es dafür keinen eigenen Aufruf — nur das neue Review aus R6.

#### R1. Lage erfassen

PR auflösen wie in Schritt 1 (noch **ohne** Review-Start), dann rein lesend:

```bash
gh pr view <pr> -R "$REPO" --json number,headRefName,headRefOid,baseRefName,mergeable,mergeStateStatus,statusCheckRollup,reviewDecision
gh pr checks <pr> -R "$REPO"
# offene Review-Threads (isResolved=false, nicht outdated) samt Kommentaren und Thread-IDs:
gh api graphql -f query='query($o:String!,$r:String!,$n:Int!){repository(owner:$o,name:$r){pullRequest(number:$n){reviewThreads(first:100){nodes{id isResolved isOutdated path line comments(first:50){nodes{id databaseId author{login} body createdAt}}}}}}}' -F o=<owner> -F r=<repo> -F n=<pr>
# allgemeine PR-Kommentare (Konversation):
gh api "repos/$REPO/issues/<pr>/comments" --paginate
```

Ist `mergeable` inzwischen `MERGEABLE` (z. B. von Hand gelöst), R2 überspringen; CI und Kommentare trotzdem abarbeiten.

#### R2. Merge-Konflikt lösen

- Worktree auf dem aktuellen Head (`git worktree add <pfad> <headRefOid>`), nie im Haupt-Checkout.
- `git fetch origin <baseRefName>` und `git merge origin/<baseRefName>` — **mergen, nicht rebasen**: kein Force-Push, Review-Kommentare behalten ihre Commits.
- Konflikte inhaltlich lösen: beide Absichten erhalten (Zielbranch-Änderung **und** PR-Änderung), nicht pauschal `ours`/`theirs`. Generierte Dateien (Lockfiles, Builds, Baselines) mit dem Repo-Tooling neu erzeugen statt von Hand zu mergen.
- Lässt sich ein Konflikt nicht ohne fachliche Entscheidung lösen: Merge abbrechen (`git merge --abort`), nichts pushen, kein neues Review — der Lauf endet mit einer Chat-Meldung, die die strittigen Stellen nennt.
- Merge-Commit mit der Standard-Message (`Merge remote-tracking branch 'origin/<base>' into <head>`), Attribution wie im Repo üblich.

#### R3. Rote CI fixen

- Fehlgeschlagene Checks aus R1 (bzw. nach dem Merge lokal reproduzierbare Fehler) ansehen: `gh run view <run-id> --log-failed -R "$REPO"`.
- Nur echte Fehler des PR bzw. des Merges beheben (Tests, Lint, Typen, Build). Offensichtlich flaky oder infrastrukturell (Timeout, Runner, externe Dienste): nicht am Code drehen, sondern im Chat nennen; nach dem Push läuft die CI ohnehin neu.
- Lokal nur das Tooling, das das Repo für lokale Läufe vorsieht (Repo-Konventionen und Nutzer-Regeln beachten, z. B. keine Tests, die nur in der CI laufen sollen).
- Eigener Commit, Message nennt die behobenen Checks.

#### R4. Offene Kommentare abarbeiten

Für jeden offenen, nicht veralteten Review-Thread und jeden noch unbeantworteten Konversations-Kommentar mit Handlungsbedarf (keine Bots, keine reinen Status-/Approve-Notizen):

- **Berechtigt und eindeutig:** fixen (ein gemeinsamer Commit, Message nennt die Punkte), nach dem Push (R5) im Thread kurz antworten (`Behoben in <sha7>: <ein Satz>`) und den Thread auflösen (`resolveReviewThread`).
- **Nicht berechtigt / bereits erledigt:** sachlich mit Begründung antworten und auflösen.
- **Fachliche Entscheidung nötig oder strittig:** nur antworten (Frage bzw. Einordnung), **nicht** auflösen und nicht raten.
- Konversations-Kommentare lassen sich nicht auflösen — dort nur antworten (`gh api repos/$REPO/issues/<pr>/comments -f body=…`).

```bash
# Antwort in einem Review-Thread (databaseId des ersten Kommentars):
gh api "repos/$REPO/pulls/<pr>/comments/<databaseId>/replies" -f body='Behoben in abc1234: …'
# Thread auflösen:
gh api graphql -f query='mutation($id:ID!){resolveReviewThread(input:{threadId:$id}){thread{isResolved}}}' -F id=<thread-id>
```

Antworten in der Sprache des Kommentars, knapp, ohne Floskeln. Diese Antworten sind die einzigen Posts am PR in diesem Modus.

#### R5. Pushen

- Erst wenn R2–R4 lokal fertig sind: `git push origin HEAD:<headRefName>`, kein Force-Push. Ist der Remote-Branch währenddessen weitergelaufen: neu holen, mergen und erneut pushen; klappt das nicht, abbrechen und melden.
- Die Thread-Antworten aus R4 mit `Behoben in <sha7>` erst **nach** dem Push senden, damit der Commit auf GitHub existiert.
- PR-Body anpassen, wenn ein Fix eine Aussage darin überholt (wie in Schritt 9); sonst bleibt er unverändert.
- Worktree danach entfernen.

#### R6. Neues Review

Direkt danach ein vollständiges Review des neuen Head (Schritte 1–6, mit Review-Start in Planstack) mit `--no-post`: Schritt 7 entfällt. Davor im Chat eine kurze Zusammenfassung: Konflikt gelöst (Dateien), CI-Fixe (Checks), Kommentare (behoben/beantwortet/offen gelassen je Thread) und die gepushten Commits; die Antwort endet wie immer mit den Abschlusszeilen aus Schritt 6.

## Übernahme-Modus: `/ps-review convert <PR> --from <login>`

Gestartet über den Button **In ps-review umwandeln** auf der Planstack-Detailseite (per `claudetask:`), sichtbar solange der PR offen ist und `<login>` Änderungen angefordert hat, ohne dass die Review aus ps-review stammt oder schon übernommen wurde. Der Modus macht aus dieser GitHub-Review ein abgeschlossenes ps-review mit Befunden, damit sie über **Findings fixen** abgearbeitet werden kann, beantwortet jeden übernommenen Kommentar mit dem Link auf seinen Befund und löst den Thread auf. Er ändert keinen Code und läuft unbeaufsichtigt: **keine Rückfragen**. Fehlt `--from`, gilt der einzige Reviewer mit `CHANGES_REQUESTED`; gibt es mehrere, abbrechen und sie nennen.

#### C1. Review und Kommentare holen

PR auflösen wie in Schritt 1 (UPSERT des PR inklusive `github_reviews`, noch **ohne** Review-Start), dann rein lesend:

```bash
# letzte CHANGES_REQUESTED-Review von <login>: id, body, commit_id, submitted_at, html_url
gh api --paginate "repos/$REPO/pulls/<pr>/reviews" \
  --jq '.[] | select(.user.login=="<login>" and .state=="CHANGES_REQUESTED") | {id, body, commit_id, submitted_at, html_url}'
# offene Threads samt Thread-ID; übernommen werden die, deren erster Kommentar von <login> stammt:
gh api graphql -f query='query($o:String!,$r:String!,$n:Int!){repository(owner:$o,name:$r){pullRequest(number:$n){reviewThreads(first:100){nodes{id isResolved isOutdated path line originalLine comments(first:50){nodes{databaseId author{login} body url pullRequestReview{databaseId}}}}}}}}' -F o=<owner> -F r=<repo> -F n=<pr>
```

Übernommen werden alle **offenen** Threads, die `<login>` begonnen hat (auch aus früheren Reviews derselben Person), plus der Body der Review, wenn er mehr als eine Floskel enthält. Folgekommentare im Thread (Antworten, Einigungen) fließen in die Einordnung ein. Gibt es weder offene Threads noch einen Body mit Inhalt, abbrechen und das melden.

#### C2. Review in Planstack starten

```bash
curl -s -w '\n%{http_code}\n' -X POST "$BASE/pull-requests/$PRID/reviews" "${AUTH[@]}" \
  --data-binary "{\"commit_sha\":\"<commit_id der Review>\",\"strictness\":\"$STRICT\",\"thoroughness\":\"$THOROUGH\",\"model\":\"<Modell-ID>\",\"skill_version\":\"<version aus dem Frontmatter>\",\"converted_from_login\":\"<login>\"}"
```

`converted_from_login` blendet den Button auf der Detailseite aus und vermerkt am Review „übernommen von <login>“.

#### C3. Befunde bilden und abschließen

Je Thread ein Befund, aus dem Review-Body je eigenständigem Punkt einer:

- `type`: Thread mit `path` und Zeile (`line`, sonst `originalLine`) → `INLINE` mit `file`/`line`; Body-Punkte und Threads ohne Zeile → `SUMMARY`.
- `title`: der Einwand in einer Zeile, in der Sprache aus Schritt 5.
- `message`: zuerst der Originalkommentar als Zitat mit Autor und Link (`> **<login>:** …` + `[auf GitHub](<url>)`), danach die eigene Einordnung: am Code nachgeprüft, was zutrifft, und ein konkreter Fix-Vorschlag mit Prozent (je Option, siehe Schritt 5). Stimmt ein Einwand nicht oder ist er schon erledigt, das sachlich festhalten — der Befund bleibt trotzdem stehen, damit „Findings fixen“ ihn mit „Nicht fixen“ und Begründung schließen kann.
- `level` nach Schritt 3, `requires_po_decision` nach Schritt 4 (fachliche Regel, Kundenverhalten), `confidence` und `confidence_reason`.
- `confidence`: wie sicher der Einwand nach eigener Prüfung zutrifft (Skala aus Schritt 3), `confidence_reason` ein Satz dazu.

Abschluss wie in Schritt 5 (Aufruf 2) mit `decision` `REQUEST_CHANGES`, `criticality`/`ai_reviewability`/`po_review_likelihood` aus einem kurzen Blick auf den Diff. `summary`: `## TLDR` mit „Übernommen aus der Review von <login> vom <Datum>“ und Link auf die GitHub-Review, danach `## Nicht übernommen` (aufgelöste oder veraltete Threads, Floskeln), sonst nichts. Die Antwort enthält unter `data.finding_items[]` je Befund (in der gesendeten Reihenfolge) die `id` und den Direktlink `url`.

#### C4. Am PR antworten und auflösen

Erst nach erfolgreichem Abschluss in C3, je übernommenem Thread:

```bash
gh api "repos/$REPO/pulls/<pr>/comments/<databaseId des ersten Kommentars>/replies" \
  -f body='In Planstack als Befund übernommen: <finding_items[i].url>'
gh api graphql -f query='mutation($id:ID!){resolveReviewThread(input:{threadId:$id}){thread{isResolved}}}' -F id=<thread-id>
```

Wurde der Review-Body übernommen, **ein** Konversations-Kommentar mit den Links dieser Befunde (`gh api repos/$REPO/issues/<pr>/comments -f body=…`). Antworten in der Sprache des Kommentars, ohne Floskeln. Die GitHub-Review selbst bleibt bis zum Fix stehen; dismisst wird sie erst im Fix-Modus (F4), sobald alle Befunde ein Ergebnis haben. Scheitert C3, wird nichts gepostet und nichts aufgelöst.

#### C5. Abschluss im Chat

Kurz: Link auf die Detailseite, Zahl der übernommenen Befunde je Schwere, Threads beantwortet/aufgelöst, nicht übernommene Threads mit Grund. Kein neues Review, kein Posten darüber hinaus.
