<?php

return [
    'all_set_the_diagram_page_now_shows_ci' => 'Alles bereit — die Diagramm-Seite zeigt jetzt CI-Status.',
    'alternative' => '(Alternative)',
    'autostart_at_login' => 'Autostart beim Login',
    'autostart_on_every_login' => 'Autostart bei jeder Anmeldung',
    'browser_extension' => '-Browser-Erweiterung',
    'checking_local_server' => 'Lokaler Server wird geprüft…',
    'copy_prompt' => 'Prompt kopieren',
    'copy_the_matching_prompt_into_a_claude' => '? Kopier den passenden Prompt in ein Claude-Code-Fenster — es installiert Node/gh (falls nötig), lädt den Server, richtet den Autostart ein und startet ihn. Nur',
    'ctrl' => 'Strg',
    'delete_the_plist' => '+ plist löschen.',
    'do_you_have' => 'Hast du',
    'done' => 'Fertig',
    'download_it_e_g_nbsp_to' => 'herunterladen, z.&nbsp;B. nach',
    'downloads' => 'Downloads',
    'expected' => 'Erwartet:',
    'follow_the_steps_below_then_reload_this' => 'Folge den Schritten unten, dann diese Seite neu laden.',
    'from_step_2_in_tampermonkey_done' => 'aus Schritt 2 in Tampermonkey installieren — fertig.',
    'from_the_startup_folder' => 'aus dem Autostart-Ordner löschen (',
    'install_node_js_github_cli_then' => 'Node.js & GitHub CLI installieren, dann',
    'install_node_js_github_cli_then_sign_in' => 'Node.js & GitHub CLI installieren, dann anmelden:',
    'install_the_userscript' => 'Userscript installieren',
    'it_now_runs_automatically_to_remove' => 'Läuft ab jetzt automatisch. Entfernen:',
    'it_now_starts_hidden_at_every_login_to' => 'Startet ab jetzt versteckt bei jedem Login. Entfernen:',
    'local_server_is_running_v' => 'Lokaler Server läuft (v',
    'local_server_unreachable' => 'Lokaler Server nicht erreichbar',
    'mac' => 'Mac',
    'no_admin_required_paste_into_powershell' => '(kein Admin nötig) — in PowerShell einfügen:',
    'open_the_userscript_link_tampermonkey' => 'Userscript-Link öffnen → Tampermonkey zeigt „Installieren".',
    'planstack_ci_status' => 'Planstack CI-Status',
    'quick_setup_with_claude_code' => 'Schnell-Einrichtung mit Claude Code',
    'quit' => 'beenden.',
    'recommended' => '(empfohlen)',
    'reload_the_diagram_page_the_ci_merge' => 'Lade die Diagramm-Seite neu. An jedem PR-Knoten erscheint jetzt der CI-/Merge-Status; der Hinweis über dem Diagramm verschwindet automatisch. Bei einer neuen Version meldet Tampermonkey das Update automatisch (bzw. der Hinweisbalken zeigt „Aktualisieren").',
    'run' => 'ausführen.',
    'save_it_e_g_nbsp_windows' => 'speichern (z.&nbsp;B. Windows',
    'set_up_manually' => 'Manuell einrichten',
    'setup_prompt_mac' => 'Richte den Planstack-CI-Status-Server auf diesem Mac ein:
1. Prüfe, ob Node.js und die GitHub CLI (gh) installiert sind. Fehlt etwas, installiere es per Homebrew:
   brew install node gh
2. Prüfe `gh auth status`. Bin ich nicht eingeloggt, sag mir, dass ich einmal `gh auth login` selbst ausführen muss, und warte darauf.
3. Lade :asset nach ~/planstack/ci-server.cjs herunter.
4. Richte einen LaunchAgent ein (~/Library/LaunchAgents/net.planstack.ciserver.plist) mit RunAtLoad und KeepAlive, der `node ~/planstack/ci-server.cjs` bei jedem Login startet, und lade ihn (launchctl load).
5. Verifiziere, dass http://127.0.0.1:8757/version die Antwort {"version":":version"} liefert, und melde mir das Ergebnis.',
    'setup_prompt_win' => 'Richte den Planstack-CI-Status-Server auf diesem Windows-PC ein:
1. Prüfe, ob Node.js und die GitHub CLI (gh) installiert sind. Fehlt etwas, installiere es per winget:
   winget install OpenJS.NodeJS.LTS
   winget install GitHub.cli
2. Prüfe `gh auth status`. Bin ich nicht eingeloggt, sag mir, dass ich einmal `gh auth login` selbst ausführen muss, und warte darauf.
3. Lade :asset nach %USERPROFILE%\planstack\ci-server.cjs herunter.
4. Richte einen Autostart bei jeder Anmeldung ein: eine .vbs im Startup-Ordner (shell:startup), die `node %USERPROFILE%\planstack\ci-server.cjs` versteckt (ohne Fenster) startet. Starte sie anschließend sofort.
5. Verifiziere, dass http://127.0.0.1:8757/version die Antwort {"version":":version"} liefert, und melde mir das Ergebnis.',
    'shows_the_real_ci_merge_status_on_each' => 'Zeigt je PR-Knoten im Planstack-Diagramm den echten CI-/Merge-Status (✓ / ✗ / x/x Steps, „ready to merge" …). Die Daten kommen über deine lokale GitHub-CLI — ganz ohne Token im Browser.',
    'test_the_server' => 'Server testen:',
    'test_the_server_powershell' => 'Server testen (PowerShell):',
    'then_install_the' => 'Danach noch das',
    'then_sign_in_once' => 'danach einmalig anmelden:',
    'via_launchagent_run_in_the_terminal' => 'via LaunchAgent — im Terminal ausführen:',
    'what_you_need' => 'Was du brauchst',
    'with' => 'Mit',
    'you_do_yourself_once_interactively' => 'machst du einmal selbst (interaktiv).',
];
