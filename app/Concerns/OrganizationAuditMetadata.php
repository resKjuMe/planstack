<?php

namespace App\Concerns;

use Illuminate\Support\Facades\Auth;

/**
 * Ergänzt jeden Audit-Log-Eintrag um die Organisation des handelnden Users
 * (im metadata-JSON der Audit-Tabelle). Überschreibt getAuditMetadata() des
 * Auditable-Traits — beim Einbinden per insteadof auflösen.
 */
trait OrganizationAuditMetadata
{
    public function getAuditMetadata(): array
    {
        $organizationId = Auth::user()?->organization_id;

        return $organizationId !== null ? ['organization_id' => $organizationId] : [];
    }
}
