<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Anleitungsseite für den lokalen claudetask:-Protokoll-Handler (Windows/macOS).
 * Verlinkt wird sie aus dem Kopier-Menü der Board-Karten: schlägt dort der Start
 * über claudetask: vermutlich fehl (Heuristik im CopyMenu), zeigt die Karte einen
 * Hinweis hierher. Reine Inhaltsseite — die Code-Schnipsel selbst stehen in der
 * React-Seite, damit ihre Formatierung nicht durch die Übersetzung läuft.
 */
class ClaudetaskSetupController extends Controller
{
    public function __invoke(): InertiaResponse
    {
        return Inertia::render('ClaudetaskSetup', [
            'strings' => [
                'title' => __('claudetask.title'),

                'introHeading' => __('claudetask.intro_heading'),
                'intro1' => __('claudetask.intro_1'),
                'intro2' => __('claudetask.intro_2'),
                'prerequisite' => __('claudetask.prerequisite'),

                'quickSetupHeading' => __('claudetask.quick_setup_heading'),
                'quickSetupIntro' => __('claudetask.quick_setup_intro'),
                'setupPromptWin' => __('claudetask.setup_prompt_win'),
                'setupPromptMac' => __('claudetask.setup_prompt_mac'),

                'windowsHeading' => __('claudetask.windows_heading'),
                'windowsStep1' => __('claudetask.windows_step_1'),
                'windowsStep2' => __('claudetask.windows_step_2'),
                'windowsStep3' => __('claudetask.windows_step_3'),

                'autoModeHeading' => __('claudetask.auto_mode_heading'),
                'autoMode1' => __('claudetask.auto_mode_1'),
                'autoMode2' => __('claudetask.auto_mode_2'),
                'autoModeWarn' => __('claudetask.auto_mode_warn'),

                'macHeading' => __('claudetask.mac_heading'),
                'macStep1' => __('claudetask.mac_step_1'),
                'macStep2' => __('claudetask.mac_step_2'),
                'macStep3' => __('claudetask.mac_step_3'),
                'macStep4' => __('claudetask.mac_step_4'),

                'troubleshootHeading' => __('claudetask.troubleshoot_heading'),
                'troubleshoot1' => __('claudetask.troubleshoot_1'),
                'troubleshoot2' => __('claudetask.troubleshoot_2'),
                'troubleshoot3' => __('claudetask.troubleshoot_3'),
                'troubleshoot4' => __('claudetask.troubleshoot_4'),

                'copy' => __('claudetask.copy'),
                'copied' => __('common.copied'),
            ],
        ]);
    }
}
