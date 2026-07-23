import Alpine from 'alpinejs';
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

// Benachrichtigungen: globaler Store für die Header-Glocke. Verbindet sich
// ausschließlich auf der Produktions-Domain planstack.eskju.net mit dem
// WebSocket-Server und zählt eingehende Nachrichten. Die Glocke (siehe
// components/notification-bell.blade.php) zeigt den Zähler als Pill; ein Klick
// öffnet ein Flyout mit den letzten Nachrichten als JSON und markiert sie als
// gelesen. Bei Verbindungsabbruch wird mit Backoff neu verbunden.
const NOTIFICATIONS_HOST = 'planstack.eskju.net';
const NOTIFICATIONS_WS_URL = 'wss://websocket.eskju.net:3000/';
const NOTIFICATIONS_MAX = 50; // so viele letzte Nachrichten im Flyout vorhalten

Alpine.store('notifications', {
    count: 0,            // ungelesene seit letztem Öffnen
    connected: false,
    open: false,         // Flyout sichtbar?
    messages: [],        // letzte Nachrichten, neueste zuerst
    _socket: null,
    _retryMs: 1000,

    init() {
        if (window.location.hostname !== NOTIFICATIONS_HOST) {
            console.info('[notifications] WebSocket-Verbindung deaktiviert: Nur auf ' + NOTIFICATIONS_HOST + ' verfügbar');
            return;
        }
        this.connect();
    },

    connect() {
        let socket;
        try {
            socket = new WebSocket(NOTIFICATIONS_WS_URL);
        } catch (e) {
            // WebSocket nicht verfügbar/blockiert → in die Konsole loggen und
            // später erneut versuchen.
            console.error('[notifications] WebSocket-Verbindung fehlgeschlagen:', NOTIFICATIONS_WS_URL, e);
            this._scheduleReconnect();
            return;
        }
        this._socket = socket;

        socket.addEventListener('open', () => {
            this.connected = true;
            this._retryMs = 1000;
        });
        socket.addEventListener('message', (event) => {
            this.count += 1;
            this._record(event.data);
        });
        socket.addEventListener('close', (event) => {
            this.connected = false;
            this._socket = null;
            // Nur unsaubere Schließungen als Fehler melden (wasClean=false bzw.
            // Code ≠ 1000), damit ein normales Schließen die Konsole nicht spamt.
            if (!event.wasClean) {
                console.error('[notifications] WebSocket-Verbindung getrennt:', NOTIFICATIONS_WS_URL, 'Code', event.code, event.reason || '');
            }
            this._scheduleReconnect();
        });
        // Der 'error'-Event des WebSocket trägt aus Sicherheitsgründen keine
        // Detailinfos; wir loggen ihn dennoch und lassen den Browser 'close'
        // auslösen (dort steht der Code).
        socket.addEventListener('error', (event) => {
            console.error('[notifications] WebSocket-Fehler:', NOTIFICATIONS_WS_URL, event);
            socket.close();
        });
    },

    _scheduleReconnect() {
        const delay = this._retryMs;
        this._retryMs = Math.min(this._retryMs * 2, 30000); // Backoff bis 30 s
        setTimeout(() => this.connect(), delay);
    },

    // Rohnachricht speichern: als JSON parsen, sonst den Rohtext übernehmen.
    _record(raw) {
        let data;
        try {
            data = JSON.parse(raw);
        } catch (e) {
            data = raw;
        }
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

window.Alpine = Alpine;

Alpine.start();
