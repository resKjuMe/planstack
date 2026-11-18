<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Jira-Issue-Key (z. B. `MN-1234`): gespeichert getrimmt und in Großbuchstaben,
 * ein leerer Wert wird zu null.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
class JiraKey implements CastsAttributes
{
    /** Validierungsmuster für Eingaben (Groß-/Kleinschreibung egal). */
    public const RULE = 'regex:/^\s*[A-Za-z][A-Za-z0-9_]*-\d+\s*$/';

    /**
     * @return list<string>
     */
    public static function rules(): array
    {
        return ['nullable', 'string', 'max:32', self::RULE];
    }

    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        $value = strtoupper(trim((string) $value));

        return $value === '' ? null : $value;
    }
}
