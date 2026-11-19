<?php

namespace Tests\Feature;

use App\Support\PsReviewSkill;
use Tests\TestCase;

class PsReviewSkillVersionTest extends TestCase
{
    // Bei jeder Änderung an der SKILL.md: `version` im Frontmatter erhöhen und beides hier nachziehen.
    private const VERSION = '1.2.2';

    private const CONTENT_HASH = 'da0299b2544b6c526eb3c96cd40db76cd846395634fdf5b3dacd2bc461b3e921';

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
