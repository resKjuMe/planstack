@props(['status', 'label' => false])
@php
    $map = [
        'UNKNOWN' => 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400',
        'BLOCKED' => 'bg-rose-100 dark:bg-rose-900/40 text-rose-700 dark:text-rose-300',
        'CONCERNED' => 'bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-300',
        'PICKABLE' => 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-400',
        'CLAIMED' => 'bg-sky-100 dark:bg-sky-900/40 text-sky-700 dark:text-sky-300',
        'ANALYZING' => 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300',
        'IN_PROGRESS' => 'bg-blue-200 dark:bg-blue-900/50 text-blue-900 dark:text-blue-200',
        'IN_REVIEW' => 'bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300',
        'COMPLETED' => 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300',
        'MERGED' => 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-300',
    ];
    $enum = $status instanceof \App\Enums\TaskStatus ? $status : \App\Enums\TaskStatus::tryFrom((string) $status);
    $value = $enum?->value ?? (string) $status;
    // Optional deutsches Label statt des rohen Enum-Werts (konsistente UI-Sprache).
    $display = $label && $enum ? $enum->label() : $value;
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium '.($map[$value] ?? 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300')]) }}>
    {{ $display }}
</span>
