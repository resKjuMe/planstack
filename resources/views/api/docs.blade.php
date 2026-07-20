<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('api.planstack_api_documentation') }}</title>
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
        @@media (max-width: 820px) {
            aside { display: none; }
            main { padding: 28px 18px 80px; }
        }
    </style>
</head>
<body>
<div class="layout">
    <aside>
        <p class="brand">Plan<span>stack</span></p>
        <p class="tagline">{{ __('api.rest_api_reference') }}</p>
        <nav>
            <a href="#einfuehrung">{{ __('api.introduction') }}</a>
            <a href="#auth">{{ __('api.authentication') }}</a>
            <a href="#konventionen">{{ __('api.conventions') }}</a>
            <a href="#fehler">{{ __('api.error_codes') }}</a>
            <a href="#mcp">{{ __('api.mcp_server') }}</a>

            <div class="group">{{ __('common.general') }}</div>
            <a class="sub" href="#user">GET /user</a>

            <div class="group">{{ __('common.projects') }}</div>
            <a class="sub" href="#projects-index">GET /projects</a>
            <a class="sub" href="#projects-store">POST /projects</a>
            <a class="sub" href="#projects-show">GET /projects/{p}</a>
            <a class="sub" href="#projects-update">PATCH /projects/{p}</a>
            <a class="sub" href="#board">GET .../board</a>

            <div class="group">{{ __('common.phases') }}</div>
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

            <div class="group">{{ __('api.task_actions') }}</div>
            <a class="sub" href="#task-claim">POST .../claim</a>
            <a class="sub" href="#task-release">POST .../release</a>
            <a class="sub" href="#task-status">POST .../status</a>
            <a class="sub" href="#task-pr">POST .../pr</a>
            <a class="sub" href="#task-merge">POST .../merge</a>
            <a class="sub" href="#task-gate">POST .../gate</a>
            <a class="sub" href="#task-concern">POST .../concern</a>
            <a class="sub" href="#task-resolve">DELETE .../concern</a>
            <a class="sub" href="#task-split">POST .../split</a>

            <div class="group">{{ __('api.schemas') }}</div>
            <a class="sub" href="#schema-project">Project</a>
            <a class="sub" href="#schema-phase">Phase</a>
            <a class="sub" href="#schema-task">Task</a>
        </nav>
    </aside>

    <main>
        <div class="topbar">
            <span class="mono" style="color:var(--faint);font-size:13px">Planstack API v1</span>
            <a href="/">{{ __('api.back_to_the_application') }}</a>
        </div>

        <section id="einfuehrung">
            <h1>{{ __('api.planstack_rest_api') }}</h1>
            <p class="lead">{{ __('api.programmatic_access_to_projects_phases') }}</p>
            <div class="card">
                <p style="margin-top:0"><b>{{ __('api.base_url') }}</b></p>
                <pre>https://planstack.eskju.net/api</pre>
                <p>{{ __('api.all_endpoints_live_under_the_prefix') }} <code>/api</code> {{ __('api.bound_all_requests_and_responses_are') }} <code>application/json</code>.</p>
            </div>
        </section>

        <section id="auth">
            <h2>{{ __('api.authentication') }}</h2>
            <p>{{ __('api.the_api_uses') }} <b>Personal-Access-Tokens</b> {{ __('api.laravel_sanctum_every_request_carries_a') }} <code>Authorization</code>{{ __('api.header_without_a_valid_token_the_api') }} <code>401</code>.</p>
            <pre>Authorization: Bearer &lt;dein-token&gt;
Accept: application/json
Content-Type: application/json</pre>
            <p>{{ __('api.you_create_a_token_in_the_application') }} <b>{{ __('common.profile_api_tokens') }}</b>{{ __('api.the_downloaded_project_skill_already') }}</p>
            <h3>{{ __('api.smoke_test') }}</h3>
            <pre><span class="c"># {{ __('api.checks_token_reachability') }}</span>
curl -s https://planstack.eskju.net/api/user \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json"</pre>
        </section>

        <section id="konventionen">
            <h2>{{ __('api.conventions') }}</h2>
            <ul class="clean">
                <li><b>{{ __('api.project_binding') }}</b> {{ __('api.is_done_via_the') }} <code>alias</code> ({{ __('api.e_g') }} <code>DEMO</code>){{ __('api.not_via_the_numeric_id') }}</li>
                <li><b>{{ __('api.task_and_phase_binding') }}</b> {{ __('api.is_done_via_the_2') }} <code>id</code> {{ __('api.and_is_scoped_to_the_project_foreign') }} <code>404</code>.</li>
                <li><b>{{ __('common.access') }}</b> {{ __('api.requires_team_owner_permission_on_the') }}</li>
                <li><b>{{ __('api.board_fields') }}</b> {{ __('api.pickable_gate_unlocks_stacking_color_pr') }} <b>{{ __('api.server_side') }}</b> {{ __('api.computed_and_delivered_by_the_server_do') }}</li>
                <li><b>{{ __('api.validation_error') }}</b> {{ __('api.return') }} <code>422</code> {{ __('api.with_a') }} <code>errors</code>{{ __('api.object') }}</li>
            </ul>
        </section>

        <section id="fehler">
            <h2>{{ __('api.error_codes') }}</h2>
            <table>
                <thead><tr><th>Code</th><th>{{ __('common.meaning') }}</th><th>{{ __('api.typical_cause_response') }}</th></tr></thead>
                <tbody>
                    <tr><td><code>200</code> / <code>201</code> / <code>204</code></td><td>{{ __('api.success') }}</td><td>{{ __('api.201_on_creation_204_with_no_body') }}</td></tr>
                    <tr><td><code>401</code></td><td>{{ __('api.not_authenticated') }}</td><td>{{ __('api.missing_invalid_token_check_the_header') }}</td></tr>
                    <tr><td><code>403</code></td><td>{{ __('api.no_access') }}</td><td>{{ __('api.missing_team_owner_permission_or_role') }}</td></tr>
                    <tr><td><code>404</code></td><td>{{ __('api.not_found') }}</td><td>{{ __('api.alias_id_is_wrong_or_not_in_the_project') }}</td></tr>
                    <tr><td><code>409</code></td><td>{{ __('api.conflict') }}</td><td>{{ __('api.task_is_already_claimed_not_claimed') }}</td></tr>
                    <tr><td><code>422</code></td><td>{{ __('api.validation') }}</td><td><code>errors</code> {{ __('api.read_it_and_correct_the_input') }}</td></tr>
                </tbody>
            </table>
        </section>

        <section id="mcp">
            <h2>{{ __('api.mcp_server') }}</h2>
            <p>{{ __('api.in_addition_to_the_rest_api_planstack') }} <b>MCP-Server</b>
            {{ __('api.model_context_protocol_this_lets_you') }} <b>Tools</b> {{ __('api.use_them_in_claude_code_or_other_mcp') }}</p>
            <div class="card">
                <p style="margin-top:0"><b>Endpoint</b> {{ __('api.one_server_per_project') }}</p>
                <pre>https://planstack.eskju.net/api/projects/<b>{alias}</b>/mcp</pre>
                <ul class="clean">
                    <li><b>Transport:</b> {{ __('api.streamable_http_json_rpc_2_0_stateless') }}</li>
                    <li><b>Auth:</b> {{ __('api.the_same_bearer_token_as_the_rest_api') }}</li>
                    <li><b>{{ __('api.methods') }}</b> <code>initialize</code>, <code>ping</code>, <code>tools/list</code>, <code>tools/call</code></li>
                </ul>
            </div>

            <h3>{{ __('api.setup_in_claude_code') }}</h3>
            <p>{{ __('api.either_a') }} <code>.mcp.json</code> {{ __('api.create_it_in_the_project_root_directory') }}</p>
            <pre>{
  "mcpServers": {
    "planstack-{alias}": {
      "type": "http",
      "url": "https://planstack.eskju.net/api/projects/{alias}/mcp",
      "headers": { "Authorization": "Bearer &lt;dein-token&gt;" }
    }
  }
}</pre>
            <p>{{ __('api.or_register_via_cli') }}</p>
            <pre>claude mcp add --transport http planstack-{alias} \
  "https://planstack.eskju.net/api/projects/{alias}/mcp" \
  --header "Authorization: Bearer &lt;dein-token&gt;"</pre>
            <p style="font-size:13.5px;color:var(--muted)">{{ __('api.the_downloadable_project_skill_ships') }} <code>.mcp.json</code> {{ __('api.including_a_token_as_well_as_a') }} <code>MCP.md</code> {{ __('api.already') }}</p>

            <h3>{{ __('api.available_tools') }}</h3>
            <table>
                <thead><tr><th>Tool</th><th>{{ __('api.purpose') }}</th></tr></thead>
                <tbody>
                    <tr><td><code>get_board</code></td><td>{{ __('api.board_read_model_totals_phases_pickable') }}</td></tr>
                    <tr><td><code>list_tasks</code></td><td>{{ __('api.all_tasks_including_computed_board') }}</td></tr>
                    <tr><td><code>get_task</code></td><td>{{ __('api.a_single_task_with_details_name_or_id') }}</td></tr>
                    <tr><td><code>claim_task</code> · <code>release_task</code></td><td>{{ __('api.claim_release') }}</td></tr>
                    <tr><td><code>set_task_status</code></td><td>analyze / in_progress / in_review / done</td></tr>
                    <tr><td><code>set_task_pr</code> · <code>merge_task</code></td><td>{{ __('api.set_pr_number_merge') }}</td></tr>
                    <tr><td><code>set_task_gate</code></td><td>{{ __('api.replace_prerequisites_gate') }}</td></tr>
                    <tr><td><code>report_concern</code> · <code>resolve_concern</code></td><td>{{ __('api.report_resolve_concern') }}</td></tr>
                    <tr><td><code>create_task</code> · <code>update_task</code> · <code>split_task</code></td><td>{{ __('api.create_update_split_task') }}</td></tr>
                    <tr><td><code>list_phases</code> · <code>create_phase</code></td><td>{{ __('api.read_create_phases') }}</td></tr>
                </tbody>
            </table>
            <p style="font-size:13.5px;color:var(--muted)">{{ __('api.the_tools_mirror_the_rest_operations') }}</p>
        </section>

        <h2 id="general-h">Allgemein</h2>

        <section id="user" class="endpoint">
            <div class="route"><span class="method m-get">GET</span><span class="path">/api/user</span></div>
            <p class="desc">{{ __('api.returns_token_user') }}</p>
            <span class="perm">Auth: <b>Token</b></span>
        </section>

        <h2 id="projects-h">Projekte</h2>

        <section id="projects-index" class="endpoint">
            <div class="route"><span class="method m-get">GET</span><span class="path">/api/projects</span></div>
            <p class="desc">{{ __('api.all_projects_the_token_user_has_access') }} <a href="#schema-project">Project</a> ({{ __('api.with') }} <code>tasks_count</code>, <code>owner</code>).</p>
            <span class="perm">Auth: <b>Token</b></span>
        </section>

        <section id="projects-store" class="endpoint">
            <div class="route"><span class="method m-post">POST</span><span class="path">/api/projects</span></div>
            <p class="desc">{{ __('api.creates_a_project_the_token_user') }} <span class="status-pill s2">201</span> <a href="#schema-project">Project</a>.</p>
            <table>
                <thead><tr><th>{{ __('common.field') }}</th><th>{{ __('api.type') }}</th><th>{{ __('api.rules') }}</th></tr></thead>
                <tbody>
                    <tr><td><code>alias</code></td><td>string</td><td><span class="req">{{ __('api.required') }}</span> · max 20 · alpha_dash · {{ __('api.unique') }}</td></tr>
                    <tr><td><code>name</code></td><td>string</td><td><span class="req">{{ __('api.required') }}</span> · max 100</td></tr>
                    <tr><td><code>description</code></td><td>string</td><td><span class="opt">{{ __('api.optional') }}</span></td></tr>
                </tbody>
            </table>
            <pre>curl -s -X POST https://planstack.eskju.net/api/projects \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"alias":"NTF","name":"Notify-Service","description":"…"}'</pre>
        </section>

        <section id="projects-show" class="endpoint">
            <div class="route"><span class="method m-get">GET</span><span class="path">/api/projects/<b>{alias}</b></span></div>
            <p class="desc">{{ __('api.complete_board_project_including') }} <code>phases</code> {{ __('api.and_decorated') }} <code>tasks</code> {{ __('api.with_all_computed_fields_response') }} <a href="#schema-project">Project</a>.</p>
            <span class="perm">Auth: <b>Token</b> · {{ __('api.permission') }} <b>view</b></span>
        </section>

        <section id="projects-update" class="endpoint">
            <div class="route"><span class="method m-patch">PATCH</span><span class="path">/api/projects/<b>{alias}</b></span></div>
            <p class="desc">{{ __('api.updates') }} <code>name</code> {{ __('api.and_or') }} <code>description</code>{{ __('api.the') }} <code>alias</code> {{ __('api.cannot_be_changed_response') }} <a href="#schema-project">Project</a>.</p>
            <table>
                <thead><tr><th>{{ __('common.field') }}</th><th>{{ __('api.type') }}</th><th>{{ __('api.rules') }}</th></tr></thead>
                <tbody>
                    <tr><td><code>name</code></td><td>string</td><td><span class="opt">{{ __('api.optional') }}</span> · max 100</td></tr>
                    <tr><td><code>description</code></td><td>string</td><td><span class="opt">{{ __('api.optional') }}</span></td></tr>
                </tbody>
            </table>
        </section>

        <section id="board" class="endpoint">
            <div class="route"><span class="method m-get">GET</span><span class="path">/api/projects/<b>{alias}</b>/board</span></div>
            <p class="desc">{{ __('api.the_read_model_the_skill_picks_from') }} <code>totals</code> {{ __('api.progress_sp_pickable') }} <code>phases</code> {{ __('api.aggregates_per_phase_and') }} <code>pickable</code> {{ __('api.the_pickable_tasks_in_descending_order') }} <code>unlocks</code> {{ __('api.sorted_the_first_entry_is_the_best_pick') }}</p>
            <span class="perm">Auth: <b>Token</b> · {{ __('api.permission') }} <b>view</b></span>
            <pre>{
  "project": { "id": 1, "alias": "DEMO", "name": "…" },
  "totals":  { "tasks": 42, "done": 18, "story_points": 130,
               "done_story_points": 56, "pct": 43, "pickable": 5 },
  "phases":  [ { "id": 1, "name": "P1 · …", "position": 1, "tasks": 8,
                 "story_points": 21, "done_story_points": 21, "pct": 100 } ],
  "pickable": [ { /* Task */ } ]
}</pre>
        </section>

        <h2 id="phases-h">{{ __('common.phases') }}</h2>

        <section id="phases-index" class="endpoint">
            <div class="route"><span class="method m-get">GET</span><span class="path">/api/projects/<b>{alias}</b>/phases</span></div>
            <p class="desc">{{ __('api.the_project_s_phases_sorted_by') }} <code>position</code> {{ __('api.sorted_response_list_of') }} <a href="#schema-phase">Phase</a>.</p>
            <span class="perm">Auth: <b>Token</b> · {{ __('api.permission') }} <b>view</b></span>
        </section>

        <section id="phases-store" class="endpoint">
            <div class="route"><span class="method m-post">POST</span><span class="path">/api/projects/<b>{alias}</b>/phases</span></div>
            <p class="desc">{{ __('api.creates_a_phase_without') }} <code>position</code> {{ __('api.it_is_appended_at_the_end_response') }} <span class="status-pill s2">201</span> <a href="#schema-phase">Phase</a>.</p>
            <table>
                <thead><tr><th>{{ __('common.field') }}</th><th>{{ __('api.type') }}</th><th>{{ __('api.rules') }}</th></tr></thead>
                <tbody>
                    <tr><td><code>name</code></td><td>string</td><td><span class="req">{{ __('api.required') }}</span> · max 100</td></tr>
                    <tr><td><code>position</code></td><td>integer</td><td><span class="opt">{{ __('api.optional') }}</span> · ≥ 0</td></tr>
                </tbody>
            </table>
        </section>

        <section id="phases-update" class="endpoint">
            <div class="route"><span class="method m-put">PUT</span><span class="method m-patch">PATCH</span><span class="path">/api/projects/<b>{alias}</b>/phases/<b>{id}</b></span></div>
            <p class="desc">{{ __('api.renames_or_moves_a_phase_response') }} <a href="#schema-phase">Phase</a>.</p>
            <table>
                <thead><tr><th>{{ __('common.field') }}</th><th>{{ __('api.type') }}</th><th>{{ __('api.rules') }}</th></tr></thead>
                <tbody>
                    <tr><td><code>name</code></td><td>string</td><td><span class="opt">{{ __('api.optional') }}</span> · max 100</td></tr>
                    <tr><td><code>position</code></td><td>integer</td><td><span class="opt">{{ __('api.optional') }}</span> · ≥ 0</td></tr>
                </tbody>
            </table>
        </section>

        <section id="phases-destroy" class="endpoint">
            <div class="route"><span class="method m-delete">DELETE</span><span class="path">/api/projects/<b>{alias}</b>/phases/<b>{id}</b></span></div>
            <p class="desc">{{ __('api.removes_a_phase_tasks_in_the_phase_are') }}<code>phase_id → null</code>{{ __('api.not_deleted_along_with_it_response') }} <span class="status-pill s2">204</span>.</p>
        </section>

        <h2 id="tasks-h">Tasks</h2>

        <section id="tasks-index" class="endpoint">
            <div class="route"><span class="method m-get">GET</span><span class="path">/api/projects/<b>{alias}</b>/tasks</span></div>
            <p class="desc">{{ __('api.all_tasks_in_the_project_including') }} <a href="#schema-task">Task</a>.</p>
            <span class="perm">Auth: <b>Token</b> · {{ __('api.permission') }} <b>view</b></span>
        </section>

        <section id="tasks-store" class="endpoint">
            <div class="route"><span class="method m-post">POST</span><span class="path">/api/projects/<b>{alias}</b>/tasks</span></div>
            <p class="desc">{{ __('api.creates_a_task_with_an_optional_gate') }} <span class="status-pill s2">201</span> <a href="#schema-task">Task</a>.</p>
            <span class="perm">{{ __('api.permission') }} <b>contribute</b></span>
            <table>
                <thead><tr><th>{{ __('common.field') }}</th><th>{{ __('api.type') }}</th><th>{{ __('api.rules') }}</th></tr></thead>
                <tbody>
                    <tr><td><code>name</code></td><td>string</td><td><span class="req">{{ __('api.required') }}</span> · max 50</td></tr>
                    <tr><td><code>summary</code></td><td>string</td><td><span class="req">{{ __('api.required') }}</span> · max 255</td></tr>
                    <tr><td><code>description</code></td><td>string</td><td><span class="opt">{{ __('api.optional') }}</span></td></tr>
                    <tr><td><code>acceptance_criteria</code></td><td>string</td><td><span class="opt">{{ __('api.optional') }}</span> · {{ __('common.acceptance_criteria') }}</td></tr>
                    <tr><td><code>phase_id</code></td><td>integer</td><td><span class="opt">{{ __('api.optional') }}</span> · {{ __('api.must_belong_to_the_project') }}</td></tr>
                    <tr><td><code>effort_man_days</code></td><td>integer</td><td><span class="opt">{{ __('api.optional') }}</span> · ≥ 0</td></tr>
                    <tr><td><code>effort_story_points</code></td><td>integer</td><td><span class="opt">{{ __('api.optional') }}</span> · ≥ 0</td></tr>
                    <tr><td><code>effort_tokens</code></td><td>integer</td><td><span class="opt">{{ __('api.optional') }}</span> · ≥ 0</td></tr>
                    <tr><td><code>affected_files</code></td><td>integer</td><td><span class="opt">{{ __('api.optional') }}</span> · ≥ 0</td></tr>
                    <tr><td><code>status</code></td><td>string</td><td><span class="opt">{{ __('api.optional') }}</span> · {{ __('api.taskstatus_value') }}</td></tr>
                    <tr><td><code>gate</code></td><td>array</td><td><span class="opt">{{ __('api.optional') }}</span> · {{ __('api.task_names_and_or_ids') }}</td></tr>
                </tbody>
            </table>
            <pre>curl -s -X POST https://planstack.eskju.net/api/projects/DEMO/tasks \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"name":"C40","summary":"…","phase_id":6,"effort_story_points":3,"gate":["C39"]}'</pre>
        </section>

        <section id="tasks-show" class="endpoint">
            <div class="route"><span class="method m-get">GET</span><span class="path">/api/projects/<b>{alias}</b>/tasks/<b>{id}</b></span></div>
            <p class="desc">{{ __('api.a_single_task_decorated_including') }} <code>prerequisites</code>, <code>concern</code>{{ __('api.board_fields_response') }} <a href="#schema-task">Task</a>.</p>
        </section>

        <section id="tasks-update" class="endpoint">
            <div class="route"><span class="method m-put">PUT</span><span class="method m-patch">PATCH</span><span class="path">/api/projects/<b>{alias}</b>/tasks/<b>{id}</b></span></div>
            <p class="desc">{{ __('api.updates_the_writable_fields_of_a_task') }} <a href="#schema-task">Task</a>.</p>
            <span class="perm">{{ __('api.permission') }} <b>update</b></span>
            <table>
                <thead><tr><th>{{ __('common.field') }}</th><th>{{ __('api.type') }}</th><th>{{ __('api.rules') }}</th></tr></thead>
                <tbody>
                    <tr><td><code>name</code></td><td>string</td><td><span class="req">{{ __('api.required') }}</span> · max 50</td></tr>
                    <tr><td><code>summary</code></td><td>string</td><td><span class="req">{{ __('api.required') }}</span> · max 255</td></tr>
                    <tr><td><code>description</code></td><td>string</td><td><span class="opt">{{ __('api.optional') }}</span></td></tr>
                    <tr><td><code>acceptance_criteria</code></td><td>string</td><td><span class="opt">{{ __('api.optional') }}</span> · {{ __('common.acceptance_criteria') }}</td></tr>
                    <tr><td><code>phase_id</code></td><td>integer</td><td><span class="opt">{{ __('api.optional') }}</span> · {{ __('api.must_belong_to_the_project') }}</td></tr>
                    <tr><td><code>effort_man_days</code></td><td>integer</td><td><span class="opt">{{ __('api.optional') }}</span> · ≥ 0</td></tr>
                    <tr><td><code>effort_story_points</code></td><td>integer</td><td><span class="opt">{{ __('api.optional') }}</span> · ≥ 0</td></tr>
                    <tr><td><code>effort_tokens</code></td><td>integer</td><td><span class="opt">{{ __('api.optional') }}</span> · ≥ 0</td></tr>
                    <tr><td><code>affected_files</code></td><td>integer</td><td><span class="opt">{{ __('api.optional') }}</span> · ≥ 0</td></tr>
                    <tr><td><code>status</code></td><td>string</td><td><span class="opt">{{ __('api.optional') }}</span> · {{ __('api.taskstatus_value_merged_sets') }} <code>merged_at</code>)</td></tr>
                    <tr><td><code>gate</code></td><td>array</td><td><span class="opt">{{ __('api.optional') }}</span> · {{ __('api.replaces_the_prerequisites_omitting_it') }}</td></tr>
                </tbody>
            </table>
            <pre>curl -s -X PUT https://planstack.eskju.net/api/projects/DEMO/tasks/123 \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"name":"C40","summary":"Neue Zusammenfassung","effort_story_points":5,"status":"IN_PROGRESS"}'</pre>
        </section>

        <section id="tasks-destroy" class="endpoint">
            <div class="route"><span class="method m-delete">DELETE</span><span class="path">/api/projects/<b>{alias}</b>/tasks/<b>{id}</b></span></div>
            <p class="desc">{{ __('api.deletes_a_task_response') }} <span class="status-pill s2">204</span>.</p>
            <span class="perm">{{ __('api.permission') }} <b>delete</b></span>
        </section>

        <h2 id="actions-h">{{ __('api.task_actions') }}</h2>
        <p>{{ __('api.all_actions_are_scoped_to_the_project') }} <a href="#schema-task">Task</a>.</p>

        <section id="task-claim" class="endpoint">
            <div class="route"><span class="method m-post">POST</span><span class="path">/api/projects/<b>{alias}</b>/tasks/<b>{id}</b>/claim</span></div>
            <p class="desc">{{ __('api.claims_a_free_task_for_the_token_user') }} <code>CLAIMED</code>{{ __('common.text') }} <span class="status-pill s4">409</span> {{ __('api.if_already_claimed_choose_a_different') }}</p>
        </section>

        <section id="task-release" class="endpoint">
            <div class="route"><span class="method m-post">POST</span><span class="path">/api/projects/<b>{alias}</b>/tasks/<b>{id}</b>/release</span></div>
            <p class="desc">{{ __('api.releases_a_claimed_task_again') }} <code>PICKABLE</code>{{ __('common.text') }} <span class="status-pill s4">409</span> {{ __('api.if_not_claimed') }}</p>
        </section>

        <section id="task-status" class="endpoint">
            <div class="route"><span class="method m-post">POST</span><span class="path">/api/projects/<b>{alias}</b>/tasks/<b>{id}</b>/status</span></div>
            <p class="desc">{{ __('api.sets_the_processing_status') }}</p>
            <table>
                <thead><tr><th>{{ __('common.field') }}</th><th>{{ __('api.type') }}</th><th>{{ __('api.values') }}</th></tr></thead>
                <tbody>
                    <tr><td><code>status</code></td><td>string</td><td><span class="req">{{ __('api.required') }}</span> · <code>analyze</code> · <code>in_progress</code> · <code>in_review</code> · <code>done</code></td></tr>
                </tbody>
            </table>
            <p style="font-size:13.5px;color:var(--muted)"><code>analyze</code> → ANALYZING,
            <code>in_progress</code> → IN_PROGRESS, <code>in_review</code> → IN_REVIEW.
            <code>done</code> {{ __('api.reports_the_work_as_done_with_a_pr_set') }} <code>/merge</code>).</p>
        </section>

        <section id="task-pr" class="endpoint">
            <div class="route"><span class="method m-post">POST</span><span class="path">/api/projects/<b>{alias}</b>/tasks/<b>{id}</b>/pr</span></div>
            <p class="desc">{{ __('api.records_the_pr_number') }} <code>pr_url</code> {{ __('api.is_created_automatically_when_a_github') }}</p>
            <table>
                <thead><tr><th>{{ __('common.field') }}</th><th>{{ __('api.type') }}</th><th>{{ __('api.rules') }}</th></tr></thead>
                <tbody>
                    <tr><td><code>pr_number</code></td><td>integer</td><td><span class="req">{{ __('api.required') }}</span> · ≥ 1</td></tr>
                </tbody>
            </table>
        </section>

        <section id="task-merge" class="endpoint">
            <div class="route"><span class="method m-post">POST</span><span class="path">/api/projects/<b>{alias}</b>/tasks/<b>{id}</b>/merge</span></div>
            <p class="desc">{{ __('api.marks_the_task_as') }} <code>MERGED</code> {{ __('api.idempotent') }} <code>merged_at</code> {{ __('api.only_on_the_first_transition_only_the') }}</p>
        </section>

        <section id="task-gate" class="endpoint">
            <div class="route"><span class="method m-post">POST</span><span class="path">/api/projects/<b>{alias}</b>/tasks/<b>{id}</b>/gate</span></div>
            <p class="desc">{{ __('api.replaces_the_task_s_prerequisites_gate') }} <span class="status-pill s4">422</span>.</p>
            <table>
                <thead><tr><th>{{ __('common.field') }}</th><th>{{ __('api.type') }}</th><th>{{ __('api.rules') }}</th></tr></thead>
                <tbody>
                    <tr><td><code>gate</code></td><td>array</td><td><span class="req">{{ __('api.required') }}</span> · {{ __('api.e_g') }} <code>["C21","C25"]</code></td></tr>
                </tbody>
            </table>
        </section>

        <section id="task-concern" class="endpoint">
            <div class="route"><span class="method m-post">POST</span><span class="path">/api/projects/<b>{alias}</b>/tasks/<b>{id}</b>/concern</span></div>
            <p class="desc">{{ __('api.creates_updates_a_concern_and_sets_the') }} <code>CONCERNED</code>.</p>
            <table>
                <thead><tr><th>{{ __('common.field') }}</th><th>{{ __('api.type') }}</th><th>{{ __('api.rules') }}</th></tr></thead>
                <tbody>
                    <tr><td><code>summary</code></td><td>string</td><td><span class="req">{{ __('api.required') }}</span> · max 255</td></tr>
                    <tr><td><code>context</code></td><td>string</td><td><span class="opt">{{ __('api.optional') }}</span></td></tr>
                    <tr><td><code>blocker</code></td><td>string</td><td><span class="opt">{{ __('api.optional') }}</span></td></tr>
                    <tr><td><code>misconception</code></td><td>string</td><td><span class="opt">{{ __('api.optional') }}</span></td></tr>
                    <tr><td><code>decisions</code></td><td>string</td><td><span class="opt">{{ __('api.optional') }}</span></td></tr>
                </tbody>
            </table>
        </section>

        <section id="task-resolve" class="endpoint">
            <div class="route"><span class="method m-delete">DELETE</span><span class="path">/api/projects/<b>{alias}</b>/tasks/<b>{id}</b>/concern</span></div>
            <p class="desc">{{ __('api.resolves_the_concern_the_task_returns_to') }} <code>CLAIMED</code> {{ __('common.or') }} <code>PICKABLE</code> {{ __('api.text') }}</p>
        </section>

        <section id="task-split" class="endpoint">
            <div class="route"><span class="method m-post">POST</span><span class="path">/api/projects/<b>{alias}</b>/tasks/<b>{id}</b>/split</span></div>
            <p class="desc">{{ __('api.sets_the_parent_to') }} <code>COMPLETED</code> {{ __('api.and_creates_n_children_in_the_same') }} <span class="status-pill s2">201</span> {{ __('api.with_the_list_of_children') }}</p>
            <table>
                <thead><tr><th>{{ __('common.field') }}</th><th>{{ __('api.type') }}</th><th>{{ __('api.rules') }}</th></tr></thead>
                <tbody>
                    <tr><td><code>children</code></td><td>array</td><td><span class="req">{{ __('api.required') }}</span> · min 1</td></tr>
                    <tr><td><code>children[].name</code></td><td>string</td><td><span class="req">{{ __('api.required') }}</span> · max 50</td></tr>
                    <tr><td><code>children[].summary</code></td><td>string</td><td><span class="req">{{ __('api.required') }}</span> · max 255</td></tr>
                    <tr><td><code>children[].effort_story_points</code></td><td>integer</td><td><span class="opt">{{ __('api.optional') }}</span> · ≥ 0</td></tr>
                    <tr><td><code>children[].effort_man_days</code></td><td>integer</td><td><span class="opt">{{ __('api.optional') }}</span> · ≥ 0</td></tr>
                    <tr><td><code>children[].effort_tokens</code></td><td>integer</td><td><span class="opt">{{ __('api.optional') }}</span> · ≥ 0</td></tr>
                    <tr><td><code>children[].affected_files</code></td><td>integer</td><td><span class="opt">{{ __('api.optional') }}</span> · ≥ 0</td></tr>
                    <tr><td><code>children[].gate</code></td><td>array</td><td><span class="opt">{{ __('api.optional') }}</span></td></tr>
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

        <h2 id="schemata-h">{{ __('api.response_schemas') }}</h2>

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
            <p>{{ __('api.the_fields_marked_with') }} <span class="c">// berechnet</span> {{ __('api.are_supplied_by_the_server_once_the') }}</p>
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
            {{ __('api.this_reference_mirrors') }} <code>routes/api.php</code> {{ __('api.and_the_api_resources_in_case_of') }}
        </p>
    </main>
</div>
</body>
</html>
