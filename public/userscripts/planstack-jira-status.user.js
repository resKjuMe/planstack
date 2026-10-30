// ==UserScript==
// @name         Planstack-Jira-Status
// @namespace    https://planstack.eskju.net/
// @version      2026-10-30.1
// @description  Färbt die Jira-Pills in Planstack-PR-Titeln nach dem Ticket-Status (grau To Do, blau in Arbeit, violett In Review, indigo Product-Review, grün erledigt). Abruf über die eingeloggte Jira-Session, kein Token nötig.
// @author       Christian Mietze <christian.mietze@clockodo.com>
// @match        https://planstack.eskju.net/*
// @icon         https://planstack.eskju.net/favicon.svg
// @grant        GM_xmlhttpRequest
// @connect      clickbits.atlassian.net
// @updateURL    https://planstack.eskju.net/userscripts/planstack-jira-status.user.js
// @downloadURL  https://planstack.eskju.net/userscripts/planstack-jira-status.user.js
// @run-at       document-idle
// ==/UserScript==

(function () {
    'use strict';

    // Planstack rendert die Pills (a.jira-pill[data-jira-key]), die Farbe je
    // data-jira-tone steht in dessen app.css.
    const PILL = 'a.jira-pill[data-jira-key]';
    const JIRA = 'https://clickbits.atlassian.net';
    const CACHE_MS = 5 * 60 * 1000;
    const BATCH = 50;

    // Erst der Statusname, sonst die Jira-Statuskategorie.
    const NAME_TONES = { 'product-review': 'po', 'in review': 'review' };
    const CATEGORY_TONES = { new: 'new', indeterminate: 'progress', done: 'done' };

    const cache = new Map(); // 'MN-1234' -> { tone, name, ts }
    const inFlight = new Set();
    let timer = null;

    function paint(el) {
        const entry = cache.get(el.dataset.jiraKey);
        if (!entry) return;
        if (el.dataset.jiraTone !== entry.tone) el.dataset.jiraTone = entry.tone;
        const tip = 'Jira: ' + el.dataset.jiraKey + ' · ' + entry.name;
        if (el.title !== tip) el.title = tip;
    }

    function store(key, fields) {
        const st = (fields && fields.status) || {};
        const name = st.name || '';
        cache.set(key, {
            tone: NAME_TONES[name.trim().toLowerCase()] || CATEGORY_TONES[st.statusCategory && st.statusCategory.key] || 'new',
            name: name || 'Status unbekannt',
            ts: Date.now(),
        });
    }

    function fetchBatch(keys) {
        keys.forEach(function (k) { inFlight.add(k); });
        const url = JIRA + '/rest/api/3/search/jql?jql=' + encodeURIComponent('key in (' + keys.join(',') + ')') +
            '&fields=status&maxResults=' + keys.length;
        GM_xmlhttpRequest({
            method: 'GET',
            url: url,
            headers: { Accept: 'application/json' },
            timeout: 12000,
            onload: function (res) {
                if (res.status >= 200 && res.status < 300) {
                    try {
                        (JSON.parse(res.responseText).issues || []).forEach(function (issue) { store(issue.key, issue.fields); });
                    } catch (e) {
                        console.debug('[PlanstackJiraStatus] Antwort kein JSON');
                    }
                } else {
                    console.debug('[PlanstackJiraStatus] Jira HTTP ' + res.status + ' (eingeloggt?)');
                }
                done(keys);
            },
            onerror: function () { done(keys); },
            ontimeout: function () { done(keys); },
        });
    }

    function done(keys) {
        keys.forEach(function (k) { inFlight.delete(k); });
        document.querySelectorAll(PILL).forEach(paint);
    }

    function scan() {
        timer = null;
        const now = Date.now();
        const missing = new Set();
        document.querySelectorAll(PILL).forEach(function (el) {
            const key = el.dataset.jiraKey;
            const entry = cache.get(key);
            if (entry) paint(el);
            if ((!entry || now - entry.ts > CACHE_MS) && !inFlight.has(key)) missing.add(key);
        });
        const keys = Array.from(missing);
        for (let i = 0; i < keys.length; i += BATCH) fetchBatch(keys.slice(i, i + BATCH));
    }

    function schedule() {
        if (!timer) timer = setTimeout(scan, 150);
    }

    // Inertia tauscht Seiten und Zeilen ohne Reload aus.
    new MutationObserver(schedule).observe(document.body, { childList: true, subtree: true });
    setInterval(schedule, CACHE_MS);
    schedule();
})();
