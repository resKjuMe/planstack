---
name: {{alias}}
description: Planstack-Board „{{alias}}" über die REST-API abarbeiten — picken, umsetzen, PR, mergen. Konfiguration, Betriebshandbuch und Statusregeln stehen unten. Einziger Zustandsspeicher ist die API.
---

# {{name}} — Planstack (Remote)

Unten folgen **Konfiguration**, **Betriebshandbuch** und **Statusregeln** (Snapshot vom Download).

## Zugang

```bash
CFG="${CLAUDE_SKILL_DIR:-.}/config.json"
j(){ python3 -c "import json;print(json.load(open('$CFG')).get('$1',''))"; }
BASE=$(j base_url); PROJ=$(j project); TOKEN=$(j token); CFGVER=$(j config_version); SKILLREV=$(j skill_revision)
AUTH=(-H "Authorization: Bearer $TOKEN" -H "Accept: application/json" -H "Content-Type: application/json")
```

## Selbst-Update

Jede Board-Antwort trägt `X-Planstack-Config-Version` und `X-Planstack-Skill-Revision`. Weicht eine von `$CFGVER`/`$SKILLREV` ab: `GET $BASE/projects/$PROJ/config` lesen und `operating_manual`, `status_rules` sowie die Konfiguration von dort übernehmen (Vorrang vor diesem Snapshot).
