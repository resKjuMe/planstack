<?php

namespace App\Models;

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
}
