@php
    // FAQ-Artikel (Nachschlagewerk). Neue Einträge einfach hier ergänzen.
    $articles = [
        [
            'route' => 'faq.status-logic',
            'title' => __('faq.status_logic_rules'),
            'desc'  => __('faq.how_a_task_s_status_comes_about_and'),
        ],
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('faq.faq') }}</h2>
    </x-slot>

    <x-slot name="subheader">
        <x-faq-tabs active="index" />
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <p class="text-sm text-gray-500">{{ __('faq.a_reference_for_planstack_s_concepts') }}</p>

            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($articles as $article)
                    <a href="{{ route($article['route']) }}"
                       class="group block bg-white rounded-lg shadow p-6 ring-1 ring-transparent transition hover:ring-indigo-200">
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="font-semibold text-gray-900 group-hover:text-indigo-700">{{ $article['title'] }}</h3>
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-gray-300 transition group-hover:text-indigo-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6l-6 6"/></svg>
                        </div>
                        <p class="mt-2 text-sm text-gray-600">{{ $article['desc'] }}</p>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
