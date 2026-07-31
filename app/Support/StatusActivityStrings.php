<?php

namespace App\Support;

/**
 * Beschriftungen der Aktivitäts-Heatmap — an EINER Stelle, weil dieselbe Karte auf
 * drei Seiten steht (Projekt-Performance, Organisations-Aktivität, persönliche
 * Statistik). Ohne das müsste jede Seite dieselben fünfzehn Schlüssel auflisten, und
 * die dritte hätte sie irgendwann anders.
 *
 * Templates mit :platzhaltern bleiben roh (der Client interpoliert, siehe
 * resources/js/summary/i18n.js), ebenso trans_choice-Formen mit „|".
 */
final class StatusActivityStrings
{
    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return [
            'heatmapTitle' => __('performance.heatmap_title'),
            'heatmapSub' => __('performance.heatmap_sub'),
            'heatmapPerson' => __('performance.heatmap_person'),
            'heatmapPersonAll' => __('performance.heatmap_person_all'),
            'heatmapPersonOption' => __('performance.heatmap_person_option'),
            'heatmapRange' => __('performance.heatmap_range'),
            'heatmapRangeWeeks' => __('performance.heatmap_range_weeks'),
            'heatmapTotal' => __('performance.heatmap_total'),
            'heatmapBusiest' => __('performance.heatmap_busiest'),
            'heatmapCell' => __('performance.heatmap_cell'),
            'heatmapGroupWork' => __('performance.heatmap_group_work'),
            'heatmapGroupReview' => __('performance.heatmap_group_review'),
            'heatmapGroupOther' => __('performance.heatmap_group_other'),
            'heatmapLegendLess' => __('performance.heatmap_legend_less'),
            'heatmapLegendMore' => __('performance.heatmap_legend_more'),
            'heatmapEmpty' => __('performance.heatmap_empty'),
            'heatmapEmptyPerson' => __('performance.heatmap_empty_person'),
            // Der Ladefehler der Karte — dieselbe Formulierung wie die übrigen
            // Ladefehler der Auswertungsseiten.
            'loadError' => __('status.load_error'),
        ];
    }
}
