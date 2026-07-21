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
