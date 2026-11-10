<?php

namespace Tests\Unit;

use App\Support\TicketMatch;
use PHPUnit\Framework\TestCase;

class TicketMatchTest extends TestCase
{
    public function test_splits_inline_points_into_bullets(): void
    {
        $this->assertSame(
            "**Weniger als verlangt:** nichts.\n\n**Mehr als verlangt:**\n\n- Flag `a; b`\n- Bündelung (AfterCommit::collect; Flush)\n- Redis-Pause.",
            TicketMatch::asBullets("**Weniger als verlangt:** nichts.\n\n**Mehr als verlangt:** Flag `a; b`; Bündelung (AfterCommit::collect; Flush); Redis-Pause."),
        );
    }

    public function test_keeps_existing_bullets(): void
    {
        $markdown = "**Mehr als verlangt:**\n\n- eins\n- zwei";

        $this->assertSame($markdown, TicketMatch::asBullets($markdown));
    }

    public function test_a_single_point_becomes_a_bullet_too(): void
    {
        $this->assertSame("**Weniger als verlangt:**\n\n- Ort der Einstellung offen.", TicketMatch::asBullets('**Weniger als verlangt:** Ort der Einstellung offen.'));
    }
}
