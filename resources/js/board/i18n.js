// Tiny helper around the server-provided string table. The templates carry
// Laravel-style :placeholders which we interpolate here, so translation stays
// server-side (see app/Support/BoardPresenter::strings()).

export function makeT(strings) {
    return function t(key, replacements = {}) {
        let out = strings[key] ?? key;
        for (const [k, v] of Object.entries(replacements)) {
            out = out.replaceAll(`:${k}`, String(v));
        }
        return out;
    };
}
