<?php

return [
    'title' => 'Set up claudetask:',

    'intro_heading' => 'What is claudetask:?',
    'intro_1' => 'claudetask: is a protocol handler on your machine. When you click the Claude icon on a command row of a board card, the browser opens claudetask:<prompt> — your machine then starts a shell and runs claude with exactly that prompt.',
    'intro_2' => 'With no handler registered, nothing happens on click. Planstack notices the missing focus change, puts the prompt on the clipboard and points here. That detection is a heuristic: if the shell starts very slowly or in the background, the hint may show up even though the launch worked.',
    'prerequisite' => 'Prerequisite: Claude Code is installed and the claude command is on your PATH.',

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
