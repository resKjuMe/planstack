---
name: ps-review
description: Code-Review eines GitHub-PR mit Ablage in Planstack — reviewt den PR (inklusive Prüfung der Code-Kommentare auf Richtigkeit, Mehrwert und Knappheit), bewertet Kritikalität und KI-Prüfbarkeit, sendet PR-Daten und Review samt Findings an die Planstack-API (sichtbar unter planstack.eskju.net/pull-requests), gibt die Review im Chat aus und schließt mit Findings-/Kritisch-/Wichtig-/Hinweis-/Empfehlung-Zeilen ab. Am PR wird nur nach ausdrücklicher Rückfrage gepostet. Nutze diesen Skill bei „ps-review 1234", „review PR 1234 nach Planstack", „/ps-review".
argument-hint: "[<pr-nummer|pr-url|branch>] [--lenient|--strict] [--relaxed|--meticulous] [--post|--comment|--no-post]"
---

# ps-review — Review mit Ablage in Planstack

Ein vollständiges Code-Review; das Ergebnis landet **nicht** als Datei, sondern in **Planstack**: gleich zu Beginn ein UPSERT des PR und der Start des Reviews (Planstack zeigt es dann als laufend), am Ende der Abschluss mit allen Findings. Die Review steht danach unter `https://planstack.eskju.net/pull-requests` **und** im Chat. Danach die Frage, ob die Review am PR gepostet werden soll.

## Harte Regeln (nicht verhandelbar)

1. **Nichts am PR posten, bevor der Nutzer zugestimmt hat.** Während des Reviews sind nur lesende `gh`-Aufrufe erlaubt (`gh pr view`, `gh pr diff`, `gh pr checks`, `gh api` mit `GET`). Geschrieben wird am PR erst in Schritt 8 — nach expliziter Zustimmung, und nur das, was der Nutzer in Schritt 7 ausgewählt hat. Ohne Antwort wird nichts gepostet; Schweigen ist kein Ja.
2. **Planstack nur über drei Aufrufe**: `PUT /api/pull-requests` und `POST /api/pull-requests/{id}/reviews` (Start) in Schritt 1, `PUT /api/pull-requests/{id}/reviews/{review}` (Abschluss) in Schritt 5. Keine Board-Aufrufe (`review-claim`, `review-next`, Events) — dieser Skill kennt kein Board.
3. **Keine lokale Report-Datei.** Die Payload-Dateien für `curl` liegen nur temporär (Scratchpad bzw. `mktemp -d`) und werden nach dem Senden gelöscht. Schlägt die API fehl, wird das gemeldet — nicht als Datei abgelegt.
4. **Nichts am Code ändern.** Das Review ist rein lesend: keine Edits, keine Commits, kein Push, kein Checkout, der lokale Änderungen gefährdet. Einzige Ausnahme: der Nutzer wählt in Schritt 7 „Fixe Findings im gleichen PR“ — dann gilt Schritt 9.
5. **Das Review wird immer abgeschlossen** — auch bei `APPROVE` ohne einen einzigen Befund. Ein gestartetes, nie abgeschlossenes Review zeigt Planstack zwei Stunden lang als laufend, danach als abgebrochen.

## Aufruf

`/ps-review [<ZIEL>] [<Schalter>]`

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
| `verbosity` | `minimal` · `default` · `maximal` | `minimal` | Wie viel Fließtext während des Laufs im Chat landet. `minimal` = keine Vorreden, keine Ankündigungen, keine Zwischenzusammenfassungen; die Review selbst, der Link und die Abschlusszeilen (Schritt 6) sind davon unberührt und werden nie gekürzt. |
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
  --data-binary "{\"commit_sha\":\"<head.sha>\",\"strictness\":\"$STRICT\",\"thoroughness\":\"$THOROUGH\",\"model\":\"<Modell-ID>\"}"
# 201; data.id = REVIEWID — für den Abschluss in Schritt 5 merken
```

Scheitert einer der beiden Aufrufe, den Fehler nennen und **trotzdem reviewen**; der Abschluss in Schritt 5 entfällt dann, und die Review steht nur im Chat. Bei 401/403 gleich zu Beginn melden, dass nichts in Planstack landet.

Den Diff **vollständig** lesen, nicht nur die Dateiliste. Die Zahl der geänderten Dateien ist der Nenner für den Fortschritt.

Bei `meticulous` zusätzlich den Kontext um jede Änderung herum lesen (die ganze Datei, nicht nur die Hunks) und die aufrufenden Stellen der geänderten Symbole suchen — ein Diff allein zeigt keine Regression, die erst beim Aufrufer entsteht.

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

Einstufung: falscher oder irreführender Kommentar an sicherheits-/datenrelevanter Stelle → **Wichtig**, sonst falsch/veraltet → **Wichtig**, redundant/Slop/zu lang → **Hinweis** (bei `strict` in Summe erwähnenswert, einzeln aber kein Blocker). Bei `lenient` nur falsche Kommentare melden.

Im Vorschlag konkret werden: die gekürzte Fassung hinschreiben oder „streichen" sagen — nicht nur „Kommentar überarbeiten".

**Jeder Befund braucht ein konkretes Fehlszenario** — Eingabe/Zustand → falsches Ergebnis. Was sich nicht so formulieren lässt, ist kein Befund, sondern eine Anmerkung.

### 3. Befunde einstufen

| Schwere | API-Wert | Bedeutung |
|---|---|---|
| **Kritisch** | `CRITICAL` | Blocker: Datenverlust, Sicherheitslücke, falsches Ergebnis im Normalbetrieb, Deploy bricht |
| **Wichtig** | `IMPORTANT` | echter Mangel, der vor dem Merge gehört, aber nichts zerstört |
| **Hinweis** | `HINT` | Stil, Lesbarkeit, Nit, Verbesserungsidee |

**Empfehlung** (`decision`) leitet sich daraus ab:

- `Kritisch ≥ 1` → **REQUEST_CHANGES**
- sonst bei `strict` und `Wichtig ≥ 3` → **REQUEST_CHANGES**
- sonst → **APPROVE** (offene *Hinweise* verhindern kein APPROVE)

Die Regel ist mechanisch: was in den Zahlen steht, muss zur Empfehlung passen. Weicht die Einschätzung davon ab, gehört die Begründung in die Summary — nicht eine stillschweigend andere Empfehlung.

### 4. Bewerten: Kritikalität, KI-Prüfbarkeit, PO-Entscheidung, Typ

Jedes Review bekommt zwei Werte von 0–100 mit je **einem Satz** Begründung, der die ausschlaggebenden Punkte nennt.

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
| `summary` | Markdown in dieser Reihenfolge: `## TLDR` · `## Analyse je Datei` · `## Risiken & Nebenwirkungen` · `## Tests` · `## Kommentare` · `## Nicht geprüft` · `## CI` (Stand aus `gh pr checks`). Leere Abschnitte entfallen. **Keine** Befunde — die stehen in `findings`. |
| `commit_sha` | der geprüfte Head-SHA aus Schritt 1 |
| `strictness`, `thoroughness` | die tatsächlich verwendeten Werte |
| `model` | das tatsächlich genutzte Claude-Modell (Modell-ID) |
| `findings[]` | je Befund `level`, `type`, `requires_po_decision`, `file`, `line`, `title` (eine Zeile) und `message` |

`message` ist Markdown:

```markdown
**Was:** <was der Code tut>
**Warum falsch:** <Begründung>
**Fehlszenario:** <konkrete Eingabe/Zustand → falsches Ergebnis>
**Vorschlag:** <konkreter Fix, ggf. mit Codeblock>
```

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
- `## Bewertung` — zwei Zeilen: `Kritikalität: <n> — <Begründung>` und `KI-Prüfbarkeit: <n> — <Begründung>`
- `## Befunde` — Übersichtstabelle (`#`, Schwere, `Datei:Zeile`, Befund, PO), darunter je Befund der Abschnitt `### <n>. <Titel> — <Schwere>` mit `**Wo:** <Datei:Zeile>` und der `message`; bei `requires_po_decision` zusätzlich `**PO-Entscheidung nötig**`
- danach die übrigen Abschnitte der `summary` (Analyse je Datei, Risiken & Nebenwirkungen, Tests, Kommentare, Nicht geprüft, CI)

Gibt es keine Befunde, bleibt `## Befunde` mit dem Satz `Keine Befunde.` stehen.

**Nicht kürzen und nicht umschreiben** — Chat und Planstack tragen dieselbe Review. Was zu lang für den Chat wirkt, war schon in der Payload zu lang: dann beides kürzen (siehe „Sprache"). Der Detailgrad folgt der Gründlichkeit, nicht dem Medium: bei `relaxed` ist die Review kurz.

**2. Der Link** (`data.url` aus Aufruf 2), eine Zeile — oder der Fehler aus Schritt 5:

```
Planstack: https://planstack.eskju.net/pull-requests/123
```

**3. Die Abschlusszeilen** — **letzter** Block der Antwort, exakt in dieser Form und Reihenfolge:

```
Findings: 12
Kritisch: 2
Wichtig: 4
Hinweis: 6
Empfehlung: REQUEST_CHANGES
```

`Findings` ist die **Gesamtzahl** aller Befunde (Kritisch + Wichtig + Hinweis), `Kritisch` die Teilmenge der Blocker, `Wichtig` und `Hinweis` die übrigen Schweregrade — die drei Teilzahlen ergeben zusammen `Findings`, `Empfehlung` genau `APPROVE` oder `REQUEST_CHANGES`. Keine weiteren Zeilen dahinter, keine Zusammenfassung des eben Getanen, kein Angebot, die Findings gleich zu fixen — die Review ist mit diesen fünf Zeilen abgeschlossen. Einzige Ausnahme ist die Rückfrage aus Schritt 7; sie läuft über `AskUserQuestion` und nicht als weitere Textzeile.

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
| **Inline-Kommentar** (je `INLINE`-Befund) | Schwere, was falsch ist, Fehlszenario, Vorschlag — kurz, nur dieser eine Befund; bei `requires_po_decision` die Zeile `**PO-Entscheidung nötig**` |
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
- Nichts am PR posten. Abschluss im Chat: eine Zeile je Befund (behoben / ausgelassen mit Grund) und der neue Commit-SHA.
