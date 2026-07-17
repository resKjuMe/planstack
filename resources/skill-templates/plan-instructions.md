## Plan-Anweisungen (`/planstack plan`, serverseitig gepflegt & versioniert)

Verbindliche Anleitung für den **Planungsmodus** des `planstack`-Skills: **Projekte, Phasen und Tasks anlegen** (statt ein Board abzuarbeiten). Eigene, versionierte Datei (`plan_revision`) — sie wird bei **jedem** `/planstack plan`-Aufruf frisch über `GET $BASE/projects/<P>/config` (`plan_instructions`) geladen und hat Vorrang vor allem Eingebackenen. Diese Anweisungen gehören **nicht** in die allgemeinen `skill_instructions`.

### Aufruf

- `/planstack plan` — ohne Argument: interaktiv. Zuerst klären, ob ein **neues Projekt** angelegt oder ein **bestehendes** geplant werden soll.
- `/planstack plan <PROJECT>` — Phasen/Tasks zu einem **bestehenden** Projekt (`<PROJECT>` = Alias) hinzufügen.

### Ablauf

1. **Ziel bestimmen.** Neues Projekt anlegen (`POST $BASE/projects`) oder bestehendes verwenden (`<PROJECT>`).
2. **Phasen anlegen** (optional, empfohlen zur Gliederung): `POST $BASE/projects/$PROJ/phases`.
3. **Tasks anlegen:** `POST $BASE/projects/$PROJ/tasks` — je Task die Felder gemäß Leitfaden unten füllen, Abhängigkeiten über `gate` setzen.
4. **Rückfragen bündeln:** Unklarheiten (Scope, Schnitt, Abhängigkeiten, Aufwände) **vor** dem Anlegen gesammelt klären, nicht Task für Task nachfragen.

Ausgabe-Umfang gemäß lokaler Einstellung `verbosity` beachten (bei `minimal` knapp: nur was angelegt wurde).

### Endpunkte

| Methode / Pfad | Zweck | Pflichtfelder |
|---|---|---|
| `POST /projects` | Projekt anlegen | `alias` (max 20, alpha_dash, eindeutig), `name` (max 100); optional `description` |
| `POST /projects/{P}/phases` | Phase anlegen | `name` (max 100); optional `position` (Integer; sonst ans Ende) |
| `POST /projects/{P}/tasks` | Task anlegen | `name` (max 50), `summary` (max 255); Rest optional (s. u.) |

`{P}` akzeptiert den Projekt-Alias. Nach dem Anlegen eines neuen Projekts steht dessen `alias` in der Antwort — ab dann diesen für Phasen/Tasks nutzen.

### Task-Felder — Leitfaden (alle Spalten)

Beim Anlegen/Bearbeiten eines Tasks gelten die **öffentlichen** Feldnamen (die API bildet sie auf die internen Spalten ab):

| Feld (API) | Pflicht | Bedeutung / wie füllen |
|---|---|---|
| `name` | ja | Kurzes, projekt-eindeutiges Kürzel (z. B. `C27`, `G5`), max 50 Zeichen. Dient als Handle in Gates, PR-Titeln, Aufrufen. |
| `summary` | ja | **Titel des Tasks** — ein Einzeiler (max 255 Zeichen), **auf Deutsch und leicht verständlich** formuliert, sodass auch Nicht-Techniker sofort erkennen, worum es geht. Prägnant und ergebnisorientiert, kein Fachjargon/Code-Kauderwelsch. |
| `description` | nein | Ausführliche fachliche Beschreibung / Kontext (Markdown). Das *Was* und *Warum*. |
| `acceptance_criteria` | nein | Abnahmekriterien — woran ist „fertig" messbar? Als Checkliste/Stichpunkte. |
| `target_actual` | nein | **IST/SOLL-Vergleich, für Menschen leicht verständlich:** IST = Verhalten **vor** dem Task, SOLL = Verhalten **nach** dem Task. Konkret und alltagsnah formulieren (kein Fachjargon nötig), damit auch Nicht-Techniker den Nutzen erkennen. |
| `test_cases` | nein | **Testanleitung für Menschen:** Schritt-für-Schritt, wie sich das Verhalten des PRs prüfen lässt (Vorbedingungen, Klicks/Eingaben, erwartetes Ergebnis). So, dass jemand ohne Codekenntnis nachtesten kann. |
| `phase_id` | nein | Zuordnung zu einer Phase dieses Projekts (Integer-id). Für die Gliederung im Board. |
| `effort_man_days` | nein | Aufwand in Personentagen (Zahl ≥ 0). |
| `effort_story_points` | nein | Story Points (Integer ≥ 0) — treibt die Fortschritts-/Prozentanzeige des Boards. |
| `effort_tokens` | nein | Geschätzte Token-Kosten der Umsetzung (Integer ≥ 0). |
| `affected_files` | **immer angeben** | Geschätzte Anzahl betroffener Dateien (Integer ≥ 0). Verbindliche Konvention, serverseitig **nicht** validiert — trotzdem stets mitgeben. |
| `gate` | nein | Abhängigkeiten (Voraussetzungen): Liste von Task-**Namen** oder -ids desselben Projekts. Der Task wird erst pickbar, wenn diese erledigt sind. Kein Selbstbezug. |
| `criticality` | nein | Wie kritisch die Änderung ist (Risiko/Blast-Radius). Einer von: `low` (unkritisch, isoliert/risikoarm), `medium` (mittel, überschaubarer Radius), `high` (hoch, breite Wirkung / heikler Bereich), `critical` (kritisch, Kernfunktion / hohes Risiko). |
| `status` | nein | Anfangsstatus; beim Planen i. d. R. weglassen (Default `UNKNOWN`) — den Lebenszyklus steuert die Abarbeitung, nicht die Planung. |

**Hinweise:**

- Der **Titel** (`summary`) ist immer **deutsch und leicht verständlich** zu halten — es ist der Text, den Menschen zuerst sehen.
- `target_actual` und `test_cases` sind für **Menschen** gedacht (Reviewer, PO, QA) — verständlich, nicht als reine Entwicklernotiz.
- Tasks sinnvoll schneiden: eigenständig umsetzbar, testbar, mit klaren Gates statt einem Riesen-Task.
- Beim Anlegen mehrerer abhängiger Tasks zuerst die Vorgänger anlegen, dann die abhängigen mit `gate` auf deren Namen.

### Formatvorgaben für die Freitextfelder (verbindlich)

Die Task-Detailseite parst diese Felder und rendert sie strukturiert (abhakbare Listen, Gegenüberstellung, Timeline). Halte dich an das Format, damit feste Bestandteile enthalten sind und alles sauber dargestellt wird. Ohne die Struktur fällt die Anzeige auf reinen Fließtext zurück.

**`description`** — Prosa (Markdown). Optionale Verlaufs-Zeilen jeweils am **Zeilenanfang**, sie wandern automatisch in die Verlaufs-Timeline (mit Datum, wenn angegeben):
```
Umgesetzt: <was wurde umgesetzt> (TT.MM.JJJJ)
Scope-Entscheidung: <Entscheidung und Begründung>
```

**`acceptance_criteria`** — **ein Kriterium pro Zeile**, als Liste (`- …` oder `1. …`). Optional gegliedert durch Abschnitts-Überschriften auf **eigener Zeile**:
```
Scope:
- <Rahmen/Nicht-Ziele> (read-only Kontext)
Done when:
- <messbare Fertig-Bedingung> (abhakbar)
- <weitere Fertig-Bedingung>
Contract:
- <API-/Verhaltens-Zusage> (read-only Kontext)
```
Nur Punkte unter **Done when** sind abhakbar; **Scope**/**Contract** sind Kontext. Ohne Überschriften sind alle Zeilen abhakbare Kriterien.

**`target_actual`** — genau zwei mit Label eingeleitete Blöcke; wird als Gegenüberstellung (zwei Karten) gerendert:
```
IST: <Verhalten VOR dem Task>
SOLL: <Verhalten NACH dem Task>
```

**`test_cases`** — **nummerierte Schritte** (`1.`, `2.`, …). Ein erwartetes Ergebnis mit Präfix `Erwartung:` (wird als Prüfschritt markiert), nicht-abhakbare Anmerkungen mit Präfix `Hinweis:` (erscheinen als Fußnote):
```
1. <Vorbedingung/Schritt>
2. <Schritt>
Erwartung: <erwartetes, prüfbares Ergebnis>
Hinweis: <Randbedingung, z. B. nur in Chrome>
```
