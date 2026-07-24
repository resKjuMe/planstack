{{-- Geteilte Nachrichtenliste der Benachrichtigungs-Glocke. Wird sowohl im
     Header-Flyout (notification-bell) als auch in der Seitenleiste
     (notification-sidebar) genutzt und erwartet die Darstellungs-Helfer des
     Alpine-Components `notificationsView` (structured/eventLabel/statusIcon/
     relative/when/raw) im umgebenden Scope. Datenquelle ist der globale Store
     $store.notifications (siehe resources/js/app.js). --}}
<template x-if="$store.notifications.messages.length === 0">
    <p class="px-2 py-6 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('nav.no_messages') }}</p>
</template>
<ul class="space-y-1">
    <template x-for="(msg, i) in $store.notifications.messages" :key="i">
        <li class="rounded px-2 py-1.5 hover:bg-gray-50 dark:hover:bg-gray-700/40">
            {{-- Lesbare Darstellung: [Status-Icon bei Statuswechsel] Projekt › Task: Event --}}
            <template x-if="structured(msg.data)">
                <div class="flex items-start gap-2">
                    <div class="flex min-w-0 flex-1 items-start gap-1.5">
                        {{-- Icon-Spalte immer reservieren, damit der Text ohne
                             Statuswechsel identisch eingerückt bleibt. --}}
                        <svg x-show="statusIcon(msg.data)" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                             class="mt-0.5 h-4 w-4 flex-none text-gray-500 dark:text-gray-400" aria-hidden="true" x-html="statusIcon(msg.data)"></svg>
                        <span x-show="!statusIcon(msg.data)" class="mt-0.5 h-4 w-4 flex-none" aria-hidden="true"></span>
                        <div class="min-w-0 text-sm leading-snug text-gray-800 dark:text-gray-200">
                            <template x-if="msg.data.project_name">
                                <span class="text-gray-500 dark:text-gray-400"><span x-text="msg.data.project_name"></span> <span class="text-gray-300 dark:text-gray-600">›</span> </span>
                            </template>
                            <span class="font-medium" x-text="msg.data.task_name ?? ('#' + msg.data.task_id)"></span><span class="text-gray-500 dark:text-gray-400">:</span>
                            <span x-text="' ' + eventLabel(msg.data)"></span>
                        </div>
                    </div>
                    <div class="mt-0.5 flex-none whitespace-nowrap text-[10px] text-gray-400 dark:text-gray-500" x-text="relative(msg.at)" :title="when(msg.at)"></div>
                </div>
            </template>
            {{-- Fallback: unbekannte/rohe Nutzlast weiterhin als JSON --}}
            <template x-if="!structured(msg.data)">
                <div>
                    <div class="mb-0.5 flex justify-end text-[10px] text-gray-400 dark:text-gray-500" x-text="relative(msg.at)" :title="when(msg.at)"></div>
                    <pre class="overflow-x-auto whitespace-pre-wrap break-words rounded bg-gray-50 p-2 text-xs leading-snug text-gray-800 dark:bg-gray-900 dark:text-gray-200" x-text="raw(msg.data)"></pre>
                </div>
            </template>
        </li>
    </template>
</ul>
