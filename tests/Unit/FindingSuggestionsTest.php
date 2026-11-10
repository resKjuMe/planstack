<?php

namespace Tests\Unit;

use App\Support\FindingSuggestions;
use PHPUnit\Framework\TestCase;

class FindingSuggestionsTest extends TestCase
{
    public function test_splits_the_options_from_the_message(): void
    {
        $message = "**Was:** x\n**Vorschlag:**\n- **A (60 %):** PO entscheiden; `UPDATE x`\n  ```sql\n  SELECT 1;\n  ```\n- **B (50 %):** Rückfall behalten — ok.\n\nNachsatz.";

        $this->assertSame([
            'before' => '**Was:** x',
            'options' => [
                ['letter' => 'A', 'percent' => 60, 'text' => "PO entscheiden; `UPDATE x`\n```sql\nSELECT 1;\n```"],
                ['letter' => 'B', 'percent' => 50, 'text' => 'Rückfall behalten — ok.'],
            ],
            'after' => 'Nachsatz.',
        ], FindingSuggestions::parse($message));
    }

    public function test_accepts_the_colon_outside_the_bold_part(): void
    {
        $parsed = FindingSuggestions::parse("Text\n\n**Vorschläge:**\n\n* **A (80%)**: Fix");

        $this->assertSame('Text', $parsed['before']);
        $this->assertSame([['letter' => 'A', 'percent' => 80, 'text' => 'Fix']], $parsed['options']);
        $this->assertSame('', $parsed['after']);
    }

    public function test_ignores_messages_without_options(): void
    {
        $this->assertNull(FindingSuggestions::parse('**Vorschlag (80 %):** einfach'));
        $this->assertNull(FindingSuggestions::parse('- **B (50 %):** ohne A'));
    }
}
