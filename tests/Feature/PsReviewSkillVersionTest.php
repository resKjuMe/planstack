<?php

namespace Tests\Feature;

use App\Support\PsReviewSkill;
use Tests\TestCase;

class PsReviewSkillVersionTest extends TestCase
{
    // Bei jeder Änderung an der SKILL.md: `version` im Frontmatter erhöhen und beides hier nachziehen.
    private const VERSION = '1.2.0';

    private const CONTENT_HASH = '872eec0f976f9deaffd162cc30784e0f98e84635420257c52fae662cac73f688';

    public function test_every_change_to_the_skill_gets_a_new_version(): void
    {
        $content = preg_replace('/^version:.*\n/m', '', str_replace("\r\n", "\n", (string) file_get_contents(PsReviewSkill::path())), 1);

        $this->assertSame(self::VERSION, PsReviewSkill::version());
        $this->assertSame(
            self::CONTENT_HASH,
            hash('sha256', $content),
            'ps-review/SKILL.md wurde geändert: `version` im Frontmatter erhöhen und VERSION/CONTENT_HASH in diesem Test nachziehen.',
        );
    }

    public function test_reviews_without_or_with_an_older_version_are_outdated(): void
    {
        $this->assertTrue(PsReviewSkill::isOutdated(null));
        $this->assertTrue(PsReviewSkill::isOutdated('0.9.0'));
        $this->assertFalse(PsReviewSkill::isOutdated(self::VERSION));
    }
}
