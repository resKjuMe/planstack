import Alpine from 'alpinejs';
import Pusher from 'pusher-js';
import './tooltip';

// Dark Mode: globaler Theme-Store. Die Wahl (light | dark | system) liegt pro
// Browser in localStorage; das Anti-Flash-Script im <head> (partials/theme-init)
// wendet sie bereits vor dem Rendern an. Hier folgt die reaktive Laufzeit-
// Umschaltung inkl. Reaktion auf Änderungen der OS-Einstellung, solange
// 'system' gewählt ist.
const themeMedia = window.matchMedia('(prefers-color-scheme: dark)');

Alpine.store('theme', {
    mode: localStorage.getItem('theme') || 'system',

    init() {
        themeMedia.addEventListener('change', () => this.apply());
    },

    get isDark() {
        return this.mode === 'dark' || (this.mode === 'system' && themeMedia.matches);
    },

    apply() {
        document.documentElement.classList.toggle('dark', this.isDark);
    },

    set(mode) {
        this.mode = mode;
        localStorage.setItem('theme', mode);
        this.apply();
    },

    // Umschalt-Reihenfolge des Header-Buttons: hell → dunkel → System → hell.
    cycle() {
        this.set({ light: 'dark', dark: 'system', system: 'light' }[this.mode] || 'light');
    },
});

// Benachrichtigungen: globaler Store für die Header-Glocke. Abonniert über
// Pusher den Organisations-Channel `organization-{id}` und zählt eingehende
// Ereignisse. Key/Cluster/Organisation kommen aus <meta>-Tags im Layout
// (nur für eingeloggte Nutzer mit Organisation gesetzt) — fehlen sie, bleibt
// die Glocke inaktiv. Die Glocke (components/notification-bell.blade.php) zeigt
// den Zähler als Pill bzw. bei getrennter Verbindung ein „✕"; ein Klick öffnet
// ein Flyout mit den letzten Nachrichten als JSON und markiert sie als gelesen.
//
// Geteilte Verbindung über Tabs: Statt je Tab eine Pusher-Verbindung zu öffnen,
// hält genau EIN Tab die Verbindung (Leader, per Web-Locks-Election) und verteilt
// eingehende Nachrichten + den Verbindungsstatus über einen BroadcastChannel an
// die übrigen Tabs. Schließt der Leader, übernimmt automatisch ein wartender Tab.
// Ohne Web-Locks-Unterstützung (alte Browser) verbindet jeder Tab einzeln.
const NOTIFICATIONS_MAX = 50; // so viele letzte Nachrichten im Flyout vorhalten
const NOTIFICATIONS_EVENT = 'task-event'; // Event-Name (siehe NotificationBroadcaster)
// Generische Entity-Änderungen (Task/Phase). Steuern NUR das partielle Nachladen
// im geteilten React-Store (resources/js/data/projectStore.js) — bewusst NICHT an
// der Glocke gezählt/gemerkt. Siehe NotificationBroadcaster::EVENT_ENTITY.
const NOTIFICATIONS_ENTITY_EVENT = 'entity-changed';
const NOTIFICATIONS_LOCK = 'planstack-notifications-leader';
const NOTIFICATIONS_CHANNEL = 'planstack-notifications';

function metaContent(name) {
    const el = document.querySelector(`meta[name="${name}"]`);
    return el ? el.getAttribute('content') : null;
}

Alpine.store('notifications', {
    count: 0,            // ungelesene seit letztem Öffnen
    enabled: false,      // Pusher konfiguriert (Key + Organisation vorhanden)?
    connected: false,    // Verbindung steht (eigene ODER die des Leaders)?
    failed: false,       // Verbindung fehlgeschlagen/unavailable (≠ „verbindet gerade")
    open: false,         // Flyout sichtbar?
    messages: [],        // letzte Nachrichten, neueste zuerst
    _pusher: null,       // nur im Leader-Tab gesetzt
    _bc: null,           // BroadcastChannel zu den anderen Tabs
    _isLeader: false,
    _sawFailure: false,  // seit dem letzten „connected" gab es eine getrennte Phase?

    init() {
        const key = metaContent('pusher-key');
        const cluster = metaContent('pusher-cluster') || 'eu';
        const orgId = metaContent('organization-id');

        if (!key || !orgId) {
            console.info('[notifications] Pusher deaktiviert: kein Key oder keine Organisation');
            return;
        }

        this.enabled = true;

        // BroadcastChannel für die Tab-übergreifende Verteilung aufsetzen.
        if ('BroadcastChannel' in window) {
            this._bc = new BroadcastChannel(NOTIFICATIONS_CHANNEL);
            this._bc.onmessage = (ev) => this._onBroadcast(ev.data);
            // Falls bereits ein Leader verbunden ist, dessen aktuellen Status erfragen
            // (BroadcastChannel spielt keine früheren Nachrichten erneut ein).
            this._bc.postMessage({ type: 'state-request' });
        }

        // Genau einen Leader wählen. Der Gewinner hält den Lock (nie-auflösende
        // Promise) bis zum Tab-Ende; erst dann rückt ein wartender Tab nach.
        if (navigator.locks && typeof navigator.locks.request === 'function') {
            navigator.locks.request(NOTIFICATIONS_LOCK, { mode: 'exclusive' }, () => {
                this._isLeader = true;
                this.connect(key, cluster, orgId);
                return new Promise(() => {}); // Lock halten, bis der Tab schließt
            }).catch((e) => {
                console.error('[notifications] Leader-Election fehlgeschlagen:', e);
            });
        } else {
            // Kein Web-Locks-Support → Fallback: dieser Tab verbindet selbst.
            this._isLeader = true;
            this.connect(key, cluster, orgId);
        }
    },

    connect(key, cluster, orgId) {
        let pusher;
        try {
            pusher = new Pusher(key, { cluster, forceTLS: true });
        } catch (e) {
            console.error('[notifications] Pusher-Initialisierung fehlgeschlagen:', e);
            return;
        }
        this._pusher = pusher;

        // Verbindungsstatus → steuert das „✕" an der Glocke (und wird an die
        // anderen Tabs verteilt). Das „✕" erscheint NUR bei tatsächlich
        // fehlgeschlagener Verbindung (unavailable/failed/disconnected), nicht
        // während des initialen Verbindens (connecting/initialized).
        pusher.connection.bind('state_change', ({ current }) => {
            const connected = current === 'connected';
            const failed = current === 'unavailable' || current === 'failed' || current === 'disconnected';
            this._setState(connected, failed);
        });
        pusher.connection.bind('error', (err) => {
            console.error('[notifications] Pusher-Verbindungsfehler:', err);
        });

        const channel = pusher.subscribe(`organization-${orgId}`);
        // Alle fachlichen Events des Channels entgegennehmen; Pusher-interne
        // Events (Präfix „pusher:") ignorieren. Als Leader zusätzlich an die
        // anderen Tabs weiterreichen.
        channel.bind_global((eventName, data) => {
            if (typeof eventName === 'string' && eventName.indexOf('pusher:') === 0) return;
            this._ingest(eventName, data, true);
        });
    },

    // Verbindungsstatus setzen und (als Leader) an die anderen Tabs verteilen.
    _setState(connected, failed) {
        this.connected = connected;
        this.failed = failed;
        this._signalReconnectIfNeeded(connected, failed);
        if (this._bc) this._bc.postMessage({ type: 'state', connected, failed });
    },

    // Nach einer getrennten Phase (failed/unavailable/disconnected) einen erneuten
    // „connected"-Übergang als DOM-Event `planstack:reconnected` melden — die
    // Daten-Loader laden dann verpasste Änderungen nach (resources/js/data). Der
    // erste Verbindungsaufbau zählt NICHT als Reconnect.
    _signalReconnectIfNeeded(connected, failed) {
        if (failed) {
            this._sawFailure = true;
        } else if (connected && this._sawFailure) {
            this._sawFailure = false;
            window.dispatchEvent(new CustomEvent('planstack:reconnected'));
        }
    },

    // Eine Nachricht verarbeiten. broadcast=true (nur Leader) verteilt sie
    // zusätzlich an die anderen Tabs.
    //
    // Zwei Klassen von Ereignissen werden getrennt:
    //  • entity-changed → NICHT an der Glocke zählen/merken; nur als DOM-Event
    //    'planstack:entity-changed' fürs partielle Nachladen im Store weiterreichen.
    //  • alles andere (task-event) → wie bisher zählen, merken, als
    //    'planstack:notification' weiterreichen (Glocke + Board-Highlight).
    _ingest(eventName, data, broadcast) {
        if (eventName === NOTIFICATIONS_ENTITY_EVENT || (data && data.type === 'entity-changed')) {
            console.debug('[notifications] entity-changed empfangen:', data);
            window.dispatchEvent(new CustomEvent('planstack:entity-changed', { detail: data }));
            if (broadcast && this._bc) this._bc.postMessage({ type: 'entity', data });
            return;
        }
        this.count += 1;
        this._record(data);
        if (broadcast && this._bc) this._bc.postMessage({ type: 'message', data });
    },

    // Nachricht eines anderen Tabs (des Leaders) über den BroadcastChannel.
    _onBroadcast(msg) {
        if (!msg) return;
        if (msg.type === 'message') {
            this._ingest(NOTIFICATIONS_EVENT, msg.data, false); // nicht zurück-broadcasten
        } else if (msg.type === 'entity') {
            window.dispatchEvent(new CustomEvent('planstack:entity-changed', { detail: msg.data }));
        } else if (msg.type === 'state') {
            this.connected = msg.connected;
            this.failed = msg.failed;
            this._signalReconnectIfNeeded(msg.connected, msg.failed);
        } else if (msg.type === 'state-request' && this._isLeader) {
            // Als Leader dem neu geöffneten Tab den aktuellen Status mitteilen.
            if (this._bc) this._bc.postMessage({ type: 'state', connected: this.connected, failed: this.failed });
        }
    },

    // Empfangene Nutzlast merken und in der Konsole ausgeben. pusher-js liefert
    // JSON bereits geparst; Rohtext wird unverändert übernommen.
    _record(data) {
        console.info('[notifications] Payload empfangen:', data);
        this.messages.unshift({ at: new Date().toISOString(), data });
        if (this.messages.length > NOTIFICATIONS_MAX) {
            this.messages.length = NOTIFICATIONS_MAX;
        }
        // Als DOM-Event weiterreichen, damit andere Views (z. B. das React-Board)
        // reagieren können.
        window.dispatchEvent(new CustomEvent('planstack:notification', { detail: data }));
    },

    // Klick auf die Glocke: Flyout auf-/zuklappen; beim Öffnen als gelesen
    // markieren (Zähler zurücksetzen).
    toggle() {
        this.open = !this.open;
        if (this.open) this.count = 0;
    },

    // Zähler zurücksetzen, ohne das Flyout zu ändern.
    reset() {
        this.count = 0;
    },

    // Gespeicherte Nachrichten leeren.
    clear() {
        this.messages = [];
        this.count = 0;
    },
});

// NOTIFICATIONS_EVENT wird bewusst nicht direkt gebunden (bind_global fängt
// alle fachlichen Events ab), bleibt aber als dokumentierter Vertrag erhalten.
void NOTIFICATIONS_EVENT;

// Darstellungs-Helfer für die Nachrichtenliste (lesbare Aufbereitung einer
// Payload, relative Zeit). Wird von BEIDEN Darstellungen der Glocke geteilt:
// dem Header-Flyout (components/notification-bell.blade.php) und der dauerhaft
// ausgeklappten Seitenleiste (components/notification-sidebar.blade.php). Die
// server-seitig lokalisierten Tabellen (Event-Label, Status-Icon-SVGs) kommen
// aus dem JSON-Script #notifications-meta (siehe app-root.blade.php).
//
// Parameter `mode`: 'dropdown' (Default) oder 'sidebar'. Im Sidebar-Modus ist
// die Liste dauerhaft sichtbar, daher tickt die relative Zeit dort immer; im
// Flyout nur, solange es geöffnet ist.
Alpine.data('notificationsView', (mode = 'dropdown') => ({
    mode,
    eventLabels: {},
    statusIcons: {},
    nowTick: 0,

    init() {
        try {
            const meta = JSON.parse(document.getElementById('notifications-meta')?.textContent || '{}');
            this.eventLabels = meta.eventLabels || {};
            this.statusIcons = meta.statusIcons || {};
        } catch (e) {
            console.error('[notifications] Meta-Daten konnten nicht gelesen werden:', e);
        }
        // Sekunden-Tick, an den die relative Zeit gekoppelt ist — nur wenn die
        // Liste sichtbar ist (Flyout offen oder Sidebar-Modus).
        setInterval(() => {
            if (this.mode === 'sidebar' || this.$store.notifications.open) this.nowTick++;
        }, 1000);
    },

    structured(d) { return d && typeof d === 'object' && typeof d.event !== 'undefined'; },
    eventLabel(d) { return this.eventLabels[d.event] ?? d.event; },
    statusIcon(d) { return d.status_changed && d.status_icon ? (this.statusIcons[d.status_icon] ?? null) : null; },
    when(iso) { try { return new Date(iso).toLocaleString(); } catch (e) { return iso; } },
    relative(iso) {
        // nowTick lesen, damit Alpine diese Ausgabe an den Sekunden-Tick koppelt.
        void this.nowTick;
        try {
            const t = new Date(iso).getTime();
            if (isNaN(t)) return iso;
            const s = Math.max(0, Math.round((Date.now() - t) / 1000));
            const de = (document.documentElement.lang || 'de').toLowerCase().startsWith('de');
            if (s < 5) return de ? 'gerade eben' : 'just now';
            if (s < 60) return de ? ('vor ' + s + 's') : (s + 's ago');
            const m = Math.floor(s / 60);
            if (m < 60) return de ? ('vor ' + m + 'min') : (m + 'min ago');
            const h = Math.floor(m / 60);
            if (h < 24) return de ? ('vor ' + h + 'h') : (h + 'h ago');
            const d = Math.floor(h / 24);
            return de ? ('vor ' + d + 'd') : (d + 'd ago');
        } catch (e) { return iso; }
    },
    raw(d) { return typeof d === 'string' ? d : JSON.stringify(d, null, 2); },
}));

window.Alpine = Alpine;

Alpine.start();
