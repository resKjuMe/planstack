@props(['status', 'label' => false])
@php
    $map = [
        'UNKNOWN' => 'bg-gray-100 text-gray-600',
        'BLOCKED' => 'bg-rose-100 text-rose-700',
        'CONCERNED' => 'bg-red-100 text-red-800',
        'PICKABLE' => 'bg-indigo-100 text-indigo-700',
        'CLAIMED' => 'bg-sky-100 text-sky-700',
        'ANALYZING' => 'bg-blue-100 text-blue-700',
        'IN_PROGRESS' => 'bg-blue-200 text-blue-900',
        'IN_REVIEW' => 'bg-purple-100 text-purple-700',
        'COMPLETED' => 'bg-green-100 text-green-700',
        'MERGED' => 'bg-emerald-100 text-emerald-800',
    ];
    $enum = $status instanceof \App\Enums\TaskStatus ? $status : \App\Enums\TaskStatus::tryFrom((string) $status);
    $value = $enum?->value ?? (string) $status;
    // Optional deutsches Label statt des rohen Enum-Werts (konsistente UI-Sprache).
    $display = $label && $enum ? $enum->label() : $value;
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium '.($map[$value] ?? 'bg-gray-100 text-gray-700')]) }}>
    {{ $display }}
</span>
