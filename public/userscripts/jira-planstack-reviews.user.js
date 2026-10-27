// ==UserScript==
// @name         Jira-Planstack-Reviews
// @namespace    https://planstack.eskju.net/
// @version      2026-10-27.1
// @description  Erweitert „Jira-GIT-Enrichment“: je PR-Zeile ein Claude-Icon, das /ps-review für den PR startet (claudetask:-Protokoll). Der Hintergrund zeigt den eigenen Review-Stand aus Planstack (rot keiner, grün aktuell, orange veraltet, violett läuft) – ohne ci-server und ohne lokale Report-Dateien.
// @author       Christian Mietze <christian.mietze@clockodo.com>
// @match        https://clickbits.atlassian.net/*
// @icon         https://planstack.eskju.net/favicon.svg
// @grant        GM_xmlhttpRequest
// @grant        GM_getValue
// @grant        GM_setValue
// @grant        GM_registerMenuCommand
// @connect      planstack.eskju.net
// @updateURL    https://planstack.eskju.net/userscripts/jira-planstack-reviews.user.js
// @downloadURL  https://planstack.eskju.net/userscripts/jira-planstack-reviews.user.js
// @run-at       document-idle
// ==/UserScript==

(function () {
    'use strict';

    // Zeilen kommen vom Script „Jira-GIT-Enrichment“ (.tm-pr-row mit data-pr-url usw.);
    // der Tooltip läuft über dessen data-tm-tip-Delegation.
    const ROW = '.tm-pr-row';
    const BTN_CLASS = 'tm-pr-planstack';
    const API = 'https://planstack.eskju.net/api';
    const REFRESH_MS = 60 * 1000;

    const CLAUDE_PATH_D = 'M4.709 15.955l4.72-2.647.08-.23-.08-.128H9.2l-.79-.048-2.698-.073-2.339-.097-2.266-.122-.571-.121L0 11.784l.055-.352.48-.321.686.06 1.52.103 2.278.158 1.652.097 2.449.255h.389l.055-.157-.134-.098-.103-.097-2.358-1.596-2.552-1.688-1.336-.972-.724-.491-.364-.462-.158-1.008.656-.722.881.06.225.061.893.686 1.908 1.476 2.491 1.833.365.304.145-.103.019-.073-.164-.274-1.355-2.446-1.446-2.49-.644-1.032-.17-.619a2.97 2.97 0 01-.104-.729L6.283.134 6.696 0l.996.134.42.364.62 1.414 1.002 2.229 1.555 3.03.456.898.243.832.091.255h.158V9.01l.128-1.706.237-2.095.23-2.695.08-.76.376-.91.747-.492.583.28.48.685-.067.444-.286 1.851-.559 2.903-.364 1.942h.212l.243-.242.985-1.306 1.652-2.064.73-.82.85-.904.547-.431h1.033l.76 1.129-.34 1.166-1.064 1.347-.881 1.142-1.264 1.7-.79 1.36.073.11.188-.02 2.856-.606 1.543-.28 1.841-.315.833.388.091.395-.328.807-1.969.486-2.309.462-3.439.813-.042.03.049.061 1.549.146.662.036h1.622l3.02.225.79.522.474.638-.079.485-1.215.62-1.64-.389-3.829-.91-1.312-.329h-.182v.11l1.093 1.068 2.006 1.81 2.509 2.33.127.578-.322.455-.34-.049-2.205-1.657-.851-.747-1.926-1.62h-.128v.17l.444.649 2.345 3.521.122 1.08-.17.353-.608.213-.668-.122-1.374-1.925-1.415-2.167-1.143-1.943-.14.08-.674 7.254-.316.37-.729.28-.607-.461-.322-.747.322-1.476.389-1.924.315-1.53.286-1.9.17-.632-.012-.042-.14.018-1.434 1.967-2.18 2.945-1.726 1.845-.414.164-.717-.37.067-.662.401-.589 2.388-3.036 1.44-1.882.93-1.086-.006-.158h-.055L4.132 18.56l-1.13.146-.487-.456.061-.746.231-.243 1.908-1.312-.006.006z';

    console.log('%c[JiraPlanstackReviews] geladen v' + GM_info.script.version, 'color:#4f46e5;font-weight:bold');

    // ===== Planstack-Token (Tampermonkey-Menü) ============================
    // Liegt nur im Tampermonkey-Speicher, nie im Code. Anlegen unter Planstack → Profil → API-Token.
    let token = GM_getValue('planstackToken', '');

    GM_registerMenuCommand('Planstack-Token ' + (token ? 'ändern' : 'setzen'), function () {
        const value = window.prompt('Planstack-API-Token (Planstack → Profil → API-Token):', '');
        if (value === null) return;
        GM_setValue('planstackToken', value.trim());
        window.location.reload();
    });

    const STATE = {
        none: { bg: '#c9372c', tip: 'Noch kein Review in Planstack' },
        current: { bg: '#1f845a', tip: 'Review aktuell' },
        outdated: { bg: '#e56910', tip: 'Review veraltet: neue Commits seit dem Review' },
        running: { bg: '#8270db', tip: 'Review läuft' },
    };

    // Stand je "<repo>#<nr>"; fehlt er (Planstack nicht erreichbar), bleibt das Icon neutral.
    const states = new Map();
    // Schon angefragte PRs; nur neue Zeilen lösen eine Sofort-Abfrage aus.
    const requested = new Set();
    let problem = token ? null : 'Planstack-Token fehlt (Tampermonkey-Menü „Planstack-Token setzen“)';

    function prKey(row) {
        const m = String(row.dataset.prUrl || '').match(/github\.com\/([^/]+\/[^/]+)\/pull\/(\d+)/);
        return m ? { repo: m[1], number: m[2], key: m[1] + '#' + m[2] } : null;
    }

    function hhmm(iso) {
        return new Date(iso).toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
    }

    function dateTime(iso) {
        return new Date(iso).toLocaleString('de-DE', { dateStyle: 'short', timeStyle: 'short' });
    }

    function load() {
        if (!token) return;
        const byRepo = new Map();
        document.querySelectorAll(ROW).forEach(function (row) {
            const pr = prKey(row);
            if (!pr) return;
            requested.add(pr.key);
            if (!byRepo.has(pr.repo)) byRepo.set(pr.repo, new Set());
            byRepo.get(pr.repo).add(pr.number);
        });

        byRepo.forEach(function (numbers, repo) {
            GM_xmlhttpRequest({
                method: 'GET',
                url: API + '/pull-requests/review-states?repository=' + encodeURIComponent(repo) + '&prs=' + Array.from(numbers).join(','),
                headers: { Authorization: 'Bearer ' + token, Accept: 'application/json' },
                timeout: 20000,
                onload: function (r) {
                    if (r.status === 401) { problem = 'Planstack-Token ungültig (Tampermonkey-Menü „Planstack-Token ändern“)'; decorate(true); return; }
                    if (r.status !== 200) { problem = 'Planstack antwortet mit HTTP ' + r.status; decorate(true); return; }
                    let data = {};
                    try { data = JSON.parse(r.responseText).data || {}; } catch (e) { data = {}; }
                    Object.keys(data).forEach(function (nr) { states.set(repo + '#' + nr, data[nr]); });
                    problem = null;
                    decorate(true);
                },
                onerror: function () { problem = 'Planstack nicht erreichbar'; decorate(true); },
                ontimeout: function () { problem = 'Planstack antwortet nicht (Timeout)'; decorate(true); },
            });
        });
    }

    // Startet Claude über claude-task.ps1 (Handler für claudetask:)
    function startClaude(prompt) {
        if (navigator.clipboard) navigator.clipboard.writeText(prompt).catch(function () {});
        window.location.href = 'claudetask:' + encodeURIComponent(prompt);
    }

    function tipFor(row, s) {
        const style = s ? STATE[s.state] : null;
        const reviewed = s && typeof s.findings === 'number';
        return [
            'Claude: /ps-review für #' + row.dataset.prNumber + ' starten',
            style && s.state === 'running'
                ? style.tip + ' seit ' + hhmm(s.runningSince) + (s.date ? ' (letztes Review vom ' + dateTime(s.date) + ')' : '')
                : (style ? style.tip + (s.date ? ' (vom ' + dateTime(s.date) + ')' : '') : ''),
            reviewed ? s.findings + ' Findings: ' + s.critical + ' kritisch, ' + s.important + ' wichtig, ' + s.hints + ' Hinweise' : '',
            s && s.recommendation ? 'Empfehlung: ' + s.recommendation : '',
            s && s.url ? 'Strg+Klick: in Planstack öffnen' : '',
            problem || '',
        ].filter(Boolean).join('\n');
    }

    function button(row, s) {
        const btn = document.createElement('span');
        btn.className = BTN_CLASS;
        btn.setAttribute('role', 'button');
        btn.dataset.state = JSON.stringify(s || null) + (problem || '');
        const style = s ? STATE[s.state] : null;
        const findings = s && s.state !== 'none' && typeof s.findings === 'number' ? s.findings : null;
        btn.dataset.tmTip = tipFor(row, s);
        // Auf farbigem Hintergrund weißes Logo, sonst Claude-Orange
        btn.innerHTML = '<svg aria-hidden="true" height="14" width="14" viewBox="0 0 24 24" fill="' + (style ? '#fff' : '#D97757') + '"><path d="' + CLAUDE_PATH_D + '"></path></svg>';
        if (findings !== null) {
            const count = document.createElement('span');
            count.textContent = String(findings);
            btn.appendChild(count);
        }
        Object.assign(btn.style, {
            display: 'inline-flex', alignItems: 'center', justifyContent: 'center', gap: '3px',
            height: '18px', minWidth: '20px', padding: findings !== null ? '0 5px 0 3px' : '0',
            fontSize: '11px', fontWeight: '600', color: '#fff', borderRadius: '3px', cursor: 'pointer',
            boxSizing: 'border-box', border: style ? 'none' : '1px solid var(--ds-border-bold, #626f86)',
            background: style ? style.bg : 'transparent',
            // Kartentext hat pointer-events:none, Klicks gehen sonst an Jiras Karten-Button
            pointerEvents: 'auto',
        });
        // Klick soll weder die Karte öffnen noch sie ziehen
        ['mousedown', 'mouseup', 'pointerdown', 'pointerup'].forEach(function (t) {
            btn.addEventListener(t, function (e) { e.stopPropagation(); });
        });
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (!row.dataset.prUrl) return;
            if ((e.ctrlKey || e.metaKey) && s && s.url) {
                window.open(s.url, '_blank', 'noopener');
                return;
            }
            startClaude('/ps-review ' + row.dataset.prUrl);
            // Sofort als laufend zeigen; den echten Stand liefert die nächste Abfrage
            btn.style.background = STATE.running.bg;
            btn.style.border = 'none';
            btn.querySelector('svg').setAttribute('fill', '#fff');
            btn.dataset.tmTip = STATE.running.tip;
            setTimeout(load, 15000);
        });
        return btn;
    }

    let pendingLoad = null;

    function decorate(fromLoad) {
        let unknown = false;
        document.querySelectorAll(ROW).forEach(function (row) {
            const pr = prKey(row);
            const s = pr ? states.get(pr.key) : null;
            if (pr && !requested.has(pr.key)) unknown = true;
            const marker = JSON.stringify(s || null) + (problem || '');
            const existing = row.querySelector(':scope > .' + BTN_CLASS);
            if (existing && existing.dataset.state === marker) return;
            if (existing) existing.remove();
            // Direkt hinter der PR-Nummer
            const first = row.firstElementChild;
            row.insertBefore(button(row, s), first ? first.nextSibling : null);
        });
        // Neue Zeilen ohne Stand: gebündelt nachladen statt je Mutation eine Anfrage
        if (unknown && !fromLoad && token && !pendingLoad) {
            pendingLoad = setTimeout(function () { pendingLoad = null; load(); }, 800);
        }
    }

    new MutationObserver(function () { decorate(false); }).observe(document.body, { childList: true, subtree: true });
    decorate(false);
    load();
    setInterval(load, REFRESH_MS);
})();
