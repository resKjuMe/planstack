## Statusregeln (verbindlich, serverseitig gepflegt)

Vorrang vor dem Skript. Lebenszyklus: `PICKABLE → CLAIMED → ANALYZING → IN_PROGRESS → IN_REVIEW → MERGED` (`COMPLETED` nur per Split).

- **Pickbar**: alle Gates erfüllt, nicht beansprucht, kein eigener PR, nicht gemergt.
- **Gate erfüllt**: Vorgänger hat einen PR (offen genügt) oder ist gemergt/erledigt.
- **claim** ist atomar: `409` → anderen Task wählen, nicht überschreiben.
- **pr** ändert den Status nicht, erfüllt aber ab sofort das Gate abhängiger Tasks.
- **done**: mit PR → `IN_REVIEW`, ohne PR → `IN_PROGRESS`.
- **COMPLETED** nur per Split; **MERGED** nur per `/merge` (idempotent) — erst der Merge nimmt den Task vom Board.
- Fortschritt und Pickbarkeit berechnet der Server — nicht lokal nachbilden.
