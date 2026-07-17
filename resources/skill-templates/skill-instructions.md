## Konventionen (planstack, serverseitig gepflegt)

Verbindliche, projektübergreifende Anweisungen für den allgemeinen `planstack`-Skill. Serverseitig gepflegt: bei Drift (`X-Planstack-Skill-Revision`) über `GET /config` → `skill_instructions` nachladen.

- **PR-Titel:** Beim Erstellen eines Pull Requests **immer** den Task-Namen als Titel-Prefix setzen: `<TASK>: <Kurzbeschreibung>` (z. B. `C27: PseudoPropertyBinding-Fallback`). `<TASK>` ist der Kurzname des Tasks (Feld `name`), nicht die numerische id. Gilt für beide Modi.

## Lokale Einstellungen (`/planstack settings`)

Der Skill kennt lokale Einstellungen, die **ausschließlich auf diesem Rechner** in `${CLAUDE_SKILL_DIR}/settings.json` (neben `config.json`) gespeichert werden — sie werden **nie** an den Server übertragen. Fehlt die Datei oder ein einzelner Schlüssel, gilt der jeweilige Default.

**Aufruf `/planstack settings`** (erstes Argument ist `settings`, kein Projekt-Alias): die aktuellen Werte anzeigen und den Nutzer die gewünschten wählen lassen. Jede Einstellung kann **`yes`** (ja), **`no`** (nein) oder **`ask`** (bei jeder Aufgabe fragen) sein. Danach die Werte nach `settings.json` schreiben.

| Schlüssel | Bedeutung | Default |
|---|---|---|
| `local_tests` | Lokale Testausführung nach der Umsetzung | `yes` |
| `local_phpstan` | Lokale PHPStan-Verifikation | `yes` |
| `local_phpcs` | Lokale PHPCS-Formatierung | `yes` |
| `babysit_prs` | PRs nach dem Öffnen betreuen (CI/Review beobachten, nachbessern) | `ask` |

`settings.json` (Beispiel mit den Defaults):

```json
{
  "local_tests": "yes",
  "local_phpstan": "yes",
  "local_phpcs": "yes",
  "babysit_prs": "ask"
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
