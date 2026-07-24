import React, { useState, useEffect } from 'react';
import { Head } from '@inertiajs/react';
import AppShell from '../AppShell.jsx';
import PageBands from '../components/PageBands.jsx';

// Planstack-CI-Status-Einrichtung (ehemals planstack-ci/setup.blade.php). Reine
// Inhaltsseite; die Downloads (Userscript + ci-server.cjs) bleiben echte
// Nicht-Inertia-Links (data-native) auf die asset()-URLs. Das vormals per
// vanilla-<script> gelöste Verhalten (Tabs, Copy, Plattform-Erkennung,
// Server-Health-Check) ist hier in React abgebildet.

// Mehrzeilige Code-Snippets als String-Konstanten, damit <pre> die Formatierung
// exakt behält (JSX-Textknoten würden Zeilenumbrüche/Whitespace zusammenfalten).
const WIN_TEST = 'node "$env:USERPROFILE\\planstack\\ci-server.cjs"';

const WIN_AUTOSTART = String.raw`$node = (Get-Command node).Source
$srv  = "$env:USERPROFILE\planstack\ci-server.cjs"
$vbs  = Join-Path ([Environment]::GetFolderPath('Startup')) 'PlanstackCiServer.vbs'
Set-Content $vbs -Encoding ASCII -Value @"
Set sh = CreateObject("WScript.Shell")
sh.Run """$node"" ""$srv""", 0, False
"@
wscript $vbs   # sofort starten`;

const MAC_INSTALL = 'brew install node gh\ngh auth login';

const MAC_PLIST = `mkdir -p ~/Library/LaunchAgents
cat > ~/Library/LaunchAgents/net.planstack.ciserver.plist <<'PLIST'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0"><dict>
  <key>Label</key><string>net.planstack.ciserver</string>
  <key>ProgramArguments</key>
  <array><string>/usr/bin/env</string><string>node</string><string>$HOME/planstack/ci-server.cjs</string></array>
  <key>RunAtLoad</key><true/>
  <key>KeepAlive</key><true/>
</dict></plist>
PLIST
launchctl load ~/Library/LaunchAgents/net.planstack.ciserver.plist`;

export default function PlanstackCiSetup({ ciVersion, urls, strings }) {
    // Manuell-Tabs (Abschnitt 4) und Quick-Setup-Tabs (Abschnitt 3) — eigene Gruppen.
    const [tab, setTab] = useState('win');
    const [qtab, setQtab] = useState('qwin');
    const [copiedKey, setCopiedKey] = useState(null);
    // Server-Health: 'checking' | 'up' | 'down' (+ version bei 'up').
    const [server, setServer] = useState({ state: 'checking', version: '' });

    // macOS-Standard einblenden, wenn keine Windows-Plattform.
    useEffect(() => {
        if (navigator.platform && /Mac/i.test(navigator.platform)) {
            setTab('mac');
            setQtab('qmac');
        }
    }, []);

    // Lokalen Server abfragen; Fehler = offline.
    useEffect(() => {
        let alive = true;
        fetch('http://127.0.0.1:8757/version')
            .then((r) => r.json())
            .then((d) => { if (alive) setServer({ state: 'up', version: d.version }); })
            .catch(() => { if (alive) setServer({ state: 'down', version: '' }); });
        return () => { alive = false; };
    }, []);

    const copy = (key, text) => {
        navigator.clipboard.writeText(String(text).trim()).then(() => {
            setCopiedKey(key);
            setTimeout(() => setCopiedKey((k) => (k === key ? null : k)), 1500);
        });
    };

    const dotClass = 'dot' + (server.state === 'up' ? ' up' : server.state === 'down' ? ' down' : '');
    const statusText =
        server.state === 'up' ? `${strings.localServerIsRunningV}${server.version})`
        : server.state === 'down' ? strings.localServerUnreachable
        : strings.checkingLocalServer;
    const statusHint =
        server.state === 'up' ? strings.allSetDiagramShowsCi
        : server.state === 'down' ? strings.followTheStepsBelow
        : '';

    return (
        <>
            <Head><title>{strings.title}</title></Head>

            <PageBands
                header={
                    <h2 className="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">
                        {strings.title}
                        <span className="ms-1 text-sm font-normal text-gray-400 dark:text-gray-500">v{ciVersion}</span>
                    </h2>
                }
            />

            {/* Styles bewusst unter .psci gescoped, damit sie das App-Layout
                (Navi/Header) nicht beeinflussen. */}
            <style>{`
      .psci { --card:#fff; --ink:#1f2328; --muted:#57606a; --line:#e5e7eb;
              --brand:#4338ca; --ok:#1a7f37; --code:#0d1117;
              color:var(--ink); line-height:1.55; }
      .psci * { box-sizing:border-box; }
      .psci h2 { font-size:1.15rem; margin:28px 0 10px; }
      .psci .sub { color:var(--muted); margin:0 0 24px; }
      .psci .card { background:var(--card); border:1px solid var(--line); border-radius:12px; padding:20px 22px; margin:16px 0; }
      .psci .status { display:flex; align-items:center; gap:10px; font-weight:600; }
      .psci .dot { width:10px; height:10px; border-radius:50%; background:#9ca3af; }
      .psci .dot.up { background:var(--ok); } .psci .dot.down { background:#cf222e; }
      .psci ol { padding-left:20px; } .psci li { margin:8px 0; }
      .psci code, .psci kbd { font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; font-size:.88em; }
      .psci code { background:#eef0f3; padding:1px 6px; border-radius:5px; }
      .psci pre { background:var(--code); color:#e6edf3; padding:14px 16px; border-radius:10px; overflow:auto; font-size:.84rem; }
      .psci pre code { background:none; padding:0; color:inherit; }
      .psci .btns { display:flex; flex-wrap:wrap; gap:10px; margin:6px 0 2px; }
      .psci a.btn { display:inline-flex; align-items:center; gap:8px; text-decoration:none; font-weight:600; font-size:.9rem;
              padding:9px 16px; border-radius:8px; background:var(--brand); color:#fff; }
      .psci a.btn.sec { background:#fff; color:var(--brand); border:1px solid var(--brand); }
      .psci .tabs { display:flex; gap:6px; margin:8px 0 0; }
      .psci .tab { cursor:pointer; padding:8px 16px; border:1px solid var(--line); border-bottom:none;
             border-radius:8px 8px 0 0; background:#eef0f3; font-weight:600; font-size:.9rem; }
      .psci .tab.active { background:var(--card); color:var(--brand); }
      .psci .panel { display:none; border:1px solid var(--line); border-radius:0 12px 12px 12px; padding:18px 20px; background:var(--card); }
      .psci .panel.active { display:block; }
      .psci .muted { color:var(--muted); font-size:.9rem; }
      .psci .kbd { background:#eef0f3; border:1px solid var(--line); border-bottom-width:2px; border-radius:5px; padding:1px 6px; }
      .psci .qtabs { display:flex; gap:6px; margin:12px 0 0; }
      .psci .qtab { cursor:pointer; padding:7px 14px; border:1px solid var(--line); border-bottom:none; border-radius:8px 8px 0 0; background:#eef0f3; font-weight:600; font-size:.85rem; }
      .psci .qtab.active { background:var(--card); color:var(--brand); }
      .psci .qpanel { display:none; border:1px solid var(--line); border-radius:0 10px 10px 10px; padding:14px 16px; background:var(--card); }
      .psci .qpanel.active { display:block; }
      .psci .qpanel pre { margin:0; white-space:pre-wrap; }
      .psci .copybtn { float:right; cursor:pointer; font-size:.8rem; font-weight:600; color:var(--brand);
                 background:#fff; border:1px solid var(--brand); border-radius:6px; padding:4px 10px; margin:0 0 8px 8px; }
      .psci .copybtn:hover { background:#eef2ff; }

      /* Dark-Mode: gedaempfte Dunkelwerte, Basisregeln unangetastet */
      .dark .psci { --card:#1f2937; --ink:#f3f4f6; --muted:#9ca3af; --line:#374151;
              --brand:#6366f1; --ok:#3fb950; --code:#0d1117; }
      .dark .psci .dot { background:#6b7280; }
      .dark .psci .dot.down { background:#f85149; }
      .dark .psci code { background:#30363d; color:#e6edf3; }
      .dark .psci pre code { background:none; color:inherit; }
      .dark .psci a.btn.sec { background:transparent; }
      .dark .psci .tab { background:#111827; }
      .dark .psci .qtab { background:#111827; }
      .dark .psci .kbd { background:#111827; }
      .dark .psci .copybtn { background:transparent; }
      .dark .psci .copybtn:hover { background:#312e81; }
            `}</style>

            <div className="psci max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <p className="sub">{strings.sub}</p>

                <div className="card">
                    <div className="status"><span className={dotClass}></span><span>{statusText}</span></div>
                    <p className="muted" style={{ margin: '8px 0 0' }}>{statusHint}</p>
                </div>

                <h2>1 · {strings.whatYouNeed}</h2>
                <div className="card">
                    <ol>
                        <li><b>Node.js</b> (LTS) — <a href="https://nodejs.org/" target="_blank" rel="noopener">nodejs.org</a></li>
                        <li><b>GitHub CLI</b> (<code>gh</code>) — <a href="https://cli.github.com/" target="_blank" rel="noopener">cli.github.com</a>, {strings.thenSignInOnce}
                            <pre><code>gh auth login</code></pre>
                        </li>
                        <li><b>Tampermonkey</b>{strings.browserExtension} — <a href="https://www.tampermonkey.net/" target="_blank" rel="noopener">tampermonkey.net</a></li>
                    </ol>
                </div>

                <h2>2 · {strings.downloads}</h2>
                <div className="card">
                    <div className="btns">
                        <a className="btn" href={urls.userscript} data-native>⬇ {strings.installTheUserscript}</a>
                        <a className="btn sec" href={urls.ciServer} data-native download>⬇ ci-server.cjs</a>
                    </div>
                    <p className="muted" style={{ marginTop: '12px' }}>{strings.openTheUserscriptLink} <code>ci-server.cjs</code> {strings.saveItEg} <code>%USERPROFILE%\planstack\</code>, {strings.mac} <code>~/planstack/</code>).</p>
                </div>

                <h2>3 · {strings.quickSetup} <span className="muted" style={{ fontSize: '.9rem' }}>{strings.recommended}</span></h2>
                <div className="card">
                    <p className="muted" style={{ marginTop: 0 }}>{strings.doYouHave} <a href="https://claude.com/claude-code" target="_blank" rel="noopener">Claude Code</a>{strings.copyTheMatchingPrompt} <code>gh auth login</code> {strings.youDoYourselfOnce}</p>
                    <div className="qtabs">
                        <div className={'qtab' + (qtab === 'qwin' ? ' active' : '')} onClick={() => setQtab('qwin')}>Windows</div>
                        <div className={'qtab' + (qtab === 'qmac' ? ' active' : '')} onClick={() => setQtab('qmac')}>macOS</div>
                    </div>

                    <div className={'qpanel' + (qtab === 'qwin' ? ' active' : '')}>
                        <button type="button" className="copybtn" onClick={() => copy('qwin', strings.setupPromptWin)}>{copiedKey === 'qwin' ? strings.copied : strings.copyPrompt}</button>
                        <pre><code>{strings.setupPromptWin}</code></pre>
                    </div>

                    <div className={'qpanel' + (qtab === 'qmac' ? ' active' : '')}>
                        <button type="button" className="copybtn" onClick={() => copy('qmac', strings.setupPromptMac)}>{copiedKey === 'qmac' ? strings.copied : strings.copyPrompt}</button>
                        <pre><code>{strings.setupPromptMac}</code></pre>
                    </div>
                    <p className="muted" style={{ marginTop: '12px' }}>{strings.thenInstallThe} <b>Userscript</b> {strings.fromStep2InTampermonkey}</p>
                </div>

                <h2>4 · {strings.setUpManually} <span className="muted" style={{ fontSize: '.9rem' }}>{strings.alternative}</span></h2>
                <div className="tabs">
                    <div className={'tab' + (tab === 'win' ? ' active' : '')} onClick={() => setTab('win')}>Windows</div>
                    <div className={'tab' + (tab === 'mac' ? ' active' : '')} onClick={() => setTab('mac')}>macOS</div>
                </div>

                <div className={'panel' + (tab === 'win' ? ' active' : '')}>
                    <ol>
                        <li>{strings.installNodeGhThen} <code>gh auth login</code> {strings.run}</li>
                        <li><code>ci-server.cjs</code> {strings.downloadItEgTo} <code>%USERPROFILE%\planstack\ci-server.cjs</code>.</li>
                        <li>{strings.testTheServerPowershell}
                            <pre><code>{WIN_TEST}</code></pre>
                            {strings.expected} <code>[ci-server] v{ciVersion} läuft auf http://127.0.0.1:8757</code>. {strings.with} <kbd className="kbd">{strings.ctrl}</kbd>+<kbd className="kbd">C</kbd> {strings.quit}</li>
                        <li><b>{strings.autostartOnEveryLogin}</b> {strings.noAdminRequired}
                            <pre><code>{WIN_AUTOSTART}</code></pre>
                            {strings.itNowStartsHidden} <code>PlanstackCiServer.vbs</code> {strings.fromTheStartupFolder}<code>explorer shell:startup</code>).</li>
                    </ol>
                </div>

                <div className={'panel' + (tab === 'mac' ? ' active' : '')}>
                    <ol>
                        <li>{strings.installNodeGhThenSignIn}
                            <pre><code>{MAC_INSTALL}</code></pre></li>
                        <li><code>ci-server.cjs</code> {strings.downloadItEgTo} <code>~/planstack/ci-server.cjs</code>.</li>
                        <li>{strings.testTheServer}
                            <pre><code>node ~/planstack/ci-server.cjs</code></pre>
                            {strings.expected} <code>[ci-server] v{ciVersion} läuft auf http://127.0.0.1:8757</code>. {strings.with} <kbd className="kbd">Ctrl</kbd>+<kbd className="kbd">C</kbd> {strings.quit}</li>
                        <li><b>{strings.autostartAtLogin}</b> {strings.viaLaunchAgent}
                            <pre><code>{MAC_PLIST}</code></pre>
                            {strings.itNowRunsAutomatically} <code>launchctl unload</code> {strings.deleteThePlist}</li>
                    </ol>
                </div>

                <h2>5 · {strings.done}</h2>
                <div className="card">
                    <p style={{ margin: 0 }}>{strings.reloadTheDiagramPage}</p>
                </div>
            </div>
        </>
    );
}

PlanstackCiSetup.layout = AppShell;
