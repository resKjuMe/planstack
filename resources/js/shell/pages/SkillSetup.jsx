import React from 'react';
import { Head } from '@inertiajs/react';
import AppShell from '../AppShell.jsx';
import PageBands from '../components/PageBands.jsx';

// Planstack-Skill-Einrichtung (ehemals skill/setup.blade.php). Reine Inhaltsseite;
// der ZIP-Download bleibt ein echter Nicht-Inertia-Link (data-native).
export default function SkillSetup({ downloadUrl, profileUrl, strings }) {
    const card = 'bg-white dark:bg-gray-800 rounded-lg shadow p-6';
    const h3 = 'font-semibold text-gray-800 dark:text-gray-100';
    const mono = 'font-mono';

    return (
        <>
            <Head><title>{strings.title}</title></Head>

            <PageBands
                header={<h2 className="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">{strings.title}</h2>}
            />

            <div className="py-8">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

                    <div className={card}>
                        <h3 className={h3}>{strings.oneSkill}</h3>
                        <p className="mt-2 text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            {strings.introPre} <b>{strings.crossProject}</b> {strings.introPost}
                        </p>

                        <div className="mt-5">
                            <a href={downloadUrl} data-native className="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500">
                                <svg className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path d="M10.75 2.75a.75.75 0 0 0-1.5 0v8.614L6.295 8.235a.75.75 0 1 0-1.09 1.03l4.25 4.5a.75.75 0 0 0 1.09 0l4.25-4.5a.75.75 0 0 0-1.09-1.03l-2.955 3.129V2.75Z" />
                                    <path d="M3.5 12.75a.75.75 0 0 0-1.5 0v2.5A2.75 2.75 0 0 0 4.75 18h10.5A2.75 2.75 0 0 0 18 15.25v-2.5a.75.75 0 0 0-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5Z" />
                                </svg>
                                {strings.downloadZip}
                            </a>
                            <p className="mt-2 text-xs text-gray-400 dark:text-gray-500">
                                {strings.zipContains} <span className={mono}>SKILL.md</span> {strings.andPrefilled} <span className={mono}>config.json</span> {strings.withToken}
                            </p>
                        </div>
                    </div>

                    <div className={card}>
                        <h3 className={h3}>{strings.installation}</h3>
                        <ol className="mt-3 space-y-2 text-sm text-gray-600 dark:text-gray-400" style={{ listStyle: 'decimal inside' }}>
                            <li>{strings.installDownload}</li>
                            <li>{strings.installFolder} <span className={mono}>planstack/</span> {strings.installTo} <span className={mono}>~/.claude/skills/</span> {strings.installMove} (Windows: <span className={mono}>%USERPROFILE%\.claude\skills\</span>).</li>
                            <li>{strings.installDone} <span className={mono}>/planstack</span> {strings.installReady}</li>
                        </ol>
                    </div>

                    <div className={card}>
                        <h3 className={h3}>{strings.usage}</h3>
                        <dl className="mt-3 space-y-3 text-sm">
                            <div>
                                <dt className={`${mono} text-gray-800 dark:text-gray-100`}>/planstack &lt;PROJEKT&gt;</dt>
                                <dd className="text-gray-600 dark:text-gray-400">{strings.usageProject}</dd>
                            </div>
                            <div>
                                <dt className={`${mono} text-gray-800 dark:text-gray-100`}>/planstack &lt;PROJEKT&gt; &lt;TASK&gt;</dt>
                                <dd className="text-gray-600 dark:text-gray-400">{strings.usageTask} (<span className={mono}>&lt;TASK&gt;</span> {strings.usageTaskShortcode} <span className={mono}>C27</span>).</dd>
                            </div>
                            <div>
                                <dt className={`${mono} text-gray-800 dark:text-gray-100`}>/planstack review [&lt;PROJEKT&gt;] [&lt;TASK&gt;]</dt>
                                <dd className="text-gray-600 dark:text-gray-400">{strings.usageReview}</dd>
                            </div>
                            <div>
                                <dt className={`${mono} text-gray-800 dark:text-gray-100`}>/planstack fix [&lt;PROJEKT&gt;] &lt;TASK|PR&gt;</dt>
                                <dd className="text-gray-600 dark:text-gray-400">{strings.usageFix}</dd>
                            </div>
                            <div>
                                <dt className={`${mono} text-gray-800 dark:text-gray-100`}>/planstack settings</dt>
                                <dd className="text-gray-600 dark:text-gray-400">{strings.usageSettings}</dd>
                            </div>
                            <div>
                                <dt className={`${mono} text-gray-800 dark:text-gray-100`}>/planstack update-config [&lt;PROJEKT&gt;]</dt>
                                <dd className="text-gray-600 dark:text-gray-400">{strings.usageUpdateConfig}</dd>
                            </div>
                        </dl>
                        <p className="mt-3 text-xs text-gray-400 dark:text-gray-500">
                            <span className={mono}>&lt;PROJEKT&gt;</span> {strings.usageAliasIs} <span className={mono}>L2L</span>, <span className={mono}>LOG</span>). {strings.usageServesEvery}
                        </p>
                    </div>

                    <div className={card}>
                        <h3 className={h3}>{strings.goodToKnow}</h3>
                        <ul className="mt-3 space-y-2 text-sm text-gray-600 dark:text-gray-400 list-disc ps-5">
                            <li><b>{strings.token}</b> {strings.tokenText} <a href={profileUrl} className="text-indigo-600 dark:text-indigo-400 hover:underline">{strings.profileApiTokens}</a> {strings.revocable}</li>
                            <li><b>{strings.selfUpdating}</b> {strings.selfUpdatingText}</li>
                            <li><b>{strings.noFixedProject}</b> {strings.theWord} <span className={mono}>config.json</span> {strings.configContains}</li>
                        </ul>
                    </div>

                </div>
            </div>
        </>
    );
}

SkillSetup.layout = AppShell;
