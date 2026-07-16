<x-status-shell :project="$project" :active="$active">
    {{-- Schmale Phasen-Kopfzeile (Name + %); Details stehen im Summary-Tab.
         Klick auf eine Phase filtert das Diagramm auf ihre Tasks (diagram.js). --}}
    <div class="flex flex-wrap items-center gap-x-2 gap-y-1.5 mb-4">
        @foreach ($phases as $ph)
            <button type="button" data-diagram-phase="{{ $ph['id'] }}"
                    class="flex items-center gap-2 rounded-md bg-gray-50 ring-1 ring-gray-100 px-2.5 py-1"
                    title="{{ $ph['name'] }} — {{ $ph['pct'] }}% · zum Filtern klicken">
                <span class="text-xs font-medium text-gray-600">{{ $ph['short'] }}</span>
                <span class="h-1.5 w-14 overflow-hidden rounded-full bg-gray-200">
                    <span class="block h-full rounded-full {{ $ph['pct'] >= 100 ? 'bg-green-600' : 'bg-indigo-500' }}" style="width: {{ $ph['pct'] }}%"></span>
                </span>
                <span class="text-[11px] tabular-nums text-gray-400">{{ $ph['pct'] }}%</span>
            </button>
        @endforeach
    </div>

    {{-- Legende: Mini-Knoten im echten Stil (Füllung + Rahmen + Icon) je Status,
         danach Kantenarten und Flaschenhals-Badge. Die Icon-Pfade spiegeln
         STATUS_ICONS in resources/js/diagram.js — bei Änderungen dort mitziehen. --}}
    @php
        $statusIcons = [
            'pickable'   => '<path d="M7 4v16l13 -8z"/>',
            'claimed'    => '<path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0"/><path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2"/>',
            'analyzing'  => '<path d="M10 10m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0"/><path d="M21 21l-6 -6"/>',
            'inprogress' => '<path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1"/><path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z"/><path d="M16 5l3 3"/>',
            'inreview'   => '<path d="M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0"/><path d="M21 12c-2.4 4 -5.4 6 -9 6c-3.6 0 -6.6 -2 -9 -6c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6"/>',
            'blocked'    => '<path d="M5 13a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v6a2 2 0 0 1 -2 2h-10a2 2 0 0 1 -2 -2z"/><path d="M11 16a1 1 0 1 0 2 0a1 1 0 0 0 -2 0"/><path d="M8 11v-4a4 4 0 1 1 8 0v4"/>',
            'concern'    => '<path d="M12 9v4"/><path d="M10.24 3.957l-8.422 14.06a1.989 1.989 0 0 0 1.7 2.983h16.845a1.989 1.989 0 0 0 1.7 -2.983l-8.423 -14.06a1.989 1.989 0 0 0 -3.4 0z"/><path d="M12 16h.01"/>',
            'done'       => '<path d="M5 12l5 5l10 -10"/>',
        ];
        $bottleneckIcon = '<path d="M6.5 7h11"/><path d="M6.5 17h11"/><path d="M6 20v-2a6 6 0 1 1 12 0v2a1 1 0 0 1 -1 1h-10a1 1 0 0 1 -1 -1z"/><path d="M6 4v2a6 6 0 1 0 12 0v-2a1 1 0 0 0 -1 -1h-10a1 1 0 0 0 -1 1z"/>';
        $legendItems = [
            ['pickable', 'pickbar'], ['claimed', 'beansprucht'], ['analyzing', 'in Analyse'],
            ['inprogress', 'in Arbeit'], ['inreview', 'in Review'], ['blocked', 'blockiert'],
            ['concern', 'Problem'], ['done', 'erledigt'],
        ];
        $lgSvg = fn ($paths) => '<svg class="ps-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$paths.'</svg>';
    @endphp
    <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
        <div class="ps-diagram-legend flex flex-wrap items-center gap-x-3 gap-y-1.5 text-xs text-gray-600">
            @foreach ($legendItems as [$cat, $label])
                <span class="lg-item"><span class="lg-swatch cat-{{ $cat }}">{!! $lgSvg($statusIcons[$cat]) !!}</span>{{ $label }}</span>
            @endforeach
            <span class="mx-1 h-4 w-px bg-gray-200"></span>
            <span class="lg-item">
                <svg width="26" height="8" aria-hidden="true"><line x1="1" y1="4" x2="25" y2="4" stroke="#64748B" stroke-width="1.5"/></svg>
                offene Abhängigkeit
            </span>
            <span class="lg-item">
                <svg width="26" height="8" aria-hidden="true"><line x1="1" y1="4" x2="25" y2="4" stroke="#CBD5E1" stroke-width="1" stroke-dasharray="4 4"/></svg>
                erfüllt
            </span>
            <span class="lg-item"><span class="ps-bn">{!! $lgSvg($bottleneckIcon) !!}</span>Flaschenhals</span>
        </div>

        <div class="flex items-center gap-3">
            <button type="button" data-diagram-reset hidden
                    class="text-xs text-indigo-600 hover:underline">Auswahl aufheben</button>
            <label data-diagram-desc-wrap
                   class="inline-flex cursor-pointer items-center gap-1.5 text-xs text-gray-600">
                <input type="checkbox" data-diagram-desc
                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                Kurzbeschreibungen
            </label>
            <label data-diagram-hidedone-wrap hidden
                   class="inline-flex cursor-pointer items-center gap-1.5 text-xs text-gray-600">
                <input type="checkbox" data-diagram-hidedone
                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                Erledigte ausblenden
            </label>
            <button type="button" data-diagram-png
                    class="inline-flex items-center gap-1 rounded-md border border-gray-200 px-2 py-1 text-xs text-gray-600 hover:bg-gray-50 disabled:opacity-50">
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2"/><path d="M7 11l5 5l5 -5"/><path d="M12 4v12"/></svg>
                Als PNG
            </button>
        </div>
    </div>

    {{-- Graph-Zeichenfläche; diagram.js liest das Modell und rendert Mermaid hinein. --}}
    <div class="ps-diagram overflow-auto"
         data-diagram
         data-diagram-name="{{ $project->alias }}"
         data-graph='@json($graph, JSON_HEX_APOS | JSON_HEX_QUOT)'>
        <div class="ps-graph min-h-[280px]"></div>
        <p class="ps-diagram-empty hidden py-10 text-center text-sm text-gray-400">Keine offenen PRs.</p>
    </div>

    <style>
        /* Phasen-Chip als Filter-Button: dezenter Hover, deutlicher Aktiv-Zustand
           (indigo-Ring + hellblauer Grund). Attribut-Selektor gewinnt über die
           Tailwind-Ring/Background-Utilities des Chips. */
        [data-diagram-phase] { cursor: pointer; transition: background-color .12s ease, box-shadow .12s ease; }
        [data-diagram-phase]:hover { background-color: #f3f4f6; }
        [data-diagram-phase][data-active] {
            background-color: #eef2ff;
            box-shadow: inset 0 0 0 1px #6366f1;
        }

        /* Zentriert das SVG nur, solange es in den Container passt; ist es
           breiter (useMaxWidth:false in diagram.js lässt es in echter Größe
           rendern), scrollt .ps-diagram (overflow-auto) bis an beide Ränder.
           Mit flex+justify-center (früher hier) klemmt der Browser bei
           zentriertem Overflow beide Seiten gleichermaßen ab — mit
           margin:auto auf einem Block-Element passiert das nicht.
           overflow:visible, damit die Eck-Badges (Flaschenhals, PR-Nummer
           erledigter Knoten), die bewusst über den Knotenrand hinausragen,
           nicht am SVG-Rand gekappt werden. */
        .ps-graph svg { display: block; margin: 0 auto; overflow: visible; }
        /* Strg+Mausrad-Zoom (diagram.js: wireZoom) skaliert per Transform ab
           der oberen linken Ecke, damit die Scroll-Ausgleichsrechnung dort
           einfach bleibt. */
        .ps-graph { transform-origin: 0 0; }
        /* Klickbarer Knoten; die Chain-Hervorhebung dimmt alles Übrige stark. */
        .ps-graph .node { cursor: pointer; }
        .ps-graph foreignObject,
        .ps-graph foreignObject > div { overflow: visible; }
        .ps-graph.has-focus .node,
        .ps-graph.has-focus .ps-edge { transition: opacity .12s ease; }
        .ps-graph.has-focus .node:not(.ps-hl) { opacity: .12; }
        .ps-graph.has-focus .ps-edge:not(.ps-hl) { opacity: .07; }
        .ps-graph .node.ps-done { opacity: .45; }
        .ps-graph.has-focus .node.ps-done.ps-hl { opacity: .8; }
        /* Pfeilspitzen in der Linienfarbe (statt mermaid-Standardgrau). Die Kanten
           selbst kommen als Inline-linkStyle aus diagram.js (offen: satt, erfüllt:
           hell gestrichelt). */
        .ps-graph .marker,
        .ps-graph marker path { fill: #64748b; stroke: #64748b; }
        /* ── Knoten: Farbe = Phasen-/Statuskategorie, Icon = exakter Status,
           Rahmendicke = Aufmerksamkeit (2px nur bei pickbar / in Arbeit / Problem).
           Alle Selektoren enthalten „ps-node", damit collectDiagramCss() sie in
           den PNG-Export übernimmt. Die Farbtokens stehen zusätzlich auf der
           Legende, damit deren Mini-Knoten exakt dieselben Werte nutzen. ── */
        .ps-node,
        .ps-diagram-legend {
            --status-pickable-bg:#F0FDF4;    --status-pickable-border:#16A34A;   --status-pickable-text:#166534;   --status-pickable-sub:#16A34A;
            --status-claimed-bg:#E0F2FE;     --status-claimed-border:#7DD3FC;    --status-claimed-text:#075985;    --status-claimed-sub:#0369A1;
            --status-analyzing-bg:#E0F2FE;   --status-analyzing-border:#7DD3FC;  --status-analyzing-text:#075985;  --status-analyzing-sub:#0369A1;
            --status-inprogress-bg:#E0F2FE;  --status-inprogress-border:#2563EB; --status-inprogress-text:#1E40AF; --status-inprogress-sub:#2563EB;
            --status-inreview-bg:#FAF5FF;    --status-inreview-border:#A855F7;   --status-inreview-text:#6B21A8;   --status-inreview-sub:#9333EA;   --status-inreview-outline:#D8B4FE;
            --status-blocked-bg:#FCFCFD;     --status-blocked-border:#C4C4CC;    --status-blocked-text:#52525B;    --status-blocked-sub:#71717A;
            --status-concern-bg:#FEF2F2;     --status-concern-border:#DC2626;    --status-concern-text:#991B1B;    --status-concern-sub:#B91C1C;
            --status-done-bg:#F5F5F5;        --status-done-border:#D4D4D8;       --status-done-text:#52525B;       --status-done-sub:#71717A;
            --bn-bg:#FEF3C7; --bn-border:#F59E0B; --bn-icon:#92400E;
        }

        .ps-node {
            position: relative;
            line-height: 1.25; text-align: center;
            padding: 5px 10px; border-radius: 8px;
            background: #ffffff; border: 1px solid transparent;
        }

        .ps-node.cat-pickable,   .ps-diagram-legend .cat-pickable   { background:var(--status-pickable-bg);   border:2px solid var(--status-pickable-border);   color:var(--status-pickable-text);   --ps-sub:var(--status-pickable-sub); }
        .ps-node.cat-claimed,    .ps-diagram-legend .cat-claimed    { background:var(--status-claimed-bg);    border:1px solid var(--status-claimed-border);    color:var(--status-claimed-text);    --ps-sub:var(--status-claimed-sub); }
        .ps-node.cat-analyzing,  .ps-diagram-legend .cat-analyzing  { background:var(--status-analyzing-bg);  border:1px solid var(--status-analyzing-border);  color:var(--status-analyzing-text);  --ps-sub:var(--status-analyzing-sub); }
        .ps-node.cat-inprogress, .ps-diagram-legend .cat-inprogress { background:var(--status-inprogress-bg); border:2px solid var(--status-inprogress-border); color:var(--status-inprogress-text); --ps-sub:var(--status-inprogress-sub); }
        .ps-node.cat-inreview,   .ps-diagram-legend .cat-inreview   { background:var(--status-inreview-bg);   border:1px solid var(--status-inreview-border);   color:var(--status-inreview-text);   --ps-sub:var(--status-inreview-sub); outline:1px solid var(--status-inreview-outline); outline-offset:2px; }
        /* In Review MIT zugewiesenem Reviewer: eigener Ton (nicht in der
           Legende). Rahmen #B18AE3 auf hellem Grund #F6F2FC; Text/Unterton
           erben vom In-Review-Basiston. */
        .ps-node.cat-inreview.is-reviewed { background:#F6F2FC; border-color:#B18AE3; outline-color:#B18AE3; }
        .ps-node.cat-blocked,    .ps-diagram-legend .cat-blocked    { background:var(--status-blocked-bg);    border:1px dashed var(--status-blocked-border);   color:var(--status-blocked-text);    --ps-sub:var(--status-blocked-sub); }
        .ps-node.cat-concern,    .ps-diagram-legend .cat-concern    { background:var(--status-concern-bg);    border:2px solid var(--status-concern-border);    color:var(--status-concern-text);    --ps-sub:var(--status-concern-sub); }
        .ps-node.cat-done,       .ps-diagram-legend .cat-done       { background:var(--status-done-bg);       border:1px solid var(--status-done-border);       color:var(--status-done-text);       --ps-sub:var(--status-done-sub); }

        /* Icon links vor dem Titel, erbt die Textfarbe. */
        .ps-node .ps-ico { width: 14px; height: 14px; display: inline-block; vertical-align: -0.2em; margin-right: 4px; }

        .ps-node .t { font-weight: 500; font-size: 12px; white-space: nowrap; }
        /* Task-Titel verlinkt zur Detailansicht; Kategorie-Textfarbe bleibt erhalten. */
        .ps-node .t .detail { color: inherit; text-decoration: none; cursor: pointer; }
        .ps-node .t .detail:hover { text-decoration: underline; }
        .ps-node .t .pr { font-weight: 400; text-decoration: none; }
        .ps-node .t .pr:hover { text-decoration: underline; }
        .ps-node .t .who { font-weight: 400; color: var(--ps-sub); }
        .ps-node .s { font-size: 10px; margin-top: 2px; color: var(--ps-sub); }
        .ps-node .s .ps-ico { width: 11px; height: 11px; margin-right: 2px; vertical-align: -0.1em; }
        /* Problem-Grund: normale Schrift, erbt die Concern-Textfarbe (#991B1B). */
        .ps-node .r { font-size: 10px; margin-top: 2px; font-style: normal; }
        /* Reviewer-Zeile (in Review): kursiv, gedämpfte Status-Unterton-Farbe.
           Der große obere Abstand setzt eine Leerzeile vor „Reviewed by". */
        .ps-node .rv { font-size: 10px; margin-top: 12px; font-style: italic; color: var(--ps-sub); overflow-wrap: anywhere; }
        /* „claim review"-Button, wenn noch kein Reviewer gesetzt ist: schmales
           Pill im Review-Ton, das den aktiven Nutzer als Reviewer einträgt.
           Gleiche Leerzeile davor wie bei der Reviewer-Zeile. */
        .ps-node .rv-claim { margin-top: 12px; }
        .ps-node .rv-claim-btn {
            font-size: 10px; line-height: 1; cursor: pointer;
            padding: 2px 8px; border-radius: 9999px;
            background: var(--status-inreview-bg);
            border: 1px solid var(--status-inreview-border);
            color: var(--status-inreview-text);
        }
        .ps-node .rv-claim-btn:hover { background: var(--status-inreview-outline); }
        /* Optionale Kurzbeschreibung (Checkbox in der Toolbar): mehrzeilig, in der
           Breite gedeckelt, damit der Knoten nicht auseinanderläuft. */
        .ps-node .d {
            font-size: 10px; opacity: .75; margin-top: 3px;
            max-width: 220px; white-space: normal; line-height: 1.3;
            /* Lange Tokens ohne Leerzeichen (z. B. Pfade wie A/B/C) sonst über
               den Kachelrand hinaus — hart umbrechen statt überlaufen. */
            overflow-wrap: anywhere; word-break: break-word;
        }

        /* PR-Nummer erledigter Knoten als gedämpftes Pill-Badge, oben rechts
           halb überlappend. Reservierter rechter Rand hält den Titel frei. */
        .ps-node.is-done { padding-right: 12px; }
        .ps-node.has-bn { padding-right: 12px; }
        .ps-node .pr-badge {
            position: absolute; top: -8px; right: -9px;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 12px; line-height: 15px; white-space: nowrap;
            padding: 0 6px; border-radius: 9999px;
            background: #f3f4f6; color: #6b7280; border: 1px solid #9ca3af;
            text-decoration: none;
        }
        .ps-node .pr-badge:hover { background: #e5e7eb; color: #4b5563; }

        /* Flaschenhals-Badge: runder Eck-Badge oben rechts (ersetzt das 🚧-Emoji). */
        .ps-node .ps-bn,
        .ps-diagram-legend .ps-bn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 20px; height: 20px; border-radius: 9999px;
            background: var(--bn-bg); border: 1px solid var(--bn-border); color: var(--bn-icon);
        }
        .ps-node .ps-bn { position: absolute; top: -9px; right: -9px; }
        .ps-node .ps-bn .ps-ico,
        .ps-diagram-legend .ps-bn .ps-ico { width: 12px; height: 12px; margin: 0; }

        /* Legende: Mini-Knoten im echten Knotenstil (Farbregeln oben teilen sich
           Node und Legende über die gemeinsamen cat-*-Selektoren). */
        .ps-diagram-legend .lg-item { display: inline-flex; align-items: center; gap: 5px; }
        .ps-diagram-legend .lg-swatch { display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 17px; border-radius: 5px; }
        .ps-diagram-legend .lg-swatch .ps-ico { width: 12px; height: 12px; margin: 0; }
        .ps-diagram-legend .cat-done { opacity: .45; }
    </style>

    @vite('resources/js/diagram.js')
</x-status-shell>
