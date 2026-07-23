import React from 'react';
import Relocate from './Relocate.jsx';
import ThemeToggle from './ThemeToggle.jsx';

// Aufklappbares Menü für schmale Viewports (< sm). Spiegelt die frühere
// „Responsive Navigation" der Blade-Nav: Primärlinks, dann ein Block mit
// Name/E-Mail, Theme-Toggle, Glocke und den Settings-Links inkl. Logout.
const linkBase =
    'block w-full ps-3 pe-4 py-2 border-l-4 text-start text-base font-medium transition duration-150 ease-in-out focus:outline-none';
const linkActive =
    'border-indigo-400 text-indigo-700 bg-indigo-50 focus:text-indigo-800 focus:bg-indigo-100 focus:border-indigo-700 dark:border-indigo-500 dark:text-indigo-300 dark:bg-indigo-900/40';
const linkInactive =
    'border-transparent text-gray-600 hover:text-gray-800 hover:bg-gray-50 hover:border-gray-300 focus:text-gray-800 focus:bg-gray-50 focus:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:bg-gray-700 dark:hover:border-gray-600';

function ResponsiveLink({ href, active, children }) {
    return (
        <a href={href} className={`${linkBase} ${active ? linkActive : linkInactive}`}>
            {children}
        </a>
    );
}

export default function MobileMenu({ shell, themeLabels, open }) {
    const menuItems = shell.menu.filter((item) => !item.orgOnly || shell.hasOrg);

    return (
        <div className={`${open ? 'block' : 'hidden'} sm:hidden`}>
            {shell.hasOrg && (
                <div className="pt-2 pb-3 space-y-1">
                    {shell.links.map((link) => (
                        <ResponsiveLink key={link.href} href={link.href} active={link.active}>
                            {link.mono ? <span className="font-mono">{link.label}</span> : link.label}
                        </ResponsiveLink>
                    ))}
                </div>
            )}

            <div className="pt-4 pb-1 border-t border-gray-200 dark:border-gray-700">
                <div className="px-4 flex items-center justify-between">
                    <div>
                        <div className="font-medium text-base text-gray-800 dark:text-gray-200">{shell.user.name}</div>
                        <div className="font-medium text-sm text-gray-500 dark:text-gray-400">{shell.user.email}</div>
                    </div>
                    <div className="flex items-center gap-1">
                        <ThemeToggle labels={themeLabels} />
                        <Relocate sourceId="shell-bell-mobile" />
                    </div>
                </div>

                <div className="mt-3 space-y-1">
                    {menuItems.map((item) => (
                        <ResponsiveLink key={item.href} href={item.href}>
                            {item.label}
                        </ResponsiveLink>
                    ))}

                    <form method="POST" action={shell.logoutHref}>
                        <input type="hidden" name="_token" value={shell.csrf} />
                        <button type="submit" className={`${linkBase} ${linkInactive}`}>
                            {shell.labels.signOut}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    );
}
