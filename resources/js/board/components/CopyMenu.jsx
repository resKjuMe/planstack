import React, { useCallback, useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

// Kopier-Menü einer Task-Karte: ein Clipboard-Icon in der Titelzeile, per Klick
// öffnet sich eine Liste von Varianten (Task-Name, Projekt + Task-Name, URL und
// die drei /planstack-Kommandos).
//
// Das Menü hängt in einem Portal mit fixer Position am Icon: die Kartenliste der
// Spalte scrollt (overflow-y-auto), ein absolut positioniertes Menü innerhalb der
// Karte würde dort abgeschnitten.

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

export default function CopyMenu({ task, t, projectAlias }) {
    const [open, setOpen] = useState(false);
    const [pos, setPos] = useState(null); // { top, left } in Viewport-Koordinaten
    const [copiedKey, setCopiedKey] = useState(null);
    const btnRef = useRef(null);
    const menuRef = useRef(null);
    const closeTimer = useRef(null);

    useEffect(() => () => clearTimeout(closeTimer.current), []);

    const ticket = projectAlias ? `${projectAlias} ${task.name}` : task.name;
    const items = [
        { key: 'name', label: t('copy_task_name'), value: task.name },
        { key: 'ticket', label: t('copy_project_task_name'), value: ticket },
        { key: 'url', label: t('copy_task_url'), value: task.url },
        { key: 'work', label: t('copy_work_command'), value: `/planstack work ${ticket}` },
        { key: 'fix', label: t('copy_fix_command'), value: `/planstack fix ${ticket}` },
        { key: 'review', label: t('copy_review_command'), value: `/planstack review ${ticket}` },
    ];

    const close = useCallback(() => {
        clearTimeout(closeTimer.current);
        setOpen(false);
        setCopiedKey(null);
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
    // Position würde sonst verrutschen) und Resize.
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
    }, [open, place, close]);

    const copy = async (item) => {
        const ok = await writeClipboard(item.value);
        if (! ok) return;

        // Kurzes „Kopiert" am gewählten Eintrag, dann schließt das Menü selbst.
        setCopiedKey(item.key);
        clearTimeout(closeTimer.current);
        closeTimer.current = setTimeout(close, 900);
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
                        <button
                            key={item.key}
                            type="button"
                            role="menuitem"
                            onClick={() => copy(item)}
                            className="block w-full rounded px-2 py-1.5 text-left hover:bg-gray-50 dark:hover:bg-gray-700/60"
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
                    ))}
                </div>,
                document.body,
            )}
        </>
    );
}
