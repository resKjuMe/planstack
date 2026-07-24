<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Erklärungs-/Einrichtungsseite des Planstack-CI-Status-Servers
 * (ehemals planstack-ci/setup.blade.php). Reine Inhaltsseite; die Downloads
 * (Userscript + ci-server.cjs) bleiben echte Nicht-Inertia-Links (data-native)
 * und zeigen weiterhin auf die asset()-URLs unter /planstack-ci/.
 */
class PlanstackCiSetupController extends Controller
{
    public function __invoke(): InertiaResponse
    {
        $ciVersion = config('planstack_ci.version');

        $userscriptUrl = asset('planstack-ci/planstack-ci.user.min.js');
        $ciServerUrl   = asset('planstack-ci/ci-server.cjs');

        // &nbsp; im Blade per {!! !!} roh gerendert — hier zu echtem NBSP
        // aufgelöst, damit React die Zeichenkette direkt als Text ausgeben kann.
        $nbsp = fn (string $key): string => str_replace('&nbsp;', "\u{00A0}", __($key));

        return Inertia::render('PlanstackCiSetup', [
            'ciVersion' => $ciVersion,
            'urls' => [
                'userscript' => $userscriptUrl,
                'ciServer' => $ciServerUrl,
            ],
            'strings' => [
                // Kopf / Intro / Status
                'title' => __('ci.planstack_ci_status'),
                'sub' => __('ci.shows_the_real_ci_merge_status_on_each'),
                'checkingLocalServer' => __('ci.checking_local_server'),

                // 1 · Was du brauchst
                'whatYouNeed' => __('ci.what_you_need'),
                'thenSignInOnce' => __('ci.then_sign_in_once'),
                'browserExtension' => __('ci.browser_extension'),

                // 2 · Downloads
                'downloads' => __('ci.downloads'),
                'installTheUserscript' => __('ci.install_the_userscript'),
                'openTheUserscriptLink' => __('ci.open_the_userscript_link_tampermonkey'),
                'saveItEg' => $nbsp('ci.save_it_e_g_nbsp_windows'),
                'mac' => __('ci.mac'),

                // 3 · Schnell-Einrichtung mit Claude Code
                'quickSetup' => __('ci.quick_setup_with_claude_code'),
                'recommended' => __('ci.recommended'),
                'doYouHave' => __('ci.do_you_have'),
                'copyTheMatchingPrompt' => __('ci.copy_the_matching_prompt_into_a_claude'),
                'youDoYourselfOnce' => __('ci.you_do_yourself_once_interactively'),
                'copyPrompt' => __('ci.copy_prompt'),
                'setupPromptWin' => __('ci.setup_prompt_win', ['asset' => $ciServerUrl, 'version' => $ciVersion]),
                'setupPromptMac' => __('ci.setup_prompt_mac', ['asset' => $ciServerUrl, 'version' => $ciVersion]),
                'thenInstallThe' => __('ci.then_install_the'),
                'fromStep2InTampermonkey' => __('ci.from_step_2_in_tampermonkey_done'),

                // 4 · Manuell einrichten
                'setUpManually' => __('ci.set_up_manually'),
                'alternative' => __('ci.alternative'),
                'installNodeGhThen' => __('ci.install_node_js_github_cli_then'),
                'run' => __('ci.run'),
                'downloadItEgTo' => $nbsp('ci.download_it_e_g_nbsp_to'),
                'testTheServerPowershell' => __('ci.test_the_server_powershell'),
                'expected' => __('ci.expected'),
                'with' => __('ci.with'),
                'ctrl' => __('ci.ctrl'),
                'quit' => __('ci.quit'),
                'autostartOnEveryLogin' => __('ci.autostart_on_every_login'),
                'noAdminRequired' => __('ci.no_admin_required_paste_into_powershell'),
                'itNowStartsHidden' => __('ci.it_now_starts_hidden_at_every_login_to'),
                'fromTheStartupFolder' => __('ci.from_the_startup_folder'),
                'installNodeGhThenSignIn' => __('ci.install_node_js_github_cli_then_sign_in'),
                'testTheServer' => __('ci.test_the_server'),
                'autostartAtLogin' => __('ci.autostart_at_login'),
                'viaLaunchAgent' => __('ci.via_launchagent_run_in_the_terminal'),
                'itNowRunsAutomatically' => __('ci.it_now_runs_automatically_to_remove'),
                'deleteThePlist' => __('ci.delete_the_plist'),

                // 5 · Fertig
                'done' => __('ci.done'),
                'reloadTheDiagramPage' => __('ci.reload_the_diagram_page_the_ci_merge'),

                // Skript-Status / Copy
                'copied' => __('common.copied'),
                'localServerIsRunningV' => __('ci.local_server_is_running_v'),
                'allSetDiagramShowsCi' => __('ci.all_set_the_diagram_page_now_shows_ci'),
                'localServerUnreachable' => __('ci.local_server_unreachable'),
                'followTheStepsBelow' => __('ci.follow_the_steps_below_then_reload_this'),
            ],
        ]);
    }
}
