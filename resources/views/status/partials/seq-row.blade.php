@php
    use App\Enums\TaskStatus;
    use Illuminate\Support\Str;

    /** Inherited from pr-sequence.blade.php: $task, $project, $ic, $isActiveFn, $isBottleneckFn, $maxSp, $inCollapse */
    $ds = $task->x_display_status;
    $cat = match ($ds) {
        TaskStatus::PICKABLE => 'pickable',
        TaskStatus::BLOCKED => 'blocked',
        TaskStatus::CONCERNED => 'concerned',
        TaskStatus::CLAIMED => 'claimed',
        default => 'other',
    };
    $isActive = $isActiveFn($task);
    // "Bereit" statt des internen PICKABLE-Wortlauts.
    $statusLabel = $ds === TaskStatus::PICKABLE ? 'bereit' : $ds->label();

    // Rail + Badge je Status (Farbschema: beansprucht=hellblau, Analyse=blau,
    // Arbeit=dunkelblau, Review=lila, Problem/blockiert=rot, pickbar=grün);
    // Badge-Text immer dunkler Ton derselben Familie.
    [$rail, $badge] = match ($ds) {
        TaskStatus::IN_REVIEW => ['bg-[var(--seq-purple-rail)]', 'bg-[var(--seq-purple-tint)] text-[var(--seq-purple-text)]'],
        TaskStatus::IN_PROGRESS => ['bg-[var(--seq-navy-rail)]', 'bg-[var(--seq-navy-tint)] text-[var(--seq-navy-text)]'],
        TaskStatus::ANALYZING => ['bg-[var(--seq-blue-rail)]', 'bg-[var(--seq-blue-tint)] text-[var(--seq-blue-text)]'],
        TaskStatus::CLAIMED => ['bg-[var(--seq-sky-rail)]', 'bg-[var(--seq-sky-tint)] text-[var(--seq-sky-text)]'],
        TaskStatus::BLOCKED => ['bg-[var(--seq-red-rail-soft)]', 'bg-[var(--seq-red-tint)] text-[var(--seq-red-text)]'],
        TaskStatus::CONCERNED => ['bg-[var(--seq-red-rail)]', 'bg-[var(--seq-red-tint)] text-[var(--seq-red-text)]'],
        TaskStatus::PICKABLE => ['bg-[var(--seq-green-rail)]', 'bg-[var(--seq-green-tint)] text-[var(--seq-green-text)]'],
        default => ['bg-transparent', 'bg-[var(--seq-surface-1)] text-[var(--seq-muted)]'],
    };

    $bottleneck = $isBottleneckFn($task);
    $sp = (int) $task->effort_story_points;
    $files = (int) ($task->affected_files ?? 0);
    $big = $sp >= 10 || $files >= 30;

    // Collapsed rows only render inside the "N weitere blockierte" expander
    // (or when the Blockiert filter is active); regular rows follow the filter.
    $xShow = $inCollapse
        ? "(filter === 'all' && blockedOpen) || filter === 'blocked'"
        : "filter === 'all' || filter === '".$cat."'";
@endphp
<div data-cat="{{ $cat }}" x-show="{{ $xShow }}" @if ($inCollapse) x-cloak @endif
     class="relative px-4 py-3 {{ $isActive ? 'bg-[var(--seq-active-row)]' : '' }}">
    {{-- Status-Rail --}}
    <span aria-hidden="true" class="absolute inset-y-0 left-0 w-[3px] {{ $rail }}"></span>

    {{-- Kopfzeile: Badge · ID · Metadaten · Marker --}}
    <div class="flex flex-wrap items-center gap-x-2 gap-y-1.5 min-w-0">
        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $badge }}">{{ $statusLabel }}</span>

        <a href="{{ route('projects.tasks.show', [$project, $task]) }}"
           class="font-mono text-sm font-medium text-[var(--seq-accent)] hover:underline">{{ $task->name }}</a>

        <span class="text-[11px] text-[var(--seq-faint)]">
            @if ($task->pr_number)
                @if ($task->x_pr_url)
                    <a href="{{ $task->x_pr_url }}" target="_blank" rel="noopener" class="hover:underline">#{{ $task->pr_number }}</a>
                @else
                    #{{ $task->pr_number }}
                @endif
                ·
            @endif
            {{ Str::before($task->phase?->name ?? '—', ' · ') }} · Pos. {{ $task->x_seq }}
        </span>

        @if ($bottleneck)
            <span class="inline-flex items-center gap-1 rounded-full bg-[var(--seq-red-tint)] px-2 py-0.5 text-[11px] font-medium text-[var(--seq-red-text)]"
                  title="{{ $task->x_dependents }} PRs hängen direkt von diesem ab">
                {!! $ic('flame', 'h-3 w-3') !!} Flaschenhals · blockiert {{ $task->x_dependents }} PR{{ (int) $task->x_dependents === 1 ? '' : 's' }}
            </span>
        @endif

        @if ($big)
            <span class="inline-flex items-center gap-1 rounded-full bg-[var(--seq-amber-tint)] px-2 py-0.5 text-[11px] font-medium text-[var(--seq-amber-text)]">
                {!! $ic('expand', 'h-3 w-3') !!} {{ $sp >= 10 && $sp === $maxSp ? 'größter PR' : 'großer PR' }}: {{ $sp >= 10 ? $sp.' SP' : $files.' Dateien' }}
            </span>
        @endif
    </div>

    {{-- Bemerkung in voller Breite (nichts abschneiden) --}}
    <p class="mt-1.5 text-sm text-[var(--seq-muted)] whitespace-normal break-words">{{ $task->summary }}</p>

    {{-- Beanspruchung: von wem, seit wann (falls erfasst) --}}
    @if ($ds === TaskStatus::CLAIMED && $task->claimer)
        <p class="mt-1 text-xs text-[var(--seq-sky-text)]">
            beansprucht von <span class="font-medium">{{ $task->claimer->name }}</span>@if ($task->claimed_at) · seit {{ $task->claimed_at->locale('de')->diffForHumans() }}@endif
        </p>
    @endif

    {{-- Problem-Grund direkt in der Zeile (nur wenn er über die Bemerkung hinausgeht) --}}
    @php $reason = $task->concern?->summary ?: $task->concern?->description_blocker; @endphp
    @if ($ds === TaskStatus::CONCERNED && $reason && $reason !== $task->summary)
        <p class="mt-1 inline-flex items-start gap-1 text-sm text-[var(--seq-red-text)]">{!! $ic('alert', 'mt-0.5 h-3.5 w-3.5') !!} {{ $reason }}</p>
    @endif

    {{-- Fußzeile: Abhängigkeits-Chips links, Metriken rechtsbündig --}}
    <div class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1.5">
        @if ($task->x_dep_open >= 1)
            <span class="text-xs text-[var(--seq-faint)]">wartet auf</span>
            @foreach ($task->x_dep_items as $dep)
                @if ($dep['met'])
                    <span class="inline-flex items-center gap-1 rounded-md border-[0.5px] border-transparent bg-[var(--seq-green-tint)] px-1.5 py-0.5 font-mono text-xs text-[var(--seq-green-text)]">{!! $ic('check', 'h-3 w-3') !!}{{ $dep['name'] }}</span>
                @else
                    <span class="inline-flex items-center gap-1 rounded-md border-[0.5px] border-[var(--seq-border)] px-1.5 py-0.5 font-mono text-xs text-[var(--seq-muted)]">{!! $ic('clock', 'h-3 w-3') !!}{{ $dep['name'] }}</span>
                @endif
            @endforeach
        @endif
        <span class="ml-auto inline-flex items-center gap-3 whitespace-nowrap text-xs text-[var(--seq-muted)]">
            <span class="inline-flex items-center gap-1">{!! $ic('chart', 'h-3.5 w-3.5') !!}{{ $task->effort_story_points }} SP</span>
            <span class="inline-flex items-center gap-1">{!! $ic('coin', 'h-3.5 w-3.5') !!}{{ $task->x_tokens }} Tokens</span>
            <span class="inline-flex items-center gap-1">{!! $ic('file', 'h-3.5 w-3.5') !!}{{ $task->affected_files ?? '—' }} Dateien</span>
        </span>
    </div>
</div>
