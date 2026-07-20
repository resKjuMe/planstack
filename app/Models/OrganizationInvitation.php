<?php

namespace App\Models;

use iamfarhad\LaravelAuditLog\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Eine individuelle, einmalige Einladung zum Beitritt einer Organisation. Der
 * Registrierungslink trägt den Token; nach der Registrierung wird das Konto der
 * Organisation und den hinterlegten Teams zugeordnet.
 */
class OrganizationInvitation extends Model
{
    use Auditable, \App\Concerns\OrganizationAuditMetadata {
        \App\Concerns\OrganizationAuditMetadata::getAuditMetadata insteadof Auditable;
    }

    protected $fillable = [
        'organization_id',
        'created_by_id',
        'email',
        'token',
        'team_ids',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'team_ids' => 'array',
            'accepted_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * A unique, unguessable token for the invitation link.
     */
    public static function generateToken(): string
    {
        do {
            $token = Str::random(40);
        } while (self::where('token', $token)->exists());

        return $token;
    }
}
