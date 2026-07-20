<?php

return [
    'all_set_the_diagram_page_now_shows_ci' => 'All set — the diagram page now shows CI status.',
    'alternative' => '(Alternative)',
    'autostart_at_login' => 'Autostart at login',
    'autostart_on_every_login' => 'Autostart on every login',
    'browser_extension' => ' browser extension',
    'checking_local_server' => 'Checking local server…',
    'copy_prompt' => 'Copy prompt',
    'copy_the_matching_prompt_into_a_claude' => '? Copy the matching prompt into a Claude Code window — it installs Node/gh (if needed), downloads the server, sets up autostart, and launches it. Only',
    'ctrl' => 'Ctrl',
    'delete_the_plist' => '+ delete the plist.',
    'do_you_have' => 'Do you have',
    'done' => 'Done',
    'download_it_e_g_nbsp_to' => 'download it, e.g.&nbsp;to',
    'downloads' => 'Downloads',
    'expected' => 'Expected:',
    'follow_the_steps_below_then_reload_this' => 'Follow the steps below, then reload this page.',
    'from_step_2_in_tampermonkey_done' => 'from step 2 in Tampermonkey — done.',
    'from_the_startup_folder' => 'from the startup folder (',
    'install_node_js_github_cli_then' => 'Install Node.js & GitHub CLI, then',
    'install_node_js_github_cli_then_sign_in' => 'Install Node.js & GitHub CLI, then sign in:',
    'install_the_userscript' => 'Install the userscript',
    'it_now_runs_automatically_to_remove' => 'It now runs automatically. To remove:',
    'it_now_starts_hidden_at_every_login_to' => 'It now starts hidden at every login. To remove:',
    'local_server_is_running_v' => 'Local server is running (v',
    'local_server_unreachable' => 'Local server unreachable',
    'mac' => 'Mac',
    'no_admin_required_paste_into_powershell' => '(no admin required) — paste into PowerShell:',
    'open_the_userscript_link_tampermonkey' => 'Open the userscript link → Tampermonkey shows "Install".',
    'planstack_ci_status' => 'Planstack CI Status',
    'quick_setup_with_claude_code' => 'Quick setup with Claude Code',
    'quit' => 'quit.',
    'recommended' => '(recommended)',
    'reload_the_diagram_page_the_ci_merge' => 'Reload the diagram page. The CI/merge status now appears on every PR node, and the notice above the diagram disappears automatically. When a new version is available, Tampermonkey reports the update automatically (or the notice bar shows "Update").',
    'run' => 'run.',
    'save_it_e_g_nbsp_windows' => 'save it (e.g.&nbsp;Windows',
    'set_up_manually' => 'Set up manually',
    'setup_prompt_mac' => 'Set up the Planstack CI status server on this Mac:
1. Check whether Node.js and the GitHub CLI (gh) are installed. If something is missing, install it via Homebrew:
   brew install node gh
2. Check `gh auth status`. If I am not logged in, tell me that I need to run `gh auth login` myself once, and wait for that.
3. Download :asset to ~/planstack/ci-server.cjs.
4. Set up a LaunchAgent (~/Library/LaunchAgents/net.planstack.ciserver.plist) with RunAtLoad and KeepAlive that starts `node ~/planstack/ci-server.cjs` at every login, and load it (launchctl load).
5. Verify that http://127.0.0.1:8757/version returns the response {"version":":version"}, and report the result to me.',
    'setup_prompt_win' => 'Set up the Planstack CI status server on this Windows PC:
1. Check whether Node.js and the GitHub CLI (gh) are installed. If something is missing, install it via winget:
   winget install OpenJS.NodeJS.LTS
   winget install GitHub.cli
2. Check `gh auth status`. If I am not logged in, tell me that I need to run `gh auth login` myself once, and wait for that.
3. Download :asset to %USERPROFILE%\planstack\ci-server.cjs.
4. Set up autostart on every sign-in: a .vbs file in the Startup folder (shell:startup) that launches `node %USERPROFILE%\planstack\ci-server.cjs` hidden (no window). Then start it immediately.
5. Verify that http://127.0.0.1:8757/version returns the response {"version":":version"}, and report the result to me.',
    'shows_the_real_ci_merge_status_on_each' => 'Shows the real CI/merge status on each PR node in the Planstack diagram (✓ / ✗ / x/x steps, "ready to merge" …). The data comes through your local GitHub CLI — with no token in the browser.',
    'test_the_server' => 'Test the server:',
    'test_the_server_powershell' => 'Test the server (PowerShell):',
    'then_install_the' => 'Then install the',
    'then_sign_in_once' => 'then sign in once:',
    'via_launchagent_run_in_the_terminal' => 'via LaunchAgent — run in the terminal:',
    'what_you_need' => 'What you need',
    'with' => 'With',
    'you_do_yourself_once_interactively' => 'you do yourself once (interactively).',
];
