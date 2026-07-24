<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Erklärungs-/Einrichtungsseite des projektübergreifenden Planstack-Skills
 * (ehemals skill/setup.blade.php). Reine Inhaltsseite; der eigentliche
 * ZIP-Download läuft weiter über SkillDownloadController (Route skill.download,
 * Link mit data-native).
 */
class SkillSetupController extends Controller
{
    public function __invoke(): InertiaResponse
    {
        return Inertia::render('SkillSetup', [
            'downloadUrl' => route('skill.download'),
            'profileUrl' => route('profile.edit'),
            'strings' => [
                'title' => __('skill.planstack_skill_for_claude_code'),
                'oneSkill' => __('skill.one_skill_for_all_your_projects'),
                'introPre' => __('skill.with_the_planstack_skill_claude_code'),
                'crossProject' => __('skill.cross_project'),
                'introPost' => __('skill.it_contains_no_fixed_project_you'),
                'downloadZip' => __('skill.download_skill_zip'),
                'zipContains' => __('skill.the_zip_contains'),
                'andPrefilled' => __('skill.and_a_prefilled'),
                'withToken' => __('skill.with_a_freshly_generated_personal'),
                'installation' => __('skill.installation'),
                'installDownload' => __('skill.download_and_unzip_the_zip'),
                'installFolder' => __('skill.the_folder'),
                'installTo' => __('skill.to'),
                'installMove' => __('skill.move'),
                'installDone' => __('skill.done_in_claude_code_the_command'),
                'installReady' => __('skill.is_ready'),
                'usage' => __('skill.usage'),
                'usageProject' => __('skill.works_through_this_project_s_entire'),
                'usageTask' => __('skill.works_through_a_single_specific_task'),
                'usageTaskShortcode' => __('skill.task_shortcode_e_g'),
                'usageAuto' => __('skill.works_through_the_board_unattended'),
                'usageWorkDeprecated' => __('skill.the_bare_form_without_work_is_deprecated'),
                'usageReview' => __('skill.reviews_tasks_that_are_in_review_with_a'),
                'usageFix' => __('skill.repairs_an_open_pr_resolves_merge'),
                'usageSettings' => __('skill.view_change_local_settings_tests'),
                'usageUpdateConfig' => __('skill.pulls_the_latest_general_and_optionally'),
                'usageAliasIs' => __('skill.is_the_project_alias_e_g'),
                'usageServesEvery' => __('skill.the_skill_serves_every_project_your'),
                'goodToKnow' => __('skill.good_to_know'),
                'token' => __('skill.token'),
                'tokenText' => __('skill.on_download_a_personal_access_token_is'),
                'profileApiTokens' => __('common.profile_api_tokens'),
                'revocable' => __('skill.revocable'),
                'selfUpdating' => __('skill.self_updating'),
                'selfUpdatingText' => __('skill.the_skill_automatically_picks_up'),
                'noFixedProject' => __('skill.no_fixed_project'),
                'theWord' => __('skill.the'),
                'configContains' => __('skill.contains_only_access_details_url_token'),
            ],
        ]);
    }
}
