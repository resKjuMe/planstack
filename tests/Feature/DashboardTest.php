<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskConcern;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Das Dashboard (/dashboard, Ziel des Logos) ist die projektübergreifende Fassung
 * des Board-Filters „Bei mir". Festgenagelt wird vor allem die Auswahlregel, denn
 * sie ist die ganze Aussage der Seite:
 *
 *  - `work`    — Tasks in einem Arbeitsschritt, die ICH beansprucht habe;
 *  - `review`  — Reviews, die mir gehören oder noch frei sind;
 *  - `blocked` — eigene Tasks in einer Ausnahme (blockiert / Concern).
 *
 * Fremde Arbeit gehört in keine der drei Gruppen, und Projekte, die ich nicht
 * sehen darf, tauchen nirgends auf.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    /** Nutzer in einer Organisation, deren Gründer er NICHT ist. */
    private function member(Organization $organization, string $name = 'Ada Lovelace'): User
    {
        $user = User::factory()->create(['name' => $name]);
        $user->organization_id = $organization->id;
        $user->save();

        return $user;
    }

    private function owner(Organization $organization): User
    {
        $owner = $organization->owner;
        $owner->organization_id = $organization->id;
        $owner->save();

        return $owner;
    }

    /** Projekt derselben Organisation, das `$owner` erstellt hat. */
    private function project(Organization $organization, User $owner): Project
    {
        return Project::factory()->create([
            'organization_id' => $organization->id,
            'created_by_id' => $owner->id,
        ]);
    }

    /**
     * @return array{0: Organization, 1: User, 2: Project}
     */
    private function scenario(): array
    {
        $organization = Organization::factory()->create();
        $ada = $this->member($organization);

        return [$organization, $ada, $this->project($organization, $ada)];
    }

    /**
     * @return array<string, mixed>
     */
    private function dashboard(User $user): array
    {
        return $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->inertiaProps('data');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>> Die Einträge der Gruppe
     */
    private function names(array $data, string $bucket): array
    {
        return collect($data['buckets'])
            ->firstWhere('key', $bucket)['items'];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    private function bucketNames(array $data, string $bucket): array
    {
        return collect($this->names($data, $bucket))->pluck('name')->sort()->values()->all();
    }

    // --- Erreichbarkeit -------------------------------------------------------

    public function test_requires_authentication(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    /**
     * Ohne Organisation gibt es keine Projekte und damit nichts anzuzeigen — wie
     * bei jeder anderen Seite hinter EnsureUserHasOrganization.
     */
    public function test_without_an_organization_it_redirects_to_the_organization_page(): void
    {
        $user = User::factory()->create(['organization_id' => null]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('organization.index'));
    }

    /** Die Startseite ist jetzt das Dashboard, nicht mehr die Projektliste. */
    public function test_root_redirects_to_the_dashboard(): void
    {
        [, $ada] = $this->scenario();

        $this->actingAs($ada)->get('/')->assertRedirect(route('dashboard'));
    }

    /** Das Logo zeigt auf das Dashboard (Shell-Prop, gilt für alle Seiten). */
    public function test_logo_points_to_the_dashboard(): void
    {
        [, $ada] = $this->scenario();

        $this->actingAs($ada)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('shell.logoHref', route('dashboard'))
                ->has('data.buckets', 3)
                ->has('data.kpis')
                ->has('strings')
            );
    }

    /**
     * Die Task-Zeilen zeigen dasselbe Kopier-Menü wie die Board-Karten. Dessen
     * Labels sind eine Teilmenge der board-Sprachdatei — fehlt einer, stünde im
     * Menü der rohe Schlüssel.
     */
    public function test_ships_the_copy_menu_labels_and_setup_url(): void
    {
        [, $ada] = $this->scenario();

        $response = $this->actingAs($ada)->get(route('dashboard'))->assertOk();

        $copyStrings = $response->inertiaProps('copyStrings');

        foreach (['copy', 'copied', 'copy_task_name', 'copy_work_command', 'start_with_claude', 'claudetask_setup_link'] as $key) {
            $this->assertArrayHasKey($key, $copyStrings);
            $this->assertNotSame("board.$key", $copyStrings[$key], 'Unaufgelöster Übersetzungsschlüssel');
        }

        // Verweis auf die Handler-Anleitung, falls der Claude-Start ins Leere läuft.
        $this->assertSame(route('claudetask.setup'), $response->inertiaProps('urls')['claudetaskSetup']);
    }

    // --- Auswahlregel „Bei mir" ------------------------------------------------

    /** Eigener Arbeitsschritt ja, fremder nein. */
    public function test_work_bucket_holds_only_my_own_claims(): void
    {
        [$organization, $ada, $project] = $this->scenario();
        $grace = $this->member($organization, 'Grace Hopper');

        Task::factory()->create([
            'project_id' => $project->id,
            'name' => 'MINE',
            'claimed_by_id' => $ada->id,
            'status' => TaskStatus::IN_PROGRESS,
            'effort_story_points' => 5,
        ]);
        Task::factory()->create([
            'project_id' => $project->id,
            'name' => 'THEIRS',
            'claimed_by_id' => $grace->id,
            'status' => TaskStatus::IN_PROGRESS,
        ]);

        $data = $this->dashboard($ada);

        $this->assertSame(['MINE'], $this->bucketNames($data, 'work'));
        $this->assertSame(5, collect($data['buckets'])->firstWhere('key', 'work')['sp']);
        $this->assertSame(1, $data['kpis']['actionable']);
        $this->assertSame(5, $data['kpis']['actionableSp']);
    }

    /**
     * Reviews: eigene und freie zählen, das Review eines Kollegen nicht — dieselbe
     * Regel wie der Board-Chip.
     */
    public function test_review_bucket_holds_mine_and_free_reviews(): void
    {
        [$organization, $ada, $project] = $this->scenario();
        $grace = $this->member($organization, 'Grace Hopper');

        Task::factory()->create([
            'project_id' => $project->id,
            'name' => 'FREE',
            'claimed_by_id' => $grace->id,
            'reviewed_by' => null,
            'status' => 'REVIEWBAR',
        ]);
        Task::factory()->create([
            'project_id' => $project->id,
            'name' => 'MINEREV',
            'claimed_by_id' => $grace->id,
            'reviewed_by' => $ada->id,
            'status' => TaskStatus::IN_REVIEW,
        ]);
        Task::factory()->create([
            'project_id' => $project->id,
            'name' => 'THEIRREV',
            'claimed_by_id' => $ada->id,
            'reviewed_by' => $grace->id,
            'status' => TaskStatus::IN_REVIEW,
        ]);

        $data = $this->dashboard($ada);

        $this->assertSame(['FREE', 'MINEREV'], $this->bucketNames($data, 'review'));
        $this->assertSame(1, $data['kpis']['reviewsFree']);
        $this->assertSame(1, $data['kpis']['reviewsMine']);

        $free = collect($this->names($data, 'review'))->firstWhere('name', 'FREE');
        $this->assertTrue($free['isFreeReview']);
        $this->assertFalse($free['isMyClaim']);
    }

    /**
     * Über den Board-Chip hinaus: eigene Tasks in einer Ausnahme. Auf dem Board
     * steht die Ausnahme-Spalte daneben, hier wäre die Arbeit sonst unsichtbar.
     */
    public function test_blocked_bucket_holds_my_own_exceptions_with_the_concern_text(): void
    {
        [$organization, $ada, $project] = $this->scenario();
        $grace = $this->member($organization, 'Grace Hopper');

        $task = Task::factory()->create([
            'project_id' => $project->id,
            'name' => 'STUCK',
            'claimed_by_id' => $ada->id,
            'status' => TaskStatus::CONCERNED,
        ]);
        TaskConcern::create([
            'task_id' => $task->id,
            'created_by_id' => $ada->id,
            'summary' => 'Schema unklar',
        ]);

        // Fremde Ausnahme — nicht meine Baustelle.
        Task::factory()->create([
            'project_id' => $project->id,
            'name' => 'THEIRSTUCK',
            'claimed_by_id' => $grace->id,
            'status' => TaskStatus::CONCERNED,
        ]);

        $data = $this->dashboard($ada);

        $this->assertSame(['STUCK'], $this->bucketNames($data, 'blocked'));
        $this->assertSame('Schema unklar', $this->names($data, 'blocked')[0]['concern']);
    }

    /** Erledigtes und Wartendes ist kein Handlungsbedarf. */
    public function test_done_and_waiting_tasks_are_not_actionable(): void
    {
        [, $ada, $project] = $this->scenario();

        Task::factory()->create([
            'project_id' => $project->id,
            'claimed_by_id' => $ada->id,
            'status' => TaskStatus::MERGED,
            'merged_at' => now(),
        ]);
        Task::factory()->create([
            'project_id' => $project->id,
            'claimed_by_id' => null,
            'status' => TaskStatus::PICKABLE,
        ]);

        $data = $this->dashboard($ada);

        $this->assertSame(0, $data['kpis']['actionable']);
    }

    /**
     * Ein Task in einem Projekt, das ich nicht sehen darf, gehört nicht auf mein
     * Dashboard — dieselbe Sichtbarkeitsregel wie Projektliste und Statistik.
     */
    public function test_only_visible_projects_are_included(): void
    {
        [$organization, $ada, $own] = $this->scenario();
        $foreign = $this->project($organization, $this->owner($organization));

        Task::factory()->create([
            'project_id' => $own->id,
            'name' => 'VISIBLE',
            'claimed_by_id' => $ada->id,
            'status' => TaskStatus::IN_PROGRESS,
        ]);
        Task::factory()->create([
            'project_id' => $foreign->id,
            'name' => 'HIDDEN',
            'claimed_by_id' => $ada->id,
            'status' => TaskStatus::IN_PROGRESS,
        ]);

        $data = $this->dashboard($ada);

        $this->assertSame(['VISIBLE'], $this->bucketNames($data, 'work'));
        $this->assertSame([$own->alias], collect($data['projects'])->pluck('alias')->all());
    }

    /**
     * Die Filter-Pills listen nur Projekte, in denen wirklich etwas bei mir liegt —
     * ein Filter, der garantiert leer bleibt, ist keine Hilfe. Die Zahlen kommen
     * aus der vollen Menge, nicht aus den je Gruppe gelieferten Einträgen.
     */
    public function test_project_filters_only_list_projects_with_actionable_items(): void
    {
        [$organization, $ada, $first] = $this->scenario();
        $second = $this->project($organization, $ada);
        $quiet = $this->project($organization, $ada);

        Task::factory()->create([
            'project_id' => $first->id,
            'claimed_by_id' => $ada->id,
            'status' => TaskStatus::IN_PROGRESS,
            'effort_story_points' => 5,
        ]);
        Task::factory()->create([
            'project_id' => $first->id,
            'claimed_by_id' => $ada->id,
            'status' => TaskStatus::IN_PROGRESS,
            'effort_story_points' => 3,
        ]);
        Task::factory()->create([
            'project_id' => $second->id,
            'claimed_by_id' => $ada->id,
            'reviewed_by' => $ada->id,
            'status' => TaskStatus::IN_REVIEW,
            'effort_story_points' => 2,
        ]);
        // Nichts, was bei mir liegt — bekommt keine Pille.
        Task::factory()->create([
            'project_id' => $quiet->id,
            'claimed_by_id' => null,
            'status' => TaskStatus::PICKABLE,
        ]);

        $data = $this->dashboard($ada);

        $this->assertSame(
            [$first->alias, $second->alias],
            collect($data['projectFilters'])->pluck('alias')->all(),
            'nach Anzahl sortiert, ohne das stille Projekt'
        );
        $this->assertSame([2, 1], collect($data['projectFilters'])->pluck('count')->all());

        // Anzahl und SP je Gruppe UND Projekt — die Grundlage der gefilterten
        // Gruppenkopfzeile.
        $work = collect($data['buckets'])->firstWhere('key', 'work');
        $this->assertSame(['count' => 2, 'sp' => 8], $work['byProject'][$first->alias]);
        $this->assertArrayNotHasKey($second->alias, $work['byProject']);

        $review = collect($data['buckets'])->firstWhere('key', 'review');
        $this->assertSame(['count' => 1, 'sp' => 2], $review['byProject'][$second->alias]);
    }

    // --- Nebenpanels ----------------------------------------------------------

    /**
     * „Frei zum Ziehen" heißt: unbeansprucht, wartend UND das Gate offen. Ein Task
     * hinter einer unerfüllten Voraussetzung ist blockiert, kein Vorschlag.
     */
    public function test_pickable_panel_skips_gated_and_claimed_tasks(): void
    {
        [, $ada, $project] = $this->scenario();

        Task::factory()->create([
            'project_id' => $project->id,
            'name' => 'FREE',
            'claimed_by_id' => null,
            'pr_number' => null,
            'status' => TaskStatus::PICKABLE,
        ]);
        $blocker = Task::factory()->create([
            'project_id' => $project->id,
            'name' => 'BLOCKER',
            'claimed_by_id' => null,
            'pr_number' => null,
            'status' => TaskStatus::PICKABLE,
        ]);
        $gated = Task::factory()->create([
            'project_id' => $project->id,
            'name' => 'GATED',
            'claimed_by_id' => null,
            'pr_number' => null,
            'status' => TaskStatus::PICKABLE,
        ]);
        $gated->prerequisites()->sync([$blocker->id]);

        Task::factory()->create([
            'project_id' => $project->id,
            'name' => 'TAKEN',
            'claimed_by_id' => $ada->id,
            'status' => TaskStatus::PICKABLE,
        ]);

        $pickable = collect($this->dashboard($ada)['pickable']);

        $this->assertSame(['BLOCKER', 'FREE'], $pickable->pluck('name')->sort()->values()->all());
        // BLOCKER hält GATED auf — das steht oben.
        $this->assertSame('BLOCKER', $pickable->first()['name']);
        $this->assertSame(1, $pickable->first()['dependents']);
        $this->assertSame(0, $pickable->firstWhere('name', 'FREE')['dependents']);
    }

    /** Archivierte Projekte liefern keine Vorschläge und keine Projektzeile. */
    public function test_archived_projects_are_left_out_of_the_side_panels(): void
    {
        [$organization, $ada] = $this->scenario();
        $archived = $this->project($organization, $ada);
        $archived->update(['archived_at' => now()]);

        Task::factory()->create([
            'project_id' => $archived->id,
            'name' => 'OLD',
            'claimed_by_id' => null,
            'pr_number' => null,
            'status' => TaskStatus::PICKABLE,
        ]);

        $data = $this->dashboard($ada);

        $this->assertSame([], $data['pickable']);
        $this->assertNotContains($archived->alias, collect($data['projects'])->pluck('alias')->all());
    }

    /**
     * Die Wochenkacheln zählen ab Wochenbeginn: eigene Merges und gegebene
     * Reviews. Älteres bleibt draußen.
     */
    public function test_week_kpis_count_this_weeks_merges_and_reviews(): void
    {
        [$organization, $ada, $project] = $this->scenario();
        $grace = $this->member($organization, 'Grace Hopper');

        Task::factory()->create([
            'project_id' => $project->id,
            'claimed_by_id' => $ada->id,
            'status' => TaskStatus::MERGED,
            'merged_at' => now(),
            'effort_story_points' => 3,
        ]);
        Task::factory()->create([
            'project_id' => $project->id,
            'claimed_by_id' => $ada->id,
            'status' => TaskStatus::MERGED,
            'merged_at' => now()->startOfWeek()->subDay(),
            'effort_story_points' => 8,
        ]);
        Task::factory()->create([
            'project_id' => $project->id,
            'claimed_by_id' => $grace->id,
            'reviewed_by' => $ada->id,
            'last_reviewed_at' => now(),
            'status' => TaskStatus::MERGED,
            'merged_at' => now(),
        ]);

        $kpis = $this->dashboard($ada)['kpis'];

        $this->assertSame(1, $kpis['deliveredTasks'], 'nur der Merge dieser Woche');
        $this->assertSame(3, $kpis['deliveredSp']);
        $this->assertSame(1, $kpis['reviewsGiven']);
    }

    /** Der Projektfortschritt zählt nach Story Points, solange es welche gibt. */
    public function test_project_row_reports_progress_and_my_open_count(): void
    {
        [, $ada, $project] = $this->scenario();

        Task::factory()->create([
            'project_id' => $project->id,
            'claimed_by_id' => $ada->id,
            'status' => TaskStatus::MERGED,
            'merged_at' => now(),
            'effort_story_points' => 3,
        ]);
        Task::factory()->create([
            'project_id' => $project->id,
            'claimed_by_id' => $ada->id,
            'status' => TaskStatus::IN_PROGRESS,
            'effort_story_points' => 1,
        ]);

        $row = collect($this->dashboard($ada)['projects'])->firstWhere('alias', $project->alias);

        $this->assertTrue($row['byStoryPoints']);
        $this->assertSame(75, $row['percent'], '3 von 4 SP geliefert');
        $this->assertSame(1, $row['myOpen']);
        $this->assertSame(1, $row['myActionable']);
    }

    // --- Leerzustand + Übersetzungen -----------------------------------------

    /** Ohne sichtbares Projekt bleibt die Seite erreichbar und sagt das auch. */
    public function test_without_visible_projects_the_payload_is_empty_but_valid(): void
    {
        $organization = Organization::factory()->create();
        $ada = $this->member($organization);

        $data = $this->dashboard($ada);

        $this->assertFalse($data['hasProjects']);
        $this->assertCount(3, $data['buckets']);
        $this->assertSame(0, $data['kpis']['actionable']);
        $this->assertNull($data['kpis']['oldestDays']);
    }

    /**
     * @return array<int, array{0: string}>
     */
    public static function locales(): array
    {
        return [['de'], ['en']];
    }

    /**
     * Die Seite besteht fast vollständig aus Übersetzungsschlüsseln — fehlt einer,
     * rendert Laravel still den Schlüssel selbst („dashboard.kpi_week").
     *
     * @dataProvider locales
     */
    public function test_carries_no_unresolved_translation_keys(string $locale): void
    {
        [, $ada] = $this->scenario();
        $ada->locale = $locale;
        $ada->save();

        $strings = $this->actingAs($ada)
            ->get(route('dashboard'))
            ->assertOk()
            ->inertiaProps('strings');

        $matched = preg_match(
            '/"(?:dashboard|statistics|common)\.[a-z0-9_]+"/',
            (string) json_encode($strings, JSON_UNESCAPED_UNICODE),
            $hit
        );

        $this->assertSame(0, $matched, 'Unaufgelöster Übersetzungsschlüssel ('.$locale.'): '.($hit[0] ?? ''));
    }
}
