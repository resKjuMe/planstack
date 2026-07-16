// ─────────────────────────────────────────────────────────────────────────
//  ci-server.js — lokaler CI-/Merge-Status-Dienst für das Planstack-Diagramm
//
//  Ruft die lokal bereits authentifizierte GitHub-CLI (`gh`) auf und liefert je
//  PR den Check-Rollup + Merge-State + Review-Decision + offene Kommentare als
//  JSON. Dadurch braucht das Userscript KEINEN GitHub-Token und umgeht, dass
//  GitHub die Checks rein clientseitig rendert.
//
//  Voraussetzungen:  Node.js  +  GitHub CLI (`gh`)  +  einmalig `gh auth login`
//
//  Start (im Hintergrund lassen):
//      node ci-server.js
//
//  Endpunkte (CORS offen, nur lokal gebunden):
//      GET /health               → "ok"
//      GET /version              → { version }
//      GET /pr?repo=o/r&number=N → CI-/Merge-Status eines PRs
// ─────────────────────────────────────────────────────────────────────────
'use strict';

const http = require('http');
const fs = require('fs');
const { execFile } = require('child_process');

const VERSION = '1.0.0';
const HOST = '127.0.0.1';
const PORT = 8757;
const CACHE_TTL_MS = 20000;   // gh nicht bei jedem Diagramm-Repaint neu aufrufen

// gh-Pfad auflösen (PATH oder Standard-Installationsorte Windows/macOS)
const GH = ['gh', 'C:\\Program Files\\GitHub CLI\\gh.exe', '/opt/homebrew/bin/gh', '/usr/local/bin/gh']
    .find((p) => p === 'gh' || fs.existsSync(p)) || 'gh';

const cache = new Map(); // key -> { t, data }

function runGh(args) {
    return new Promise((resolve, reject) => {
        execFile(GH, args, { maxBuffer: 20 * 1024 * 1024, windowsHide: true }, (err, stdout, stderr) => {
            if (err) { reject(new Error(String(stderr || err.message || '').trim())); return; }
            resolve(String(stdout));
        });
    });
}

async function prStatus(repo, number) {
    const key = repo + '#' + number;
    const hit = cache.get(key);
    if (hit && Date.now() - hit.t < CACHE_TTL_MS) return hit.data;

    // Alles Nötige in einem gh-Aufruf: Checks-Rollup + Merge-State + Review.
    const view = JSON.parse(await runGh([
        'pr', 'view', String(number), '--repo', repo,
        '--json', 'state,mergeStateStatus,reviewDecision,statusCheckRollup',
    ]));

    // Offene (nicht aufgelöste) Review-Threads via GraphQL (best effort).
    let unresolved = 0;
    try {
        const [owner, name] = repo.split('/');
        const q = 'query($o:String!,$n:String!,$p:Int!){repository(owner:$o,name:$n){pullRequest(number:$p){reviewThreads(first:100){nodes{isResolved}}}}}';
        const gr = JSON.parse(await runGh(['api', 'graphql', '-F', 'o=' + owner, '-F', 'n=' + name, '-F', 'p=' + number, '-f', 'query=' + q]));
        const rt = (((gr.data || {}).repository || {}).pullRequest || {}).reviewThreads;
        if (rt && rt.nodes) unresolved = rt.nodes.filter((t) => !t.isResolved).length;
    } catch (e) { /* Kommentare optional — Rest funktioniert trotzdem */ }

    const data = {
        state: view.state,
        mergeStateStatus: view.mergeStateStatus,
        reviewDecision: view.reviewDecision,
        statusCheckRollup: view.statusCheckRollup || [],
        unresolved: unresolved,
    };
    cache.set(key, { t: Date.now(), data });
    return data;
}

function sendJson(res, code, obj) {
    res.writeHead(code, { 'Content-Type': 'application/json; charset=utf-8' });
    res.end(JSON.stringify(obj));
}

const server = http.createServer(async (req, res) => {
    res.setHeader('Access-Control-Allow-Origin', '*');   // erlaubt den Abruf von der (https-)Planstack-Seite
    let url;
    try { url = new URL(req.url, 'http://x'); } catch (e) { res.writeHead(400); res.end(); return; }
    const p = url.pathname;

    if (p === '/health') { res.writeHead(200, { 'Content-Type': 'text/plain' }); res.end('ok'); return; }
    if (p === '/version') { sendJson(res, 200, { version: VERSION }); return; }

    if (p === '/pr') {
        const repo = url.searchParams.get('repo');
        const number = url.searchParams.get('number');
        if (!repo || !number || !/^[\w.-]+\/[\w.-]+$/.test(repo) || !/^\d+$/.test(number)) {
            sendJson(res, 400, { error: 'repo=owner/name & number=<zahl> erforderlich' });
            return;
        }
        try { sendJson(res, 200, await prStatus(repo, number)); }
        catch (e) { sendJson(res, 502, { error: String((e && e.message) || e) }); }
        return;
    }

    res.writeHead(404, { 'Content-Type': 'text/plain' });
    res.end('not found');
});

server.listen(PORT, HOST, () => {
    console.log('[ci-server] v' + VERSION + ' läuft auf http://' + HOST + ':' + PORT + '  (gh: ' + GH + ')');
});
