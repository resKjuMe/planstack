import React, { useEffect, useState } from 'react';
import { Head } from '@inertiajs/react';
import AppShell from '../AppShell.jsx';
import PageBands from '../components/PageBands.jsx';

// Anleitung für den lokalen claudetask:-Handler. Verlinkt aus dem Kopier-Menü der
// Board-Karten (Hinweis, wenn der Start über claudetask: vermutlich fehlschlug).
//
// Die Code-Schnipsel stehen bewusst hier und nicht in den Sprachdateien: sie sind
// Kommandos, keine Prosa. Bash-Blöcke werden aus Zeilen-Arrays gebaut, weil
// Template-Literale ${...} als JS-Interpolation lesen würden; die PowerShell- und
// AppleScript-Blöcke nutzen String.raw, damit Backslashes stehen bleiben.

const WIN_SCRIPT = String.raw`$dir = "$env:USERPROFILE\planstack"
New-Item -ItemType Directory -Force $dir | Out-Null

Set-Content -Encoding utf8 (Join-Path $dir 'claude-task.ps1') -Value @'
param([string]$Uri)

# claudetask:<url-encodierter Prompt>  ->  claude <Prompt>
$prompt = $Uri -replace '^claudetask:(//)?', ''
$prompt = [System.Uri]::UnescapeDataString($prompt)

Write-Host "Starte Claude mit Prompt:" -ForegroundColor Cyan
Write-Host $prompt
Write-Host ""

claude $prompt
'@`;

const WIN_REGISTER = String.raw`$ps1 = "$env:USERPROFILE\planstack\claude-task.ps1"
$key = 'HKCU:\SOFTWARE\Classes\claudetask'

$cmd = 'powershell.exe -NoExit -ExecutionPolicy Bypass -File "' + $ps1 + '" "%1"'

New-Item -Path "$key\shell\open\command" -Force | Out-Null
Set-ItemProperty $key -Name '(default)' -Value 'URL:Claude Task Protocol'
Set-ItemProperty $key -Name 'URL Protocol' -Value ''
Set-ItemProperty "$key\shell\open\command" -Name '(default)' -Value $cmd`;

const WIN_TEST = 'claudetask:%2Fplanstack%20fix%20TXSAFE%20CS-UserStorage';

// Auto-Mode: dieselbe Aufruf-Zeile im Handler-Skript, aber ohne Rückfragen.
const WIN_AUTO_LINE = 'claude --permission-mode auto $prompt';

const WIN_AUTO_DIRECT = 'claude --permission-mode auto "/planstack fix TXSAFE CS-UserStorage"';

const MAC_SCRIPT = [
    'mkdir -p ~/bin',
    "cat > ~/bin/claude-task.sh <<'SH'",
    '#!/bin/bash',
    '# $1 = die komplette URI, z. B. claudetask:%2Fplanstack%20fix%20TXSAFE%20CS-UserStorage',
    'raw="${1#claudetask:}"; raw="${raw#//}"',
    'prompt=$(python3 -c \'import sys,urllib.parse;print(urllib.parse.unquote(sys.argv[1]))\' "$raw")',
    '',
    '# Prompt in ein Start-Skript schreiben und im Terminal oeffnen (Auto-Mode)',
    'tmp="$(mktemp -t claude-task)".command',
    'printf \'#!/bin/bash\\ncd ~\\nclaude --permission-mode auto %s\\n\' "$(printf \'%q\' "$prompt")" > "$tmp"',
    'chmod +x "$tmp"',
    'open -a Terminal "$tmp"',
    'SH',
    'chmod +x ~/bin/claude-task.sh',
].join('\n');

const MAC_APPLESCRIPT = String.raw`on open location this_URL
    do shell script "$HOME/bin/claude-task.sh " & quoted form of this_URL
end open location`;

const MAC_PLIST = [
    'PLIST=/Applications/ClaudeTask.app/Contents/Info.plist',
    '',
    '/usr/libexec/PlistBuddy -c \'Add :CFBundleURLTypes array\' "$PLIST"',
    '/usr/libexec/PlistBuddy -c \'Add :CFBundleURLTypes:0:CFBundleURLName string ClaudeTask\' "$PLIST"',
    '/usr/libexec/PlistBuddy -c \'Add :CFBundleURLTypes:0:CFBundleURLSchemes array\' "$PLIST"',
    '/usr/libexec/PlistBuddy -c \'Add :CFBundleURLTypes:0:CFBundleURLSchemes:0 string claudetask\' "$PLIST"',
    '',
    '/System/Library/Frameworks/CoreServices.framework/Frameworks/LaunchServices.framework/Support/lsregister \\',
    '    -f /Applications/ClaudeTask.app',
].join('\n');

function CodeBlock({ code, strings, wrap = false }) {
    const [copied, setCopied] = useState(false);

    useEffect(() => {
        if (! copied) return;
        const timer = setTimeout(() => setCopied(false), 1500);
        return () => clearTimeout(timer);
    }, [copied]);

    const copy = () => {
        navigator.clipboard?.writeText(code).then(() => setCopied(true)).catch(() => {});
    };

    return (
        <div className="relative mt-2">
            <button
                type="button"
                onClick={copy}
                className="absolute right-2 top-2 rounded bg-white/10 px-2 py-1 text-[11px] font-semibold text-gray-200 hover:bg-white/20"
            >
                {copied ? strings.copied : strings.copy}
            </button>
            <pre
                className={[
                    'overflow-x-auto rounded-lg bg-gray-900 p-4 pr-20 text-[12px] leading-relaxed text-gray-100',
                    // Prompt-Blöcke sind Prosa und dürfen umbrechen; Kommandos nicht.
                    wrap ? 'whitespace-pre-wrap' : '',
                ].join(' ')}
            >
                <code>{code}</code>
            </pre>
        </div>
    );
}

export default function ClaudetaskSetup({ strings }) {
    const card = 'bg-white dark:bg-gray-800 rounded-lg shadow p-6';
    const h3 = 'font-semibold text-gray-800 dark:text-gray-100';
    const p = 'mt-2 text-sm text-gray-600 dark:text-gray-400 leading-relaxed';
    const step = 'mt-5 text-sm text-gray-600 dark:text-gray-400 leading-relaxed';

    // Windows zuerst, auf einem Mac die macOS-Karte zuerst — spart Scrollen.
    const [macFirst, setMacFirst] = useState(false);
    useEffect(() => {
        if (navigator.platform && /Mac/i.test(navigator.platform)) setMacFirst(true);
    }, []);

    // Jede Plattform-Anleitung beginnt mit dem Claude-Prompt, der die Einrichtung
    // erledigt; die Einzelschritte darunter sind der manuelle Weg.
    const quickSetup = (prompt) => (
        <div className="mt-1 rounded-md bg-indigo-50 dark:bg-indigo-900/20 p-4">
            <p className="text-sm font-semibold text-indigo-900 dark:text-indigo-200">{strings.quickSetupHeading}</p>
            <p className="mt-1 text-xs text-indigo-800/80 dark:text-indigo-300/80 leading-relaxed">{strings.quickSetupIntro}</p>
            <CodeBlock code={prompt} strings={strings} wrap />
        </div>
    );

    const windows = (
        <div className={card} key="windows">
            <h3 className={h3}>{strings.windowsHeading}</h3>

            {quickSetup(strings.setupPromptWin)}

            <p className={step}>{strings.windowsStep1}</p>
            <CodeBlock code={WIN_SCRIPT} strings={strings} />

            <p className={step}>{strings.windowsStep2}</p>
            <CodeBlock code={WIN_REGISTER} strings={strings} />

            <p className={step}>{strings.windowsStep3}</p>
            <CodeBlock code={WIN_TEST} strings={strings} />
        </div>
    );

    const mac = (
        <div className={card} key="mac">
            <h3 className={h3}>{strings.macHeading}</h3>

            {quickSetup(strings.setupPromptMac)}

            <p className={step}>{strings.macStep1}</p>
            <CodeBlock code={MAC_SCRIPT} strings={strings} />

            <p className={step}>{strings.macStep2}</p>
            <CodeBlock code={MAC_APPLESCRIPT} strings={strings} />

            <p className={step}>{strings.macStep3}</p>
            <CodeBlock code={MAC_PLIST} strings={strings} />

            <p className={step}>{strings.macStep4}</p>
        </div>
    );

    return (
        <>
            <Head><title>{strings.title}</title></Head>

            <PageBands
                header={<h2 className="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">{strings.title}</h2>}
            />

            <div className="py-8">
                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

                    <div className={card}>
                        <h3 className={h3}>{strings.introHeading}</h3>
                        <p className={p}>{strings.intro1}</p>
                        <p className={p}>{strings.intro2}</p>
                        <p className="mt-3 text-xs text-gray-400 dark:text-gray-500">{strings.prerequisite}</p>
                    </div>

                    {macFirst ? [mac, windows] : [windows, mac]}

                    <div className={card}>
                        <h3 className={h3}>{strings.autoModeHeading}</h3>

                        <p className={step}>{strings.autoMode1}</p>
                        <CodeBlock code={WIN_AUTO_LINE} strings={strings} />

                        <p className={step}>{strings.autoMode2}</p>
                        <CodeBlock code={WIN_AUTO_DIRECT} strings={strings} />

                        <p className="mt-4 rounded-md bg-amber-50 dark:bg-amber-900/20 px-3 py-2 text-xs text-amber-700 dark:text-amber-400">
                            {strings.autoModeWarn}
                        </p>
                    </div>

                    <div className={card}>
                        <h3 className={h3}>{strings.troubleshootHeading}</h3>
                        <ul className="mt-3 space-y-2 text-sm text-gray-600 dark:text-gray-400 list-disc ps-5">
                            <li>{strings.troubleshoot1}</li>
                            <li>{strings.troubleshoot2}</li>
                            <li>{strings.troubleshoot3}</li>
                            <li>{strings.troubleshoot4}</li>
                        </ul>
                    </div>

                </div>
            </div>
        </>
    );
}

ClaudetaskSetup.layout = AppShell;
