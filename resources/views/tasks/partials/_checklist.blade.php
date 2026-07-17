{{-- Wiederverwendbare, abhakbare Checkliste für Akzeptanzkriterien (kind=acceptance)
     und Testschritte (kind=test). Optimistic UI via Alpine (acItem) + Container-Zähler.
     Props: task, project, kind, title, source (Alt-Prosa), unit (Zähler-Suffix). --}}
@props(['task', 'project', 'kind', 'title', 'source' => null, 'unit' => ''])

@php
    $items = $task->checklistItems->where('kind', $kind)->sortBy('position')->values();
    $hints = $items->where('role', 'hint')->values();
    $listItems = $items->reject(fn ($i) => $i->role === 'hint')->values();
    $checkable = $items->filter(fn ($i) => $i->isCheckable());
    $done = $checkable->where('checked', true)->count();
    $total = $checkable->count();
    $canUpdate = auth()->user()?->can('update', $task);
    $sectionLabels = ['scope' => 'Scope', 'done_when' => 'Done when', 'contract' => 'Contract'];
    $hasContent = $items->isNotEmpty() || filled($source);
@endphp

@if ($hasContent)
<section class="bg-white rounded-lg shadow p-6">
    @if ($items->isNotEmpty())
        <div x-data="{ done: {{ $done }}, total: {{ $total }}, saved: false, err: false, _t: null,
                        ping() { this.saved = true; this.err = false; clearTimeout(this._t); this._t = setTimeout(() => this.saved = false, 1500); },
                        fail() { this.err = true; clearTimeout(this._t); this._t = setTimeout(() => this.err = false, 3000); } }"
             @item-count="done += $event.detail"
             @item-saved="ping()"
             @item-error="fail()">

            <div class="mb-4 flex items-center justify-between gap-3">
                <h3 class="font-semibold text-gray-900">{{ $title }}</h3>
                <div class="flex items-center gap-2 text-xs">
                    <span x-cloak x-show="saved" class="text-green-600">Gespeichert ✓</span>
                    <span x-cloak x-show="err" class="text-red-600">Fehler – nicht gespeichert</span>
                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 font-medium text-gray-600">
                        <span x-text="done + '/' + total"></span>@if ($unit)&nbsp;{{ $unit }}@endif
                    </span>
                </div>
            </div>

            <ul class="space-y-1.5">
                @php $lastSection = null; $stepNo = 0; @endphp
                @foreach ($listItems as $i)
                    @php $isSection = array_key_exists($i->role, $sectionLabels); @endphp
                    @if ($isSection && $i->role !== $lastSection)
                        <li class="pt-2 first:pt-0 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ $sectionLabels[$i->role] }}</li>
                        @php $lastSection = $i->role; @endphp
                    @endif

                    @if ($i->isCheckable())
                        <li>
                            <label @class(['flex items-start gap-2.5 text-sm', 'cursor-pointer' => $canUpdate])
                                   @if ($canUpdate) x-data="acItem({ url: '{{ route('projects.tasks.checklist.toggle', [$project, $task, $i->id]) }}', checked: {{ $i->checked ? 'true' : 'false' }} })" @endif>
                                @if ($kind === 'test' && $i->role === 'expectation')
                                    <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700" title="Prüfschritt">
                                        <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M2 12s3.5-7 10-7s10 7 10 7s-3.5 7-10 7s-10-7-10-7z"/><circle cx="12" cy="12" r="2.5"/></svg>
                                    </span>
                                @elseif ($kind === 'test')
                                    <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-500">{{ ++$stepNo }}</span>
                                @endif

                                @if ($canUpdate)
                                    <input type="checkbox" :checked="checked" @click.prevent="toggle()" :disabled="busy"
                                           class="mt-0.5 h-4 w-4 shrink-0 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <span class="min-w-0" :class="checked ? 'text-gray-400 line-through' : 'text-gray-800'">{{ $i->text }}</span>
                                @else
                                    <input type="checkbox" @checked($i->checked) disabled class="mt-0.5 h-4 w-4 shrink-0 rounded border-gray-300 text-indigo-400">
                                    <span class="min-w-0 {{ $i->checked ? 'text-gray-400 line-through' : 'text-gray-800' }}">{{ $i->text }}</span>
                                @endif
                            </label>
                        </li>
                    @else
                        {{-- read-only Rolle (scope/contract) --}}
                        <li class="flex items-start gap-2.5 text-sm text-gray-700">
                            <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-gray-300"></span>
                            <span class="min-w-0">{{ $i->text }}</span>
                        </li>
                    @endif
                @endforeach
            </ul>

            @if ($hints->isNotEmpty())
                <div class="mt-4 space-y-1 border-t border-gray-100 pt-3">
                    @foreach ($hints as $h)
                        <p class="flex items-start gap-2 text-xs text-gray-500">
                            <span class="font-semibold text-gray-400">Hinweis:</span>
                            <span>{{ $h->text }}</span>
                        </p>
                    @endforeach
                </div>
            @endif
        </div>
    @else
        {{-- Keine Items, aber Alt-Prosa: read-only splitten + Umwandeln-Button --}}
        @php $parsed = \App\Support\TaskContentParser::checklist((string) $source, $kind); @endphp
        <div class="mb-3 flex items-center justify-between gap-3">
            <h3 class="font-semibold text-gray-900">{{ $title }}</h3>
            @if ($canUpdate && count($parsed))
                <form method="POST" action="{{ route('projects.tasks.checklist.convert', [$project, $task]) }}">
                    @csrf
                    <input type="hidden" name="kind" value="{{ $kind }}">
                    <button class="rounded-md bg-white px-2.5 py-1 text-xs font-semibold text-indigo-600 ring-1 ring-indigo-200 hover:bg-indigo-50">In Checkliste umwandeln</button>
                </form>
            @endif
        </div>
        @if (count($parsed))
            <ul class="space-y-1.5">
                @foreach ($parsed as $p)
                    <li class="flex items-start gap-2.5 text-sm text-gray-700">
                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-gray-300"></span>
                        <span class="min-w-0">{{ $p['text'] }}</span>
                    </li>
                @endforeach
            </ul>
        @else
            <x-markdown :content="$source" />
        @endif
    @endif
</section>
@endif
