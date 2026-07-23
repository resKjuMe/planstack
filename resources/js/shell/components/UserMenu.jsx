import React, { useEffect, useRef, useState } from 'react';
import { ChevronDownIcon, MenuIcon, UpdateIcon } from '../icons.jsx';

// User-Dropdown rechts in der Kopfzeile (Name + Menü). Verhalten wie die frühere
// Alpine-`x-dropdown`: Klick außerhalb und Escape schließen; ein Klick auf einen
// Eintrag schließt ebenfalls. Der Logout ist ein echtes POST-Formular (CSRF).
export default function UserMenu({ shell, ciUpdate }) {
    const [open, setOpen] = useState(false);
    const ref = useRef(null);

    useEffect(() => {
        if (!open) return undefined;
        const onDocClick = (e) => {
            if (ref.current && !ref.current.contains(e.target)) setOpen(false);
        };
        const onKey = (e) => {
            if (e.key === 'Escape') setOpen(false);
        };
        document.addEventListener('click', onDocClick);
        document.addEventListener('keydown', onKey);
        return () => {
            document.removeEventListener('click', onDocClick);
            document.removeEventListener('keydown', onKey);
        };
    }, [open]);

    const menuItems = shell.menu.filter((item) => !item.orgOnly || shell.hasOrg);

    return (
        <div className="relative" ref={ref}>
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                className="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150 dark:bg-gray-800 dark:text-gray-400 dark:hover:text-gray-200"
            >
                {ciUpdate && (
                    <span className="me-1 align-middle text-indigo-600" title={shell.labels.ciUpdate}>
                        <UpdateIcon className="h-4 w-4" />
                    </span>
                )}
                <div>{shell.user.name}</div>
                <div className="ms-1">
                    <ChevronDownIcon className="fill-current h-4 w-4" />
                </div>
            </button>

            {open && (
                <div
                    className="absolute z-50 mt-2 w-56 rounded-md shadow-lg ltr:origin-top-right rtl:origin-top-left end-0"
                    onClick={() => setOpen(false)}
                >
                    <div className="rounded-md ring-1 ring-black ring-opacity-5 dark:ring-white/10 py-1 bg-white dark:bg-gray-800">
                        {menuItems.map((item) => (
                            <a
                                key={item.href}
                                href={item.href}
                                className="flex items-center gap-2 whitespace-nowrap w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 transition duration-150 ease-in-out dark:text-gray-300 dark:hover:bg-gray-700 dark:focus:bg-gray-700"
                            >
                                <MenuIcon name={item.icon} className="h-4 w-4 shrink-0 text-gray-400 dark:text-gray-500" />
                                {item.label}
                                {item.badge && ciUpdate && (
                                    <span className="ms-2 rounded-full bg-indigo-600 px-1.5 py-0.5 text-[10px] font-semibold text-white align-middle">
                                        {item.badge}
                                    </span>
                                )}
                            </a>
                        ))}

                        <form method="POST" action={shell.logoutHref}>
                            <input type="hidden" name="_token" value={shell.csrf} />
                            <button
                                type="submit"
                                className="flex items-center gap-2 w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 transition duration-150 ease-in-out dark:text-gray-300 dark:hover:bg-gray-700 dark:focus:bg-gray-700"
                            >
                                <MenuIcon name="logout" className="h-4 w-4 shrink-0 text-gray-400 dark:text-gray-500" />
                                {shell.labels.signOut}
                            </button>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}
