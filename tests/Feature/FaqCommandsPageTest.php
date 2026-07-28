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

    /** Spalten der Standard-Konfiguration ohne eigenen TaskStatus-Fall. */
    private const ORG_COLUMNS = ['BEREINIGEN', 'REVIEWBAR', 'APPROVED'];

    public function test_renders_the_article_with_its_content_blocks(): void
    {
        // Jeder Kern-Status plus die fest hinterlegten Standard-Spalten.
        $expected = count(TaskStatus::cases()) + count(self::ORG_COLUMNS);

        $this->actingAs($this->member())
            ->get(route('faq.commands'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('FaqCommands')
                ->has('lifecycle')
                ->has('commands')
                ->has('events')
                ->has('statuses', $expected)
                ->where('statuses.0.name', 'UNKNOWN')
                ->where('statuses.'.($expected - 1).'.name', 'MERGED')
            );
    }

    /**
     * „in Bereinigung", „reviewbar" und „approved" sind in der Standard-
     * Konfiguration überall vorhanden, haben aber keinen TaskStatus-Fall — sie
     * fielen deshalb zunächst aus der enum-getriebenen Tabelle heraus. Sie müssen
     * dabei sein, ein Badge tragen und als Org-Spalte gekennzeichnet sein.
     */
    public function test_includes_the_default_org_columns(): void
    {
        $page = $this->actingAs($this->member())
            ->get(route('faq.commands'))
            ->assertOk()
            ->inertiaPage()['props'];

        $byName = collect($page['statuses'])->keyBy('name');

        foreach (self::ORG_COLUMNS as $key) {
            $this->assertTrue($byName->has($key), $key.' fehlt in der Status-Tabelle');
            $this->assertTrue($byName[$key]['orgColumn'], $key.' nicht als Org-Spalte gekennzeichnet');
            $this->assertFalse($byName[$key]['derived'], $key.' ist ein gespeicherter Status');
            $this->assertNotEmpty($page['badges'][$key]['label'] ?? null, $key.' ohne Badge-Label');
        }

        // Kern-Status bleiben unmarkiert, damit die Kennzeichnung etwas aussagt.
        $this->assertFalse($byName['IN_PROGRESS']['orgColumn']);

        // Reihenfolge: die Spalten stehen an ihrer Stelle im Lebenszyklus.
        $names = collect($page['statuses'])->pluck('name')->all();
        $this->assertLessThan(array_search('REVIEWBAR', $names, true), array_search('BEREINIGEN', $names, true));
        $this->assertLessThan(array_search('IN_REVIEW', $names, true), array_search('REVIEWBAR', $names, true));
        $this->assertLessThan(array_search('COMPLETED', $names, true), array_search('APPROVED', $names, true));
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
     * Die Statuszeile gilt für JEDES Kommando, nicht nur den Auto-Modus — auch für
     * `plan`, `update-config` und das Nachziehen des Skills selbst. Der Artikel
     * muss deshalb überall zeigen, wann sie geschrieben wird: je Kommando eine
     * Beispielzeile (auch `settings`, das gar keine API-Aufrufe hat) und zusätzlich
     * an den einzelnen Schritten.
     */
    public function test_shows_when_the_sticky_status_line_is_written(): void
    {
        $props = $this->actingAs($this->member())
            ->get(route('faq.commands'))
            ->assertOk()
            ->inertiaPage()['props'];

        // Das erste Wort ist das laufende Kommando; im Auto-Modus zwei Ebenen.
        $commandTokens = ['Work', 'Auto', 'Review', 'Fix', 'Plan', 'Settings', 'Update'];
        $prefix = fn (string $line) => (bool) preg_match(
            '/^[⚙⏳] ('.implode('|', $commandTokens).')( › ('.implode('|', $commandTokens).'))? \(/u',
            $line
        );

        $lifecycleLines = collect($props['lifecycle'])->pluck('statusLine')->filter();
        $this->assertGreaterThan(1, $lifecycleLines->count(), 'Lebenszyklus ohne Statuszeilen');

        $byName = collect($props['commands'])->keyBy('name');

        // Kein Kommando ohne eigene Beispielzeile — „immer" heißt ausnahmslos.
        foreach ($byName as $name => $command) {
            $this->assertNotEmpty($command['statusLine'], $name.' ohne Beispiel-Statuszeile');
        }

        // Kommandos mit Aufruf-Kette zeigen die Zeile zusätzlich je Schritt.
        foreach ($byName as $name => $command) {
            if ($command['steps'] === []) {
                continue;
            }

            $lines = collect($command['steps'])->pluck('statusLine')->filter();
            $this->assertGreaterThan(0, $lines->count(), $name.' ohne Statuszeilen an den Schritten');
        }

        // Jede Zeile folgt dem Format — sonst wäre der Artikel kein Vorbild.
        $all = $lifecycleLines
            ->merge($byName->pluck('statusLine'))
            ->merge($byName->flatMap(fn ($c) => collect($c['steps'])->pluck('statusLine')))
            ->filter();

        foreach ($all as $line) {
            $this->assertTrue($prefix($line), 'Statuszeile ohne Kommando-Prefix: '.$line);
        }

        // Das Nachziehen des Skills ist sichtbar — sonst passiert es unbemerkt.
        $update = collect($byName['/planstack update-config [<PROJECT>]']['steps'])->pluck('statusLine')->implode(' ');
        $this->assertStringContainsString('Update (', $update);
        $this->assertStringContainsString('Schreibe Skill', $update);
    }

    /**
     * Die Standard-Konfiguration ist ereignisgesteuert: der Status folgt den
     * Fortschritts-Events, direkte status-Calls ignoriert der Server. Der Artikel
     * muss deshalb je Event den Zielstatus zeigen — und bei den rein
     * protokollierenden Events ausdrücklich keinen.
     */
    public function test_events_carry_their_target_status(): void
    {
        $events = collect($this->actingAs($this->member())
            ->get(route('faq.commands'))
            ->assertOk()
            ->inertiaProps('events'))->keyBy('event');

        $drives = [
            'CLAIMED' => 'CLAIMED',
            'ANALYZING' => 'ANALYZING',
            'PROCESSING' => 'IN_PROGRESS',
            'POLISHING' => 'BEREINIGEN',
            'POLISHED' => 'REVIEWBAR',
            'REVIEWING' => 'IN_REVIEW',
            'APPROVED' => 'APPROVED',
            'CHANGES_REQUESTED' => 'IN_PROGRESS',
            'CONCERNED' => 'CONCERNED',
            'UNCLAIMED' => 'PICKABLE',
            'MERGED' => 'MERGED',
            'DEPLOYED' => 'COMPLETED',
        ];

        foreach ($drives as $event => $target) {
            $this->assertSame($target, $events[$event]['target'] ?? null, $event.' ohne Zielstatus');
        }

        // Reines Protokoll — kein Zielstatus, sonst wäre die Tabelle irreführend.
        foreach (['CLAIMING', 'ANALYZED', 'PROCESSED', 'PUBLISHING', 'REVIEWED'] as $event) {
            $this->assertNull($events[$event]['target'], $event.' sollte kein Status setzen');
        }
    }

    /**
     * Die erlaubten Wechsel sind eine geschützte Zustandsmaschine (unerlaubt →
     * 409). Aus den Ausnahme-Status muss ein Rückweg bestehen, sonst steckt ein
     * Task fest.
     */
    public function test_lists_transitions_and_field_automations(): void
    {
        $props = $this->actingAs($this->member())
            ->get(route('faq.commands'))
            ->assertOk()
            ->inertiaPage()['props'];

        $transitions = collect($props['transitions'])->keyBy('from');

        $this->assertContains('CLAIMED', $transitions['PICKABLE']['to']);
        $this->assertContains('REVIEWBAR', $transitions['BEREINIGEN']['to']);
        $this->assertContains('APPROVED', $transitions['IN_REVIEW']['to']);

        foreach (['BLOCKED', 'CONCERNED'] as $exception) {
            $this->assertContains('PICKABLE', $transitions[$exception]['to'], $exception.' ohne Rückweg');
        }

        // Jede Zeile nennt nur Status, die die Tabelle oben auch erklärt.
        $known = collect($props['statuses'])->pluck('name')->all();
        foreach ($props['transitions'] as $row) {
            $this->assertContains($row['from'], $known);
            foreach ($row['to'] as $target) {
                $this->assertContains($target, $known, $target.' ist kein erklärter Status');
            }
        }

        $fields = collect($props['fieldAutomations'])->keyBy('status');
        $this->assertStringContainsString('claimed_at', $fields['CLAIMED']['fields']);
        $this->assertStringContainsString('reviewed_by', $fields['IN_REVIEW']['fields']);
        $this->assertStringContainsString('merged_at', $fields['MERGED']['fields']);
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
