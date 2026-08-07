<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Support\SkillTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SkillEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_a_token(): void
    {
        $this->getJson('/api/skill')->assertUnauthorized();
    }

    public function test_returns_the_composed_skill_and_its_revision(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/skill')->assertOk();

        $this->assertSame(SkillTemplate::composed(), $response->json('skill_md'));
        $this->assertSame(SkillTemplate::sharedRevision(), $response->json('skill_revision'));
        $this->assertSame(SkillTemplate::planRevision(), $response->json('plan_revision'));
    }

    /**
     * `skill_revision` is one hash over all maintained files, so it says THAT something
     * changed, never WHICH block. The per-block map is what lets a client pull just the
     * drifted block back into context via `?parts=<key>` — so its keys have to be
     * exactly the `parts` names, and it must not replace the shared revision that the
     * per-project skills (L2LR/LOG) watch.
     */
    public function test_returns_a_revision_per_maintained_block(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/skill')->assertOk();

        $this->assertSame(
            ['operating_manual', 'status_rules', 'skill_instructions', 'plan_instructions'],
            array_keys((array) $response->json('revisions')),
        );

        // Every block revision differs from the others (they hash different content) and
        // none of them equals the shared revision, which still covers all of them.
        $revisions = (array) $response->json('revisions');
        $this->assertSame($revisions, array_unique($revisions));
        $this->assertNotContains($response->json('skill_revision'), $revisions);

        // The shared revision keeps its meaning — L2LR/LOG rely on this header value.
        $this->assertSame(SkillTemplate::sharedRevision(), $response->json('skill_revision'));
    }

    /**
     * The endpoint exists so a client can replace its stale SKILL.md — the text it
     * serves must therefore carry the server-maintained conventions, above all the
     * PR-title prefix (a stale copy dropped the project alias).
     */
    public function test_served_skill_carries_the_pr_title_convention(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $skill = $this->getJson('/api/skill')->assertOk()->json('skill_md');

        $this->assertStringContainsString('<PROJECT>-<TASK>: <Kurzbeschreibung>', $skill);
        $this->assertStringContainsString('Snapshot mitschreiben', $skill);
    }

    /**
     * The auto mode runs unattended, so the served skill must carry the sticky
     * status line directives — above all the two that are easy to lose in an
     * edit and silently break it: the per-session state file (without the
     * session_id the line shows up in EVERY session of the user) and
     * refreshInterval (without it the line freezes while an auto run works as a
     * subagent, because the status line is otherwise only event-driven).
     */
    public function test_served_skill_carries_the_auto_mode_status_line(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $skill = $this->getJson('/api/skill')->assertOk()->json('skill_md');

        $this->assertStringContainsString('Sticky-Statuszeile', $skill);
        $this->assertStringContainsString('<Symbol> <Kommando> (<Phase> <%>) <PROJECT> · <TASK> — <kurzer Schritt>', $skill);
        $this->assertStringContainsString('planstack-status-<session_id>.txt', $skill);
        $this->assertStringContainsString('refreshInterval', $skill);
        $this->assertStringContainsString('OSC 8', $skill);
    }

    /**
     * Mehrere Worker teilen sich ein Fenster: ohne die Slot-Dateien schreiben sie alle
     * in dieselbe Zustandsdatei, und die Zeile zeigt zufällig den, der zuletzt dran
     * war. Die Einstellung, die den Parallelbetrieb überhaupt einschaltet, gehört
     * ebenfalls in den ausgelieferten Text — die Auto-Anleitung liest sie dort.
     */
    public function test_served_skill_carries_the_parallel_worker_settings(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $skill = $this->getJson('/api/skill')->assertOk()->json('skill_md');

        $this->assertStringContainsString('auto_workers', $skill);
        $this->assertStringContainsString('planstack-status-<session_id>.w<k>.txt', $skill);
    }

    /**
     * Die Statuszeile trägt eine Prozentzahl, und der Text wird mit `printf`
     * geschrieben (die OSC-8-Steuerzeichen müssen echt sein). Wer beides
     * zusammenbringt, escaped `%` reflexhaft zu `%%` — und schreibt die Zeile dann
     * mit einem Werkzeug ohne Format-String, sodass die Verdopplung sichtbar wird
     * („Review 94 %%"). Die Regel dagegen muss im ausgelieferten Text stehen, sonst
     * kommt der Fehler mit dem nächsten Aufruf zurück.
     */
    public function test_served_skill_forbids_doubling_the_percent_sign(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $skill = $this->getJson('/api/skill')->assertOk()->json('skill_md');

        $this->assertStringContainsString('nie `%%`', $skill, 'die Regel gegen das doppelte Prozentzeichen fehlt');
        $this->assertStringContainsString("printf '%s\\n'", $skill, 'der sichere Schreibweg fehlt');

        // Und der Anleitungstext selbst darf keine Prozentangabe verdoppeln: die
        // Beispielzeilen werden abgeschrieben, ein `%%` darin lehrt genau den Fehler.
        $this->assertSame(
            0,
            preg_match('/\d\s*%%/', $skill, $hit),
            'verdoppeltes Prozentzeichen in einer Beispielzeile: '.($hit[0] ?? ''),
        );
    }
}
