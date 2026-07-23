import { useEffect, useState } from 'react';

// Semantischer Versionsvergleich (a<b → -1, a>b → 1, gleich → 0). Fehlende
// Segmente zählen als 0. Identisch zum früheren cmp() der Blade-Navigation.
function cmp(a, b) {
    const pa = String(a || '0').split('.').map(Number);
    const pb = String(b || '0').split('.').map(Number);
    for (let i = 0; i < 3; i++) {
        const d = (pa[i] || 0) - (pb[i] || 0);
        if (d) return d < 0 ? -1 : 1;
    }
    return 0;
}

// Kapselt die beiden „Update"-Hinweise der Kopfzeile:
//   ciUpdate       – das CI-Status-Userscript läuft (Marker data-planstack-ci am
//                    <html>) UND ist älter als die aktuelle Version.
//   changelogUnseen – die aktuelle Changelog-Version ist neuer als die zuletzt
//                    gesehene (localStorage), und wir sind nicht auf der
//                    Changelog-Seite. Erstbesuch (nichts gespeichert) → kein Hinweis.
// Beides wird ausschließlich gelesen; die „gesehen"-Version aktualisiert die
// Changelog-Seite selbst.
export function useUpdateIndicators({ ciVersion, changelogVersion, onChangelog }) {
    const [ciUpdate, setCiUpdate] = useState(false);
    const [changelogUnseen, setChangelogUnseen] = useState(false);

    useEffect(() => {
        const refreshCi = () => {
            const installed = document.documentElement.getAttribute('data-planstack-ci');
            setCiUpdate(!!installed && cmp(installed, ciVersion) < 0);
        };
        document.addEventListener('planstack-ci-ready', refreshCi);
        refreshCi();
        const timers = [400, 1200, 2500].map((ms) => setTimeout(refreshCi, ms));

        try {
            const seen = localStorage.getItem('changelog-seen-version');
            setChangelogUnseen(!onChangelog && !!seen && cmp(seen, changelogVersion) < 0);
        } catch (e) {
            /* localStorage evtl. nicht verfügbar */
        }

        return () => {
            document.removeEventListener('planstack-ci-ready', refreshCi);
            timers.forEach(clearTimeout);
        };
    }, [ciVersion, changelogVersion, onChangelog]);

    return { ciUpdate, changelogUnseen };
}
