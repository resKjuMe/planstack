## Konventionen (planstack, serverseitig gepflegt)

Verbindliche, projektübergreifende Anweisungen für den allgemeinen `planstack`-Skill. Serverseitig gepflegt: bei Drift (`X-Planstack-Skill-Revision`) über `GET /config` → `skill_instructions` nachladen.

- **PR-Titel:** Beim Erstellen eines Pull Requests **immer** den Task-Namen als Titel-Prefix setzen: `<TASK>: <Kurzbeschreibung>` (z. B. `C27: PseudoPropertyBinding-Fallback`). `<TASK>` ist der Kurzname des Tasks (Feld `name`), nicht die numerische id. Gilt für beide Modi.
