---
name: planstack
description: Planstack-Boards über die REST-API abarbeiten — projektübergreifend. Aufruf „/planstack work <PROJECT>" (ganzes Board) oder „/planstack work <PROJECT> <TASK>" (ein Task). Das Projekt kommt aus dem Argument, der Zugang aus config.json. Einziger Zustandsspeicher ist die API.
argument-hint: work <project> [task] · auto <project> · review [project] [task] · fix [project] <task|pr> · plan [project] · settings · update-config [project]
---

# Planstack (Remote, projektübergreifend)

Ein Planstack-Board wird über die **REST-API** abgearbeitet: Board lesen, Task picken, claimen, analysieren, umsetzen oder Concern melden, PR setzen, mergen. Der Board-Zustand (pickable, Gate, Stacking, Unlocks, PR, Status) wird **serverseitig live berechnet** — es gibt keine lokalen Zustandsdateien.

## Aufruf

- `/planstack work <PROJECT>` — das Board von `<PROJECT>` abarbeiten (besten Pick wählen, Zyklus s. u.).
- `/planstack work <PROJECT> <TASK>` — gezielt **einen** Task (`<TASK>` = Task-Name, z. B. `C27`) dieses Projekts abarbeiten. `work` steht in der **Sub-Kommando-Position** (erstes Argument); `<PROJECT>` = Alias **oder** id, `<TASK>` = Name **oder** id (optional).
- `/planstack auto <PROJECT>` — **Auto-Modus**: das Board von `<PROJECT>` dauerhaft und unbeaufsichtigt abarbeiten (reviewen → eigene Tasks fertigstellen → pickbaren Task umsetzen; bei Leerlauf 5 min warten, dann weiter). `auto` steht in der **Sub-Kommando-Position** (erstes Argument), gefolgt vom Projekt. Arbeitet mit **mehreren Workern gleichzeitig**, wenn die Einstellung `auto_workers` und der Projekt-Knopf `parallelism.max_workers` das zulassen. Die ausführliche Anleitung ist serverseitig gepflegt (siehe „Auto-Modus").
- `/planstack review [<PROJECT>] [<TASK>]` — in-review Task(s) mit PR reviewen (übernimmt Review, führt den Review-Skill aus, erfasst das Ergebnis; ohne Argumente projektübergreifend; siehe „Review").
- `/planstack fix [<PROJECT>] <TASK|PR-NUMMER>` — offenen PR reparieren (Task/PR erforderlich): Merge-Konflikte auflösen, Kommentare + Review-Kommentare beantworten/fixen/resolven, rote CI korrigieren (siehe „Fix").
- `/planstack plan [<PROJECT>]` — Projekte, Phasen und Tasks anlegen (Planung). Die Anleitung dazu ist serverseitig gepflegt und wird bei **jedem** Aufruf frisch geladen (siehe „Plan").
- `/planstack settings` — lokale Einstellungen (Tests, PHPStan, PHPCS, Babysit-PRs, parallele Worker) anzeigen/ändern (nur lokal gespeichert; siehe „Lokale Einstellungen").
- `/planstack update-config [<PROJECT>]` — neueste allgemeine (+ Projekt-)Konfiguration ziehen und die Versionsnummern anzeigen (siehe „Konfiguration ziehen").

`<PROJECT>` ist der Projekt-Alias (z. B. `L2L`, `LOG`). Dasselbe installierte Skill bedient **alle** Projekte, auf die dein Token Zugriff hat.

> **Rückwärtskompatibilität (bis v3.0.0):** Die alte Kurzform ohne `work` — `/planstack <PROJECT>` bzw. `/planstack <PROJECT> <TASK>` — funktioniert weiterhin und ist gleichbedeutend mit `work`. Sie ist **veraltet** und wird mit **v3.0.0 abgeschafft**; ab dann ist `work` verpflichtend. Das frühere Alias `/planstack do <PROJECT> [<TASK>]` bleibt als Synonym für `work` bestehen (nützlich bei Namenskollision mit einem reservierten Sub-Kommando: `work`, `auto`, `review`, `fix`, `settings`, `update-config`, `plan`).

## Zugang

`config.json` liegt neben dieser SKILL.md; Claude Code stellt deren Verzeichnis in `CLAUDE_SKILL_DIR` bereit. Sie enthält nur `base_url` + `token` (user-gebunden, gilt projektübergreifend) und `skill_revision` — **kein** Projekt: das kommt aus dem Aufruf.

```bash
CFG="${CLAUDE_SKILL_DIR:-.}/config.json"
j(){ python3 -c "import json;print(json.load(open('$CFG')).get('$1',''))"; }
BASE=$(j base_url); TOKEN=$(j token); SKILLREV=$(j skill_revision)
AUTH=(-H "Authorization: Bearer $TOKEN" -H "Accept: application/json" -H "Content-Type: application/json")

# Aufruf: /planstack work <PROJECT> [<TASK>]  →  "work" (Synonym: "do") ist das
# Sub-Kommando fuer den Abarbeitungs-Modus und wird verworfen; PROJ = zweites
# Argument (Alias oder id), TASK = optionales drittes (Name oder id).
# /planstack auto <PROJECT>  →  Auto-Modus, PROJ = zweites Argument.
# Rueckwaertskompatibel bis v3.0.0: fehlt "work"/"do", ist A1 direkt das PROJEKT
# (veraltet, wird mit v3.0.0 abgeschafft).
read -r A1 A2 A3 <<<"$ARGUMENTS"
if [ "$A1" = "work" ] || [ "$A1" = "do" ]; then PROJ=$A2; TASK=$A3
elif [ "$A1" = "auto" ]; then MODE=auto; PROJ=$A2
else PROJ=$A1; TASK=$A2; fi   # <- veraltete Kurzform (ohne work), bis v3.0.0

# Sprechende Kennung DIESER Session fuers Board. Mehrere Sessions arbeiten unter
# demselben Token; ohne das Label zeigt das Board nur den Nutzer und man sieht
# nicht, welche Session welchen Task haelt. Ab hier tragen alle Aufrufe mit
# "${AUTH[@]}" die Kennung automatisch mit.
case "$A1" in work|do|auto) CMD=$A1 ;; *) CMD=work ;; esac
AUTH+=(-H "X-Planstack-Session: $CMD ${PROJ}${TASK:+/$TASK}")

# Fortschritt faehrt huckepack: PS_STEP/PS_PCT setzt der sp-Helfer (s. u.), und
# JEDER Aufruf ueber "pc" nimmt den aktuellen Stand mit aufs Board. Anders als ein
# separat abzusetzendes Event kann das nicht vergessen werden — es haengt an einem
# Wrapper, der einmal eingerichtet wird. Ab hier "pc" statt "curl -s" benutzen.
PS_STEP=""; PS_PCT=""
pc(){
  # Header als ARRAY aufbauen: der Schritt-Text enthaelt Leerzeichen ("4/9 Dateien:
  # TaskController.php") und wuerde bei unquoted ${VAR:+...} in Woerter zerlegt.
  local hs=()
  [ -n "$PS_STEP" ] && hs+=(-H "X-Planstack-Step: $PS_STEP")
  [ -n "$PS_PCT" ]  && hs+=(-H "X-Planstack-Progress: $PS_PCT")
  curl -s "${AUTH[@]}" "${hs[@]}" "$@"
}
```

Alle Endpunkte laufen unter `$BASE/projects/$PROJ` (siehe Betriebshandbuch). Fehler: `401` Token · `403` kein Zugriff aufs Projekt · `404` unbekannter Alias.

**Alle Planstack-Aufrufe gehen über `pc`** (nicht über blankes `curl`): nur so trägt jeder Aufruf den aktuellen Schritt mit. Ein leerer Header meldet nichts und lässt den letzten Stand stehen; ein krummer Wert wird serverseitig gekappt statt den Aufruf abzulehnen — die Angabe darf einen Claim oder Merge nie scheitern lassen.

## Zwei Modi

**A — ganzes Board (`/planstack work <PROJECT>`):** dem Zyklus des Betriebshandbuchs folgen. Pro Runde `POST $BASE/projects/$PROJ/claim-next` → das wählt den besten pickbaren Task (höchste `unlocks`) und claimt ihn atomar in einem Aufruf; die Antwort ist der geclaimte Task mit Arbeitsdetails (spart `GET /board` + `claim` + `GET /task`). Dann `analyze` → umsetzen bzw. `concern` → PR → `done` → `merge`. Kommt `{"claimed": null}` zurück, ist nichts (mehr) pickbar → fertig bzw. warten.

**B — ein Task (`/planstack work <PROJECT> <TASK>`):** Der Task ist direkt per Name ansprechbar (Pfadsegment akzeptiert Name **oder** id) — kein name→id-Lookup nötig: `POST $BASE/projects/$PROJ/tasks/$TASK/claim`, dann `GET .../tasks/$TASK` für die Details (falls `claim.return_details` aus ist), und denselben Zyklus **nur für diesen Task** (analyze → umsetzen/concern → PR → done → merge). Ist der Task nicht pickbar (Gate offen, bereits beansprucht oder schon mit PR), das melden statt es zu erzwingen.

**In beiden Modi und bei jedem Kommando** läuft die Sticky-Statuszeile mit (Format, Phasen, Einrichtung: Abschnitt „Sticky-Statuszeile" weiter unten) — auch beim Nachziehen des Skills selbst.

## Kommando-Anleitungen kommen zur Laufzeit

`review`, `fix` und `auto` haben eine eigene, ausführliche Anleitung, die **absichtlich nicht** in dieser Datei steht: sie wird an die Antwort des Aufrufs gehängt, ohne den das Kommando nicht stattfinden kann, im Feld **`command_instructions`**.

| Kommando | Aufruf, der die Anleitung mitbringt |
|---|---|
| `review` | `POST .../review-next` bzw. `POST .../tasks/<TASK>/review-claim` |
| `fix` | `GET .../tasks/<TASK>` |
| `auto` | `POST .../next-action` |

Ausgelöst wird das vom Header `X-Planstack-Session`, dessen **erstes Wort** das laufende Kommando nennt (`review DCE/A1`) — er wird im Zugang oben ohnehin gesetzt. Zwei Konsequenzen, die zu beachten sind:

1. **Der Header muss stimmen.** Steht dort nicht das tatsächlich laufende Sub-Kommando, kommt die falsche oder keine Anleitung.
2. **`command_instructions` ist verbindlich und aktuell** — der Server liefert immer den gepflegten Stand. Ist das Feld da, gilt es; eine abweichende Fassung irgendwo sonst ist veraltet.

Fehlt das Feld (älterer Server, Header nicht gesetzt), die Anleitung nachladen: `GET $BASE/projects/$PROJ/config?parts=skill_instructions` enthält **alle** Kommando-Anleitungen. Erst danach mit dem Kommando fortfahren, nicht improvisieren.

`work`, `plan`, `settings` und `update-config` brauchen kein solches Nachladen: `work` und `settings` sind vollständig in dieser Datei beschrieben, `plan` holt seine Anleitung ohnehin bei jedem Aufruf (`plan_instructions`).

## Plan (`/planstack plan [<PROJECT>]`)

Legt **Projekte, Phasen und Tasks** an (Planungsmodus statt Abarbeitung). Die vollständige, verbindliche Anleitung wird **serverseitig gepflegt** und ist bewusst **nicht** in dieser SKILL.md eingebacken, damit sie sich ohne Neu-Download aktualisiert.

**Self-updating (bei jedem Aufruf):** Zu Beginn von `/planstack plan` **immer zuerst** `GET $BASE/projects/<P>/config` lesen und den Abschnitt **`plan_instructions`** befolgen — er ist eigenständig versioniert (`plan_revision`) und beschreibt Ablauf, Endpunkte und den Feld-für-Feld-Leitfaden für Tasks (inkl. IST/SOLL-Vergleich und Testanleitung). `<P>` ist der übergebene `<PROJECT>` bzw. — wenn ein **neues** Projekt angelegt werden soll und noch kein Alias existiert — ein beliebiges zugängliches Projekt aus `GET $BASE/projects` (nur um `plan_instructions` zu ziehen). Erst danach mit der Planung beginnen.

## Selbst-Update

**Sync-at-start:** Zu Beginn **jedes** `/planstack`-Aufrufs die Aktualität prüfen, **bevor** die eigentliche Arbeit beginnt. Jede Board-/Task-Antwort trägt `X-Planstack-Config-Version`, `X-Planstack-Skill-Revision` und `X-Planstack-Status-Config-Version`. Weicht `X-Planstack-Skill-Revision` von `$SKILLREV` ab, sofort nachziehen (unten), dann erst arbeiten. Weicht nur `X-Planstack-Status-Config-Version` ab, genügt der `status_rules`-Block: `GET $BASE/projects/$PROJ/config?parts=status_rules` (siehe „Feingranulare Config-Aktualisierung"). `settings` ist rein lokal (kein Sync); `update-config` ist der explizite Sync.

**Snapshot mitschreiben (Pflicht):** Alle Abschnitte unterhalb dieses Kapitels sind nur eine **Kopie** des serverseitig gepflegten Textes. Wer eine neue `skill_revision` in `config.json` schreibt, **ohne** diese Kopie zu erneuern, folgt danach dauerhaft dem alten Text — die Drift-Prüfung schlägt nie wieder an, weil die Revisionen übereinstimmen. Deshalb bei **jedem** Nachziehen (Drift **und** `update-config`): **erst** die SKILL.md ersetzen, **dann** die Baseline setzen — nie die Baseline allein.

**Den Skill-Text nie durch den Kontext schleifen.** `skill_md` ist ~60 KB; einlesen und mit einem Datei-Werkzeug wieder hinausschreiben kostet ~32.000 Token für einen reinen Kopiervorgang. Stattdessen direkt in eine Datei pipen — im Kontext landet nur die Revision:

```bash
SD="${CLAUDE_SKILL_DIR:-.}"
NEW=$(curl -s "${AUTH[@]}" "$BASE/skill" | python3 -c 'import json,io,sys
d=json.load(sys.stdin)
io.open(sys.argv[1],"w",encoding="utf-8",newline="\n").write(d["skill_md"])
print(d["skill_revision"])' "$SD/SKILL.md.new")

# Erst uebernehmen, wenn die neue Datei plausibel ist: Marker der PR-Titel-Konvention
# vorhanden und nicht verstuemmelt. Sonst bleibt die alte SKILL.md unangetastet.
if [ -n "$NEW" ] && grep -q '<PROJECT>-<TASK>' "$SD/SKILL.md.new" \
   && [ "$(wc -c <"$SD/SKILL.md.new")" -gt 4000 ]; then
  mv -f "$SD/SKILL.md.new" "$SD/SKILL.md"   # Snapshot erneuert …
  # … und erst JETZT $NEW als skill_revision in config.json schreiben (Baseline).
else
  rm -f "$SD/SKILL.md.new"                  # Fehlschlag: Baseline NICHT anheben,
fi                                          # damit die Drift-Pruefung wirksam bleibt.
```

`newline="\n"` verhindert, dass Windows die Zeilenenden umschreibt. Schlägt irgendetwas fehl — Netzabbruch, `404` auf `GET $BASE/skill` (älterer Server), fehlender Marker —, die Baseline **nicht** anheben; dann bleibt die Drift-Prüfung wirksam und der Text wird bei jedem Lauf frisch aus `/config` (`operating_manual` + `status_rules` + `skill_instructions`) befolgt. Diese Inhalte haben ohnehin **Vorrang** vor der lokalen Kopie.

**Danach den geänderten Block einmal lesen — aber nur diesen.** Die Datei auf der Platte ist jetzt aktuell, der eigene Kontext trägt aber noch den alten Text. Welcher Block sich geändert hat, sagt die Map `revisions` (aus `GET $BASE/skill` bzw. `/config`): sie führt je Block — `operating_manual`, `status_rules`, `skill_instructions`, `plan_instructions` — eine eigene Revision. Diese gegen die lokale Baseline `revisions` in `config.json` vergleichen und **nur die abweichenden** Blöcke nachlesen: `GET $BASE/projects/$PROJ/config?parts=<block>[,<block>]`. Danach die neue Map als Baseline zurückschreiben. `skill_revision` allein genügt dafür nicht — es ist **ein** Hash über alle Blöcke und sagt nur, *dass* sich etwas geändert hat, nicht *was*.

**Selbstheilung (neue Kommandos):** Wird ein Sub-Kommando aufgerufen, das in dieser SKILL.md **nicht** beschrieben ist (z. B. ein später ergänztes), zuerst `GET $BASE/projects/$P/config` lesen (mit einem zugänglichen Projekt `$P` aus `GET $BASE/projects`) und `skill_instructions` befolgen — dort steht die **aktuelle Kommandoliste**. So stehen neue Features auch ohne Neu-Download bereit, sobald diese Selbstheilungs-Regel einmal installiert ist. Die **projektspezifische** Board-Konfiguration (Verhaltens-Hinweise wie `execution.mode`, `run.mode`, `parallelism.max_workers` …) liefert das Board bei Bedarf als `client_hints`-Block mit — separat je `<PROJECT>`, nichts davon ist fest im Skill hinterlegt.
