// Clientseitige Ableitung des Abhängigkeitsdiagramm-Modells aus den rohen
// Store-Daten (Tasks + Phasen + Org-Status-Konfiguration). Portiert
// ProjectDiagramController::buildGraph/phaseHeader/buildLegend/nodeCategory samt
// transitiver Abhängigkeits- und Bottleneck-Berechnung — damit Board, Summary UND
// Diagramm aus derselben einmalig geladenen Datenbasis leben und live (per
// entity-changed) partiell aktualisiert werden. Reine Funktion, keine Netzzugriffe.
//
// Die Feldnamen der Knoten spiegeln das bisherige Server-Modell, sodass der
// Renderer (resources/js/diagram/DependencyGraph.js) unverändert bleibt.

// Verhaltens-Kategorie eines Status (Rahmen/Claimer/Review — nicht die Farbe).
function categoryOf(role, kind) {
    switch (role) {
        case 'COMPLETED':
        case 'MERGED':
            return 'done';
        case 'CONCERNED':
            return 'concern';
        case 'PICKABLE':
            return 'pickable';
        case 'CLAIMED':
            return 'claimed';
        case 'ANALYZING':
            return 'analyzing';
        case 'IN_PROGRESS':
            return 'inprogress';
        case 'REVIEWABLE':
        case 'IN_REVIEW':
            // REVIEWABLE (Pool REVIEWBAR, wartet auf Reviewer) und IN_REVIEW (in
            // Review) teilen sich die Review-Darstellung inkl. „claim review"-Button;
            // Farbe/Label kommen weiterhin aus der Status-Config und unterscheiden
            // die beiden Spalten.
            return 'inreview';
        case 'BLOCKED':
            return 'blocked';
        default:
            switch (kind) {
                case 'done':
                    return 'done';
                case 'exception':
                    return 'concern';
                case 'review':
                case 'active':
                    return 'inprogress';
                default:
                    return 'pickable';
            }
    }
}

// Transitive Nachfolger-Anzahl (iterativ + visited → zyklensicher).
function descendantCount(id, children) {
    const seen = new Set();
    const stack = [...(children[id] || [])];
    while (stack.length) {
        const cur = stack.pop();
        if (seen.has(cur)) continue;
        seen.add(cur);
        for (const next of children[cur] || []) {
            if (!seen.has(next)) stack.push(next);
        }
    }
    return seen.size;
}

/**
 * @param {object} args
 * @param {Array}  args.tasks                 rohe API-Tasks (TaskResource fields=full)
 * @param {Array}  args.phases                [{id, name, position}]
 * @param {object} args.statusConfig          { statuses, roleKey }
 * @param {number} args.currentUserId
 * @param {string} args.taskUrlTemplate       mit __ID__
 * @param {string} args.reviewClaimUrlTemplate mit __ID__
 * @returns {{nodes: Array, edges: Array, phases: Array, legend: Array}}
 */
export function deriveDiagram({
    tasks,
    phases,
    statusConfig,
    currentUserId,
    taskUrlTemplate,
    reviewClaimUrlTemplate,
}) {
    const roleKey = statusConfig?.roleKey || {};
    const statuses = statusConfig?.statuses || [];
    const byKey = new Map(statuses.map((s) => [s.key, s]));
    const byId = new Map(tasks.map((t) => [t.id, t]));

    const url = (tpl, id) => (tpl ? tpl.replace('__ID__', String(id)) : null);
    const displayKeyOf = (t) => roleKey[t.display_status] || t.display_status;
    const statusOf = (t) => byKey.get(displayKeyOf(t));
    const isDone = (t) => !!statusOf(t)?.counts_as_done;
    const isDelivered = (t) => t.pr_number != null || isDone(t);
    const sp = (t) => Number(t.effort?.story_points || 0);

    // Stabile, mermaid-sichere Knoten-Keys aus dem Task-Namen (Präfix 'n').
    const keys = {};
    const used = new Set();
    for (const t of tasks) {
        let safe = 'n' + (String(t.name).replace(/[^A-Za-z0-9]/g, '') || 'N');
        while (used.has(safe)) safe += 'x';
        used.add(safe);
        keys[t.id] = safe;
    }

    // children[parentId] = [childId] über den gezeichneten Satz (für transitive
    // Nachfolger + direkte Abhängige).
    const children = {};
    for (const t of tasks) {
        for (const pre of t.prerequisites || []) {
            if (keys[pre.id] != null) {
                (children[pre.id] ||= []).push(t.id);
            }
        }
    }

    const transitive = {};
    for (const t of tasks) transitive[t.id] = descendantCount(t.id, children);

    // Bottleneck-Schwelle: Mittelwert + eine Standardabweichung (min. 2).
    const vals = Object.values(transitive);
    const n = vals.length;
    const avg = n ? vals.reduce((a, b) => a + b, 0) / n : 0;
    const variance = n ? vals.reduce((a, v) => a + (v - avg) ** 2, 0) / n : 0;
    const threshold = avg + Math.sqrt(variance);

    const nodes = tasks.map((t) => {
        const st = statusOf(t);
        const cat = categoryOf(st?.role, st?.kind);
        const prereqs = t.prerequisites || [];
        const depTotal = prereqs.length;
        const depMet = prereqs.filter((p) => {
            const parent = byId.get(p.id);
            return parent ? isDelivered(parent) : false;
        }).length;
        const reason = t.concern?.summary || t.concern?.blocker || null;
        const direct = (children[t.id] || []).length;

        return {
            key: keys[t.id],
            name: t.name,
            summary: t.summary,
            url: url(taskUrlTemplate, t.id),
            cat,
            color: st?.color_token,
            icon: st?.icon || null,
            phase: t.phase_id,
            done: isDone(t),
            statusKey: st?.key || null,
            ciStatus: t.pr_ci_status ?? null,
            mergeable: t.pr_mergeable ?? null,
            sp: sp(t),
            files: t.affected_files ?? null,
            pr: t.pr_number,
            prUrl: t.pr_url,
            statusLabel: st?.label,
            unlocks: Number(t.unlocks || 0),
            depOpen: depTotal - depMet,
            depTotal,
            depMet,
            reason: cat === 'concern' ? reason : null,
            reviewedBy: t.reviewed_by_name || null,
            reviewRecommendation: t.last_review_recommendation || null,
            reviewedByMe: t.reviewed_by != null && t.reviewed_by === currentUserId,
            reviewClaimUrl:
                cat === 'inreview' && t.reviewed_by == null && t.claimed_by_id !== currentUserId
                    ? url(reviewClaimUrlTemplate, t.id)
                    : null,
            claimer: ['claimed', 'analyzing', 'inprogress', 'inreview'].includes(cat)
                ? t.claimed_by || null
                : null,
            dependents: transitive[t.id],
            directDependents: direct,
            bottleneck: !isDone(t) && transitive[t.id] > threshold && transitive[t.id] >= 2,
        };
    });

    const edges = [];
    for (const t of tasks) {
        for (const pre of t.prerequisites || []) {
            if (keys[pre.id] == null) continue;
            const parent = byId.get(pre.id);
            edges.push({
                from: keys[pre.id],
                to: keys[t.id],
                met: parent ? isDelivered(parent) : false,
            });
        }
    }

    // Schmale Phasen-Kopfzeile (Kurzname + %-erledigt, SP-basiert).
    const phaseHeader = [...phases]
        .sort((a, b) => (a.position ?? 0) - (b.position ?? 0))
        .map((phase) => {
            const pt = tasks.filter((t) => t.phase_id === phase.id);
            const total = Math.max(1, pt.reduce((a, t) => a + sp(t), 0));
            const done = pt.filter(isDone).reduce((a, t) => a + sp(t), 0);
            return {
                id: phase.id,
                short: String(phase.name).split(' ')[0],
                name: phase.name,
                pct: Math.round((done / total) * 100),
            };
        });

    // Legende: je konfiguriertem Status (nach Board-Position), im echten Knotenstil.
    const legend = [...statuses]
        .sort((a, b) => (a.position ?? 0) - (b.position ?? 0))
        .map((s) => ({
            key: s.key,
            label: s.label,
            color: s.color_token,
            icon: s.icon || null,
            cat: categoryOf(s.role, s.kind),
        }));

    return { nodes, edges, phases: phaseHeader, legend };
}
