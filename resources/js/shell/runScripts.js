// Beim Einsetzen von HTML per innerHTML führt der Browser enthaltene <script>-
// Tags NICHT aus. Diese Helferfunktion ersetzt jedes Script durch ein frisch
// erzeugtes, gleichwertiges Element — dann führt der Browser es aus. So laufen
// die Inline-Payload-Skripte (z. B. window.__PLANSTACK_BOARD__) und die @vite-
// Modul-Tags der eingebetteten Blade-Inhalte nach jeder Navigation.
//
// ES-Module (type="module") werden pro URL nur einmal ausgeführt; für die
// eigentliche (Neu-)Initialisierung der Islands sorgt daher zusätzlich das
// Event `planstack:content-ready`, auf das board/diagram lauschen.
export function runScripts(container) {
    if (!container) return;
    container.querySelectorAll('script').forEach((old) => {
        const s = document.createElement('script');
        for (const attr of old.attributes) {
            s.setAttribute(attr.name, attr.value);
        }
        if (!old.src) {
            s.textContent = old.textContent;
        }
        old.replaceWith(s);
    });
}
