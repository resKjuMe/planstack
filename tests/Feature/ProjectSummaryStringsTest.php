<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Die Summary-Kacheln werden clientseitig zusammengesetzt
 * (resources/js/summary/derive.js); der Server liefert nur die Label- und
 * TEMPLATE-Strings. Genau da liegt das stille Risiko: `__()` gibt bei einem
 * fehlenden Schlüssel den Schlüssel selbst zurück, und ein Template, dessen
 * Platzhalter beim Übersetzen verloren geht (`:date`, `:count`, `:eta`), rendert
 * eine Kachel ohne die Zahl, um die es geht — beides ohne Fehler und ohne
 * Log-Eintrag. Diese Tests decken beides für die Prognose- und die
 * Letzter-Merge-Kachel ab, in beiden Sprachen.
 */
class ProjectSummaryStringsTest extends TestCase
{
    use RefreshDatabase;

    /** Erwartete Platzhalter je Template-String. */
    private const TEMPLATES = [
        'forecastEta' => ':eta',
        'forecastEtaDay' => ':date',
        'mergedToday' => ':count',
    ];

    private function summaryStrings(string $locale): array
    {
        $organization = Organization::factory()->create();
        $owner = $organization->owner;
        $owner->organization_id = $organization->id;
        $owner->locale = $locale;
        $owner->save();

        $project = Project::factory()->create([
            'organization_id' => $organization->id,
            'created_by_id' => $owner->id,
        ]);

        return $this->actingAs($owner)
            ->get(route('projects.summary', $project))
            ->assertOk()
            ->inertiaProps('summary.strings');
    }

    /**
     * @return array<int, array{0: string}>
     */
    public static function locales(): array
    {
        return [['de'], ['en']];
    }

    /**
     * @dataProvider locales
     */
    public function test_forecast_and_last_merge_templates_keep_their_placeholders(string $locale): void
    {
        $strings = $this->summaryStrings($locale);

        foreach (self::TEMPLATES as $key => $placeholder) {
            $this->assertArrayHasKey($key, $strings, $key.' fehlt in den Summary-Strings');
            $this->assertStringContainsString(
                $placeholder,
                $strings[$key],
                $key.' ('.$locale.') hat den Platzhalter '.$placeholder.' verloren'
            );
        }

        // Die „heute gemergt"-Zeile ist ein trans_choice-Template (Singular|Plural),
        // das der Client selbst auswertet — ohne den Trenner zeigt er immer
        // dieselbe Form.
        $this->assertStringContainsString('|', $strings['mergedToday']);
        $this->assertNotEmpty($strings['mergedTodayNone']);
    }

    /**
     * @dataProvider locales
     */
    public function test_carries_no_unresolved_translation_keys(string $locale): void
    {
        $strings = $this->summaryStrings($locale);

        $matched = preg_match(
            '/"(?:status|common|components|projects)\.[a-z0-9_]+"/',
            (string) json_encode($strings, JSON_UNESCAPED_UNICODE),
            $hit
        );

        $this->assertSame(0, $matched, 'Unaufgelöster Übersetzungsschlüssel ('.$locale.'): '.($hit[0] ?? ''));
    }
}
