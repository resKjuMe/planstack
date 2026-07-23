// Winziger Client-i18n-Helfer für die Summary-Ableitung. Die Übersetzungen kommen
// als ROH-Templates vom Server (Laravel `__('key')` ohne Ersetzungen, sodass die
// :platzhalter erhalten bleiben) — hier werden sie clientseitig interpoliert, weil
// es in React weder __() noch route() gibt.

/** Ersetzt :name-Platzhalter durch params.name. */
export function interpolate(template, params = {}) {
    return String(template ?? '').replace(/:(\w+)/g, (m, key) =>
        Object.prototype.hasOwnProperty.call(params, key) ? String(params[key]) : m,
    );
}

/**
 * Minimales trans_choice für die hier genutzten Strings der Form
 * "Singular|Plural" (deutsche/englische Pluralregel: count === 1 → Singular).
 */
export function transChoice(template, count, params = {}) {
    const parts = String(template ?? '').split('|');
    const form = count === 1 ? parts[0] : parts[1] ?? parts[0];
    return interpolate(form, { count, ...params });
}
