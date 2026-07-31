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
