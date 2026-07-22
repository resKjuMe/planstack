## Statusregeln (verbindlich, serverseitig gepflegt)

Vorrang vor dem Skript. Kanonischer Basis-Lebenszyklus: `PICKABLE → CLAIMED → ANALYZING → IN_PROGRESS → IN_REVIEW → MERGED` (`COMPLETED` nur per Split). **Das ist nur die Grundform** — jede Organisation kann zusätzliche Spalten einziehen (z. B. `REVIEWBAR` zwischen `IN_PROGRESS` und `IN_REVIEW`, `APPROVED` vor `MERGED`). Der **tatsächliche** Status-Satz, die Bezeichnungen und die erlaubten Übergänge stehen im per-Org-Abschnitt „Status dieser Organisation" (unten bzw. via `GET /config` → `status_rules`); ihm folgen, nicht dieser Grundform. Setzt die Org den Status **ereignisgesteuert** (Abschnitt „Ereignis-gesteuerte Status-Zuweisung"), folgt der Status ausschließlich den Fortschritts-Events — dann **keine** direkten `POST /status`-Calls.

- **Pickbar**: alle Gates erfüllt, nicht beansprucht, kein eigener PR, nicht gemergt.
- **Gate erfüllt**: Vorgänger hat einen PR (offen genügt) oder ist gemergt/erledigt.
- **claim** ist atomar: `409` → anderen Task wählen, nicht überschreiben.
- **pr** ändert den Status nicht, erfüllt aber ab sofort das Gate abhängiger Tasks.
- **done** (nur im nicht-ereignisgesteuerten Modus): mit PR → `IN_REVIEW`, ohne PR → `IN_PROGRESS`. Im ereignisgesteuerten Modus stattdessen die Fortschritts-Events melden (`POLISHED` → reviewbar, `REVIEWING` → in Review) und den zurückgemeldeten `status` als maßgeblich nehmen.
- **COMPLETED** nur per Split; **MERGED** nur per `/merge` (idempotent) — erst der Merge nimmt den Task vom Board.
- Fortschritt und Pickbarkeit berechnet der Server — nicht lokal nachbilden.

**Wichtig – Statuswechsel sind erzwungen:** Der konkrete Status-Satz, seine Bezeichnungen und die **erlaubten Übergänge** sind **pro Organisation konfigurierbar**. Die verdrahteten Aktionen (`claim`, `set-status`/`done`, `merge`, `complete`) werden serverseitig gegen den Übergangsgraphen geprüft — ein unerlaubter Wechsel liefert `409` (bzw. MCP-Tool-Fehler). Folge daher dem Lebenszyklus in der richtigen Reihenfolge (nicht Status „überspringen"). Den **tatsächlichen** Status-/Übergangsplan dieser Organisation liefert `GET /config` unter `status_rules` (Abschnitt „Status dieser Organisation"); bei einer Änderung ändert sich `skill_revision` — dann neu laden. Ausnahmen ohne Übergangsprüfung: `release`, `concern`/`resolve`, `split`.
