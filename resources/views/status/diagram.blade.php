<x-status-shell :project="$project" :active="$active">
    {{-- CI-Status-Teaser über der Diagramm-Card (blendet sich aus, sobald das
         Userscript läuft; sonst Einrichten-/Update-Hinweis). --}}
    <x-slot:beforeCard>
        <x-page-head :title="__('common.diagram')">
            <ul class="list-disc space-y-1 ps-4">
                <li><span class="font-medium">{{ __('status.dependency_diagram') }}</span>: {{ __('status.arrows_point_from_a_prerequisite_to_the') }}</li>
                <li>{{ __('status.node_color_icon_status_see_legend_thick') }}</li>
                <li>{{ __('status.edges_solid_open_dependency_light') }}</li>
                <li>{{ __('status.clicking_a_node_highlights_its_chain') }}</li>
            </ul>
        </x-page-head>
        @include('status.partials.ci-teaser')
    </x-slot>

    {{-- Schmale Phasen-Kopfzeile (Name + %); Details stehen im Summary-Tab.
         Klick auf eine Phase filtert das Diagramm auf ihre Tasks (diagram.js). --}}
    <div class="flex flex-wrap items-center gap-x-2 gap-y-1.5 mb-4">
        @foreach ($phases as $ph)
            <button type="button" data-diagram-phase="{{ $ph['id'] }}"
                    class="flex items-center gap-2 rounded-md bg-gray-50 dark:bg-gray-700/40 ring-1 ring-gray-100 dark:ring-gray-700 px-2.5 py-1"
                    title="{{ $ph['name'] }} — {{ $ph['pct'] }}% · {{ __('status.click_to_filter') }}">
                <span class="text-xs font-medium text-gray-600 dark:text-gray-400">{{ $ph['short'] }}</span>
                <span class="h-1.5 w-14 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                    <span class="block h-full rounded-full {{ $ph['pct'] >= 100 ? 'bg-green-600' : 'bg-indigo-500' }}" style="width: {{ $ph['pct'] }}%"></span>
                </span>
                <span class="text-[11px] tabular-nums text-gray-400 dark:text-gray-500">{{ $ph['pct'] }}%</span>
            </button>
        @endforeach
    </div>

    {{-- Legende: Mini-Knoten im echten Stil (Füllung + Rahmen + Icon) je Status,
         danach Kantenarten und Flaschenhals-Badge. Die Icon-Pfade spiegeln
         STATUS_ICONS in resources/js/diagram.js — bei Änderungen dort mitziehen. --}}
    @php
        $bottleneckIcon = '<path d="M6.5 7h11"/><path d="M6.5 17h11"/><path d="M6 20v-2a6 6 0 1 1 12 0v2a1 1 0 0 1 -1 1h-10a1 1 0 0 1 -1 -1z"/><path d="M6 4v2a6 6 0 1 0 12 0v-2a1 1 0 0 0 -1 -1h-10a1 1 0 0 0 -1 1z"/>';
        $lgSvg = fn ($paths) => '<svg class="ps-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$paths.'</svg>';
    @endphp
    <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
        {{-- Legende: je konfiguriertem Status ein Mini-Knoten in seiner Farbe + Icon. --}}
        <div class="ps-diagram-legend flex flex-wrap items-center gap-x-3 gap-y-1.5 text-xs text-gray-600 dark:text-gray-400">
            @foreach ($legend as $item)
                <span class="lg-item"><span class="lg-swatch tok-{{ $item['color'] }} cat-{{ $item['cat'] }}">{!! $lgSvg($item['icon']) !!}</span>{{ $item['label'] }}</span>
            @endforeach
            <span class="mx-1 h-4 w-px bg-gray-200 dark:bg-gray-700"></span>
            <span class="lg-item">
                <svg width="26" height="8" aria-hidden="true"><line x1="1" y1="4" x2="25" y2="4" stroke="#64748B" stroke-width="1.5"/></svg>
                {{ __('status.open_dependency') }}
            </span>
            <span class="lg-item">
                <svg width="26" height="8" aria-hidden="true"><line x1="1" y1="4" x2="25" y2="4" stroke="#CBD5E1" stroke-width="1" stroke-dasharray="4 4"/></svg>
                {{ __('status.satisfied') }}
            </span>
            <span class="lg-item"><span class="ps-bn">{!! $lgSvg($bottleneckIcon) !!}</span>{{ __('status.bottleneck') }}</span>
        </div>

        <div class="flex items-center gap-3">
            <button type="button" data-diagram-reset hidden
                    class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">{{ __('status.clear_selection') }}</button>
            <label data-diagram-desc-wrap
                   class="inline-flex cursor-pointer items-center gap-1.5 text-xs text-gray-600 dark:text-gray-400">
                <input type="checkbox" data-diagram-desc
                       class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-indigo-600 dark:text-indigo-400 focus:ring-indigo-500">
                {{ __('status.short_descriptions') }}
            </label>
            <label data-diagram-hidedone-wrap hidden
                   class="inline-flex cursor-pointer items-center gap-1.5 text-xs text-gray-600 dark:text-gray-400">
                <input type="checkbox" data-diagram-hidedone
                       class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-indigo-600 dark:text-indigo-400 focus:ring-indigo-500">
                {{ __('status.hide_done') }}
            </label>
            <button type="button" data-diagram-png
                    class="inline-flex items-center gap-1 rounded-md border border-gray-200 dark:border-gray-700 px-2 py-1 text-xs text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50 disabled:opacity-50">
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2"/><path d="M7 11l5 5l5 -5"/><path d="M12 4v12"/></svg>
                {{ __('status.as_png') }}
            </button>
        </div>
    </div>

    {{-- Graph-Zeichenfläche; diagram.js liest das Modell und rendert Mermaid hinein. --}}
    <div class="ps-diagram overflow-auto"
         data-diagram
         data-diagram-name="{{ $project->alias }}"
         data-graph='@json($graph, JSON_HEX_APOS | JSON_HEX_QUOT)'>
        <div class="ps-graph min-h-[280px]"></div>
        <p class="ps-diagram-empty hidden py-10 text-center text-sm text-gray-400 dark:text-gray-500">{{ __('status.no_open_prs') }}</p>
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
        .dark [data-diagram-phase]:hover { background-color: #374151; }
        .dark [data-diagram-phase][data-active] { background-color: rgb(99 102 241 / 0.15); }

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
        /* Flaschenhals-Badge-Farben (status-unabhängig). */
        .ps-node,
        .ps-diagram-legend { --bn-bg:#FEF3C7; --bn-border:#F59E0B; --bn-icon:#92400E; }

        .ps-node {
            position: relative;
            line-height: 1.25; text-align: center;
            padding: 5px 10px; border-radius: 8px;
            /* Farbe = tatsächlicher Status (Token, s.u.); neutraler Fallback. */
            background: var(--n-bg, #ffffff);
            border: 1.5px solid var(--n-border, transparent);
            color: var(--n-text, #3f3f46);
            --ps-sub: var(--n-sub, #71717a);
        }

        /* ── Status-Farbtoken (bg/border/text/sub) — geteilt von Knoten und
           Legenden-Swatch; helle Tönung analog zur Board-/Summary-Palette. Die
           Auswahl je Status trifft die Statusverwaltung (color_token). ── */
        .ps-node.tok-gray,    .ps-diagram-legend .tok-gray    { --n-bg:#F9FAFB; --n-border:#6B7280; --n-text:#1F2937; --n-sub:#4B5563; }
        .ps-node.tok-slate,   .ps-diagram-legend .tok-slate   { --n-bg:#F8FAFC; --n-border:#64748B; --n-text:#1E293B; --n-sub:#475569; }
        .ps-node.tok-indigo,  .ps-diagram-legend .tok-indigo  { --n-bg:#EEF2FF; --n-border:#6366F1; --n-text:#3730A3; --n-sub:#4F46E5; }
        .ps-node.tok-sky,     .ps-diagram-legend .tok-sky     { --n-bg:#F0F9FF; --n-border:#0EA5E9; --n-text:#075985; --n-sub:#0284C7; }
        .ps-node.tok-blue,    .ps-diagram-legend .tok-blue    { --n-bg:#EFF6FF; --n-border:#3B82F6; --n-text:#1E40AF; --n-sub:#2563EB; }
        .ps-node.tok-navy,    .ps-diagram-legend .tok-navy    { --n-bg:#EFF6FF; --n-border:#1D4ED8; --n-text:#1E3A8A; --n-sub:#1D4ED8; }
        .ps-node.tok-purple,  .ps-diagram-legend .tok-purple  { --n-bg:#FAF5FF; --n-border:#A855F7; --n-text:#6B21A8; --n-sub:#9333EA; }
        .ps-node.tok-green,   .ps-diagram-legend .tok-green   { --n-bg:#F0FDF4; --n-border:#22C55E; --n-text:#166534; --n-sub:#16A34A; }
        .ps-node.tok-emerald, .ps-diagram-legend .tok-emerald { --n-bg:#ECFDF5; --n-border:#10B981; --n-text:#065F46; --n-sub:#059669; }
        .ps-node.tok-teal,    .ps-diagram-legend .tok-teal    { --n-bg:#F0FDFA; --n-border:#14B8A6; --n-text:#115E59; --n-sub:#0D9488; }
        .ps-node.tok-rose,    .ps-diagram-legend .tok-rose    { --n-bg:#FFF1F2; --n-border:#F43F5E; --n-text:#9F1239; --n-sub:#E11D48; }
        .ps-node.tok-red,     .ps-diagram-legend .tok-red     { --n-bg:#FEF2F2; --n-border:#EF4444; --n-text:#991B1B; --n-sub:#DC2626; }
        .ps-node.tok-orange,  .ps-diagram-legend .tok-orange  { --n-bg:#FFF7ED; --n-border:#F97316; --n-text:#9A3412; --n-sub:#EA580C; }
        .ps-node.tok-amber,   .ps-diagram-legend .tok-amber   { --n-bg:#FFFBEB; --n-border:#F59E0B; --n-text:#92400E; --n-sub:#D97706; }

        /* Rahmen-Betonung/Stil = Aufmerksamkeit (nach Verhaltens-Kategorie);
           die Farbe stammt aus dem Status-Token. */
        .ps-node.cat-pickable, .ps-node.cat-inprogress, .ps-node.cat-concern { border-width: 2px; }
        .ps-node.cat-blocked { border-style: dashed; }
        .ps-node.cat-inreview { outline: 1px solid var(--n-border); outline-offset: 2px; }
        /* In Review MIT Reviewer: eigene Farbe (ich selbst = lila, jemand
           anderes = grün) — überschreibt das Status-Token. */
        .ps-node.cat-inreview.is-reviewed       { --n-bg:#F6F2FC; --n-border:#B18AE3; --n-text:#6B21A8; }
        .ps-node.cat-inreview.is-reviewed-other { --n-bg:#EDF7F2; --n-border:#7DAD9B; --n-text:#2F5D4C; --n-sub:#4C8571; }

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
            background: var(--n-bg);
            border: 1px solid var(--n-border);
            color: var(--n-text);
        }
        .ps-node .rv-claim-btn:hover { filter: brightness(0.96); }
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

        /* Review-Badge: runder Eck-Badge oben rechts (gleicher Stil wie Flaschenhals). */
        .ps-node .ps-rv {
            display: inline-flex; align-items: center; justify-content: center;
            position: absolute; top: -9px; right: -9px;
            width: 20px; height: 20px; border-radius: 9999px;
        }
        .ps-node .ps-rv .ps-ico { width: 12px; height: 12px; margin: 0; }
        .ps-node .ps-rv-approve { background: #dcfce7; border: 1px solid #16a34a; color: #16a34a; }
        .ps-node .ps-rv-changes { background: #fef3c7; border: 1px solid #d97706; color: #b45309; }
        /* Weicht einem anderen Eck-Badge oben rechts aus. */
        .ps-node .ps-rv--shift { right: 15px; }

        /* Legende: Mini-Knoten im echten Knotenstil (Farbregeln oben teilen sich
           Node und Legende über die gemeinsamen cat-*-Selektoren). */
        .ps-diagram-legend .lg-item { display: inline-flex; align-items: center; gap: 5px; }
        .ps-diagram-legend .lg-swatch { display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 17px; border-radius: 5px; background: var(--n-bg, #fff); border: 1px solid var(--n-border, #d4d4d8); color: var(--n-text, #52525b); }
        .ps-diagram-legend .lg-swatch .ps-ico { width: 12px; height: 12px; margin: 0; }
        .ps-diagram-legend .cat-done { opacity: .45; }
    </style>

    @vite('resources/js/diagram.js')
</x-status-shell>
