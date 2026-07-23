import { createCachedResource } from '../data/createCachedResource';
import { fetchProjectsOverview } from '../data/projectApi';
import { ensureStatusConfig } from '../data/projectStore';

// Projektübersicht: kompakte Aggregat-Daten (GET /api/projects/overview) + die
// org-weite, geteilte status-config. Refresh-Politik kommt aus createCachedResource:
// bei jeder Entity-Änderung entprellt neu laden, WENN die Liste sichtbar ist; ist
// sie nicht sichtbar, nur als stale markieren und beim nächsten Öffnen laden
// (kein org-weiter Hintergrund-Refetch, während man auf einer Unterseite ist).
// Reconnect löst denselben (lazy) Refresh aus.
const useResource = createCachedResource({
    load: async () => {
        const [overview, statusConfig] = await Promise.all([
            fetchProjectsOverview(),
            ensureStatusConfig(),
        ]);
        return { projects: overview.projects ?? [], statusConfig };
    },
});

export function useProjectsList() {
    const { data, status, error } = useResource();
    return {
        projects: data?.projects ?? [],
        statusConfig: data?.statusConfig ?? null,
        status,
        error,
    };
}
