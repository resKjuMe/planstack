import React, { useCallback, useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

// Kopier-Menü einer Task-Karte: ein Clipboard-Icon in der Titelzeile, per Klick
// öffnet sich eine Liste von Varianten (Task-Name, Projekt + Task-Name, URL und
// die drei /planstack-Kommandos).
//
// Das Menü hängt in einem Portal mit fixer Position am Icon: die Kartenliste der
// Spalte scrollt (overflow-y-auto), ein absolut positioniertes Menü innerhalb der
// Karte würde dort abgeschnitten.
//
// Nicht nur das Board nutzt es: die Task-Zeilen des Dashboards binden dasselbe
// Menü ein (resources/js/shell/pages/Dashboard.jsx) und bekommen die Labels als
// Teilmenge der board-Sprachdatei geliefert. `task` braucht nur `name` und `url`.

// navigator.clipboard braucht einen Secure Context (https/localhost). Fällt sonst
// auf das alte execCommand-Verfahren zurück, damit das Kopieren auch hinter
// http-Setups funktioniert.
async function writeClipboard(text) {
    try {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(text);
            return true;
        }
    } catch {
        /* auf den Legacy-Pfad zurückfallen */
    }

    try {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.position = 'fixed';
        ta.style.top = '0';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        const ok = document.execCommand('copy');
        ta.remove();
        return ok;
    } catch {
        return false;
    }
}

const MENU_WIDTH = 288; // = w-72, für die Kanten-Korrektur der fixen Position

// Claude-Bildmarke (dieselbe Kontur wie in PrSequenceView/TaskShowPresenter).
const CLAUDE_LOGO =
    'M4.709 15.955l4.72-2.647.08-.23-.08-.128H9.2l-.79-.048-2.698-.073-2.339-.097-2.266-.122-.571-.121L0 11.784l.055-.352.48-.321.686.06 1.52.103 2.278.158 1.652.097 2.449.255h.389l.055-.157-.134-.098-.103-.097-2.358-1.596-2.552-1.688-1.336-.972-.724-.491-.364-.462-.158-1.008.656-.722.881.06.225.061.893.686 1.908 1.476 2.491 1.833.365.304.145-.103.019-.073-.164-.274-1.355-2.446-1.446-2.49-.644-1.032-.17-.619a2.97 2.97 0 01-.104-.729L6.283.134 6.696 0l.996.134.42.364.62 1.414 1.002 2.229 1.555 3.03.456.898.243.832.091.255h.158V9.01l.128-1.706.237-2.095.23-2.695.08-.76.376-.91.747-.492.583.28.48.685-.067.444-.286 1.851-.559 2.903-.364 1.942h.212l.243-.242.985-1.306 1.652-2.064.73-.82.85-.904.547-.431h1.033l.76 1.129-.34 1.166-1.064 1.347-.881 1.142-1.264 1.7-.79 1.36.073.11.188-.02 2.856-.606 1.543-.28 1.841-.315.833.388.091.395-.328.807-1.969.486-2.309.462-3.439.813-.042.03.049.061 1.549.146.662.036h1.622l3.02.225.79.522.474.638-.079.485-1.215.62-1.64-.389-3.829-.91-1.312-.329h-.182v.11l1.093 1.068 2.006 1.81 2.509 2.33.127.578-.322.455-.34-.049-2.205-1.657-.851-.747-1.926-1.62h-.128v.17l.444.649 2.345 3.521.122 1.08-.17.353-.608.213-.668-.122-1.374-1.925-1.415-2.167-1.143-1.943-.14.08-.674 7.254-.316.37-.729.28-.607-.461-.322-.747.322-1.476.389-1.924.315-1.53.286-1.9.17-.632-.012-.042-.14.018-1.434 1.967-2.18 2.945-1.726 1.845-.414.164-.717-.37.067-.662.401-.589 2.388-3.036 1.44-1.882.93-1.086-.006-.158h-.055L4.132 18.56l-1.13.146-.487-.456.061-.746.231-.243 1.908-1.312-.006.006z';

// Wartezeit für den Fokus-Check nach dem Start über claudetask: (siehe launch()).
const LAUNCH_PROBE_MS = 900;

export default function CopyMenu({ task, t, projectAlias, setupUrl = null }) {
    const [open, setOpen] = useState(false);
    const [pos, setPos] = useState(null); // { top, left } in Viewport-Koordinaten
    const [copiedKey, setCopiedKey] = useState(null);
    // true, sobald ein Start über claudetask: vermutlich ins Leere lief.
    const [launchFailed, setLaunchFailed] = useState(false);
    const btnRef = useRef(null);
    const menuRef = useRef(null);
    const closeTimer = useRef(null);
    const probeTimer = useRef(null);

    useEffect(() => () => {
        clearTimeout(closeTimer.current);
        clearTimeout(probeTimer.current);
    }, []);

    const ticket = projectAlias ? `${projectAlias} ${task.name}` : task.name;
    const items = [
        { key: 'name', label: t('copy_task_name'), value: task.name },
        { key: 'ticket', label: t('copy_project_task_name'), value: ticket },
        { key: 'url', label: t('copy_task_url'), value: task.url },
        { key: 'work', label: t('copy_work_command'), value: `/planstack work ${ticket}`, command: true },
        { key: 'fix', label: t('copy_fix_command'), value: `/planstack fix ${ticket}`, command: true },
        { key: 'review', label: t('copy_review_command'), value: `/planstack review ${ticket}`, command: true },
    ];

    const close = useCallback(() => {
        clearTimeout(closeTimer.current);
        setOpen(false);
        setCopiedKey(null);
        setLaunchFailed(false);
    }, []);

    // Position aus dem Icon-Rechteck: standardmäßig darunter und rechts
    // ausgerichtet, bei zu wenig Platz nach unten klappt es nach oben.
    const place = useCallback(() => {
        const rect = btnRef.current?.getBoundingClientRect();
        if (! rect) return;

        const height = menuRef.current?.offsetHeight ?? 240;
        const below = rect.bottom + 6;
        const top = below + height > window.innerHeight - 8 && rect.top - 6 - height > 8
            ? rect.top - 6 - height
            : below;
        const left = Math.max(8, Math.min(rect.right - MENU_WIDTH, window.innerWidth - MENU_WIDTH - 8));

        setPos({ top, left });
    }, []);

    // Offenes Menü schließen bei Klick außerhalb, Escape, Scrollen (die fixe
    // Position würde sonst verrutschen) und Resize. Die eingeblendete Infobox
    // verändert die Menühöhe — deshalb hängt die Neuplatzierung auch an ihr.
    useEffect(() => {
        if (! open) return;

        place();

        const onPointerDown = (e) => {
            if (menuRef.current?.contains(e.target) || btnRef.current?.contains(e.target)) return;
            close();
        };
        const onKeyDown = (e) => {
            if (e.key === 'Escape') close();
        };

        document.addEventListener('pointerdown', onPointerDown, true);
        document.addEventListener('keydown', onKeyDown);
        window.addEventListener('scroll', close, true);
        window.addEventListener('resize', close);

        return () => {
            document.removeEventListener('pointerdown', onPointerDown, true);
            document.removeEventListener('keydown', onKeyDown);
            window.removeEventListener('scroll', close, true);
            window.removeEventListener('resize', close);
        };
    }, [open, launchFailed, place, close]);

    const copy = async (item) => {
        const ok = await writeClipboard(item.value);
        if (! ok) return;

        // Kurzes „Kopiert" am gewählten Eintrag, dann schließt das Menü selbst.
        setCopiedKey(item.key);
        clearTimeout(closeTimer.current);
        closeTimer.current = setTimeout(close, 900);
    };

    // Claude-Icon der Kommando-Zeilen: startet den registrierten Protokoll-Handler
    // claudetask: (öffnet eine PowerShell mit `claude "<Prompt>"`) — dasselbe
    // Verfahren wie in der PR-Sequenz und im Concern-Assistenten der Task-Seite.
    //
    // Ob der Handler registriert ist, lässt sich nicht abfragen (Browser legen das
    // aus Fingerprinting-Gründen nicht offen). Heuristik: ist er registriert,
    // wandert der Fokus weg — Chromes „Externe App öffnen?"-Dialog bzw. die
    // startende Shell. Bleibt der Fokus nach LAUNCH_PROBE_MS liegen, war
    // wahrscheinlich keiner da; dann wandert der Prompt in die Zwischenablage und
    // die Karte verweist auf die Einrichtungsanleitung. Deshalb wird VORHER nicht
    // kopiert: im Normalfall bleibt die Zwischenablage unangetastet.
    const launch = (item) => {
        setLaunchFailed(false);
        clearTimeout(closeTimer.current);
        clearTimeout(probeTimer.current);

        let leftPage = false;
        const onLeave = () => { leftPage = true; };
        window.addEventListener('blur', onLeave);
        document.addEventListener('visibilitychange', onLeave);

        window.location.href = `claudetask:${encodeURIComponent(item.value)}`;

        probeTimer.current = setTimeout(async () => {
            window.removeEventListener('blur', onLeave);
            document.removeEventListener('visibilitychange', onLeave);

            if (leftPage || document.hidden || ! document.hasFocus()) {
                close(); // gestartet — Menü kann zu
                return;
            }

            await writeClipboard(item.value);
            setLaunchFailed(true);
            setOpen(true); // falls das Menü zwischenzeitlich zu ging
        }, LAUNCH_PROBE_MS);
    };

    return (
        <>
            <button
                ref={btnRef}
                type="button"
                // Die ganze Karte ist Drag-Quelle; ohne stopPropagation würde der
                // Klick als Drag-Start interpretiert.
                onPointerDown={(e) => e.stopPropagation()}
                onClick={() => (open ? close() : setOpen(true))}
                title={t('copy')}
                aria-label={t('copy')}
                aria-haspopup="menu"
                aria-expanded={open}
                className={[
                    'shrink-0 rounded p-0.5 transition',
                    open
                        ? 'text-indigo-600 dark:text-indigo-400 opacity-100'
                        : 'text-gray-400 dark:text-gray-500 opacity-60 group-hover:opacity-100 hover:text-indigo-600 dark:hover:text-indigo-400',
                ].join(' ')}
            >
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    className="h-4 w-4"
                    aria-hidden="true"
                >
                    <rect width="14" height="14" x="8" y="8" rx="2" ry="2" />
                    <path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2" />
                </svg>
            </button>

            {open && createPortal(
                <div
                    ref={menuRef}
                    role="menu"
                    onPointerDown={(e) => e.stopPropagation()}
                    style={{ top: pos?.top ?? -9999, left: pos?.left ?? -9999, width: MENU_WIDTH }}
                    className="fixed z-50 overflow-hidden rounded-lg bg-white dark:bg-gray-800 p-1 shadow-lg ring-1 ring-gray-200 dark:ring-gray-700"
                >
                    {items.map((item) => (
                        <div key={item.key} className="flex items-center rounded hover:bg-gray-50 dark:hover:bg-gray-700/60">
                            <button
                                type="button"
                                role="menuitem"
                                onClick={() => copy(item)}
                                className="min-w-0 flex-1 rounded px-2 py-1.5 text-left"
                            >
                                <span className="flex items-center justify-between gap-2">
                                    <span className="truncate text-xs font-medium text-gray-700 dark:text-gray-200">
                                        {item.label}
                                    </span>
                                    {copiedKey === item.key && (
                                        <span className="shrink-0 text-[10px] font-semibold text-green-600 dark:text-green-500">
                                            {t('copied')}
                                        </span>
                                    )}
                                </span>
                                <span className="block truncate font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                    {item.value}
                                </span>
                            </button>

                            {/* Nur an den Kommando-Zeilen: direkt in Claude starten
                                statt nur kopieren. */}
                            {item.command && (
                                <button
                                    type="button"
                                    role="menuitem"
                                    onClick={() => launch(item)}
                                    title={t('start_with_claude', { command: item.value })}
                                    aria-label={t('start_with_claude', { command: item.value })}
                                    className="mr-1 shrink-0 rounded-full p-1.5 text-[#D97757] hover:bg-[#D97757]/10"
                                >
                                    <svg viewBox="0 0 24 24" className="h-3.5 w-3.5" fill="currentColor" aria-hidden="true">
                                        <path d={CLAUDE_LOGO} />
                                    </svg>
                                </button>
                            )}
                        </div>
                    ))}

                    {/* Start über claudetask: lief vermutlich ins Leere (Fokus-Check
                        in launch()) — Prompt liegt in der Zwischenablage, hier der
                        Weg zur Einrichtung. */}
                    {launchFailed && (
                        <div className="mt-1 rounded-md bg-amber-50 dark:bg-amber-900/20 px-2 py-2 text-[11px] leading-snug text-amber-800 dark:text-amber-300">
                            <p className="font-semibold">{t('claudetask_not_registered')}</p>
                            <p className="mt-0.5">{t('claudetask_clipboard_fallback')}</p>
                            {setupUrl && (
                                <a href={setupUrl} className="mt-1 inline-block font-semibold underline hover:no-underline">
                                    {t('claudetask_setup_link')}
                                </a>
                            )}
                        </div>
                    )}
                </div>,
                document.body,
            )}
        </>
    );
}
