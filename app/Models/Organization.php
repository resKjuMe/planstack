<?php

namespace App\Models;

use App\Enums\StatusRole;
use App\Enums\TaskEvent;
use iamfarhad\LaravelAuditLog\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use Auditable, \App\Concerns\OrganizationAuditMetadata {
        \App\Concerns\OrganizationAuditMetadata::getAuditMetadata insteadof Auditable;
    }
    use HasFactory;

    protected $fillable = [
        'created_by_id',
        'name',
    ];

    /**
     * The user who founded the organization.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * All users belonging to this organization (1:n — a user has at most one).
     */
    public function members(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Individual invitations issued for this organization.
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(OrganizationInvitation::class);
    }

    public function isOwner(User $user): bool
    {
        return $this->created_by_id === $user->id;
    }

    /**
     * The organization's configurable task statuses, ordered for the board.
     */
    public function statuses(): HasMany
    {
        return $this->hasMany(OrgStatus::class)->orderBy('position');
    }

    public function statusGroups(): HasMany
    {
        return $this->hasMany(OrgStatusGroup::class)->orderBy('position');
    }

    public function statusAutomations(): HasMany
    {
        return $this->hasMany(OrgStatusAutomation::class);
    }

    /**
     * Per-event automation configuration (see docs/event-api.md).
     */
    public function eventAutomations(): HasMany
    {
        return $this->hasMany(OrgEventAutomation::class);
    }

    /**
     * The configured automation for a given progress event, or null if the
     * organization has not configured that event (⇒ the event is a no-op).
     */
    public function eventAutomationFor(TaskEvent $event): ?OrgEventAutomation
    {
        return $this->eventAutomations()->where('event', $event->value)->first();
    }

    /**
     * Resolve the (single) status carrying a given action role, or null if the
     * organization has none configured for it.
     */
    public function statusForRole(StatusRole $role): ?OrgStatus
    {
        return $this->statuses()->where('role', $role->value)->first();
    }

    public function statusForKey(string $key): ?OrgStatus
    {
        return $this->statuses()->where('key', $key)->first();
    }
}
