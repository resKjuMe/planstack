<?php

namespace Tests\Unit;

use App\Support\PullRequestSkippedFindings;
use PHPUnit\Framework\TestCase;

class PullRequestSkippedFindingsTest extends TestCase
{
    public function test_creates_the_section_before_the_attribution(): void
    {
        $body = "Text\r\n\r\n🤖 Generated with [Claude Code](https://claude.com/claude-code)\r\n";

        $this->assertSame(
            "Text\n\n## Review-Entscheidungen\n\n### Nicht umgesetzt\n\n- A\n\n🤖 Generated with [Claude Code](https://claude.com/claude-code)",
            PullRequestSkippedFindings::withSkippedFinding($body, '- A'),
        );
    }

    public function test_creates_the_section_at_the_end_without_attribution(): void
    {
        $this->assertSame("Text\n\n## Review-Entscheidungen\n\n### Nicht umgesetzt\n\n- A", PullRequestSkippedFindings::withSkippedFinding("Text\n", '- A'));
        $this->assertSame("## Review-Entscheidungen\n\n### Nicht umgesetzt\n\n- A", PullRequestSkippedFindings::withSkippedFinding('', '- A'));
    }

    public function test_adds_the_subsection_to_an_existing_section(): void
    {
        $body = "## Review-Entscheidungen\n\n| Befund |\n|---|\n\n### PO-Entscheidungen\n\n- **X:** y\n\n## Tests\n\nok";

        $this->assertSame(
            "## Review-Entscheidungen\n\n| Befund |\n|---|\n\n### PO-Entscheidungen\n\n- **X:** y\n\n### Nicht umgesetzt\n\n- A\n\n## Tests\n\nok",
            PullRequestSkippedFindings::withSkippedFinding($body, '- A'),
        );
    }

    public function test_appends_to_an_existing_list(): void
    {
        $body = "## Review-Entscheidungen\n\n### Nicht umgesetzt\n\n- A\n\n### PO-Entscheidungen\n\n- B";

        $this->assertSame(
            "## Review-Entscheidungen\n\n### Nicht umgesetzt\n\n- A\n- C\n\n### PO-Entscheidungen\n\n- B",
            PullRequestSkippedFindings::withSkippedFinding($body, '- C'),
        );
    }
}
