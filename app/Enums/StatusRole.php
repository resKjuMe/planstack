<?php

namespace App\Enums;

/**
 * The fixed semantic roles a per-organization task status can carry. The wired
 * actions (claim/release/review/merge/…) and the board/lifecycle logic reference
 * a status by ROLE, not by name, so an organization may rename/recolor/reorder
 * its statuses (and add custom, role-less ones) while the automation keeps
 * working.
 *
 * Values match the legacy App\Enums\TaskStatus values 1:1 (minus UNKNOWN, which
 * is retired — the initial status is PICKABLE) so the seeded default set stays
 * wire-compatible with the existing API/MCP clients. TaskStatus is progressively
 * replaced by this enum (see the phased plan); until then both coexist.
 */
enum StatusRole: string
{
    case PICKABLE = 'PICKABLE';
    case BLOCKED = 'BLOCKED';
    case CONCERNED = 'CONCERNED';
    case CLAIMED = 'CLAIMED';
    case ANALYZING = 'ANALYZING';
    case IN_PROGRESS = 'IN_PROGRESS';
    case IN_REVIEW = 'IN_REVIEW';
    case COMPLETED = 'COMPLETED';
    case MERGED = 'MERGED';

    /**
     * Semantic family — mirrors TaskStatus::kind() so both enums agree while
     * they coexist.
     */
    public function kind(): string
    {
        return match ($this) {
            self::PICKABLE => 'waiting',
            self::BLOCKED, self::CONCERNED => 'exception',
            self::CLAIMED, self::ANALYZING, self::IN_PROGRESS => 'active',
            self::IN_REVIEW => 'review',
            self::COMPLETED, self::MERGED => 'done',
        };
    }

    public function isDone(): bool
    {
        return $this->kind() === 'done';
    }

    public function isException(): bool
    {
        return $this->kind() === 'exception';
    }
}
