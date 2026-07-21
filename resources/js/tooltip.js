// Globale Tooltips: ersetzt die nativen `title`-Tooltips des Browsers durch
// gestylte, theme-bewusste Overlays — allgemeingueltig fuer alle Seiten.
//
// Umsetzung ueber Event-Delegation an `document`, damit auch dynamisch (Alpine,
// React) eingefuegte Elemente ohne Zutun erfasst werden. Beim Anzeigen wird der
// `title`-Text kurz nach `data-ps-title` ausgelagert (unterdrueckt den nativen
// Tooltip) und beim Verlassen zurueckgeschrieben, damit er fuer Assistenz-
// technik, Kopieren und serverseitiges Markup erhalten bleibt.

const SHOW_DELAY = 300;

let tip = null; // das Tooltip-DOM-Element (lazy erzeugt)
let target = null; // Element, zu dem der Tooltip gehoert (geplant oder sichtbar)
let showTimer = null;

function ensureTip() {
    if (tip) {
        return tip;
    }
    tip = document.createElement('div');
    tip.className = 'ps-tooltip';
    tip.setAttribute('role', 'tooltip');
    tip.hidden = true;
    document.body.appendChild(tip);

    return tip;
}

// Kandidat: naechstes Vorfahren-Element mit nicht-leerem title. <option> wird
// ausgelassen (liegt im nativen Select-Dropdown, nicht positionierbar).
function candidate(node) {
    if (!(node instanceof Element)) {
        return null;
    }
    const el = node.closest('[title]');
    if (!el || el.tagName === 'OPTION') {
        return null;
    }
    const text = el.getAttribute('title');

    return text && text.trim() ? el : null;
}

function place(el) {
    const t = ensureTip();
    const rect = el.getBoundingClientRect();
    const margin = 8;

    t.hidden = false; // messbar machen
    const tw = t.offsetWidth;
    const th = t.offsetHeight;

    let top = rect.top - th - margin;
    let placement = 'above';
    if (top < margin) {
        top = rect.bottom + margin;
        placement = 'below';
    }

    let left = rect.left + rect.width / 2 - tw / 2;
    left = Math.max(margin, Math.min(left, window.innerWidth - tw - margin));

    t.style.top = `${Math.round(top)}px`;
    t.style.left = `${Math.round(left)}px`;
    t.dataset.placement = placement;
}

function reveal(el) {
    const text = el.getAttribute('title');
    if (!text || !text.trim()) {
        return;
    }
    // Nativen Tooltip unterdruecken, Original merken.
    el.setAttribute('data-ps-title', text);
    el.removeAttribute('title');

    const t = ensureTip();
    t.textContent = text;
    t.classList.remove('is-visible');
    place(el);
    requestAnimationFrame(() => t.classList.add('is-visible'));
}

function restoreTitle(el) {
    if (!el) {
        return;
    }
    const text = el.getAttribute('data-ps-title');
    if (text != null) {
        el.setAttribute('title', text);
        el.removeAttribute('data-ps-title');
    }
}

function reset() {
    if (showTimer) {
        clearTimeout(showTimer);
        showTimer = null;
    }
    restoreTitle(target);
    target = null;
    if (tip) {
        tip.classList.remove('is-visible');
        tip.hidden = true;
    }
}

document.addEventListener('mouseover', (e) => {
    const el = candidate(e.target);
    if (!el || el === target) {
        return;
    }
    reset();
    target = el;
    showTimer = setTimeout(() => reveal(el), SHOW_DELAY);
});

document.addEventListener('mouseout', (e) => {
    if (!target) {
        return;
    }
    // Bewegung innerhalb des Zielelements (auf ein Kind) nicht als Verlassen werten.
    if (e.relatedTarget instanceof Node && target.contains(e.relatedTarget)) {
        return;
    }
    reset();
});

document.addEventListener('focusin', (e) => {
    const el = candidate(e.target);
    if (!el || el === target) {
        return;
    }
    reset();
    target = el;
    reveal(el);
});

document.addEventListener('focusout', () => reset());

// Position wuerde bei Scroll/Resize veralten; Klick beendet die Interaktion.
window.addEventListener('scroll', () => target && reset(), true);
window.addEventListener('resize', () => target && reset());
document.addEventListener('click', () => target && reset(), true);
// Sicherheitsnetz: verstecktes Original vor dem Verlassen der Seite zuruecksetzen.
window.addEventListener('beforeunload', () => restoreTitle(target));
