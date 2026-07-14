@verbatim
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Planstack — API-Dokumentation</title>
    <link rel="icon" href="/favicon.ico" sizes="48x48">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon-180.png">
    <style>
        :root {
            --bg: #f7f7f8; --surface: #ffffff; --border: #e4e4e7;
            --text: #18181b; --muted: #52525b; --faint: #71717a;
            --accent: #4338ca; --code-bg: #1e1e2e; --code-text: #e4e4e7;
            --get: #0d7d4d; --get-bg: #e6f4ec;
            --post: #1d4ed8; --post-bg: #e6edfd;
            --patch: #b45309; --patch-bg: #fbf0dd;
            --put: #7c3aed; --put-bg: #f0e9fd;
            --delete: #b91c1c; --delete-bg: #fce8e8;
        }
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0; background: var(--bg); color: var(--text);
            font-family: Figtree, ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            line-height: 1.6; font-size: 15px;
        }
        a { color: var(--accent); text-decoration: none; }
        a:hover { text-decoration: underline; }
        code, pre, .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }

        .layout { display: flex; max-width: 1180px; margin: 0 auto; }
        /* Sidebar */
        aside {
            width: 260px; flex: none; position: sticky; top: 0; align-self: flex-start;
            height: 100vh; overflow-y: auto; padding: 28px 20px; border-right: 1px solid var(--border);
        }
        aside .brand { font-weight: 600; font-size: 18px; margin: 0 0 4px; }
        aside .brand span { color: var(--accent); }
        aside .tagline { color: var(--faint); font-size: 12.5px; margin: 0 0 20px; }
        aside nav a { display: block; color: var(--muted); padding: 4px 0; font-size: 14px; }
        aside nav a:hover { color: var(--accent); text-decoration: none; }
        aside nav .group { font-size: 11px; text-transform: uppercase; letter-spacing: .06em;
            color: var(--faint); margin: 18px 0 6px; font-weight: 600; }
        aside nav .sub { padding-left: 12px; font-size: 13px; }

        main { flex: 1; min-width: 0; padding: 40px 40px 120px; }
        h1 { font-size: 30px; margin: 0 0 8px; letter-spacing: -.01em; }
        h2 { font-size: 22px; margin: 48px 0 10px; padding-top: 14px; letter-spacing: -.01em; }
        h3 { font-size: 16px; margin: 28px 0 8px; }
        p { margin: 10px 0; color: #27272a; }
        .lead { font-size: 17px; color: var(--muted); }
        section { scroll-margin-top: 20px; }

        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px;
            padding: 18px 20px; margin: 14px 0; }

        /* Endpoint */
        .endpoint { background: var(--surface); border: 1px solid var(--border); border-radius: 12px;
            padding: 16px 20px 20px; margin: 18px 0; }
        .route { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .method { font-family: ui-monospace, monospace; font-size: 12px; font-weight: 700;
            padding: 3px 9px; border-radius: 6px; letter-spacing: .03em; }
        .m-get { color: var(--get); background: var(--get-bg); }
        .m-post { color: var(--post); background: var(--post-bg); }
        .m-patch { color: var(--patch); background: var(--patch-bg); }
        .m-put { color: var(--put); background: var(--put-bg); }
        .m-delete { color: var(--delete); background: var(--delete-bg); }
        .path { font-family: ui-monospace, monospace; font-size: 14.5px; font-weight: 600; word-break: break-all; }
        .path b { color: var(--accent); font-weight: 600; }
        .desc { color: var(--muted); margin: 8px 0 0; }
        .perm { display: inline-block; font-size: 12px; color: var(--faint); margin-top: 8px; }
        .perm b { color: var(--muted); }

        table { width: 100%; border-collapse: collapse; font-size: 13.5px; margin: 12px 0; }
        th, td { border: 1px solid var(--border); padding: 7px 10px; text-align: left; vertical-align: top; }
        th { background: #fafafa; font-weight: 600; color: #3f3f46; font-size: 12.5px;
            text-transform: uppercase; letter-spacing: .03em; }
        td code { background: #f4f4f5; padding: 1px 5px; border-radius: 4px; font-size: 12.5px; }
        .req { color: var(--delete); font-weight: 600; font-size: 12px; }
        .opt { color: var(--faint); font-size: 12px; }

        pre { background: var(--code-bg); color: var(--code-text); padding: 14px 16px; border-radius: 10px;
            overflow-x: auto; font-size: 13px; line-height: 1.5; margin: 12px 0; }
        pre .c { color: #a1a1aa; }
        p code, li code { background: #ececef; padding: 1px 6px; border-radius: 4px; font-size: 13px; }

        .status-pill { display: inline-block; font-family: ui-monospace, monospace; font-size: 12px;
            font-weight: 700; padding: 2px 7px; border-radius: 5px; margin-right: 6px; }
        .s2 { color: var(--get); background: var(--get-bg); }
        .s4 { color: var(--delete); background: var(--delete-bg); }

        ul.clean { margin: 10px 0; padding-left: 20px; }
        ul.clean li { margin: 4px 0; }
        .topbar { display: flex; justify-content: space-between; align-items: baseline; }
        .topbar a { font-size: 14px; }
        @media (max-width: 820px) {
            aside { display: none; }
            main { padding: 28px 18px 80px; }
        }
    </style>
</head>
<body>
<div class="layout">
    <aside>
        <p class="brand">Plan<span>stack</span></p>
        <p class="tagline">REST-API-Referenz</p>
        <nav>
            <a href="#einfuehrung">Einführung</a>
            <a href="#auth">Authentifizierung</a>
            <a href="#konventionen">Konventionen</a>
            <a href="#fehler">Fehlercodes</a>
            <a href="#mcp">MCP-Server</a>

            <div class="group">Allgemein</div>
            <a class="sub" href="#user">GET /user</a>

            <div class="group">Projekte</div>
            <a class="sub" href="#projects-index">GET /projects</a>
            <a class="sub" href="#projects-store">POST /projects</a>
            <a class="sub" href="#projects-show">GET /projects/{p}</a>
            <a class="sub" href="#projects-update">PATCH /projects/{p}</a>
            <a class="sub" href="#board">GET .../board</a>

            <div class="group">Phasen</div>
            <a class="sub" href="#phases-index">GET .../phases</a>
            <a class="sub" href="#phases-store">POST .../phases</a>
            <a class="sub" href="#phases-update">PUT/PATCH .../phases/{id}</a>
            <a class="sub" href="#phases-destroy">DELETE .../phases/{id}</a>

            <div class="group">Tasks</div>
            <a class="sub" href="#tasks-index">GET .../tasks</a>
            <a class="sub" href="#tasks-store">POST .../tasks</a>
            <a class="sub" href="#tasks-show">GET .../tasks/{id}</a>
            <a class="sub" href="#tasks-update">PUT .../tasks/{id}</a>
            <a class="sub" href="#tasks-destroy">DELETE .../tasks/{id}</a>

            <div class="group">Task-Aktionen</div>
            <a class="sub" href="#task-claim">POST .../claim</a>
            <a class="sub" href="#task-release">POST .../release</a>
            <a class="sub" href="#task-status">POST .../status</a>
            <a class="sub" href="#task-pr">POST .../pr</a>
            <a class="sub" href="#task-merge">POST .../merge</a>
            <a class="sub" href="#task-gate">POST .../gate</a>
            <a class="sub" href="#task-concern">POST .../concern</a>
            <a class="sub" href="#task-resolve">DELETE .../concern</a>
            <a class="sub" href="#task-split">POST .../split</a>

            <div class="group">Schemata</div>
            <a class="sub" href="#schema-project">Project</a>
            <a class="sub" href="#schema-phase">Phase</a>
            <a class="sub" href="#schema-task">Task</a>
        </nav>
    </aside>

    <main>
        <div class="topbar">
            <span class="mono" style="color:var(--faint);font-size:13px">Planstack API v1</span>
            <a href="/">← Zur Anwendung</a>
        </div>

        <section id="einfuehrung">
            <h1>Planstack REST-API</h1>
            <p class="lead">Programmatischer Zugriff auf Projekte, Phasen, Tasks und das
            live berechnete Board. Dieselbe API steuert auch den herunterladbaren Skill fern.</p>
            <div class="card">
                <p style="margin-top:0"><b>Basis-URL</b></p>
                <pre>https://planstack.eskju.net/api</pre>
                <p>Alle Endpunkte sind unter dem Präfix <code>/api</code> gebunden. Alle
                Requests und Antworten sind <code>application/json</code>.</p>
            </div>
        </section>

        <section id="auth">
            <h2>Authentifizierung</h2>
            <p>Die API nutzt <b>Personal-Access-Tokens</b> (Laravel Sanctum). Jeder Request
            trägt einen Bearer-Token im <code>Authorization</code>-Header. Ohne gültiges
            Token antwortet die API mit <code>401</code>.</p>
            <pre>Authorization: Bearer &lt;dein-token&gt;
Accept: application/json
Content-Type: application/json</pre>
            <p>Ein Token erzeugst du in der Anwendung unter <b>Profil → API-Token</b>. Der
            heruntergeladene Projekt-Skill bringt bereits einen vorbefüllten Token mit.</p>
            <h3>Smoke-Test</h3>
            <pre><span class="c"># Prüft Token + Erreichbarkeit</span>
curl -s https://planstack.eskju.net/api/user \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json"</pre>
        </section>

        <section id="konventionen">
            <h2>Konventionen</h2>
            <ul class="clean">
                <li><b>Projekt-Bindung</b> erfolgt über den <code>alias</code> (z. B. <code>DEMO</code>),
                    nicht über die numerische id.</li>
                <li><b>Task- und Phasen-Bindung</b> erfolgt über die <code>id</code> und ist an das
                    Projekt gescoped — fremde ids liefern <code>404</code>.</li>
                <li><b>Zugriff</b> setzt Team-/Owner-Berechtigung am Projekt voraus; Schreib­aktionen
                    zusätzlich die passende Rolle.</li>
                <li><b>Board-Felder</b> (pickable, gate, unlocks, stacking, Farbe, pr_url) werden
                    <b>serverseitig</b> berechnet und mitgeliefert — nicht lokal nachbilden.</li>
                <li><b>Validierungsfehler</b> liefern <code>422</code> mit einem <code>errors</code>-Objekt.</li>
            </ul>
        </section>

        <section id="fehler">
            <h2>Fehlercodes</h2>
            <table>
                <thead><tr><th>Code</th><th>Bedeutung</th><th>Typische Ursache / Reaktion</th></tr></thead>
                <tbody>
                    <tr><td><code>200</code> / <code>201</code> / <code>204</code></td><td>Erfolg</td><td>201 bei Anlage, 204 ohne Body (Löschen).</td></tr>
                    <tr><td><code>401</code></td><td>Nicht authentifiziert</td><td>Kein/ungültiges Token — Header prüfen.</td></tr>
                    <tr><td><code>403</code></td><td>Kein Zugriff</td><td>Fehlende Team-/Owner-Berechtigung bzw. Rolle.</td></tr>
                    <tr><td><code>404</code></td><td>Nicht gefunden</td><td>Alias/id falsch oder nicht im Projekt.</td></tr>
                    <tr><td><code>409</code></td><td>Konflikt</td><td>Task bereits beansprucht / nicht beansprucht.</td></tr>
                    <tr><td><code>422</code></td><td>Validierung</td><td><code>errors</code> lesen, Eingabe korrigieren.</td></tr>
                </tbody>
            </table>
        </section>

        <section id="mcp">
            <h2>MCP-Server</h2>
            <p>Zusätzlich zur REST-API stellt Planstack pro Projekt einen <b>MCP-Server</b>
            (Model Context Protocol) bereit. Damit lassen sich die Board-/Task-/Phasen-Operationen
            direkt als <b>Tools</b> in Claude Code (oder anderen MCP-Clients) nutzen — ohne curl.</p>
            <div class="card">
                <p style="margin-top:0"><b>Endpoint</b> (ein Server je Projekt)</p>
                <pre>https://planstack.eskju.net/api/projects/<b>{alias}</b>/mcp</pre>
                <ul class="clean">
                    <li><b>Transport:</b> Streamable HTTP (JSON-RPC 2.0, statuslos)</li>
                    <li><b>Auth:</b> derselbe Bearer-Token wie die REST-API; der Aufrufer braucht Projektzugriff</li>
                    <li><b>Methoden:</b> <code>initialize</code>, <code>ping</code>, <code>tools/list</code>, <code>tools/call</code></li>
                </ul>
            </div>

            <h3>Einrichtung in Claude Code</h3>
            <p>Entweder eine <code>.mcp.json</code> im Projektwurzelverzeichnis anlegen …</p>
            <pre>{
  "mcpServers": {
    "planstack-{alias}": {
      "type": "http",
      "url": "https://planstack.eskju.net/api/projects/{alias}/mcp",
      "headers": { "Authorization": "Bearer &lt;dein-token&gt;" }
    }
  }
}</pre>
            <p>… oder per CLI registrieren:</p>
            <pre>claude mcp add --transport http planstack-{alias} \
  "https://planstack.eskju.net/api/projects/{alias}/mcp" \
  --header "Authorization: Bearer &lt;dein-token&gt;"</pre>
            <p style="font-size:13.5px;color:var(--muted)">Der herunterladbare Projekt-Skill bringt eine
            vorbefüllte <code>.mcp.json</code> (inkl. Token) sowie eine <code>MCP.md</code> bereits mit.</p>

            <h3>Verfügbare Tools</h3>
            <table>
                <thead><tr><th>Tool</th><th>Zweck</th></tr></thead>
                <tbody>
                    <tr><td><code>get_board</code></td><td>Board-Read-Modell (totals, phases, pickable)</td></tr>
                    <tr><td><code>list_tasks</code></td><td>Alle Tasks inkl. berechneter Board-Felder</td></tr>
                    <tr><td><code>get_task</code></td><td>Ein Task mit Details (Name oder id)</td></tr>
                    <tr><td><code>claim_task</code> · <code>release_task</code></td><td>Beanspruchen / freigeben</td></tr>
                    <tr><td><code>set_task_status</code></td><td>analyze / in_progress / in_review / done</td></tr>
                    <tr><td><code>set_task_pr</code> · <code>merge_task</code></td><td>PR-Nummer setzen / mergen</td></tr>
                    <tr><td><code>set_task_gate</code></td><td>Voraussetzungen (Gate) ersetzen</td></tr>
                    <tr><td><code>report_concern</code> · <code>resolve_concern</code></td><td>Concern melden / auflösen</td></tr>
                    <tr><td><code>create_task</code> · <code>update_task</code> · <code>split_task</code></td><td>Task anlegen / ändern / splitten</td></tr>
                    <tr><td><code>list_phases</code> · <code>create_phase</code></td><td>Phasen lesen / anlegen</td></tr>
                </tbody>
            </table>
            <p style="font-size:13.5px;color:var(--muted)">Die Tools spiegeln die untenstehenden REST-Operationen
            (gleiche Validierung, gleiche Board-Berechnung).</p>
        </section>

        <h2 id="general-h">Allgemein</h2>

        <section id="user" class="endpoint">
            <div class="route"><span class="method m-get">GET</span><span class="path">/api/user</span></div>
            <p class="desc">Gibt den zum Token gehörenden Benutzer zurück. Smoke-Test für die Token-Auth.</p>
            <span class="perm">Auth: <b>Token</b></span>
        </section>

        <h2 id="projects-h">Projekte</h2>

        <section id="projects-index" class="endpoint">
            <div class="route"><span class="method m-get">GET</span><span class="path">/api/projects</span></div>
            <p class="desc">Alle Projekte, auf die der Token-Benutzer Zugriff hat (Owner oder über ein Team).
            Antwort: Liste von <a href="#schema-project">Project</a> (mit <code>tasks_count</code>, <code>owner</code>).</p>
            <span class="perm">Auth: <b>Token</b></span>
        </section>

        <section id="projects-store" class="endpoint">
            <div class="route"><span class="method m-post">POST</span><span class="path">/api/projects</span></div>
            <p class="desc">Legt ein Projekt an; der Token-Benutzer wird Owner (ADMIN).
            Antwort <span class="status-pill s2">201</span> <a href="#schema-project">Project</a>.</p>
            <table>
                <thead><tr><th>Feld</th><th>Typ</th><th>Regeln</th></tr></thead>
                <tbody>
                    <tr><td><code>alias</code></td><td>string</td><td><span class="req">erforderlich</span> · max 20 · alpha_dash · eindeutig</td></tr>
                    <tr><td><code>name</code></td><td>string</td><td><span class="req">erforderlich</span> · max 100</td></tr>
                    <tr><td><code>description</code></td><td>string</td><td><span class="opt">optional</span></td></tr>
                </tbody>
            </table>
            <pre>curl -s -X POST https://planstack.eskju.net/api/projects \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"alias":"NTF","name":"Notify-Service","description":"…"}'</pre>
        </section>

        <section id="projects-show" class="endpoint">
            <div class="route"><span class="method m-get">GET</span><span class="path">/api/projects/<b>{alias}</b></span></div>
            <p class="desc">Vollständiges Board: Projekt inkl. <code>phases</code> und dekorierter
            <code>tasks</code> (mit allen berechneten Feldern). Antwort: <a href="#schema-project">Project</a>.</p>
            <span class="perm">Auth: <b>Token</b> · Recht: <b>view</b></span>
        </section>

        <section id="projects-update" class="endpoint">
            <div class="route"><span class="method m-patch">PATCH</span><span class="path">/api/projects/<b>{alias}</b></span></div>
            <p class="desc">Aktualisiert <code>name</code> und/oder <code>description</code>. Das
            <code>alias</code> ist nicht änderbar. Antwort: <a href="#schema-project">Project</a>.</p>
            <table>
                <thead><tr><th>Feld</th><th>Typ</th><th>Regeln</th></tr></thead>
                <tbody>
                    <tr><td><code>name</code></td><td>string</td><td><span class="opt">optional</span> · max 100</td></tr>
                    <tr><td><code>description</code></td><td>string</td><td><span class="opt">optional</span></td></tr>
                </tbody>
            </table>
        </section>

        <section id="board" class="endpoint">
            <div class="route"><span class="method m-get">GET</span><span class="path">/api/projects/<b>{alias}</b>/board</span></div>
            <p class="desc">Das Read-Modell, aus dem der Skill pickt: <code>totals</code> (Fortschritt/SP/pickable),
            <code>phases</code> (Aggregate je Phase) und <code>pickable</code> — die pickbaren Tasks,
            absteigend nach <code>unlocks</code> sortiert. Der erste Eintrag ist der „beste Pick".</p>
            <span class="perm">Auth: <b>Token</b> · Recht: <b>view</b></span>
            <pre>{
  "project": { "id": 1, "alias": "DEMO", "name": "…" },
  "totals":  { "tasks": 42, "done": 18, "story_points": 130,
               "done_story_points": 56, "pct": 43, "pickable": 5 },
  "phases":  [ { "id": 1, "name": "P1 · …", "position": 1, "tasks": 8,
                 "story_points": 21, "done_story_points": 21, "pct": 100 } ],
  "pickable": [ { /* Task */ } ]
}</pre>
        </section>

        <h2 id="phases-h">Phasen</h2>

        <section id="phases-index" class="endpoint">
            <div class="route"><span class="method m-get">GET</span><span class="path">/api/projects/<b>{alias}</b>/phases</span></div>
            <p class="desc">Phasen des Projekts, nach <code>position</code> sortiert. Antwort: Liste von <a href="#schema-phase">Phase</a>.</p>
            <span class="perm">Auth: <b>Token</b> · Recht: <b>view</b></span>
        </section>

        <section id="phases-store" class="endpoint">
            <div class="route"><span class="method m-post">POST</span><span class="path">/api/projects/<b>{alias}</b>/phases</span></div>
            <p class="desc">Legt eine Phase an. Ohne <code>position</code> wird sie hinten angehängt.
            Antwort <span class="status-pill s2">201</span> <a href="#schema-phase">Phase</a>.</p>
            <table>
                <thead><tr><th>Feld</th><th>Typ</th><th>Regeln</th></tr></thead>
                <tbody>
                    <tr><td><code>name</code></td><td>string</td><td><span class="req">erforderlich</span> · max 100</td></tr>
                    <tr><td><code>position</code></td><td>integer</td><td><span class="opt">optional</span> · ≥ 0</td></tr>
                </tbody>
            </table>
        </section>

        <section id="phases-update" class="endpoint">
            <div class="route"><span class="method m-put">PUT</span><span class="method m-patch">PATCH</span><span class="path">/api/projects/<b>{alias}</b>/phases/<b>{id}</b></span></div>
            <p class="desc">Benennt eine Phase um bzw. verschiebt sie. Antwort: <a href="#schema-phase">Phase</a>.</p>
            <table>
                <thead><tr><th>Feld</th><th>Typ</th><th>Regeln</th></tr></thead>
                <tbody>
                    <tr><td><code>name</code></td><td>string</td><td><span class="opt">optional</span> · max 100</td></tr>
                    <tr><td><code>position</code></td><td>integer</td><td><span class="opt">optional</span> · ≥ 0</td></tr>
                </tbody>
            </table>
        </section>

        <section id="phases-destroy" class="endpoint">
            <div class="route"><span class="method m-delete">DELETE</span><span class="path">/api/projects/<b>{alias}</b>/phases/<b>{id}</b></span></div>
            <p class="desc">Entfernt eine Phase. Tasks der Phase werden gelöst (<code>phase_id → null</code>),
            nicht mitgelöscht. Antwort <span class="status-pill s2">204</span>.</p>
        </section>

        <h2 id="tasks-h">Tasks</h2>

        <section id="tasks-index" class="endpoint">
            <div class="route"><span class="method m-get">GET</span><span class="path">/api/projects/<b>{alias}</b>/tasks</span></div>
            <p class="desc">Alle Tasks des Projekts inkl. berechneter Board-Felder. Antwort: Liste von <a href="#schema-task">Task</a>.</p>
            <span class="perm">Auth: <b>Token</b> · Recht: <b>view</b></span>
        </section>

        <section id="tasks-store" class="endpoint">
            <div class="route"><span class="method m-post">POST</span><span class="path">/api/projects/<b>{alias}</b>/tasks</span></div>
            <p class="desc">Legt einen Task mit optionalem Gate an. Antwort <span class="status-pill s2">201</span> <a href="#schema-task">Task</a>.</p>
            <span class="perm">Recht: <b>contribute</b></span>
            <table>
                <thead><tr><th>Feld</th><th>Typ</th><th>Regeln</th></tr></thead>
                <tbody>
                    <tr><td><code>name</code></td><td>string</td><td><span class="req">erforderlich</span> · max 50</td></tr>
                    <tr><td><code>summary</code></td><td>string</td><td><span class="req">erforderlich</span> · max 255</td></tr>
                    <tr><td><code>description</code></td><td>string</td><td><span class="opt">optional</span></td></tr>
                    <tr><td><code>acceptance_criteria</code></td><td>string</td><td><span class="opt">optional</span> · Akzeptanzkriterien</td></tr>
                    <tr><td><code>phase_id</code></td><td>integer</td><td><span class="opt">optional</span> · muss zum Projekt gehören</td></tr>
                    <tr><td><code>effort_man_days</code></td><td>integer</td><td><span class="opt">optional</span> · ≥ 0</td></tr>
                    <tr><td><code>effort_story_points</code></td><td>integer</td><td><span class="opt">optional</span> · ≥ 0</td></tr>
                    <tr><td><code>effort_tokens</code></td><td>integer</td><td><span class="opt">optional</span> · ≥ 0</td></tr>
                    <tr><td><code>affected_files</code></td><td>integer</td><td><span class="opt">optional</span> · ≥ 0</td></tr>
                    <tr><td><code>status</code></td><td>string</td><td><span class="opt">optional</span> · TaskStatus-Wert</td></tr>
                    <tr><td><code>gate</code></td><td>array</td><td><span class="opt">optional</span> · Task-Namen und/oder ids</td></tr>
                </tbody>
            </table>
            <pre>curl -s -X POST https://planstack.eskju.net/api/projects/DEMO/tasks \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"name":"C40","summary":"…","phase_id":6,"effort_story_points":3,"gate":["C39"]}'</pre>
        </section>

        <section id="tasks-show" class="endpoint">
            <div class="route"><span class="method m-get">GET</span><span class="path">/api/projects/<b>{alias}</b>/tasks/<b>{id}</b></span></div>
            <p class="desc">Ein Task, dekoriert (inkl. <code>prerequisites</code>, <code>concern</code>, Board-Felder).
            Antwort: <a href="#schema-task">Task</a>.</p>
        </section>

        <section id="tasks-update" class="endpoint">
            <div class="route"><span class="method m-put">PUT</span><span class="method m-patch">PATCH</span><span class="path">/api/projects/<b>{alias}</b>/tasks/<b>{id}</b></span></div>
            <p class="desc">Aktualisiert die beschreibbaren Felder eines Tasks (gleicher Feldsatz wie beim Anlegen).
            Antwort: <a href="#schema-task">Task</a>.</p>
            <span class="perm">Recht: <b>update</b></span>
            <table>
                <thead><tr><th>Feld</th><th>Typ</th><th>Regeln</th></tr></thead>
                <tbody>
                    <tr><td><code>name</code></td><td>string</td><td><span class="req">erforderlich</span> · max 50</td></tr>
                    <tr><td><code>summary</code></td><td>string</td><td><span class="req">erforderlich</span> · max 255</td></tr>
                    <tr><td><code>description</code></td><td>string</td><td><span class="opt">optional</span></td></tr>
                    <tr><td><code>acceptance_criteria</code></td><td>string</td><td><span class="opt">optional</span> · Akzeptanzkriterien</td></tr>
                    <tr><td><code>phase_id</code></td><td>integer</td><td><span class="opt">optional</span> · muss zum Projekt gehören</td></tr>
                    <tr><td><code>effort_man_days</code></td><td>integer</td><td><span class="opt">optional</span> · ≥ 0</td></tr>
                    <tr><td><code>effort_story_points</code></td><td>integer</td><td><span class="opt">optional</span> · ≥ 0</td></tr>
                    <tr><td><code>effort_tokens</code></td><td>integer</td><td><span class="opt">optional</span> · ≥ 0</td></tr>
                    <tr><td><code>affected_files</code></td><td>integer</td><td><span class="opt">optional</span> · ≥ 0</td></tr>
                    <tr><td><code>status</code></td><td>string</td><td><span class="opt">optional</span> · TaskStatus-Wert (MERGED setzt <code>merged_at</code>)</td></tr>
                    <tr><td><code>gate</code></td><td>array</td><td><span class="opt">optional</span> · ersetzt die Voraussetzungen; weglassen lässt sie unverändert</td></tr>
                </tbody>
            </table>
            <pre>curl -s -X PUT https://planstack.eskju.net/api/projects/DEMO/tasks/123 \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"name":"C40","summary":"Neue Zusammenfassung","effort_story_points":5,"status":"IN_PROGRESS"}'</pre>
        </section>

        <section id="tasks-destroy" class="endpoint">
            <div class="route"><span class="method m-delete">DELETE</span><span class="path">/api/projects/<b>{alias}</b>/tasks/<b>{id}</b></span></div>
            <p class="desc">Löscht einen Task. Antwort <span class="status-pill s2">204</span>.</p>
            <span class="perm">Recht: <b>delete</b></span>
        </section>

        <h2 id="actions-h">Task-Aktionen</h2>
        <p>Alle Aktionen sind an das Projekt gescoped und antworten (sofern nicht anders angegeben)
        mit dem aktualisierten <a href="#schema-task">Task</a>.</p>

        <section id="task-claim" class="endpoint">
            <div class="route"><span class="method m-post">POST</span><span class="path">/api/projects/<b>{alias}</b>/tasks/<b>{id}</b>/claim</span></div>
            <p class="desc">Beansprucht einen freien Task für den Token-Benutzer (atomar → <code>CLAIMED</code>).
            <span class="status-pill s4">409</span> wenn bereits beansprucht → anderen Task wählen.</p>
        </section>

        <section id="task-release" class="endpoint">
            <div class="route"><span class="method m-post">POST</span><span class="path">/api/projects/<b>{alias}</b>/tasks/<b>{id}</b>/release</span></div>
            <p class="desc">Gibt einen beanspruchten Task wieder frei (→ <code>PICKABLE</code>).
            <span class="status-pill s4">409</span> wenn nicht beansprucht.</p>
        </section>

        <section id="task-status" class="endpoint">
            <div class="route"><span class="method m-post">POST</span><span class="path">/api/projects/<b>{alias}</b>/tasks/<b>{id}</b>/status</span></div>
            <p class="desc">Setzt den Bearbeitungsstatus.</p>
            <table>
                <thead><tr><th>Feld</th><th>Typ</th><th>Werte</th></tr></thead>
                <tbody>
                    <tr><td><code>status</code></td><td>string</td><td><span class="req">erforderlich</span> · <code>analyze</code> · <code>in_progress</code> · <code>in_review</code> · <code>done</code></td></tr>
                </tbody>
            </table>
            <p style="font-size:13.5px;color:var(--muted)"><code>analyze</code> → ANALYZING,
            <code>in_progress</code> → IN_PROGRESS, <code>in_review</code> → IN_REVIEW.
            <code>done</code> meldet die Arbeit als fertig: mit gesetztem PR → IN_REVIEW, sonst IN_PROGRESS
            (ein offener PR macht einen Task nicht „erledigt"; COMPLETED entsteht nur per Split,
            MERGED nur per <code>/merge</code>).</p>
        </section>

        <section id="task-pr" class="endpoint">
            <div class="route"><span class="method m-post">POST</span><span class="path">/api/projects/<b>{alias}</b>/tasks/<b>{id}</b>/pr</span></div>
            <p class="desc">Trägt die PR-Nummer ein. <code>pr_url</code> entsteht automatisch, wenn am Projekt
            ein GitHub-Repo hinterlegt ist. Ein (offener) PR erfüllt das Gate abhängiger Tasks.</p>
            <table>
                <thead><tr><th>Feld</th><th>Typ</th><th>Regeln</th></tr></thead>
                <tbody>
                    <tr><td><code>pr_number</code></td><td>integer</td><td><span class="req">erforderlich</span> · ≥ 1</td></tr>
                </tbody>
            </table>
        </section>

        <section id="task-merge" class="endpoint">
            <div class="route"><span class="method m-post">POST</span><span class="path">/api/projects/<b>{alias}</b>/tasks/<b>{id}</b>/merge</span></div>
            <p class="desc">Markiert den Task als <code>MERGED</code> (idempotent; <code>merged_at</code> nur beim
            ersten Übergang). Erst der Merge nimmt den Task vom Board.</p>
        </section>

        <section id="task-gate" class="endpoint">
            <div class="route"><span class="method m-post">POST</span><span class="path">/api/projects/<b>{alias}</b>/tasks/<b>{id}</b>/gate</span></div>
            <p class="desc">Ersetzt die Voraussetzungen (Gate) des Tasks. Referenzen als Task-Namen und/oder ids,
            projekt-gescoped, kein Self-Gate. Unbekannte Referenzen → <span class="status-pill s4">422</span>.</p>
            <table>
                <thead><tr><th>Feld</th><th>Typ</th><th>Regeln</th></tr></thead>
                <tbody>
                    <tr><td><code>gate</code></td><td>array</td><td><span class="req">erforderlich</span> · z. B. <code>["C21","C25"]</code></td></tr>
                </tbody>
            </table>
        </section>

        <section id="task-concern" class="endpoint">
            <div class="route"><span class="method m-post">POST</span><span class="path">/api/projects/<b>{alias}</b>/tasks/<b>{id}</b>/concern</span></div>
            <p class="desc">Legt/aktualisiert einen Concern und setzt den Task auf <code>CONCERNED</code>.</p>
            <table>
                <thead><tr><th>Feld</th><th>Typ</th><th>Regeln</th></tr></thead>
                <tbody>
                    <tr><td><code>summary</code></td><td>string</td><td><span class="req">erforderlich</span> · max 255</td></tr>
                    <tr><td><code>context</code></td><td>string</td><td><span class="opt">optional</span></td></tr>
                    <tr><td><code>blocker</code></td><td>string</td><td><span class="opt">optional</span></td></tr>
                    <tr><td><code>misconception</code></td><td>string</td><td><span class="opt">optional</span></td></tr>
                    <tr><td><code>decisions</code></td><td>string</td><td><span class="opt">optional</span></td></tr>
                </tbody>
            </table>
        </section>

        <section id="task-resolve" class="endpoint">
            <div class="route"><span class="method m-delete">DELETE</span><span class="path">/api/projects/<b>{alias}</b>/tasks/<b>{id}</b>/concern</span></div>
            <p class="desc">Löst den Concern auf; der Task kehrt nach <code>CLAIMED</code> bzw. <code>PICKABLE</code> zurück.</p>
        </section>

        <section id="task-split" class="endpoint">
            <div class="route"><span class="method m-post">POST</span><span class="path">/api/projects/<b>{alias}</b>/tasks/<b>{id}</b>/split</span></div>
            <p class="desc">Setzt den Parent auf <code>COMPLETED</code> und legt N Kinder in derselben Phase an
            (eigene Gates/Aufwände, atomar). Antwort <span class="status-pill s2">201</span> mit der Kinder-Liste.</p>
            <table>
                <thead><tr><th>Feld</th><th>Typ</th><th>Regeln</th></tr></thead>
                <tbody>
                    <tr><td><code>children</code></td><td>array</td><td><span class="req">erforderlich</span> · min 1</td></tr>
                    <tr><td><code>children[].name</code></td><td>string</td><td><span class="req">erforderlich</span> · max 50</td></tr>
                    <tr><td><code>children[].summary</code></td><td>string</td><td><span class="req">erforderlich</span> · max 255</td></tr>
                    <tr><td><code>children[].effort_story_points</code></td><td>integer</td><td><span class="opt">optional</span> · ≥ 0</td></tr>
                    <tr><td><code>children[].effort_man_days</code></td><td>integer</td><td><span class="opt">optional</span> · ≥ 0</td></tr>
                    <tr><td><code>children[].effort_tokens</code></td><td>integer</td><td><span class="opt">optional</span> · ≥ 0</td></tr>
                    <tr><td><code>children[].affected_files</code></td><td>integer</td><td><span class="opt">optional</span> · ≥ 0</td></tr>
                    <tr><td><code>children[].gate</code></td><td>array</td><td><span class="opt">optional</span></td></tr>
                </tbody>
            </table>
            <pre>curl -s -X POST https://planstack.eskju.net/api/projects/DEMO/tasks/123/split \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"children":[
        {"name":"C27a","summary":"…","effort_story_points":3,"gate":["C25"]},
        {"name":"C27b","summary":"…","effort_story_points":2}
      ]}'</pre>
        </section>

        <h2 id="schemata-h">Antwort-Schemata</h2>

        <section id="schema-project">
            <h3>Project</h3>
            <pre>{
  "id": 1,
  "alias": "DEMO",
  "name": "Laminas → Laravel",
  "description": "…",
  "owner": { "id": 3, "name": "…" },      <span class="c">// wenn geladen</span>
  "is_owner": true,                        <span class="c">// bei authentifiziertem User</span>
  "role": "ADMIN",                         <span class="c">// Rolle des Users, sonst null</span>
  "tasks_count": 42,                       <span class="c">// bei GET /projects</span>
  "phases": [ /* Phase */ ],               <span class="c">// bei GET /projects/{alias}</span>
  "tasks":  [ /* Task */ ]                 <span class="c">// bei GET /projects/{alias}</span>
}</pre>
        </section>

        <section id="schema-phase">
            <h3>Phase</h3>
            <pre>{
  "id": 1,
  "name": "P1 · Grundlagen",
  "position": 1
}</pre>
        </section>

        <section id="schema-task">
            <h3>Task</h3>
            <p>Die mit <span class="c">// berechnet</span> markierten Felder liefert der Server, sobald der
            Task dekoriert ist (Board-Endpunkte, Einzel-Task, Aktionen).</p>
            <pre>{
  "id": 123,
  "name": "C27",
  "summary": "Kurzbeschreibung",
  "description": "…",
  "acceptance_criteria": "…",
  "status": "IN_PROGRESS",
  "status_label": "In Arbeit",
  "display_status": "IN_PROGRESS",
  "display_status_label": "In Arbeit",
  "phase_id": 6,
  "phase": { "id": 6, "name": "…", "position": 6 },   <span class="c">// wenn geladen</span>
  "effort": { "man_days": 2, "story_points": 3, "tokens": 12000 },
  "affected_files": 4,
  "pr_number": 7890,
  "pr_url": "https://github.com/owner/repo/pull/7890", <span class="c">// berechnet</span>
  "claimed_by_id": 3,
  "claimed_by": "…",                                    <span class="c">// wenn geladen</span>
  "claimed_at": "2026-07-14T10:00:00Z",
  "merged_at": null,
  "gate": "C21, C25",                                   <span class="c">// berechnet</span>
  "stacking": "C25",                                    <span class="c">// berechnet</span>
  "pickable": false,                                    <span class="c">// berechnet</span>
  "unlocks": 4,                                         <span class="c">// berechnet</span>
  "unmet": 0,                                           <span class="c">// berechnet</span>
  "color": "…",                                         <span class="c">// berechnet</span>
  "prerequisites": [ { "id": 1, "name": "C21", "status": "MERGED" } ],
  "concern": {                                          <span class="c">// wenn vorhanden</span>
    "summary": "…", "context": "…", "blocker": "…",
    "misconception": "…", "decisions": "…"
  }
}</pre>
        </section>

        <p style="margin-top:48px;color:var(--faint);font-size:13px">
            Diese Referenz spiegelt <code>routes/api.php</code> und die API-Resources wider.
            Bei Abweichungen gilt das Verhalten der laufenden Instanz.
        </p>
    </main>
</div>
</body>
</html>
@endverbatim
