<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Die Performance-Unterseite zeigt personenbezogene Leistungsdaten und ist
 * deshalb ALLEIN für den Organisations-Owner bestimmt. Zwei Dinge müssen dafür
 * zusammenpassen, und beide werden hier festgenagelt:
 *
 *  1. Die Route lehnt jeden anderen mit 403 ab — ein fehlender Navigationstab ist
 *     keine Zugriffskontrolle, die URL ist ratbar.
 *  2. Der Presenter schickt Tab UND Labels nur dem Owner mit; sonst lägen die
 *     Kennzahlen-Bezeichner in einer Antwort, die die Seite nie rendert.
 *
 * Die Aggregation selbst passiert clientseitig (resources/js/performance/derive.js)
 * aus dem geteilten Store — hier gibt es dafür keinen Server-Payload zu prüfen.
 */
class ProjectPerformanceTest extends TestCase
{
    use RefreshDatabase;

    /** Owner der Organisation, zu der das Projekt gehört. */
    private function owner(Organization $organization): User
    {
        $owner = $organization->owner;
        $owner->organization_id = $organization->id;
        $owner->save();

        return $owner;
    }

    /** Projektmitglied ohne Owner-Rolle in derselben Organisation. */
    private function member(Project $project, ProjectRole $role = ProjectRole::ADMIN): User
    {
        $user = User::factory()->create();
        $user->organization_id = $project->organization_id;
        $user->save();

        $project->members()->attach($user->id, ['role' => $role->value]);

        return $user;
    }

    private function project(): Project
    {
        $organization = Organization::factory()->create();

        return Project::factory()->create([
            'organization_id' => $organization->id,
            'created_by_id' => $organization->created_by_id,
        ]);
    }

    public function test_requires_authentication(): void
    {
        $project = $this->project();

        $this->get(route('projects.performance', $project))->assertRedirect(route('login'));
    }

    public function test_organization_owner_sees_the_page(): void
    {
        $project = $this->project();

        $this->actingAs($this->owner($project->organization))
            ->get(route('projects.performance', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ProjectWorkspace')
                ->where('activeTab', 'performance')
                ->where('can.viewPerformance', true)
                ->has('performance.strings')
                ->where('performance.strings.title', 'Performance')
            );
    }

    /**
     * Ein Projekt-Admin darf das Board sehen und bearbeiten — die Leistungsdaten
     * seiner Kollegen trotzdem nicht.
     */
    public function test_project_member_is_forbidden_even_as_admin(): void
    {
        $project = $this->project();

        $this->actingAs($this->member($project))
            ->get(route('projects.performance', $project))
            ->assertForbidden();
    }

    public function test_tab_and_labels_are_absent_for_non_owners(): void
    {
        $project = $this->project();

        $props = $this->actingAs($this->member($project))
            ->get(route('projects.show', $project))
            ->assertOk()
            ->inertiaPage()['props'];

        $this->assertFalse($props['can']['viewPerformance']);
        $this->assertNull($props['performance']);
        $this->assertNotContains('performance', collect($props['tabs'])->pluck('key')->all());
    }

    public function test_tab_is_present_for_the_organization_owner(): void
    {
        $project = $this->project();

        $tabs = collect($this->actingAs($this->owner($project->organization))
            ->get(route('projects.show', $project))
            ->assertOk()
            ->inertiaProps('tabs'));

        $tab = $tabs->firstWhere('key', 'performance');

        $this->assertNotNull($tab, 'Performance-Tab fehlt für den Organisations-Owner');
        $this->assertSame('Performance', $tab['label']);
        $this->assertSame(route('projects.performance', $project), $tab['href']);
    }

    /**
     * @return array<int, array{0: string}>
     */
    public static function locales(): array
    {
        return [['de'], ['en']];
    }

    /**
     * Die Seite besteht fast vollständig aus Übersetzungsschlüsseln. Fehlt einer,
     * rendert Laravel still den Schlüssel selbst („performance.m_wip") — ohne
     * Fehler, ohne Log-Eintrag.
     *
     * @dataProvider locales
     */
    public function test_carries_no_unresolved_translation_keys(string $locale): void
    {
        $project = $this->project();
        $owner = $this->owner($project->organization);
        $owner->locale = $locale;
        $owner->save();

        $strings = $this->actingAs($owner)
            ->get(route('projects.performance', $project))
            ->assertOk()
            ->inertiaProps('performance');

        $matched = preg_match(
            '/"(?:performance|common|status)\.[a-z0-9_]+"/',
            (string) json_encode($strings, JSON_UNESCAPED_UNICODE),
            $hit
        );

        $this->assertSame(0, $matched, 'Unaufgelöster Übersetzungsschlüssel ('.$locale.'): '.($hit[0] ?? ''));
    }
}
