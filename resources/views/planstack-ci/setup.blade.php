@php $ciVersion = config('planstack_ci.version'); @endphp
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Planstack CI-Status — Einrichtung</title>
<style>
  :root { --bg:#f6f7f9; --card:#fff; --ink:#1f2328; --muted:#57606a; --line:#e5e7eb;
          --brand:#4338ca; --ok:#1a7f37; --code:#0d1117; }
  * { box-sizing: border-box; }
  body { margin:0; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
         background:var(--bg); color:var(--ink); line-height:1.55; }
  .wrap { max-width:820px; margin:0 auto; padding:32px 20px 80px; }
  a.back { color:var(--muted); text-decoration:none; font-size:.9rem; }
  a.back:hover { color:var(--ink); }
  h1 { font-size:1.7rem; margin:12px 0 4px; }
  h2 { font-size:1.15rem; margin:28px 0 10px; }
  .sub { color:var(--muted); margin:0 0 24px; }
  .card { background:var(--card); border:1px solid var(--line); border-radius:12px; padding:20px 22px; margin:16px 0; }
  .status { display:flex; align-items:center; gap:10px; font-weight:600; }
  .dot { width:10px; height:10px; border-radius:50%; background:#9ca3af; }
  .dot.up { background:var(--ok); } .dot.down { background:#cf222e; }
  ol { padding-left:20px; } li { margin:8px 0; }
  code, kbd { font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; font-size:.88em; }
  code { background:#eef0f3; padding:1px 6px; border-radius:5px; }
  pre { background:var(--code); color:#e6edf3; padding:14px 16px; border-radius:10px; overflow:auto; font-size:.84rem; }
  pre code { background:none; padding:0; color:inherit; }
  .btns { display:flex; flex-wrap:wrap; gap:10px; margin:6px 0 2px; }
  a.btn { display:inline-flex; align-items:center; gap:8px; text-decoration:none; font-weight:600; font-size:.9rem;
          padding:9px 16px; border-radius:8px; background:var(--brand); color:#fff; }
  a.btn.sec { background:#fff; color:var(--brand); border:1px solid var(--brand); }
  .tabs { display:flex; gap:6px; margin:8px 0 0; }
  .tab { cursor:pointer; padding:8px 16px; border:1px solid var(--line); border-bottom:none;
         border-radius:8px 8px 0 0; background:#eef0f3; font-weight:600; font-size:.9rem; }
  .tab.active { background:var(--card); color:var(--brand); }
  .panel { display:none; border:1px solid var(--line); border-radius:0 12px 12px 12px; padding:18px 20px; background:var(--card); }
  .panel.active { display:block; }
  .muted { color:var(--muted); font-size:.9rem; }
  .kbd { background:#eef0f3; border:1px solid var(--line); border-bottom-width:2px; border-radius:5px; padding:1px 6px; }
</style>
</head>
<body>
<div class="wrap">
  <a class="back" href="{{ url()->previous() !== url()->current() ? url()->previous() : '/' }}">← zurück</a>
  <h1>Planstack CI-Status <span class="muted" style="font-size:.9rem">v{{ $ciVersion }}</span></h1>
  <p class="sub">Zeigt je PR-Knoten im Planstack-Diagramm den echten CI-/Merge-Status (✓ / ✗ / x/x Steps, „ready to merge" …). Die Daten kommen über deine lokale GitHub-CLI — ganz ohne Token im Browser.</p>

  <div class="card">
    <div class="status"><span id="dot" class="dot"></span><span id="statustext">Lokaler Server wird geprüft…</span></div>
    <p class="muted" id="statushint" style="margin:8px 0 0"></p>
  </div>

  <h2>1 · Was du brauchst</h2>
  <div class="card">
    <ol>
      <li><b>Node.js</b> (LTS) — <a href="https://nodejs.org/" target="_blank" rel="noopener">nodejs.org</a></li>
      <li><b>GitHub CLI</b> (<code>gh</code>) — <a href="https://cli.github.com/" target="_blank" rel="noopener">cli.github.com</a>, danach einmalig anmelden:
        <pre><code>gh auth login</code></pre>
      </li>
      <li><b>Tampermonkey</b>-Browser-Erweiterung — <a href="https://www.tampermonkey.net/" target="_blank" rel="noopener">tampermonkey.net</a></li>
    </ol>
  </div>

  <h2>2 · Downloads</h2>
  <div class="card">
    <div class="btns">
      <a class="btn" href="{{ asset('planstack-ci/planstack-ci.user.min.js') }}">⬇ Userscript installieren</a>
      <a class="btn sec" href="{{ asset('planstack-ci/ci-server.cjs') }}" download>⬇ ci-server.cjs</a>
    </div>
    <p class="muted" style="margin-top:12px">Userscript-Link öffnen → Tampermonkey zeigt „Installieren". <code>ci-server.cjs</code> speichern (z.&nbsp;B. Windows <code>%USERPROFILE%\planstack\</code>, Mac <code>~/planstack/</code>).</p>
  </div>

  <h2>3 · Lokalen Server einrichten</h2>
  <div class="tabs">
    <div class="tab active" data-tab="win">Windows</div>
    <div class="tab" data-tab="mac">macOS</div>
  </div>

  <div class="panel active" id="win">
    <ol>
      <li>Node.js &amp; GitHub CLI installieren, dann <code>gh auth login</code> ausführen.</li>
      <li><code>ci-server.cjs</code> herunterladen, z.&nbsp;B. nach <code>%USERPROFILE%\planstack\ci-server.cjs</code>.</li>
      <li>Server testen (PowerShell):
        <pre><code>node "$env:USERPROFILE\planstack\ci-server.cjs"</code></pre>
        Erwartet: <code>[ci-server] v{{ $ciVersion }} läuft auf http://127.0.0.1:8757</code>. Mit <kbd class="kbd">Strg</kbd>+<kbd class="kbd">C</kbd> beenden.</li>
      <li><b>Autostart bei jeder Anmeldung</b> (kein Admin nötig) — in PowerShell einfügen:
        <pre><code>$node = (Get-Command node).Source
$srv  = "$env:USERPROFILE\planstack\ci-server.cjs"
$vbs  = Join-Path ([Environment]::GetFolderPath('Startup')) 'PlanstackCiServer.vbs'
Set-Content $vbs -Encoding ASCII -Value @"
Set sh = CreateObject("WScript.Shell")
sh.Run """$node"" ""$srv""", 0, False
"@
wscript $vbs   # sofort starten</code></pre>
        Startet ab jetzt versteckt bei jedem Login. Entfernen: <code>PlanstackCiServer.vbs</code> aus dem Autostart-Ordner löschen (<code>explorer shell:startup</code>).</li>
    </ol>
  </div>

  <div class="panel" id="mac">
    <ol>
      <li>Node.js &amp; GitHub CLI installieren, dann anmelden:
        <pre><code>brew install node gh
gh auth login</code></pre></li>
      <li><code>ci-server.cjs</code> herunterladen, z.&nbsp;B. nach <code>~/planstack/ci-server.cjs</code>.</li>
      <li>Server testen:
        <pre><code>node ~/planstack/ci-server.cjs</code></pre>
        Erwartet: <code>[ci-server] v{{ $ciVersion }} läuft auf http://127.0.0.1:8757</code>. Mit <kbd class="kbd">Ctrl</kbd>+<kbd class="kbd">C</kbd> beenden.</li>
      <li><b>Autostart beim Login</b> via LaunchAgent — im Terminal ausführen:
        <pre><code>mkdir -p ~/Library/LaunchAgents
cat > ~/Library/LaunchAgents/net.planstack.ciserver.plist <<'PLIST'
&lt;?xml version="1.0" encoding="UTF-8"?&gt;
&lt;!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd"&gt;
&lt;plist version="1.0"&gt;&lt;dict&gt;
  &lt;key&gt;Label&lt;/key&gt;&lt;string&gt;net.planstack.ciserver&lt;/string&gt;
  &lt;key&gt;ProgramArguments&lt;/key&gt;
  &lt;array&gt;&lt;string&gt;/usr/bin/env&lt;/string&gt;&lt;string&gt;node&lt;/string&gt;&lt;string&gt;$HOME/planstack/ci-server.cjs&lt;/string&gt;&lt;/array&gt;
  &lt;key&gt;RunAtLoad&lt;/key&gt;&lt;true/&gt;
  &lt;key&gt;KeepAlive&lt;/key&gt;&lt;true/&gt;
&lt;/dict&gt;&lt;/plist&gt;
PLIST
launchctl load ~/Library/LaunchAgents/net.planstack.ciserver.plist</code></pre>
        Läuft ab jetzt automatisch. Entfernen: <code>launchctl unload</code> + plist löschen.</li>
    </ol>
  </div>

  <h2>4 · Fertig</h2>
  <div class="card">
    <p style="margin:0">Lade die Diagramm-Seite neu. An jedem PR-Knoten erscheint jetzt der CI-/Merge-Status; der Hinweis über dem Diagramm verschwindet automatisch. Bei einer neuen Version meldet Tampermonkey das Update automatisch (bzw. der Hinweisbalken zeigt „Aktualisieren").</p>
  </div>
</div>

<script>
  document.querySelectorAll('.tab').forEach(function (t) {
    t.addEventListener('click', function () {
      document.querySelectorAll('.tab').forEach(function (x) { x.classList.remove('active'); });
      document.querySelectorAll('.panel').forEach(function (x) { x.classList.remove('active'); });
      t.classList.add('active');
      document.getElementById(t.dataset.tab).classList.add('active');
    });
  });
  // macOS-Standard einblenden, wenn kein Windows
  if (navigator.platform && /Mac/i.test(navigator.platform)) {
    document.querySelector('.tab[data-tab="mac"]').click();
  }
  fetch('http://127.0.0.1:8757/version').then(function (r) { return r.json(); }).then(function (d) {
    document.getElementById('dot').className = 'dot up';
    document.getElementById('statustext').textContent = 'Lokaler Server läuft (v' + d.version + ')';
    document.getElementById('statushint').textContent = 'Alles bereit — die Diagramm-Seite zeigt jetzt CI-Status.';
  }).catch(function () {
    document.getElementById('dot').className = 'dot down';
    document.getElementById('statustext').textContent = 'Lokaler Server nicht erreichbar';
    document.getElementById('statushint').textContent = 'Folge den Schritten unten, dann diese Seite neu laden.';
  });
</script>
</body>
</html>
