import mermaid from 'mermaid';
import elkLayouts from '@mermaid-js/layout-elk';

// ELK statt des Standard-Renderers (dagre): dagre legt unabhängige Tasks in eine
// einzige, sehr breite Zeile, die dann klein herunterskaliert und unlesbar wird.
// ELK packt breite Graphen kompakt in mehrere Reihen. In mermaid v11 ist ELK ein
// separates Paket, das erst registriert werden muss.
mermaid.registerLayoutLoaders(elkLayouts);

mermaid.initialize({
    startOnLoad: false,
    securityLevel: 'loose',
    theme: 'default',
    layout: 'elk',
    // Defaults (50k Zeichen / 500 Kanten) sind für generische Nutzereingaben
    // gedacht; unser Graph kommt aus den eigenen, vertrauenswürdigen
    // Board-Daten und darf größer werden als die historischen Limits.
    maxTextSize: Infinity,
    maxEdges: Infinity,
    flowchart: {
        htmlLabels: true,
        curve: 'basis',
        // Roomy spacing so the done-node corner badge (5a) never crowds a
        // neighbour or an edge — mermaid can't inflate the dagre box for an
        // out-of-flow badge, so we buy the clearance here instead.
        nodeSpacing: 70,
        rankSpacing: 120,
        padding: 8,
        useMaxWidth: false,
    },
});

// The visible node is rendered entirely as our own HTML label (see nodeLabel and
// the .ps-node CSS in diagram.blade.php): colour = phase/status category,
// icon = exact status, border weight = attention. The mermaid shape behind the
// label is made invisible so only our styled box shows — every node uses the
// same throw-away class.
const CLASS_DEFS = 'classDef plain fill:transparent,stroke:none;';

// Tabler-Outline-Icon-Pfade je Status (24er-ViewBox). Inline-SVG, damit die
// Icons ohne Webfont auskommen und im PNG-Export (Canvas) erhalten bleiben.
// Spiegelbild in diagram.blade.php (Legende) — dort mitziehen.
const STATUS_ICONS = {
    pickable: '<path d="M7 4v16l13 -8z"/>',
    claimed: '<path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0"/><path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2"/>',
    analyzing: '<path d="M10 10m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0"/><path d="M21 21l-6 -6"/>',
    inprogress: '<path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1"/><path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z"/><path d="M16 5l3 3"/>',
    inreview: '<path d="M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0"/><path d="M21 12c-2.4 4 -5.4 6 -9 6c-3.6 0 -6.6 -2 -9 -6c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6"/>',
    blocked: '<path d="M5 13a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v6a2 2 0 0 1 -2 2h-10a2 2 0 0 1 -2 -2z"/><path d="M11 16a1 1 0 1 0 2 0a1 1 0 0 0 -2 0"/><path d="M8 11v-4a4 4 0 1 1 8 0v4"/>',
    concern: '<path d="M12 9v4"/><path d="M10.24 3.957l-8.422 14.06a1.989 1.989 0 0 0 1.7 2.983h16.845a1.989 1.989 0 0 0 1.7 -2.983l-8.423 -14.06a1.989 1.989 0 0 0 -3.4 0z"/><path d="M12 16h.01"/>',
    done: '<path d="M5 12l5 5l10 -10"/>',
};
const BOTTLENECK_ICON = '<path d="M6.5 7h11"/><path d="M6.5 17h11"/><path d="M6 20v-2a6 6 0 1 1 12 0v2a1 1 0 0 1 -1 1h-10a1 1 0 0 1 -1 -1z"/><path d="M6 4v2a6 6 0 1 0 12 0v-2a1 1 0 0 0 -1 -1h-10a1 1 0 0 0 -1 1z"/>';
const FILE_ICON = '<path d="M14 3v4a1 1 0 0 0 1 1h4"/><path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z"/>';

function svgIcon(paths) {
    return `<svg class='ps-ico' xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' aria-hidden='true'>${paths}</svg>`;
}

// Initialen aus einem Namen (max. zwei Wörter), z. B. "Christian Mietze" → "CM".
function initials(name) {
    return String(name).trim().split(/\s+/).filter(Boolean).slice(0, 2)
        .map((w) => w[0].toUpperCase()).join('');
}

// Kanten: offene Abhängigkeit kräftig durchgezogen, erfüllte hell gestrichelt.
const EDGE_OPEN = 'stroke:#64748b,stroke-width:1.5px';
const EDGE_MET = 'stroke:#cbd5e1,stroke-width:1px,stroke-dasharray:4 4';

// CSRF token for the inline review-claim form (from the app layout meta tag).
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

function esc(value) {
    return String(value ?? '').replace(/[&<>"]/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;',
    }[c]));
}

function truncate(text, max = 70) {
    const t = String(text).trim();
    return t.length > max ? t.slice(0, max - 1).trimEnd() + '…' : t;
}

// Collect our own diagram label CSS (typography of the foreignObject labels) so
// an exported SVG renders faithfully without the page stylesheet attached.
function collectDiagramCss() {
    let css = '';
    for (const sheet of document.styleSheets) {
        let rules;
        try {
            rules = sheet.cssRules;
        } catch {
            continue; // cross-origin sheet — not readable, skip
        }
        if (!rules) continue;
        for (const rule of rules) {
            if (rule.cssText && rule.cssText.includes('ps-node')) {
                css += rule.cssText + '\n';
            }
        }
    }
    return css;
}

// Subtitle text per status bucket. SP + geschätzte Dateianzahl sind immer
// sichtbar; die Initialen des Bearbeiters leben in der Titelzeile, nicht hier.
function subtitle(n) {
    const files = n.files === null ? '---' : n.files;
    const sp = `${n.sp} SP · ${svgIcon(FILE_ICON)}${files}`;
    if (n.cat === 'pickable') {
        return n.unlocks > 0 ? `${sp} · schaltet ${n.unlocks} frei` : sp;
    }
    if (n.cat === 'blocked') {
        return n.depTotal > 0 ? `${sp} · wartet auf ${n.depOpen} von ${n.depTotal}` : sp;
    }
    return sp;
}

function nodeLabel(n, showDesc = false) {
    const cls = `ps-node cat-${n.cat}${n.done ? ' is-done' : ''}${n.bottleneck ? ' has-bn' : ''}`;
    const parts = [`<div class='${cls}'>`];

    // Title line: status icon · name (links to the ticket, click handled in
    // wireInteraction) · inline PR (open nodes) · assignee initials. Done nodes
    // move the PR number out to a corner badge.
    let title = n.url
        ? `<a class='detail' href='${esc(n.url)}'>${esc(n.name)}</a>`
        : esc(n.name);
    if (n.pr && !n.done) {
        title += ' ';
        title += n.prUrl
            ? `<a class='pr' href='${esc(n.prUrl)}' target='_blank' rel='noopener'>#${esc(n.pr)}</a>`
            : `<span class='pr'>#${esc(n.pr)}</span>`;
    }
    if (n.claimer) {
        title += ` <span class='who'>· ${esc(initials(n.claimer))}</span>`;
    }
    parts.push(`<div class='t'>${svgIcon(STATUS_ICONS[n.cat] ?? '')}${title}</div>`);

    parts.push(`<div class='s'>${subtitle(n)}</div>`);

    // Optionale Kurzbeschreibung unter dem Untertitel (Toolbar-Checkbox).
    if (showDesc && n.summary) {
        parts.push(`<div class='d'>${esc(truncate(n.summary, 120))}</div>`);
    }

    // Problem-Grund: normale Schrift (kein Kursiv), erbt die Concern-Textfarbe.
    if (n.cat === 'concern' && n.reason) {
        parts.push(`<div class='r'>${esc(truncate(n.reason))}</div>`);
    }

    // In Review: Reviewer kursiv anzeigen — oder, solange niemand reviewt und
    // der Betrachter nicht selbst Bearbeiter ist, ein schmaler „claim review"-
    // Button, der den aktiven Nutzer per Formular-POST als Reviewer einträgt.
    if (n.cat === 'inreview') {
        if (n.reviewedBy) {
            parts.push(`<div class='rv'>Reviewed by ${esc(n.reviewedBy)}</div>`);
        } else if (n.reviewClaimUrl) {
            parts.push(
                `<form class='rv-claim' method='POST' action='${esc(n.reviewClaimUrl)}'>`
                + `<input type='hidden' name='_token' value='${esc(CSRF_TOKEN)}'>`
                + `<button type='submit' class='rv-claim-btn'>claim review</button>`
                + '</form>'
            );
        }
    }

    // Corner PR badge for done nodes only; muted grey, links to the PR, and is
    // excluded from the node's highlight click (see wireInteraction). Omitted
    // entirely when there is no PR number — no placeholder.
    if (n.done && n.pr) {
        parts.push(n.prUrl
            ? `<a class='pr-badge' href='${esc(n.prUrl)}' target='_blank' rel='noopener'>#${esc(n.pr)}</a>`
            : `<span class='pr-badge'>#${esc(n.pr)}</span>`);
    }

    // Flaschenhals: runder Eck-Badge oben rechts (ersetzt das frühere 🚧-Emoji).
    // Done-Knoten sind nie Flaschenhals (Controller), daher kollidiert er nie mit
    // dem PR-Badge oben rechts.
    if (n.bottleneck) {
        const cnt = n.directDependents ?? n.dependents ?? 0;
        parts.push(`<span class='ps-bn' title='Flaschenhals · blockiert ${cnt} PR${cnt === 1 ? '' : 's'}'>${svgIcon(BOTTLENECK_ICON)}</span>`);
    }

    parts.push('</div>');
    return parts.join('');
}

// Tasks ohne jede Kante (weder Vorgänger noch Nachfolger) haben für den Layout-
// Algorithmus keinen Grund, in unterschiedliche Ränge zu wandern — sie landen
// sonst alle im selben Rang und reihen sich in einer einzigen, breiten Zeile
// auf. Unsichtbare Kanten (~~~) verketten sie stattdessen zu einer einzigen
// Spalte, ohne dass eine sichtbare Linie/Pfeil entsteht.
function independentColumnLinks(nodes, edges) {
    const connected = new Set();
    for (const e of edges) {
        connected.add(e.from);
        connected.add(e.to);
    }
    const loose = nodes.filter((n) => !connected.has(n.key));

    const links = [];
    for (let i = 1; i < loose.length; i++) {
        links.push(`${loose[i - 1].key} ~~~ ${loose[i].key}`);
    }
    return links;
}

// Turn the graph model into mermaid flowchart source. Direction is top→bottom
// throughout; the layout engine gives the topological ranks and crossing
// minimisation and keeps independent strands (VE / G clusters …) in separate
// columns.
function buildSource(nodes, edges, showDesc = false) {
    const lines = ['flowchart TB', CLASS_DEFS];

    for (const n of nodes) {
        lines.push(`${n.key}["${nodeLabel(n, showDesc)}"]:::plain`);
    }

    const styles = [];
    edges.forEach((e, i) => {
        lines.push(`${e.from} --> ${e.to}`);
        styles.push(`linkStyle ${i} ${e.met ? EDGE_MET : EDGE_OPEN}`);
    });
    lines.push(...styles);
    lines.push(...independentColumnLinks(nodes, edges));

    return lines.join('\n');
}

let renderSeq = 0;

class DependencyGraph {
    constructor(root) {
        this.root = root;
        this.canvas = root.querySelector('.ps-graph');
        this.emptyEl = root.querySelector('.ps-diagram-empty');
        this.name = root.dataset.diagramName || 'diagramm';

        const model = JSON.parse(root.dataset.graph || '{"nodes":[],"edges":[]}');
        this.allNodes = model.nodes;
        this.allEdges = model.edges;

        // Everything (incl. merged) is shown by default; the "Erledigte
        // ausblenden" toggle hides done (COMPLETED/MERGED) nodes on demand.
        // The choice is remembered per browser (defaults to "shown").
        this.hideDone = localStorage.getItem('ps-diagram-hidedone') === '1';
        // Kurzbeschreibungen unter dem Titel (per Browser gemerkt, Standard: aus).
        this.showDesc = localStorage.getItem('ps-diagram-desc') === '1';
        this.lockedKey = null;
        // Header-chip phase filter (transient, not remembered): the phase id as a
        // string, or null for "all phases".
        this.phaseFilter = null;

        this.nodeEls = new Map();   // key -> <g class="node">
        this.edgeEls = [];          // [{ from, to, el }]
        this.parents = new Map();   // key -> Set(parentKeys)
        this.children = new Map();  // key -> Set(childKeys)

        // Ctrl+Mausrad-Zoom (siehe wireZoom); bleibt über Re-Renders hinweg
        // erhalten, da nur canvas.innerHTML ersetzt wird, nicht canvas selbst.
        this.zoom = 1;
    }

    async init() {
        if (this.allNodes.length === 0) {
            this.canvas.classList.add('hidden');
            this.emptyEl.classList.remove('hidden');
            return;
        }
        this.wireToolbar();
        this.wireZoom();
        await this.render();
    }

    activeGraph() {
        // Apply the phase filter and the "hide done" toggle in turn; edges
        // touching a dropped node fall away with it (the "wartet auf X von Y"
        // subtitle still carries the met count).
        let nodes = this.allNodes;
        if (this.phaseFilter !== null) {
            nodes = nodes.filter((n) => String(n.phase) === this.phaseFilter);
        }
        if (this.hideDone) {
            nodes = nodes.filter((n) => !n.done);
        }
        const kept = new Set(nodes.map((n) => n.key));
        return {
            nodes,
            edges: this.allEdges.filter((e) => kept.has(e.from) && kept.has(e.to)),
        };
    }

    async render() {
        const { nodes, edges } = this.activeGraph();
        const source = buildSource(nodes, edges, this.showDesc);

        let svg;
        try {
            ({ svg } = await mermaid.render(`psGraph${renderSeq++}`, source));
        } catch (err) {
            this.canvas.innerHTML =
                `<p class="py-10 text-sm text-red-600">Diagramm konnte nicht gerendert werden.</p>`;
            console.error('[ps-diagram]', err);
            return;
        }

        this.canvas.innerHTML = svg;
        this.mapElements(nodes, edges);
        this.buildAdjacency(edges);
        this.wireInteraction(nodes);

        // A locked selection survives a re-render only if the node still exists.
        if (this.lockedKey && this.nodeEls.has(this.lockedKey)) {
            this.applyFocus(this.lockedKey);
        } else {
            this.lockedKey = null;
            this.updateResetButton();
        }
    }

    mapElements(nodes, edges) {
        this.nodeEls.clear();
        const doneKeys = new Set(nodes.filter((n) => n.done).map((n) => n.key));

        this.canvas.querySelectorAll('g.node').forEach((g) => {
            const m = /^flowchart-(.+)-\d+$/.exec(g.id);
            if (!m) return;
            const key = m[1];
            this.nodeEls.set(key, g);
            if (doneKeys.has(key)) g.classList.add('ps-done');
        });

        // Edge paths render in declaration order → index maps to the edge
        // model. Selector is resilient to mermaid's class naming; each matched
        // path gets our own ps-edge class so the dimming CSS doesn't depend on
        // mermaid internals.
        let paths = this.canvas.querySelectorAll('g.edgePaths path.flowchart-link');
        if (!paths.length) paths = this.canvas.querySelectorAll('g.edgePaths path');
        if (!paths.length) paths = this.canvas.querySelectorAll('path.flowchart-link');

        this.edgeEls = [];
        paths.forEach((el, i) => {
            const e = edges[i];
            if (!e) return;
            el.classList.add('ps-edge');
            this.edgeEls.push({ from: e.from, to: e.to, el });
        });
    }

    buildAdjacency(edges) {
        this.parents = new Map();
        this.children = new Map();
        for (const e of edges) {
            if (!this.children.has(e.from)) this.children.set(e.from, new Set());
            if (!this.parents.has(e.to)) this.parents.set(e.to, new Set());
            this.children.get(e.from).add(e.to);
            this.parents.get(e.to).add(e.from);
        }
    }

    // focus + all ancestors + all descendants
    chainOf(key) {
        const chain = new Set([key]);
        const walk = (start, map) => {
            const stack = [start];
            while (stack.length) {
                const cur = stack.pop();
                for (const next of map.get(cur) ?? []) {
                    if (!chain.has(next)) {
                        chain.add(next);
                        stack.push(next);
                    }
                }
            }
        };
        walk(key, this.parents);
        walk(key, this.children);
        return chain;
    }

    applyFocus(key) {
        const chain = this.chainOf(key);
        this.canvas.classList.add('has-focus');

        this.nodeEls.forEach((el, k) => el.classList.toggle('ps-hl', chain.has(k)));
        for (const { from, to, el } of this.edgeEls) {
            el.classList.toggle('ps-hl', chain.has(from) && chain.has(to));
        }
    }

    clearFocus() {
        this.canvas.classList.remove('has-focus');
        this.nodeEls.forEach((el) => el.classList.remove('ps-hl'));
        this.edgeEls.forEach(({ el }) => el.classList.remove('ps-hl'));
    }

    wireInteraction() {
        this.nodeEls.forEach((g, key) => {
            g.addEventListener('mouseenter', () => {
                if (!this.lockedKey) this.applyFocus(key);
            });
            g.addEventListener('mouseleave', () => {
                if (!this.lockedKey) this.clearFocus();
            });
            g.addEventListener('click', (ev) => {
                // Let the detail link, PR links (inline or corner badge) and the
                // review-claim button act without toggling the selection.
                if (ev.target.closest('a.detail, a.pr, a.pr-badge, .rv-claim-btn')) {
                    ev.stopPropagation();
                    return;
                }
                ev.stopPropagation();
                if (this.lockedKey === key) {
                    this.lockedKey = null;
                    this.clearFocus();
                } else {
                    this.lockedKey = key;
                    this.applyFocus(key);
                }
                this.updateResetButton();
            });
        });

        // Click into the empty canvas clears a locked selection.
        this.canvas.addEventListener('click', () => {
            if (this.lockedKey) {
                this.lockedKey = null;
                this.clearFocus();
                this.updateResetButton();
            }
        });
    }

    wireToolbar() {
        this.resetBtn = document.querySelector('[data-diagram-reset]');
        this.resetBtn?.addEventListener('click', () => {
            const hadPhase = this.phaseFilter !== null;
            this.lockedKey = null;
            this.phaseFilter = null;
            if (hadPhase) {
                this.render(); // bring back the other phases
            } else {
                this.clearFocus();
            }
            this.updatePhaseButtons();
            this.updateResetButton();
        });

        // Header phase chips: clicking one filters the graph to that phase;
        // clicking the active one again clears the filter.
        this.phaseButtons = Array.from(document.querySelectorAll('[data-diagram-phase]'));
        this.phaseButtons.forEach((btn) => {
            btn.addEventListener('click', () => {
                const id = btn.dataset.diagramPhase;
                this.phaseFilter = this.phaseFilter === id ? null : id;
                this.lockedKey = null; // a stale node focus makes no sense after refiltering
                this.render();
                this.updatePhaseButtons();
                this.updateResetButton();
            });
        });

        // Kurzbeschreibungen ein-/ausblenden; die Wahl wird pro Browser gemerkt.
        const descCb = document.querySelector('[data-diagram-desc]');
        if (descCb) {
            descCb.checked = this.showDesc; // gemerkte Wahl spiegeln
            descCb.addEventListener('change', (ev) => {
                this.showDesc = ev.target.checked;
                localStorage.setItem('ps-diagram-desc', this.showDesc ? '1' : '0');
                this.render();
            });
        }

        this.pngBtn = document.querySelector('[data-diagram-png]');
        this.pngBtn?.addEventListener('click', () => this.exportPng());

        // "Hide done" is only offered when there is done work to hide.
        if (this.allNodes.some((n) => n.done)) {
            const wrap = document.querySelector('[data-diagram-hidedone-wrap]');
            wrap?.removeAttribute('hidden');
            const cb = document.querySelector('[data-diagram-hidedone]');
            if (cb) {
                cb.checked = this.hideDone; // reflect the remembered choice
                cb.addEventListener('change', (ev) => {
                    this.hideDone = ev.target.checked;
                    localStorage.setItem('ps-diagram-hidedone', this.hideDone ? '1' : '0');
                    this.render();
                });
            }
        }
    }

    // Strg+Mausrad zoomt das Diagramm (Trackpad-Pinch setzt ctrlKey ebenfalls,
    // funktioniert also mit); normales Wheel scrollt weiter wie gewohnt.
    // preventDefault() auf dem nicht-passiven Listener unterdrückt dabei den
    // sonst greifenden Browser-Zoom der ganzen Seite. Skaliert wird per CSS-
    // Transform auf .ps-graph (transform-origin 0 0, siehe diagram.blade.php),
    // mit Scroll-Ausgleich, damit der Punkt unter dem Mauszeiger stehen bleibt.
    wireZoom() {
        const MIN_ZOOM = 0.25;
        const MAX_ZOOM = 3;

        this.root.addEventListener('wheel', (ev) => {
            if (!ev.ctrlKey) return;
            ev.preventDefault();

            const rect = this.root.getBoundingClientRect();
            const cursorX = ev.clientX - rect.left + this.root.scrollLeft;
            const cursorY = ev.clientY - rect.top + this.root.scrollTop;

            const factor = Math.exp(-ev.deltaY * 0.0015);
            const nextZoom = Math.min(MAX_ZOOM, Math.max(MIN_ZOOM, this.zoom * factor));
            const ratio = nextZoom / this.zoom;
            this.zoom = nextZoom;

            this.canvas.style.transform = `scale(${this.zoom})`;
            this.root.scrollLeft = cursorX * ratio - (ev.clientX - rect.left);
            this.root.scrollTop = cursorY * ratio - (ev.clientY - rect.top);
        }, { passive: false });
    }

    updateResetButton() {
        if (!this.resetBtn) return;
        this.resetBtn.toggleAttribute('hidden', !this.lockedKey && this.phaseFilter === null);
    }

    updatePhaseButtons() {
        this.phaseButtons?.forEach((btn) => {
            btn.toggleAttribute('data-active', btn.dataset.diagramPhase === this.phaseFilter);
        });
    }

    // Rasterise the currently rendered SVG (whatever is on screen, incl. the
    // hide-done state) to a PNG download. Everything is inlined and served as a
    // data URL, so the canvas stays untainted and toBlob works.
    async exportPng() {
        const svgEl = this.canvas.querySelector('svg');
        if (!svgEl) return;

        if (this.pngBtn) this.pngBtn.disabled = true;
        try {
            const clone = svgEl.cloneNode(true);

            // Intrinsic size from the viewBox (fallback: on-screen box). Pad on
            // all sides: the corner badges (Flaschenhals, PR-Nummer) overhang the
            // node and thus the tight mermaid viewBox — without the margin they
            // would be cropped in the raster at the graph's outer edges, unlike
            // on screen.
            const PAD = 16;
            const vb = svgEl.viewBox?.baseVal;
            const rect = svgEl.getBoundingClientRect();
            const baseW = (vb && vb.width) || rect.width || 1;
            const baseH = (vb && vb.height) || rect.height || 1;
            const minX = (vb && vb.x) || 0;
            const minY = (vb && vb.y) || 0;
            const width = Math.ceil(baseW + 2 * PAD);
            const height = Math.ceil(baseH + 2 * PAD);

            clone.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
            clone.setAttribute('viewBox', `${minX - PAD} ${minY - PAD} ${baseW + 2 * PAD} ${baseH + 2 * PAD}`);
            clone.setAttribute('width', width);
            clone.setAttribute('height', height);

            // Inline our label CSS so the foreignObject HTML keeps its styling
            // when the SVG is rasterised standalone. Plus the structural rules
            // collectDiagramCss() can't carry — they aren't scoped under
            // .ps-node, and in the standalone clone the .ps-graph wrapper is
            // gone: let foreignObjects overflow so the badges show in full, and
            // colour the arrow markers like the open edges (mirrors diagram.blade.php).
            const css = collectDiagramCss()
                + '\nsvg{overflow:visible}'
                + '\nforeignObject{overflow:visible}'
                + '\n.marker,marker path{fill:#64748b;stroke:#64748b}';
            const style = document.createElementNS('http://www.w3.org/2000/svg', 'style');
            style.textContent = css;
            clone.insertBefore(style, clone.firstChild);

            const svgStr = new XMLSerializer().serializeToString(clone);
            const svgUrl = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svgStr);

            // 2×–4× for a crisp raster on hi-dpi screens.
            const scale = Math.min(2 * (window.devicePixelRatio || 1), 4);

            const blob = await new Promise((resolve, reject) => {
                const img = new Image();
                img.onload = () => {
                    const canvas = document.createElement('canvas');
                    canvas.width = Math.max(1, Math.round(width * scale));
                    canvas.height = Math.max(1, Math.round(height * scale));
                    const ctx = canvas.getContext('2d');
                    ctx.fillStyle = '#ffffff';
                    ctx.fillRect(0, 0, canvas.width, canvas.height);
                    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                    canvas.toBlob((b) => (b ? resolve(b) : reject(new Error('toBlob failed'))), 'image/png');
                };
                img.onerror = () => reject(new Error('SVG konnte nicht geladen werden'));
                img.src = svgUrl;
            });

            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `diagramm-${this.name}.png`;
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
        } catch (err) {
            console.error('[ps-diagram] PNG-Export fehlgeschlagen', err);
            window.alert('PNG-Export fehlgeschlagen.');
        } finally {
            if (this.pngBtn) this.pngBtn.disabled = false;
        }
    }
}

document.querySelectorAll('[data-diagram]').forEach((root) => {
    new DependencyGraph(root).init();
});
