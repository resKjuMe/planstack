import mermaid from 'mermaid';
import elkLayouts from '@mermaid-js/layout-elk';

// ELK statt dagre: dagre legt unabhängige Tasks in eine einzige, sehr breite Zeile.
// ELK packt breite Graphen kompakt in mehrere Reihen. In mermaid v11 ist ELK ein
// separates Paket, das erst registriert werden muss.
mermaid.registerLayoutLoaders(elkLayouts);

mermaid.initialize({
    startOnLoad: false,
    securityLevel: 'loose',
    theme: 'default',
    layout: 'elk',
    maxTextSize: Infinity,
    maxEdges: Infinity,
    flowchart: {
        htmlLabels: true,
        curve: 'basis',
        nodeSpacing: 70,
        rankSpacing: 120,
        padding: 8,
        useMaxWidth: false,
    },
});

const CLASS_DEFS = 'classDef plain fill:transparent,stroke:none;';

// Tabler-Outline-Icon-Pfade je Kategorie (Fallback, wenn ein Status kein eigenes
// Icon trägt). Der Regelfall nutzt das konfigurierte Status-Icon (n.icon).
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
const REVIEW_APPROVE_ICON = '<path d="M5 12l5 5l10 -10"/>';
const REVIEW_CHANGES_ICON = '<path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.24 3.957l-8.422 14.06a1.989 1.989 0 0 0 1.7 2.983h16.845a1.989 1.989 0 0 0 1.7 -2.983l-8.423 -14.06a1.989 1.989 0 0 0 -3.4 0z"/>';

function svgIcon(paths) {
    return `<svg class='ps-ico' xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' aria-hidden='true'>${paths}</svg>`;
}

// CI-Rollup-Icon vor der PR-Nummer — gleiche Logik wie auf der Board-Karte:
// SUCCESS=Haken (grün), FAILURE/ERROR=Kreuz (rot), PENDING/EXPECTED=Uhr (gelb),
// sonst/null=Fragezeichen (grau, „unbekannt"). Farbe via CSS-Klasse (.ps-ci-*).
const CI_ICON = {
    SUCCESS: { cls: 'ps-ci-success', paths: '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4 -4"/>' },
    FAILURE: { cls: 'ps-ci-failure', paths: '<circle cx="12" cy="12" r="10"/><path d="m15 9 -6 6"/><path d="m9 9 6 6"/>' },
    ERROR: { cls: 'ps-ci-failure', paths: '<circle cx="12" cy="12" r="10"/><path d="m15 9 -6 6"/><path d="m9 9 6 6"/>' },
    PENDING: { cls: 'ps-ci-pending', paths: '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>' },
    EXPECTED: { cls: 'ps-ci-pending', paths: '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>' },
};
const CI_UNKNOWN = { cls: 'ps-ci-unknown', paths: '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.82 1c0 2 -3 3 -3 3"/><path d="M12 17h.01"/>' };

function ciIcon(status) {
    const meta = CI_ICON[status] || CI_UNKNOWN;
    return `<span class='ps-ci ${meta.cls}'>${svgIcon(meta.paths)}</span>`;
}

function initials(name) {
    return String(name).trim().split(/\s+/).filter(Boolean).slice(0, 2)
        .map((w) => w[0].toUpperCase()).join('');
}

const EDGE_OPEN = 'stroke:#64748b,stroke-width:1.5px';
const EDGE_MET = 'stroke:#cbd5e1,stroke-width:1px,stroke-dasharray:4 4';

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

function collectDiagramCss() {
    let css = '';
    for (const sheet of document.styleSheets) {
        let rules;
        try {
            rules = sheet.cssRules;
        } catch {
            continue;
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
    let reviewed = '';
    if (n.cat === 'inreview' && n.reviewedBy) {
        reviewed = n.reviewedByMe ? ' is-reviewed' : ' is-reviewed-other';
    }
    const cls = `ps-node cat-${n.cat} tok-${n.color || 'gray'}${reviewed}${n.done ? ' is-done' : ''}${n.bottleneck ? ' has-bn' : ''}${n.reviewRecommendation ? ' has-rv' : ''}`;
    const parts = [`<div class='${cls}'>`];

    let title = n.url
        ? `<a class='detail' href='${esc(n.url)}'>${esc(n.name)}</a>`
        : esc(n.name);
    if (n.pr && !n.done) {
        title += ' ';
        title += ciIcon(n.ciStatus); // CI-Status vor der PR-Nummer
        title += n.prUrl
            ? `<a class='pr' href='${esc(n.prUrl)}' target='_blank' rel='noopener'>#${esc(n.pr)}</a>`
            : `<span class='pr'>#${esc(n.pr)}</span>`;
    }
    if (n.claimer) {
        title += ` <span class='who'>· ${esc(initials(n.claimer))}</span>`;
    }
    parts.push(`<div class='t'>${svgIcon(n.icon || STATUS_ICONS[n.cat] || '')}${title}</div>`);

    parts.push(`<div class='s'>${subtitle(n)}</div>`);

    if (showDesc && n.summary) {
        parts.push(`<div class='d'>${esc(truncate(n.summary, 120))}</div>`);
    }

    if (n.cat === 'concern' && n.reason) {
        parts.push(`<div class='r'>${esc(truncate(n.reason))}</div>`);
    }

    if (n.reviewedBy) {
        parts.push(`<div class='rv'>Reviewed by ${esc(n.reviewedBy)}</div>`);
    } else if (n.cat === 'inreview' && n.reviewClaimUrl) {
        parts.push(
            `<form class='rv-claim' method='POST' action='${esc(n.reviewClaimUrl)}'>`
            + `<input type='hidden' name='_token' value='${esc(CSRF_TOKEN)}'>`
            + `<button type='submit' class='rv-claim-btn'>claim review</button>`
            + '</form>'
        );
    }

    if (n.done && n.pr) {
        const badge = ciIcon(n.ciStatus) + `#${esc(n.pr)}`; // CI-Status vor der PR-Nummer
        parts.push(n.prUrl
            ? `<a class='pr-badge' href='${esc(n.prUrl)}' target='_blank' rel='noopener'>${badge}</a>`
            : `<span class='pr-badge'>${badge}</span>`);
    }

    if (n.bottleneck) {
        const cnt = n.directDependents ?? n.dependents ?? 0;
        parts.push(`<span class='ps-bn' title='Flaschenhals · blockiert ${cnt} PR${cnt === 1 ? '' : 's'}'>${svgIcon(BOTTLENECK_ICON)}</span>`);
    }

    if (n.reviewRecommendation) {
        // Review-Empfehlung sitzt in der oberen LINKEN Ecke, damit sie nie mit
        // PR-Badge/Flaschenhals (obere rechte Ecke) kollidiert — unabhängig von der
        // Breite der PR-Nummer.
        const approve = n.reviewRecommendation === 'APPROVE';
        const cls2 = approve ? 'ps-rv ps-rv-approve' : 'ps-rv ps-rv-changes';
        const label = approve ? 'Review: genehmigt' : 'Review: Änderungen erforderlich';
        const icon = approve ? REVIEW_APPROVE_ICON : REVIEW_CHANGES_ICON;
        parts.push(`<span class='${cls2}' title='${label}'>${svgIcon(icon)}</span>`);
    }

    parts.push('</div>');
    return parts.join('');
}

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

/**
 * Rendert das Abhängigkeitsdiagramm (Mermaid) in eine Zeichenfläche und verdrahtet
 * Highlight-Interaktion, Strg+Mausrad-Zoom und PNG-Export. Vom Framework entkoppelt:
 * Daten + Optionen kommen über update(); die Toolbar/Phasen-Chips/Legende liefert
 * die React-View (DiagramView). Wird als Modul lazy geladen, damit Mermaid nur beim
 * Öffnen des Diagramm-Tabs in den Bundle kommt.
 */
export class DependencyGraph {
    constructor(root, { name = 'diagramm', onLockChange = () => {} } = {}) {
        this.root = root;
        this.canvas = root.querySelector('.ps-graph');
        this.emptyEl = root.querySelector('.ps-diagram-empty');
        this.name = name;
        this.onLockChange = onLockChange;

        this.allNodes = [];
        this.allEdges = [];
        this.hiddenStatuses = new Set();
        this.showDesc = false;
        this.phaseFilter = null;
        this.lockedKey = null;

        this.nodeEls = new Map();
        this.edgeEls = [];
        this.parents = new Map();
        this.children = new Map();

        this.zoom = 1;
        this._zoomWired = false;
        this._canvasClickWired = false;
    }

    /**
     * Daten + Optionen setzen und neu rendern. Wird von der React-View bei jeder
     * Store- oder Optionsänderung aufgerufen.
     */
    async update({ nodes, edges, hiddenStatuses, showDesc, phaseFilter }) {
        this.allNodes = nodes || [];
        this.allEdges = edges || [];
        this.hiddenStatuses = new Set(hiddenStatuses || []);
        this.showDesc = !!showDesc;
        this.phaseFilter = phaseFilter ?? null;

        if (!this._zoomWired) {
            this.wireZoom();
            this.wireCanvasClick();
            this._zoomWired = true;
        }

        if (this.allNodes.length === 0) {
            this.canvas.classList.add('hidden');
            this.emptyEl?.classList.remove('hidden');
            return;
        }
        this.canvas.classList.remove('hidden');
        this.emptyEl?.classList.add('hidden');

        await this.render();
    }

    clearLock() {
        this.lockedKey = null;
        this.clearFocus();
        this.onLockChange(false);
    }

    activeGraph() {
        let nodes = this.allNodes;
        if (this.phaseFilter !== null) {
            nodes = nodes.filter((n) => String(n.phase) === String(this.phaseFilter));
        }
        if (this.hiddenStatuses.size) {
            nodes = nodes.filter((n) => !this.hiddenStatuses.has(n.statusKey));
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
        this.wireInteraction();

        if (this.lockedKey && this.nodeEls.has(this.lockedKey)) {
            this.applyFocus(this.lockedKey);
        } else {
            this.lockedKey = null;
        }
        this.onLockChange(this.lockedKey !== null);
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
                this.onLockChange(this.lockedKey !== null);
            });
        });
    }

    // Klick in die leere Zeichenfläche hebt eine gesperrte Auswahl auf. Einmalig
    // gebunden (die Canvas bleibt über Re-Renders erhalten, nur innerHTML wechselt).
    wireCanvasClick() {
        if (this._canvasClickWired) return;
        this._canvasClickWired = true;
        this.canvas.addEventListener('click', () => {
            if (this.lockedKey) {
                this.lockedKey = null;
                this.clearFocus();
                this.onLockChange(false);
            }
        });
    }

    // Strg+Mausrad zoomt (Trackpad-Pinch setzt ctrlKey ebenfalls). Skaliert per
    // CSS-Transform auf .ps-graph (transform-origin 0 0) mit Scroll-Ausgleich.
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

    async exportPng() {
        const svgEl = this.canvas.querySelector('svg');
        if (!svgEl) return;

        const clone = svgEl.cloneNode(true);

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

        const css = collectDiagramCss()
            + '\nsvg{overflow:visible}'
            + '\nforeignObject{overflow:visible}'
            + '\n.marker,marker path{fill:#64748b;stroke:#64748b}';
        const style = document.createElementNS('http://www.w3.org/2000/svg', 'style');
        style.textContent = css;
        clone.insertBefore(style, clone.firstChild);

        const svgStr = new XMLSerializer().serializeToString(clone);
        const svgUrl = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svgStr);

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
    }
}
