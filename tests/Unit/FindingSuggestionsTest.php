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

    public function test_splits_labelled_paragraphs_into_sections(): void
    {
        $this->assertSame([
            ['label' => 'Was', 'text' => "a\nb"],
            ['label' => 'Warum falsch', 'text' => 'c'],
        ], FindingSuggestions::sections("**Was:** a\nb\n**Warum falsch:** c"));
    }

    public function test_has_no_sections_for_free_text(): void
    {
        $this->assertNull(FindingSuggestions::sections("Freitext\n**Was:** a"));
        $this->assertNull(FindingSuggestions::sections('**fett**'));
    }

    public function test_splits_an_option_into_title_and_text(): void
    {
        $this->assertSame(
            ['title' => 'PO entscheiden lassen', 'text' => 'Bei `UPDATE x; y` — Rollout.'],
            FindingSuggestions::headline('PO entscheiden lassen; bei `UPDATE x; y` — Rollout.'),
        );
        $this->assertSame(['title' => null, 'text' => '`a; b` nur Code'], FindingSuggestions::headline('`a; b` nur Code'));
        $this->assertSame(['title' => null, 'text' => 'Ohne Trenner'], FindingSuggestions::headline('Ohne Trenner'));
    }

    public function test_ignores_messages_without_options(): void
    {
        $this->assertNull(FindingSuggestions::parse('**Vorschlag (80 %):** einfach'));
        $this->assertNull(FindingSuggestions::parse('- **B (50 %):** ohne A'));
    }
}
