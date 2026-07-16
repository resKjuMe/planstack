// ==UserScript==
// @name         Planstack CI Status
// @namespace    https://planstack.eskju.net/
// @version      1.0.0
// @description  Zeigt je PR-Knoten im Planstack-Diagramm den CI-/Merge-Status (failed / succeeded / x/x Steps) — Daten via lokalem ci-server (gh CLI), kein Token im Browser.
// @author       Planstack
// @match        https://planstack.eskju.net/*
// @icon         https://www.google.com/s2/favicons?sz=64&domain=github.com
// @grant        GM_xmlhttpRequest
// @connect      127.0.0.1
// @connect      localhost
// @downloadURL  https://planstack.eskju.net/planstack-ci/planstack-ci.user.min.js
// @updateURL    https://planstack.eskju.net/planstack-ci/planstack-ci.user.min.js
// @run-at       document-start
// ==/UserScript==

(function () {
    'use strict';

    const PSCI_VERSION = '1.0.0';
    console.log('%c[PlanstackCI] geladen v' + PSCI_VERSION, 'color:#8250df;font-weight:bold', location.pathname);

    // Marker für den (server-seitigen) Teaser: signalisiert „Userscript läuft" +
    // seine Version. Der Teaser blendet sich damit aus bzw. zeigt Update-Hinweise.
    try {
        document.documentElement.setAttribute('data-planstack-ci', PSCI_VERSION);
        document.dispatchEvent(new CustomEvent('planstack-ci-ready', { detail: { version: PSCI_VERSION } }));
    } catch (e) { /* egal */ }

    // ── KONFIG ──────────────────────────────────────────────────────────────
    // Lokaler ci-server (ci-server.js), der `gh` mit deiner CLI-Auth aufruft.
    // Einrichtung siehe https://planstack.eskju.net/planstack-ci
    const CI_SERVER = 'http://127.0.0.1:8757';

    // ── Badge-Farben (nach dem Flow-Diagramm) ───────────────────────────────
    const C = {
        green:   '#1a7f37',  // ready to merge / CI grün
        red:     '#cf222e',  // Step gefailt / conflict / unresolved comments
        darkred: '#8b1a1a',  // CI status fetch failed
        orange:  '#e16f24',  // CI läuft (mit Fortschritt)
        yellow:  '#bf8700',  // waiting for approve
        grey:    '#57606a',  // pending / loading / keine Checks
    };

    // ─────────────────────────────────────────────────────────────────────────
    //  HTTP-Helfer: GM_xmlhttpRequest als Promise, optional als JSON.
    // ─────────────────────────────────────────────────────────────────────────
    function ghGet(url, asJson) {
        return new Promise(function (resolve, reject) {
            if (typeof GM_xmlhttpRequest !== 'function') {
                reject(new Error('GM_xmlhttpRequest nicht verfügbar'));
                return;
            }
            GM_xmlhttpRequest({
                method: 'GET',
                url: url,
                headers: { 'Accept': asJson ? 'application/json' : 'text/html' },
                timeout: 15000,
                onload: function (res) {
                    if (res.status < 200 || res.status >= 300) {
                        reject(new Error('HTTP ' + res.status + ' für ' + url));
                        return;
                    }
                    if (!asJson) { resolve(res.responseText); return; }
                    try { resolve(JSON.parse(res.responseText)); }
                    catch (e) { reject(new Error('Antwort kein JSON: ' + url)); }
                },
                onerror: function () { reject(new Error('Netzwerkfehler für ' + url)); },
                ontimeout: function () { reject(new Error('Timeout für ' + url)); },
            });
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  CI-Zähler: statusCheckRollup von `gh pr view` auszählen (CheckRun + StatusContext).
    // ─────────────────────────────────────────────────────────────────────────
    const FAIL_CONCL = ['FAILURE', 'TIMED_OUT', 'CANCELLED', 'STARTUP_FAILURE', 'ACTION_REQUIRED', 'STALE', 'ERROR'];
    function countsFromRollup(rollup) {
        let total = 0, success = 0, failed = 0, pending = 0;
        (rollup || []).forEach(function (it) {
            total++;
            if (it.__typename === 'StatusContext') {
                const s = String(it.state || '').toUpperCase();
                if (s === 'SUCCESS') success++;
                else if (s === 'FAILURE' || s === 'ERROR') failed++;
                else pending++; // PENDING / EXPECTED
            } else { // CheckRun
                if (String(it.status || '').toUpperCase() !== 'COMPLETED') pending++;
                else if (FAIL_CONCL.indexOf(String(it.conclusion || '').toUpperCase()) !== -1) failed++;
                else success++; // SUCCESS / NEUTRAL / SKIPPED
            }
        });
        return { total: total, success: success, failed: failed, pending: pending };
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Entscheidungsbaum — 1:1 nach dem Flow-Diagramm:
    //    Konflikt → "conflicted"; CI läuft → done/total; CI fertig → ✗ / Merge-Readiness.
    //  CI-Summary = bestandene/gesamt Checks (success/total).
    // ─────────────────────────────────────────────────────────────────────────
    function decide(state) {
        if (state.merge && String(state.merge.mergeStateStatus || '').toUpperCase() === 'DIRTY') {
            return { text: 'conflicted', color: C.red };
        }
        const c = state.ci;
        if (!c || c.total === 0) return { text: 'keine Checks', color: C.grey };

        const summary = c.success + '/' + c.total;
        const done = c.success + c.failed;

        if (c.failed > 0) return { text: '✗ ' + summary, color: C.red };

        if (c.pending > 0) {
            if (done === 0) return { text: 'pending', color: C.grey };
            return { text: summary, color: C.orange };
        }

        if (state.unresolved > 0) return { text: state.unresolved + ' unresolved comments', color: C.red };
        if (!state.approved) return { text: 'waiting for approve', color: C.yellow };
        return { text: 'ready to merge', color: C.green };
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Ein PR: Status vom lokalen ci-server holen (gh CLI, kein Token im Browser).
    // ─────────────────────────────────────────────────────────────────────────
    async function fetchPrStatus(owner, repo, number) {
        const url = CI_SERVER + '/pr?repo=' + encodeURIComponent(owner + '/' + repo) +
            '&number=' + encodeURIComponent(number);
        const data = await ghGet(url, true);
        if (data && data.error) throw new Error(data.error);
        return {
            merge: { state: data.state, mergeStateStatus: data.mergeStateStatus },
            approved: String(data.reviewDecision || '').toUpperCase() === 'APPROVED',
            unresolved: Number(data.unresolved) || 0,
            ci: countsFromRollup(data.statusCheckRollup),
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Rendering: eine CI-Zeile in den .ps-node einhängen.
    // ─────────────────────────────────────────────────────────────────────────
    function pill(text, color) {
        return '<span style="display:inline-block;padding:1px 6px;border-radius:9999px;' +
            'font-size:9px;font-weight:600;line-height:1.4;white-space:nowrap;' +
            'color:' + color + ';border:1px solid ' + color + '33;background:' + color + '14;">' +
            text + '</span>';
    }

    function renderInto(psNode, state, err) {
        let row = psNode.querySelector('.tm-ci-row');
        if (!row) {
            row = document.createElement('div');
            row.className = 'tm-ci-row';
            row.style.cssText = 'margin-top:4px;display:flex;flex-wrap:wrap;gap:4px;justify-content:center;';
            psNode.appendChild(row);
        }
        if (err) {
            row.innerHTML = pill('CI status fetch failed', C.darkred);
            row.title = err + '\n\nLäuft der lokale ci-server? Einrichtung: ' + location.origin + '/planstack-ci';
            return;
        }
        const lbl = decide(state);
        row.innerHTML = pill(lbl.text, lbl.color);
        row.title = 'CI-/Merge-Status von GitHub';
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Diagramm einlesen und Knoten verarbeiten.
    // ─────────────────────────────────────────────────────────────────────────
    function buildNodeMap() {
        const host = document.querySelector('.ps-diagram[data-graph]');
        if (!host) return null;
        let graph;
        try { graph = JSON.parse(host.getAttribute('data-graph')); }
        catch (e) { console.warn('[PlanstackCI] data-graph nicht parsebar', e); return null; }
        const byUrl = {};
        (graph.nodes || []).forEach(function (n) { if (n.url) byUrl[n.url] = n; });
        return byUrl;
    }

    function parsePrUrl(prUrl) {
        const m = String(prUrl || '').match(/github\.com\/([^/]+)\/([^/]+)\/pull\/(\d+)/);
        if (!m) return null;
        return { owner: m[1], repo: m[2], number: m[3] };
    }

    function processNodes(byUrl) {
        document.querySelectorAll('.ps-graph g.node').forEach(function (g) {
            const psNode = g.querySelector('.ps-node');
            if (!psNode || psNode.dataset.tmCi) return;

            const detail = g.querySelector('a.detail');
            if (!detail) return;
            const node = byUrl[detail.href];
            if (!node) return;

            if (!node.prUrl || node.done) { psNode.dataset.tmCi = 'skip'; return; }
            const pr = parsePrUrl(node.prUrl);
            if (!pr) { psNode.dataset.tmCi = 'skip'; return; }

            psNode.dataset.tmCi = '1';
            renderInto(psNode, { ci: null, merge: null });
            const placeholder = psNode.querySelector('.tm-ci-row');
            if (placeholder) placeholder.innerHTML = pill('loading CI …', C.grey);

            fetchPrStatus(pr.owner, pr.repo, pr.number)
                .then(function (state) { renderInto(psNode, state); })
                .catch(function (e) {
                    console.debug('[PlanstackCI] Status-Fehler PR #' + pr.number, e && e.message);
                    renderInto(psNode, null, e && e.message);
                });
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Start: auf das Diagramm warten; mermaid baut das SVG ggf. neu auf → erneut scannen.
    // ─────────────────────────────────────────────────────────────────────────
    function scan() {
        const byUrl = buildNodeMap();
        if (!byUrl) return;
        if (!document.querySelector('.ps-graph g.node')) return;
        processNodes(byUrl);
    }

    function boot() {
        scan();
        const graphHost = document.querySelector('.ps-diagram') || document.body;
        let pending = null;
        const obs = new MutationObserver(function () {
            clearTimeout(pending);
            pending = setTimeout(scan, 250);
        });
        obs.observe(graphHost, { childList: true, subtree: true });
        [500, 1500, 3500].forEach(function (ms) { setTimeout(scan, ms); });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
