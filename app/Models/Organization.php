<?php

namespace App\Models;

use iamfarhad\LaravelAuditLog\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use Auditable;
    use HasFactory;

    protected $fillable = [
        'created_by_id',
        'name',
        'invite_code',
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
     * A unique, human-friendly invite code (8 chars, ambiguous characters
     * like 0/O/1/I/L excluded), stored raw and displayed as XXXX-XXXX.
     */
    public static function generateInviteCode(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (self::where('invite_code', $code)->exists());

        return $code;
    }

    /**
     * The invite code formatted for display: XXXX-XXXX.
     */
    public function formattedInviteCode(): string
    {
        return rtrim(implode('-', str_split($this->invite_code, 4)), '-');
    }
}
