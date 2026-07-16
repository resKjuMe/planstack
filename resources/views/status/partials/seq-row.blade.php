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

    // Anthropic/Claude-Logo (viewBox 0 0 24 24), wie im "mit Claude"-Button auf
    // der Task-Detailseite. Startet per claudetask:-Protokoll (siehe
    // C:\Projekt\tampermonkey\claude-task.ps1) eine neue PowerShell mit
    // "claude /L2LR <Taskname>", damit der Task direkt abgearbeitet wird.
    $claudeLogoPath = 'M4.709 15.955l4.72-2.647.08-.23-.08-.128H9.2l-.79-.048-2.698-.073-2.339-.097-2.266-.122-.571-.121L0 11.784l.055-.352.48-.321.686.06 1.52.103 2.278.158 1.652.097 2.449.255h.389l.055-.157-.134-.098-.103-.097-2.358-1.596-2.552-1.688-1.336-.972-.724-.491-.364-.462-.158-1.008.656-.722.881.06.225.061.893.686 1.908 1.476 2.491 1.833.365.304.145-.103.019-.073-.164-.274-1.355-2.446-1.446-2.49-.644-1.032-.17-.619a2.97 2.97 0 01-.104-.729L6.283.134 6.696 0l.996.134.42.364.62 1.414 1.002 2.229 1.555 3.03.456.898.243.832.091.255h.158V9.01l.128-1.706.237-2.095.23-2.695.08-.76.376-.91.747-.492.583.28.48.685-.067.444-.286 1.851-.559 2.903-.364 1.942h.212l.243-.242.985-1.306 1.652-2.064.73-.82.85-.904.547-.431h1.033l.76 1.129-.34 1.166-1.064 1.347-.881 1.142-1.264 1.7-.79 1.36.073.11.188-.02 2.856-.606 1.543-.28 1.841-.315.833.388.091.395-.328.807-1.969.486-2.309.462-3.439.813-.042.03.049.061 1.549.146.662.036h1.622l3.02.225.79.522.474.638-.079.485-1.215.62-1.64-.389-3.829-.91-1.312-.329h-.182v.11l1.093 1.068 2.006 1.81 2.509 2.33.127.578-.322.455-.34-.049-2.205-1.657-.851-.747-1.926-1.62h-.128v.17l.444.649 2.345 3.521.122 1.08-.17.353-.608.213-.668-.122-1.374-1.925-1.415-2.167-1.143-1.943-.14.08-.674 7.254-.316.37-.729.28-.607-.461-.322-.747.322-1.476.389-1.924.315-1.53.286-1.9.17-.632-.012-.042-.14.018-1.434 1.967-2.18 2.945-1.726 1.845-.414.164-.717-.37.067-.662.401-.589 2.388-3.036 1.44-1.882.93-1.086-.006-.158h-.055L4.132 18.56l-1.13.146-.487-.456.061-.746.231-.243 1.908-1.312-.006.006z';
    $claudeTaskHref = 'claudetask:'.rawurlencode('/L2LR '.$task->name);

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

        <a href="{{ $claudeTaskHref }}" onclick="event.stopPropagation()"
           title="Mit Claude abarbeiten (/L2LR {{ $task->name }})"
           class="inline-flex items-center justify-center rounded-full p-1 text-[#D97757] hover:bg-[var(--seq-amber-tint)]">
            <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="currentColor" aria-hidden="true"><path d="{{ $claudeLogoPath }}" /></svg>
        </a>

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
