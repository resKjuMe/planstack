<?php

namespace App\Support;

/**
 * PHP-side color-token palette for status presentation in Blade (progress-bar
 * segments, badges). The counterpart to resources/js/board/statusColors.js
 * (dot/head) — here we need the bar / text / badge classes the summary and
 * project-overview progress bars consume.
 *
 * Tokens mirror OrganizationTaskStatusController::COLORS. Class strings are kept
 * literal so Tailwind's content scanner (which scans the app PHP files) emits
 * them. For the default color tokens these values equal the former hard-coded
 * App\Enums\TaskStatus classes, so a default-seeded org renders identically.
 */
class StatusPalette
{
    /**
     * token => ['bar' => …, 'text' => …, 'badge' => …]
     *
     * @var array<string, array<string, string>>
     */
    public const TOKENS = [
        'gray' => [
            'bar' => 'bg-gray-300',
            'text' => 'text-gray-500 dark:text-gray-400',
            'badge' => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
        ],
        'slate' => [
            'bar' => 'bg-slate-400',
            'text' => 'text-slate-500 dark:text-slate-400',
            'badge' => 'bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300',
        ],
        'indigo' => [
            'bar' => 'bg-indigo-400',
            'text' => 'text-indigo-500 dark:text-indigo-400',
            'badge' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300',
        ],
        'sky' => [
            'bar' => 'bg-sky-400',
            'text' => 'text-sky-600 dark:text-sky-400',
            'badge' => 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300',
        ],
        'blue' => [
            'bar' => 'bg-blue-400',
            'text' => 'text-blue-500 dark:text-blue-400',
            'badge' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
        ],
        'navy' => [
            'bar' => 'bg-blue-700',
            'text' => 'text-blue-700 dark:text-blue-400',
            'badge' => 'bg-blue-200 text-blue-900 dark:bg-blue-900/50 dark:text-blue-200',
        ],
        'purple' => [
            'bar' => 'bg-purple-500',
            'text' => 'text-purple-600 dark:text-purple-400',
            'badge' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300',
        ],
        'green' => [
            'bar' => 'bg-green-500',
            'text' => 'text-green-600 dark:text-green-400',
            'badge' => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
        ],
        'emerald' => [
            'bar' => 'bg-emerald-500',
            'text' => 'text-emerald-600 dark:text-emerald-400',
            'badge' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
        ],
        'teal' => [
            'bar' => 'bg-teal-500',
            'text' => 'text-teal-600 dark:text-teal-400',
            'badge' => 'bg-teal-100 text-teal-700 dark:bg-teal-900/40 dark:text-teal-300',
        ],
        'rose' => [
            'bar' => 'bg-rose-400',
            'text' => 'text-rose-500 dark:text-rose-400',
            'badge' => 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',
        ],
        'red' => [
            'bar' => 'bg-red-500',
            'text' => 'text-red-600 dark:text-red-400',
            'badge' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
        ],
        'orange' => [
            'bar' => 'bg-orange-500',
            'text' => 'text-orange-600 dark:text-orange-400',
            'badge' => 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300',
        ],
        'amber' => [
            'bar' => 'bg-amber-500',
            'text' => 'text-amber-600 dark:text-amber-400',
            'badge' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
        ],
    ];

    private const FALLBACK = [
        'bar' => 'bg-gray-300',
        'text' => 'text-gray-500 dark:text-gray-400',
        'badge' => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
    ];

    public static function bar(?string $token): string
    {
        return (self::TOKENS[$token] ?? self::FALLBACK)['bar'];
    }

    public static function text(?string $token): string
    {
        return (self::TOKENS[$token] ?? self::FALLBACK)['text'];
    }

    public static function badge(?string $token): string
    {
        return (self::TOKENS[$token] ?? self::FALLBACK)['badge'];
    }
}
