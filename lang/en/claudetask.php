<?php

return [
    'title' => 'Set up claudetask:',

    'intro_heading' => 'What is claudetask:?',
    'intro_1' => 'claudetask: is a protocol handler on your machine. When you click the Claude icon on a command row of a board card, the browser opens claudetask:<prompt> — your machine then starts a shell and runs claude with exactly that prompt.',
    'intro_2' => 'With no handler registered, nothing happens on click. Planstack notices the missing focus change, puts the prompt on the clipboard and points here. That detection is a heuristic: if the shell starts very slowly or in the background, the hint may show up even though the launch worked.',
    'prerequisite' => 'Prerequisite: Claude Code is installed and the claude command is on your PATH.',

    'quick_setup_heading' => 'Quick setup with Claude',
    'quick_setup_intro' => 'Paste this prompt into a Claude Code session — Claude creates the script and the registration for you. The steps below are the manual route.',

    'setup_prompt_win' => 'Set up the claudetask: protocol handler on this Windows PC so Planstack can start Claude with a prompt directly:
1. Check whether the claude command is on the PATH ((Get-Command claude).Source). If it is missing, tell me and stop.
2. Create %USERPROFILE%\planstack\claude-task.ps1. The script takes the full URI as a parameter, strips the "claudetask:" prefix (optionally with //), decodes the rest with [System.Uri]::UnescapeDataString and runs claude <prompt> with it. Print the prompt first for control. Add the auto-mode variant as a comment line: claude --permission-mode auto $prompt
3. Register the protocol for the current user only (no admin): key HKCU:\SOFTWARE\Classes\claudetask with default value "URL:Claude Task Protocol" and an empty "URL Protocol" value; below it shell\open\command with
   powershell.exe -NoExit -ExecutionPolicy Bypass -File "<path to the script>" "%1"
4. Test with Start-Process "claudetask:%2Fhelp" whether a PowerShell with Claude opens.
5. Show me the registered command value and remind me that Chrome asks for permission on the first click (tick “always allow”).
Ask me first if you would have to overwrite an existing file or registration along the way.',

    'setup_prompt_mac' => 'Set up the claudetask: protocol handler on this Mac so Planstack can start Claude with a prompt directly:
1. Check whether the claude command is on the PATH (command -v claude). If it is missing, tell me and stop.
2. Create ~/bin/claude-task.sh (executable). It takes the full URI as an argument, strips the claudetask: prefix (optionally with //), URL-decodes the rest (python3, urllib.parse.unquote), writes a temporary .command script running claude --permission-mode auto <prompt> and opens it via open -a Terminal.
3. Build /Applications/ClaudeTask.app as an app that registers the scheme: an AppleScript with “on open location this_URL” calling ~/bin/claude-task.sh with the URI (osacompile or Automator).
4. Add CFBundleURLTypes with CFBundleURLSchemes = claudetask to the app’s Info.plist (PlistBuddy) and register the app with LaunchServices (lsregister -f).
5. Test with open "claudetask:%2Fhelp" whether a Terminal with Claude opens, and report the result.
Ask me first if you would have to overwrite an existing file, app or registration along the way.',

    'windows_heading' => 'Windows (PowerShell, no admin rights)',
    'windows_step_1' => 'Create the handler script — this block writes %USERPROFILE%\planstack\claude-task.ps1:',
    'windows_step_2' => 'Register the protocol (current user only, hence no admin needed):',
    'windows_step_3' => 'Test it: click the Claude icon on a board card. Chrome asks for permission the first time — tick “always allow” there. Or type the URI straight into the address bar:',

    'auto_mode_heading' => 'Auto mode: PowerShell with Claude, no prompts',
    'auto_mode_1' => 'The script above starts Claude interactively. To let the prompt run unattended, replace the invocation line with --permission-mode auto:',
    'auto_mode_2' => 'The same invocation straight from a PowerShell, without the handler:',
    'auto_mode_warn' => 'In auto mode Claude runs tool calls without asking. Only use it for prompts you trust that far.',

    'mac_heading' => 'macOS',
    'mac_step_1' => 'Create the handler script (decodes the prompt and opens it in Terminal, in auto mode):',
    'mac_step_2' => 'Open Automator → “New Document” → “Application” → action “Run AppleScript” with this content, then save it as /Applications/ClaudeTask.app:',
    'mac_step_3' => 'Declare the protocol in the app’s Info.plist and register it with LaunchServices:',
    'mac_step_4' => 'Test it: click the Claude icon on a board card. The first time, macOS asks whether ClaudeTask may be opened.',

    'troubleshoot_heading' => 'If it does not work',
    'troubleshoot_1' => 'Nothing happens on click and the browser asks nothing: the handler is not registered — redo step 2 (Windows) or step 3 (macOS).',
    'troubleshoot_2' => 'A window opens and closes right away: claude is not on the shell’s PATH. Check it in PowerShell with (Get-Command claude).Source.',
    'troubleshoot_3' => 'The hint shows up although the handler works: detection waits about a second for the focus change. The prompt did start anyway — the hint can be ignored.',
    'troubleshoot_4' => 'After every unsuccessful attempt the prompt sits on the clipboard and can be pasted into a running Claude session.',

    'copy' => 'Copy',
];
