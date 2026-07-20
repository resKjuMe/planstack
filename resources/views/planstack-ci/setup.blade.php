@php $ciVersion = config('planstack_ci.version'); @endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('ci.planstack_ci_status') }}
            <span class="ms-1 text-sm font-normal text-gray-400">v{{ $ciVersion }}</span>
        </h2>
    </x-slot>

    {{-- Styles bewusst unter .psci gescoped, damit sie das App-Layout (Navi/Header)
         nicht beeinflussen. --}}
    <style>
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
    </style>

    <div class="psci max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <p class="sub">{{ __('ci.shows_the_real_ci_merge_status_on_each') }}</p>

      <div class="card">
        <div class="status"><span id="dot" class="dot"></span><span id="statustext">{{ __('ci.checking_local_server') }}</span></div>
        <p class="muted" id="statushint" style="margin:8px 0 0"></p>
      </div>

      <h2>1 · {{ __('ci.what_you_need') }}</h2>
      <div class="card">
        <ol>
          <li><b>Node.js</b> (LTS) — <a href="https://nodejs.org/" target="_blank" rel="noopener">nodejs.org</a></li>
          <li><b>GitHub CLI</b> (<code>gh</code>) — <a href="https://cli.github.com/" target="_blank" rel="noopener">cli.github.com</a>, {{ __('ci.then_sign_in_once') }}
            <pre><code>gh auth login</code></pre>
          </li>
          <li><b>Tampermonkey</b>{{ __('ci.browser_extension') }} — <a href="https://www.tampermonkey.net/" target="_blank" rel="noopener">tampermonkey.net</a></li>
        </ol>
      </div>

      <h2>2 · {{ __('ci.downloads') }}</h2>
      <div class="card">
        <div class="btns">
          <a class="btn" href="{{ asset('planstack-ci/planstack-ci.user.min.js') }}">⬇ {{ __('ci.install_the_userscript') }}</a>
          <a class="btn sec" href="{{ asset('planstack-ci/ci-server.cjs') }}" download>⬇ ci-server.cjs</a>
        </div>
        <p class="muted" style="margin-top:12px">{{ __('ci.open_the_userscript_link_tampermonkey') }} <code>ci-server.cjs</code> {!! __('ci.save_it_e_g_nbsp_windows') !!} <code>%USERPROFILE%\planstack\</code>, {{ __('ci.mac') }} <code>~/planstack/</code>).</p>
      </div>

      <h2>3 · {{ __('ci.quick_setup_with_claude_code') }} <span class="muted" style="font-size:.9rem">{{ __('ci.recommended') }}</span></h2>
      <div class="card">
        <p class="muted" style="margin-top:0">{{ __('ci.do_you_have') }} <a href="https://claude.com/claude-code" target="_blank" rel="noopener">Claude Code</a>{{ __('ci.copy_the_matching_prompt_into_a_claude') }} <code>gh auth login</code> {{ __('ci.you_do_yourself_once_interactively') }}</p>
        <div class="qtabs">
          <div class="qtab active" data-qtab="qwin">Windows</div>
          <div class="qtab" data-qtab="qmac">macOS</div>
        </div>

        <div class="qpanel active" id="qwin">
          <button type="button" class="copybtn" data-copy="#qwin-prompt">{{ __('ci.copy_prompt') }}</button>
<pre><code id="qwin-prompt">{{ __('ci.setup_prompt_win', ['asset' => asset('planstack-ci/ci-server.cjs'), 'version' => $ciVersion]) }}</code></pre>
        </div>

        <div class="qpanel" id="qmac">
          <button type="button" class="copybtn" data-copy="#qmac-prompt">{{ __('ci.copy_prompt') }}</button>
<pre><code id="qmac-prompt">{{ __('ci.setup_prompt_mac', ['asset' => asset('planstack-ci/ci-server.cjs'), 'version' => $ciVersion]) }}</code></pre>
        </div>
        <p class="muted" style="margin-top:12px">{{ __('ci.then_install_the') }} <b>Userscript</b> {{ __('ci.from_step_2_in_tampermonkey_done') }}</p>
      </div>

      <h2>4 · {{ __('ci.set_up_manually') }} <span class="muted" style="font-size:.9rem">{{ __('ci.alternative') }}</span></h2>
      <div class="tabs">
        <div class="tab active" data-tab="win">Windows</div>
        <div class="tab" data-tab="mac">macOS</div>
      </div>

      <div class="panel active" id="win">
        <ol>
          <li>{{ __('ci.install_node_js_github_cli_then') }} <code>gh auth login</code> {{ __('ci.run') }}</li>
          <li><code>ci-server.cjs</code> {!! __('ci.download_it_e_g_nbsp_to') !!} <code>%USERPROFILE%\planstack\ci-server.cjs</code>.</li>
          <li>{{ __('ci.test_the_server_powershell') }}
            <pre><code>node "$env:USERPROFILE\planstack\ci-server.cjs"</code></pre>
            {{ __('ci.expected') }} <code>[ci-server] v{{ $ciVersion }} läuft auf http://127.0.0.1:8757</code>. {{ __('ci.with') }} <kbd class="kbd">{{ __('ci.ctrl') }}</kbd>+<kbd class="kbd">C</kbd> {{ __('ci.quit') }}</li>
          <li><b>{{ __('ci.autostart_on_every_login') }}</b> {{ __('ci.no_admin_required_paste_into_powershell') }}
            <pre><code>$node = (Get-Command node).Source
$srv  = "$env:USERPROFILE\planstack\ci-server.cjs"
$vbs  = Join-Path ([Environment]::GetFolderPath('Startup')) 'PlanstackCiServer.vbs'
Set-Content $vbs -Encoding ASCII -Value @"
Set sh = CreateObject("WScript.Shell")
sh.Run """$node"" ""$srv""", 0, False
"@
wscript $vbs   # sofort starten</code></pre>
            {{ __('ci.it_now_starts_hidden_at_every_login_to') }} <code>PlanstackCiServer.vbs</code> {{ __('ci.from_the_startup_folder') }}<code>explorer shell:startup</code>).</li>
        </ol>
      </div>

      <div class="panel" id="mac">
        <ol>
          <li>{{ __('ci.install_node_js_github_cli_then_sign_in') }}
            <pre><code>brew install node gh
gh auth login</code></pre></li>
          <li><code>ci-server.cjs</code> {!! __('ci.download_it_e_g_nbsp_to') !!} <code>~/planstack/ci-server.cjs</code>.</li>
          <li>{{ __('ci.test_the_server') }}
            <pre><code>node ~/planstack/ci-server.cjs</code></pre>
            {{ __('ci.expected') }} <code>[ci-server] v{{ $ciVersion }} läuft auf http://127.0.0.1:8757</code>. {{ __('ci.with') }} <kbd class="kbd">Ctrl</kbd>+<kbd class="kbd">C</kbd> {{ __('ci.quit') }}</li>
          <li><b>{{ __('ci.autostart_at_login') }}</b> {{ __('ci.via_launchagent_run_in_the_terminal') }}
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
            {{ __('ci.it_now_runs_automatically_to_remove') }} <code>launchctl unload</code> {{ __('ci.delete_the_plist') }}</li>
        </ol>
      </div>

      <h2>5 · {{ __('ci.done') }}</h2>
      <div class="card">
        <p style="margin:0">{{ __('ci.reload_the_diagram_page_the_ci_merge') }}</p>
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
      // Quick-Setup-Tabs (Claude-Code-Prompts) — eigene Gruppe
      document.querySelectorAll('.qtab').forEach(function (t) {
        t.addEventListener('click', function () {
          document.querySelectorAll('.qtab').forEach(function (x) { x.classList.remove('active'); });
          document.querySelectorAll('.qpanel').forEach(function (x) { x.classList.remove('active'); });
          t.classList.add('active');
          document.getElementById(t.dataset.qtab).classList.add('active');
        });
      });
      // {{ __('ci.copy_prompt') }}
      document.querySelectorAll('.copybtn').forEach(function (b) {
        b.addEventListener('click', function () {
          var el = document.querySelector(b.dataset.copy);
          if (!el) return;
          navigator.clipboard.writeText(el.textContent.trim()).then(function () {
            var prev = b.textContent; b.textContent = @js(__('common.copied'));
            setTimeout(function () { b.textContent = prev; }, 1500);
          });
        });
      });
      // macOS-Standard einblenden, wenn kein Windows
      if (navigator.platform && /Mac/i.test(navigator.platform)) {
        document.querySelector('.tab[data-tab="mac"]').click();
        var qm = document.querySelector('.qtab[data-qtab="qmac"]'); if (qm) qm.click();
      }
      fetch('http://127.0.0.1:8757/version').then(function (r) { return r.json(); }).then(function (d) {
        document.getElementById('dot').className = 'dot up';
        document.getElementById('statustext').textContent = @js(__('ci.local_server_is_running_v')) + d.version + ')';
        document.getElementById('statushint').textContent = @js(__('ci.all_set_the_diagram_page_now_shows_ci'));
      }).catch(function () {
        document.getElementById('dot').className = 'dot down';
        document.getElementById('statustext').textContent = @js(__('ci.local_server_unreachable'));
        document.getElementById('statushint').textContent = @js(__('ci.follow_the_steps_below_then_reload_this'));
      });
    </script>
</x-app-layout>
