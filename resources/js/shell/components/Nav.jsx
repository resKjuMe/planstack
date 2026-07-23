import React, { useState } from 'react';
import { LogoIcon, SkillIcon, SparkleIcon, HamburgerIcon } from '../icons.jsx';
import { useUpdateIndicators } from '../useUpdateIndicators.js';
import Relocate from './Relocate.jsx';
import ThemeToggle from './ThemeToggle.jsx';
import UserMenu from './UserMenu.jsx';
import MobileMenu from './MobileMenu.jsx';

const navLinkBase = 'inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none';
const navLinkActive = 'border-indigo-400 text-gray-900 focus:border-indigo-700 dark:text-gray-100 dark:border-indigo-500';
const navLinkInactive =
    'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:text-gray-700 focus:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:border-gray-600';

// Icon vor bestimmten Primärlinks (Skill = Download-Pfeil). Der Changelog-Link
// erhält stattdessen den „ungelesen"-Funken, der abhängig vom Zustand blinkt.
function LinkIcon({ link, changelogUnseen, labels }) {
    if (link.icon === 'skill') {
        return <SkillIcon className="me-1 inline h-4 w-4 text-gray-400 dark:text-gray-500" />;
    }
    if (link.icon === 'changelog' && changelogUnseen) {
        return <SparkleIcon className="me-1 inline h-4 w-4 text-indigo-500" title={labels.newChanges} />;
    }
    return null;
}

export default function Nav({ shell }) {
    const [mobileOpen, setMobileOpen] = useState(false);
    const themeLabels = shell.labels.theme;
    const { ciUpdate, changelogUnseen } = useUpdateIndicators({
        ciVersion: shell.ciVersion,
        changelogVersion: shell.changelogVersion,
        onChangelog: shell.onChangelog,
    });

    return (
        <nav className="bg-white border-b border-gray-100 dark:bg-gray-800 dark:border-gray-700">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="flex justify-between h-16">
                    <div className="flex">
                        {/* Logo */}
                        <div className="shrink-0 flex items-center">
                            <a href={shell.logoHref}>
                                <LogoIcon />
                            </a>
                        </div>

                        {/* Primärlinks (nur mit Organisation) */}
                        {shell.hasOrg && (
                            <div className="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                                {shell.links.map((link) => (
                                    <a
                                        key={link.href}
                                        href={link.href}
                                        className={`${navLinkBase} ${link.active ? navLinkActive : navLinkInactive} ${link.mono ? 'font-mono' : ''}`}
                                    >
                                        <LinkIcon link={link} changelogUnseen={changelogUnseen} labels={shell.labels} />
                                        {link.label}
                                    </a>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Rechte Seite (Desktop) */}
                    <div className="hidden sm:flex sm:items-center sm:ms-6">
                        <ThemeToggle labels={themeLabels} className="me-1" />
                        <Relocate sourceId="shell-bell" className="me-2" />
                        <UserMenu shell={shell} ciUpdate={ciUpdate} />
                    </div>

                    {/* Hamburger (Mobile) */}
                    <div className="-me-2 flex items-center sm:hidden">
                        <button
                            type="button"
                            onClick={() => setMobileOpen((v) => !v)}
                            className="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out dark:text-gray-500 dark:hover:text-gray-300 dark:hover:bg-gray-700 dark:focus:bg-gray-700 dark:focus:text-gray-300"
                            aria-label="Menu"
                        >
                            <HamburgerIcon open={mobileOpen} className="h-6 w-6" />
                        </button>
                    </div>
                </div>
            </div>

            {/* Mobile-Menü: bleibt gemountet (nur Sichtbarkeit wird geschaltet),
                damit der umgehängte Glocken-Knoten beim Schließen nicht verloren geht. */}
            <MobileMenu shell={shell} themeLabels={themeLabels} open={mobileOpen} />
        </nav>
    );
}
