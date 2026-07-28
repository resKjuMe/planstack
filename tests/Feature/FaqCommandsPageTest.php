<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * FAQ-Artikel „Kommandos & Status". Die Seite ist reine Inhaltsauslieferung, aber
 * sie besteht fast vollständig aus Übersetzungsschlüsseln: fehlt einer, rendert
 * Laravel still den Schlüssel selbst („faq_commands.cmd_fix_3") statt eines Satzes
 * — ohne Fehler, ohne Log-Eintrag. Genau das sichern diese Tests ab, in beiden
 * Sprachen. Außerdem ist der Status-Katalog enum-getrieben: die Zähl-Assertion
 * schlägt an, wenn ein neuer TaskStatus ohne die zugehörigen Texte ergänzt wird.
 */
class FaqCommandsPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Die FAQ-Routen liegen hinter EnsureUserHasOrganization — ohne Organisation
     * landet man auf der Organisationsseite statt im Artikel.
     */
    private function member(string $locale = 'de'): User
    {
        $user = User::factory()->create(['locale' => $locale]);
        $organization = Organization::factory()->create(['created_by_id' => $user->id]);

        // organization_id ist nicht fillable — direkt zuweisen.
        $user->organization_id = $organization->id;
        $user->save();

        return $user;
    }

    public function test_requires_authentication(): void
    {
        $this->get(route('faq.commands'))->assertRedirect(route('login'));
    }

    public function test_renders_the_article_with_its_content_blocks(): void
    {
        $last = count(TaskStatus::cases()) - 1;

        $this->actingAs($this->member())
            ->get(route('faq.commands'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('FaqCommands')
                ->has('lifecycle')
                ->has('commands')
                ->has('events')
                // Ein Eintrag je TaskStatus — kein Status ohne Erklärung.
                ->has('statuses', count(TaskStatus::cases()))
                ->where('statuses.0.name', 'UNKNOWN')
                ->where('statuses.'.$last.'.name', 'MERGED')
            );
    }

    /**
     * Jeder Status erklärt, was er bewirkt, wer ihn setzt und was danach kommt.
     */
    public function test_every_status_is_explained(): void
    {
        $statuses = $this->actingAs($this->member())
            ->get(route('faq.commands'))
            ->assertOk()
            ->inertiaProps('statuses');

        foreach ($statuses as $status) {
            foreach (['meaning', 'does', 'setBy', 'next'] as $field) {
                $this->assertNotEmpty($status[$field], $status['name'].': '.$field.' fehlt');
            }
        }

        // Die Herkunfts-Marken kommen aus dem Enum, nicht aus einer zweiten Liste.
        $byName = collect($statuses)->keyBy('name');
        $this->assertTrue($byName['BLOCKED']['derived']);
        $this->assertFalse($byName['BLOCKED']['stored']);
        $this->assertTrue($byName['MERGED']['countsDone']);
        $this->assertFalse($byName['IN_REVIEW']['countsDone']);
    }

    /**
     * Jedes Kommando trägt seinen Zweck; alle außer `settings` (rein lokal, keine
     * API-Aufrufe) zusätzlich eine chronologische Aufruf-Kette.
     */
    public function test_every_command_has_a_purpose_and_ordered_calls(): void
    {
        $commands = $this->actingAs($this->member())
            ->get(route('faq.commands'))
            ->assertOk()
            ->inertiaProps('commands');

        $this->assertNotEmpty($commands);

        foreach ($commands as $command) {
            $this->assertNotEmpty($command['name']);
            $this->assertNotEmpty($command['purpose'], $command['name'].' ohne Zweck');

            if ($command['name'] === '/planstack settings') {
                $this->assertSame([], $command['steps']);

                continue;
            }

            $this->assertNotEmpty($command['steps'], $command['name'].' ohne Aufrufe');

            foreach ($command['steps'] as $step) {
                $this->assertNotEmpty($step['call']);
                $this->assertNotEmpty($step['what']);
            }
        }
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
    public function test_carries_no_unresolved_translation_keys(string $locale): void
    {
        $props = $this->actingAs($this->member($locale))
            ->get(route('faq.commands'))
            ->assertOk()
            ->inertiaPage()['props'];

        // Ein durchgereichter Schlüssel sieht aus wie "faq_commands.foo_bar" — in
        // einem übersetzten Satz kommt so etwas nicht vor.
        $matched = preg_match(
            '/"(?:faq_commands|faq|common|components)\.[a-z0-9_]+"/',
            (string) json_encode($props, JSON_UNESCAPED_UNICODE),
            $hit
        );

        $this->assertSame(0, $matched, 'Unaufgelöster Übersetzungsschlüssel ('.$locale.'): '.($hit[0] ?? ''));
    }
}
