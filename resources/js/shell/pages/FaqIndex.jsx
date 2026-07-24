import React from 'react';
import { Head } from '@inertiajs/react';
import AppShell from '../AppShell.jsx';
import PageBands from '../components/PageBands.jsx';
import FaqTabs from '../components/FaqTabs.jsx';

// FAQ-Übersicht (ehemals faq/index.blade.php). Reine Inhaltsseite: Artikel-Karten
// verlinken auf die Nachschlage-Artikel.
export default function FaqIndex({ tabs, articles, strings }) {
    return (
        <>
            <Head><title>{strings.faq}</title></Head>

            <PageBands
                header={<h2 className="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">{strings.faq}</h2>}
                subnav={<FaqTabs tabs={tabs} />}
            />

            <div className="py-8">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                    <p className="text-sm text-gray-500 dark:text-gray-400">{strings.intro}</p>

                    <div className="grid gap-4 sm:grid-cols-2">
                        {articles.map((article) => (
                            <a key={article.href} href={article.href} className="group block bg-white dark:bg-gray-800 rounded-lg shadow p-6 ring-1 ring-transparent transition hover:ring-indigo-200">
                                <div className="flex items-start justify-between gap-3">
                                    <h3 className="font-semibold text-gray-900 dark:text-gray-100 group-hover:text-indigo-700 dark:group-hover:text-indigo-400">{article.title}</h3>
                                    <svg className="mt-0.5 h-4 w-4 shrink-0 text-gray-300 dark:text-gray-600 transition group-hover:text-indigo-500 dark:group-hover:text-indigo-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M9 6l6 6l-6 6" /></svg>
                                </div>
                                <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">{article.desc}</p>
                            </a>
                        ))}
                    </div>
                </div>
            </div>
        </>
    );
}

FaqIndex.layout = AppShell;
