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
const NOTIFICATIONS_MAX = 50; // so viele letzte Nachrichten im Flyout vorhalten
const NOTIFICATIONS_EVENT = 'task-event'; // Event-Name (siehe NotificationBroadcaster)

function metaContent(name) {
    const el = document.querySelector(`meta[name="${name}"]`);
    return el ? el.getAttribute('content') : null;
}

Alpine.store('notifications', {
    count: 0,            // ungelesene seit letztem Öffnen
    enabled: false,      // Pusher konfiguriert (Key + Organisation vorhanden)?
    connected: false,    // Pusher-Verbindung steht?
    open: false,         // Flyout sichtbar?
    messages: [],        // letzte Nachrichten, neueste zuerst
    _pusher: null,

    init() {
        const key = metaContent('pusher-key');
        const cluster = metaContent('pusher-cluster') || 'eu';
        const orgId = metaContent('organization-id');

        if (!key || !orgId) {
            console.info('[notifications] Pusher deaktiviert: kein Key oder keine Organisation');
            return;
        }

        this.enabled = true;
        this.connect(key, cluster, orgId);
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

        // Verbindungsstatus → steuert das „✕" an der Glocke.
        pusher.connection.bind('connected', () => { this.connected = true; });
        pusher.connection.bind('disconnected', () => { this.connected = false; });
        pusher.connection.bind('unavailable', () => { this.connected = false; });
        pusher.connection.bind('error', (err) => {
            this.connected = false;
            console.error('[notifications] Pusher-Verbindungsfehler:', err);
        });

        const channel = pusher.subscribe(`organization-${orgId}`);
        // Alle fachlichen Events des Channels entgegennehmen; Pusher-interne
        // Events (Präfix „pusher:") ignorieren.
        channel.bind_global((eventName, data) => {
            if (typeof eventName === 'string' && eventName.indexOf('pusher:') === 0) return;
            this.count += 1;
            this._record(data);
        });
    },

    // Empfangene Nutzlast merken und in der Konsole ausgeben. pusher-js liefert
    // JSON bereits geparst; Rohtext wird unverändert übernommen.
    _record(data) {
        console.info('[notifications] Payload empfangen:', data);
        this.messages.unshift({ at: new Date().toISOString(), data });
        if (this.messages.length > NOTIFICATIONS_MAX) {
            this.messages.length = NOTIFICATIONS_MAX;
        }
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

window.Alpine = Alpine;

Alpine.start();
