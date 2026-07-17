<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Planstack-Skill für Claude Code</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Intro + Download --}}
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="font-semibold text-gray-800">Ein Skill für alle deine Projekte</h3>
                <p class="mt-2 text-sm text-gray-600 leading-relaxed">
                    Mit dem Planstack-Skill arbeitet Claude Code ein Planstack-Board über die REST-API ab:
                    Board lesen, Task picken, umsetzen, PR setzen, mergen. Das Paket ist
                    <b>projektübergreifend</b> — es enthält kein festes Projekt. Welches Board du bearbeiten
                    willst, gibst du beim Aufruf an.
                </p>

                <div class="mt-5">
                    <a href="{{ route('skill.download') }}"
                       class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M10.75 2.75a.75.75 0 0 0-1.5 0v8.614L6.295 8.235a.75.75 0 1 0-1.09 1.03l4.25 4.5a.75.75 0 0 0 1.09 0l4.25-4.5a.75.75 0 0 0-1.09-1.03l-2.955 3.129V2.75Z" />
                            <path d="M3.5 12.75a.75.75 0 0 0-1.5 0v2.5A2.75 2.75 0 0 0 4.75 18h10.5A2.75 2.75 0 0 0 18 15.25v-2.5a.75.75 0 0 0-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5Z" />
                        </svg>
                        Skill herunterladen (ZIP)
                    </a>
                    <p class="mt-2 text-xs text-gray-400">
                        Das ZIP enthält <span class="font-mono">SKILL.md</span> und eine vorausgefüllte
                        <span class="font-mono">config.json</span> mit einem frisch erzeugten, persönlichen Zugriffstoken.
                    </p>
                </div>
            </div>

            {{-- Installation --}}
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="font-semibold text-gray-800">Installation</h3>
                <ol class="mt-3 space-y-2 text-sm text-gray-600" style="list-style: decimal inside;">
                    <li>ZIP herunterladen und entpacken.</li>
                    <li>Den Ordner <span class="font-mono">planstack/</span> nach
                        <span class="font-mono">~/.claude/skills/</span> verschieben
                        (Windows: <span class="font-mono">%USERPROFILE%\.claude\skills\</span>).</li>
                    <li>Fertig — in Claude Code steht der Befehl <span class="font-mono">/planstack</span> bereit.</li>
                </ol>
            </div>

            {{-- Benutzung --}}
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="font-semibold text-gray-800">Benutzung</h3>
                <dl class="mt-3 space-y-3 text-sm">
                    <div>
                        <dt class="font-mono text-gray-800">/planstack &lt;PROJEKT&gt;</dt>
                        <dd class="text-gray-600">Arbeitet das ganze Board dieses Projekts ab (besten Pick wählen, Zyklus bis zum Merge).</dd>
                    </div>
                    <div>
                        <dt class="font-mono text-gray-800">/planstack &lt;PROJEKT&gt; &lt;TASK&gt;</dt>
                        <dd class="text-gray-600">Arbeitet gezielt einen einzelnen Task ab (<span class="font-mono">&lt;TASK&gt;</span> = Task-Kürzel, z. B. <span class="font-mono">C27</span>).</dd>
                    </div>
                    <div>
                        <dt class="font-mono text-gray-800">/planstack review [&lt;PROJEKT&gt;] [&lt;TASK&gt;]</dt>
                        <dd class="text-gray-600">Reviewt Tasks, die „in Review" sind (mit PR): übernimmt das Review, führt den Review-Skill aus und erfasst das Ergebnis. Ohne Argumente projektübergreifend.</dd>
                    </div>
                    <div>
                        <dt class="font-mono text-gray-800">/planstack fix [&lt;PROJEKT&gt;] &lt;TASK|PR&gt;</dt>
                        <dd class="text-gray-600">Repariert einen offenen PR: löst Merge-Konflikte, beantwortet/fixt Review-Kommentare und korrigiert fehlschlagende CI.</dd>
                    </div>
                    <div>
                        <dt class="font-mono text-gray-800">/planstack settings</dt>
                        <dd class="text-gray-600">Lokale Einstellungen anzeigen/ändern (Tests, PHPStan, PHPCS, Babysit-PRs — je „ja/nein/bei jeder Aufgabe fragen"). Wird nur lokal gespeichert.</dd>
                    </div>
                    <div>
                        <dt class="font-mono text-gray-800">/planstack update-config [&lt;PROJEKT&gt;]</dt>
                        <dd class="text-gray-600">Zieht die neueste allgemeine (und optional Projekt-)Konfiguration und zeigt die Versionsnummern an.</dd>
                    </div>
                </dl>
                <p class="mt-3 text-xs text-gray-400">
                    <span class="font-mono">&lt;PROJEKT&gt;</span> ist der Projekt-Alias (z. B. <span class="font-mono">L2L</span>, <span class="font-mono">LOG</span>).
                    Der Skill bedient alle Projekte, auf die dein Token Zugriff hat.
                </p>
            </div>

            {{-- Hinweise --}}
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="font-semibold text-gray-800">Gut zu wissen</h3>
                <ul class="mt-3 space-y-2 text-sm text-gray-600 list-disc ps-5">
                    <li><b>Token:</b> Beim Download wird ein persönlicher Zugriffstoken erzeugt und eingebettet.
                        Er ist jederzeit unter <a href="{{ route('profile.edit') }}" class="text-indigo-600 hover:underline">Profil → API-Token</a> widerrufbar.</li>
                    <li><b>Selbst-aktualisierend:</b> Betriebshandbuch, Statusregeln und die allgemeinen
                        Skill-Anweisungen (z. B. die PR-Titel-Konvention) zieht der Skill bei Änderungen
                        automatisch nach — ohne erneuten Download (ein einmaliges Neu-Laden vorausgesetzt).</li>
                    <li><b>Kein festes Projekt:</b> Die <span class="font-mono">config.json</span> enthält nur Zugang
                        (URL + Token) — das Projekt kommt aus dem Aufruf.</li>
                </ul>
            </div>

        </div>
    </div>
</x-app-layout>
