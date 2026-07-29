import React from 'react';
import { interpolate } from '../../summary/i18n.js';
import { formatDuration } from '../../stats/format.js';

// Anzeige-Bausteine für Verweildauern, geteilt zwischen der persönlichen
// Statistik (Daten vom UserStatisticsPresenter) und der Projekt-Performance
// (Daten clientseitig aus resources/js/stats/durations.js). Beide Quellen liefern
// dieselbe Form, damit es hier nur EINE Implementierung gibt:
//
//   { segments: [{key, label, bar, days}], totalDays, medianTaskDays? }
//
// Alle Labels kommen als `strings` herein; die Schlüssel heißen auf beiden Seiten
// gleich (durationsMedian, durationsTotal, …).

/**
 * Gestapelter Balken „wo lag die Zeit" für eine Tabellenzeile.
 *
 * Der Balken füllt die Spur IMMER vollständig: die Segmente zeigen die
 * Zusammensetzung (welcher Status wie viel Anteil hatte), nicht die absolute
 * Größe. Ein teilgefüllter Stapel liest sich als Defekt — und der
 * Größenvergleich zwischen den Zeilen steckt ohnehin in den Zahlen des Tooltips.
 *
 * `variant` steuert nur noch die Kopfzeile des Tooltips:
 *  - `group` (Projekt, Mitarbeiter): Median über die Gesamtzeiten der TASKS der
 *    Gruppe — die typische Task; die Summe wüchse mit der Zahl der Tasks.
 *  - `task`: nur die Gesamtzeit. Ein Median über die Status EINER Task wäre keine
 *    sinnvolle Größe (unvergleichbare Posten).
 */
export function DurationBar({ durations, variant = 'group', strings }) {
    if (!durations || !durations.segments?.length) {
        return <span className="text-gray-300 dark:text-gray-600">{strings.none}</span>;
    }

    const isGroup = variant === 'group';

    // EIN Tooltip für die ganze Zelle: Kopfzeile, darunter je Status eine Zeile.
    // Die Segmente tragen bewusst KEINEN eigenen title — sonst gewänne beim
    // Überfahren eines Segments dessen Tooltip und man sähe nur den einen Wert
    // statt der ganzen Aufschlüsselung.
    const total = interpolate(strings.durationsTotal, { value: formatDuration(durations.totalDays, strings) });
    const head = isGroup
        ? [interpolate(strings.durationsMedianTask, { value: formatDuration(durations.medianTaskDays, strings) }), total].join(' · ')
        : total;

    const tip = [
        head,
        ...durations.segments.map((seg) => `${seg.label}: ${formatDuration(seg.days, strings)}`),
    ].join('\n');

    // Feste Spurbreite (w-40 statt w-full): so ist der Balken über Tabellen hinweg
    // gleich breit, obwohl das auto-Tabellenlayout je Tabelle anders aufteilt.
    return (
        <div className="flex items-center justify-end" title={tip}>
            <span className="flex h-2.5 w-40 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-900">
                {durations.segments.map((seg) => (
                    <span
                        key={seg.key}
                        className={'h-full ' + seg.bar}
                        style={{
                            width: `${durations.totalDays > 0 ? (seg.days / durations.totalDays) * 100 : 0}%`,
                            // Sehr kurze Aufenthalte (Minuten neben Tagen) wären
                            // sonst unsichtbar und der Status fehlte im Balken.
                            minWidth: seg.days > 0 ? '2px' : 0,
                        }}
                    />
                ))}
            </span>
        </div>
    );
}

/**
 * Panel „Verweildauer je Status". Führend ist der MEDIAN der kumulierten Zeit je
 * Task — er bestimmt auch die Balkenbreite, weil ein einzelner liegen gebliebener
 * Task den Durchschnitt verzerrt und den Engpass damit falsch anzeigen würde. Der
 * Durchschnitt steht als Zusatzwert daneben. Die Reihenfolge folgt dem Lebenszyklus
 * (die Zeilen kommen nach Status-Position sortiert), damit die Liste als Trichter
 * lesbar bleibt.
 */
export function StatusDurations({ rows, strings }) {
    const max = Math.max(...rows.map((r) => r.medianPerTaskDays ?? 0), 0.0001);

    return (
        <div className="mt-4 space-y-3">
            {rows.map((r) => {
                const median = formatDuration(r.medianPerTaskDays, strings);
                const pct = r.medianPerTaskDays > 0 ? Math.max(2, (r.medianPerTaskDays / max) * 100) : 0;
                const meta = [
                    interpolate(strings.durationsMeta, { tasks: r.tasks, visits: r.visits }),
                    // visits > tasks ⇒ es gab Rückläufer; genau der Fall, den die
                    // Zählung getrennt erfassen muss.
                    r.visits > r.tasks
                        ? interpolate(strings.durationsReturnsHint, { count: r.visits - r.tasks })
                        : null,
                    r.openVisits > 0 ? interpolate(strings.durationsOpenHint, { count: r.openVisits }) : null,
                ].filter(Boolean).join(' · ');

                const detail = [
                    r.avgPerTaskDays !== null
                        ? interpolate(strings.durationsAvg, { value: formatDuration(r.avgPerTaskDays, strings) })
                        : null,
                    r.visits > r.tasks && r.avgPerVisitDays !== null
                        ? interpolate(strings.durationsPerVisit, { value: formatDuration(r.avgPerVisitDays, strings) })
                        : null,
                    interpolate(strings.durationsTotal, { value: formatDuration(r.totalDays, strings) }),
                ].filter(Boolean).join(' · ');

                return (
                    <div key={r.key}>
                        <div className="flex flex-wrap items-baseline justify-between gap-2">
                            <span className={'rounded-full px-2 py-0.5 text-xs font-medium ' + r.badge}>{r.label}</span>
                            <span
                                className="text-sm font-semibold text-gray-800 dark:text-gray-200"
                                title={interpolate(strings.durationsMedian, { value: median ?? strings.none })}
                            >
                                {median || strings.none}
                            </span>
                        </div>
                        <div className="mt-1 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-900">
                            <div className={'h-full ' + r.bar} style={{ width: `${pct}%` }} />
                        </div>
                        <div className="mt-1 flex flex-wrap justify-between gap-2 text-xs text-gray-400 dark:text-gray-500">
                            <span>{meta}</span>
                            <span>{detail}</span>
                        </div>
                    </div>
                );
            })}
        </div>
    );
}
