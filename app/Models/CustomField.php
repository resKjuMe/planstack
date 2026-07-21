<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Benutzerdefiniertes Task-Feld einer Organisation (Tabelle `custom_fields`).
 * Der Wert wird je Task in tasks.custom_fields (JSON, keyed by `key`) gehalten
 * und über die API befüllt.
 */
class CustomField extends Model
{
    protected $fillable = [
        'organization_id', 'key', 'label', 'label_en', 'type', 'validation', 'position',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    /**
     * Vordefinierte Feld-Vorlagen (Presets) für gängige externe Referenzen. Ein
     * Klick legt das Feld mit festem Schlüssel, Typ und Validierung an.
     *
     * @var array<string, array{key: string, label: string, label_en: string, type: string, validation: ?string}>
     */
    public const PRESETS = [
        'jira' => [
            'key' => 'jira_issue_key',
            'label' => 'Jira Issue Key',
            'label_en' => 'Jira Issue Key',
            'type' => 'string',
            'validation' => 'regex:/^[A-Z][A-Z0-9]+-[0-9]+$/',
        ],
        'sentry' => [
            'key' => 'sentry_issue',
            'label' => 'Sentry Issue',
            'label_en' => 'Sentry Issue',
            'type' => 'string',
            'validation' => 'max:255',
        ],
        'hubspot' => [
            'key' => 'hubspot_ticket_id',
            'label' => 'HubSpot Ticket ID',
            'label_en' => 'HubSpot Ticket ID',
            // HubSpot-Record-IDs sind numerisch (positive Ganzzahl).
            'type' => 'integer',
            'validation' => 'min:1',
        ],
    ];

    /** Erlaubte Datentypen (key => Basis-Validierungsregeln für den Wert). */
    public const TYPES = [
        'string' => ['string'],
        'text' => ['string'],
        'integer' => ['integer'],
        'decimal' => ['numeric'],
        'boolean' => ['boolean'],
        'date' => ['date'],
        'datetime' => ['date'],
        'url' => ['url'],
        'email' => ['email'],
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Validierungsregeln für einen gesetzten Wert: die Typ-Basisregel plus die
     * optionale, org-definierte Laravel-Regel (pipe-getrennt).
     *
     * @return array<int, string>
     */
    public function valueRules(): array
    {
        $base = self::TYPES[$this->type] ?? ['string'];
        $custom = $this->validation
            ? preg_split('/\|/', $this->validation, -1, PREG_SPLIT_NO_EMPTY)
            : [];

        return array_values(array_merge($base, $custom));
    }
}
