{{-- CI-Status-Teaser über der Diagramm-Card.
     Sichtbar für alle; blendet sich per JS aus, sobald das installierte Userscript
     seinen Marker (data-planstack-ci) setzt. Ist eine ältere Version installiert,
     erscheint stattdessen ein Update-Hinweis. Version zentral: config/planstack_ci.php

     Wichtig: Der <script>-Block liegt INNERHALB von #psci-teaser, damit der Slot
     nur EIN Element beiträgt. So zählt space-y-6 (> :not([hidden]) ~ :not([hidden]))
     den Teaser bei „kein Banner" (Host [hidden]) nicht mit → kein Leerraum. --}}
@php $ciVersion = config('planstack_ci.version'); @endphp

<div id="psci-teaser" hidden
     data-current="{{ $ciVersion }}"
     data-setup="{{ url('/planstack-ci/setup') }}"
     data-userscript="{{ url('/planstack-ci/planstack-ci.user.min.js') }}">

    {{-- Variante „installieren" (Userscript läuft nicht) --}}
    <div data-variant="install" hidden
         class="flex items-start gap-3 rounded-lg border border-orange-300 bg-orange-100 px-4 py-3">
        <svg class="mt-0.5 h-5 w-5 shrink-0 text-orange-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg>
        <div class="flex-1 text-sm">
            <p class="font-semibold text-orange-900">CI-Status direkt im Diagramm</p>
            <p class="mt-0.5 text-orange-800">Sieh je PR den CI-/Merge-Status (✓ / ✗ / x/x Steps, „ready to merge") direkt an den Knoten. Einmalig einrichten — ohne GitHub-Token.</p>
        </div>
        <a href="{{ url('/planstack-ci/setup') }}"
           class="shrink-0 self-center rounded-md bg-orange-600 px-3 py-2 text-sm font-semibold text-white hover:bg-orange-500">
            Einrichten
        </a>
    </div>

    {{-- Variante „Update" (Userscript läuft, aber ältere Version) --}}
    <div data-variant="update" hidden
         class="flex items-start gap-3 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3">
        <svg class="mt-0.5 h-5 w-5 shrink-0 text-indigo-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12"/><path d="m8 11 4 4 4-4"/><path d="M4 21h16"/></svg>
        <div class="flex-1 text-sm">
            <p class="font-semibold text-indigo-900">Update für die CI-Status-Anzeige verfügbar</p>
            <p class="mt-0.5 text-indigo-800">Installiert: <span data-installed class="font-mono"></span> · Aktuell: <span class="font-mono">{{ $ciVersion }}</span></p>
        </div>
        <a href="{{ url('/planstack-ci/setup') }}"
           class="shrink-0 self-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
            Aktualisieren
        </a>
    </div>

    <script>
    (function () {
        var host = document.getElementById('psci-teaser');
        if (!host) return;
        var current = host.dataset.current;

        function cmp(a, b) { // -1 / 0 / 1
            var pa = String(a || '0').split('.').map(Number), pb = String(b || '0').split('.').map(Number);
            for (var i = 0; i < 3; i++) { var d = (pa[i] || 0) - (pb[i] || 0); if (d) return d < 0 ? -1 : 1; }
            return 0;
        }
        function show(variant) {
            host.hidden = false;
            // Inline-display steuern statt [hidden]: die Varianten tragen die Klasse
            // „flex", und .flex würde das hidden-Attribut überschreiben (beide sichtbar).
            host.querySelectorAll('[data-variant]').forEach(function (el) {
                el.style.display = (el.getAttribute('data-variant') === variant) ? 'flex' : 'none';
            });
        }
        function evaluate() {
            var installed = document.documentElement.getAttribute('data-planstack-ci');
            if (!installed) { show('install'); return; }          // Userscript läuft nicht → einrichten
            if (cmp(installed, current) < 0) {                     // installiert & ältere Version → update
                var slot = host.querySelector('[data-installed]');
                if (slot) slot.textContent = 'v' + installed;
                show('update');
                return;
            }
            host.hidden = true;                                    // installiert & aktuell → nichts
        }

        // Sofort prüfen, auf das „ready"-Event des Userscripts hören und kurz nachfassen.
        document.addEventListener('planstack-ci-ready', evaluate);
        evaluate();
        [400, 1200, 2500].forEach(function (ms) { setTimeout(evaluate, ms); });
    })();
    </script>
</div>
