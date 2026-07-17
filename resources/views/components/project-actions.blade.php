@props(['project'])

<div class="flex items-center gap-2">
    @can('update', $project)
        <form method="POST" action="{{ route('projects.sync-prs', $project) }}"
              onsubmit="return confirm('Merge-Status aller offenen PRs von GitHub abrufen und gemergte Tasks taggen?');">
            @csrf
            <button class="inline-flex items-center gap-1.5 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50">
                <svg class="h-4 w-4 text-gray-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 01-9.201 2.466l-.312-.311h2.433a.75.75 0 000-1.5H3.989a.75.75 0 00-.75.75v4.242a.75.75 0 001.5 0v-2.43l.31.31a7 7 0 0011.712-3.138.75.75 0 00-1.449-.39zm1.23-3.723a.75.75 0 00.219-.53V2.929a.75.75 0 00-1.5 0V5.36l-.31-.31A7 7 0 003.239 8.188a.75.75 0 101.448.389A5.5 5.5 0 0113.89 6.11l.311.311h-2.432a.75.75 0 000 1.5h4.243a.75.75 0 00.53-.219z" clip-rule="evenodd" />
                </svg>
                Sync
            </button>
        </form>
        <a href="{{ route('projects.edit', $project) }}"
           class="inline-flex items-center gap-1.5 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50">
            <svg class="h-4 w-4 text-gray-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M8.34 1.804A1 1 0 019.32 1h1.36a1 1 0 01.98.804l.295 1.473c.618.16 1.2.4 1.735.708l1.25-.833a1 1 0 011.276.13l.962.962a1 1 0 01.13 1.277l-.833 1.25c.308.535.547 1.117.708 1.735l1.473.294a1 1 0 01.804.98v1.361a1 1 0 01-.804.98l-1.473.295a5.973 5.973 0 01-.708 1.735l.833 1.25a1 1 0 01-.13 1.276l-.962.962a1 1 0 01-1.277.13l-1.25-.833a5.973 5.973 0 01-1.735.708l-.294 1.473a1 1 0 01-.98.804H9.32a1 1 0 01-.98-.804l-.295-1.473a5.973 5.973 0 01-1.735-.708l-1.25.833a1 1 0 01-1.277-.13l-.962-.962a1 1 0 01-.13-1.277l.833-1.25a5.973 5.973 0 01-.708-1.735l-1.473-.294A1 1 0 011 10.68V9.32a1 1 0 01.804-.98l1.473-.295c.16-.618.4-1.2.708-1.735l-.833-1.25a1 1 0 01.13-1.276l.962-.962a1 1 0 011.277-.13l1.25.833a5.973 5.973 0 011.735-.708l.294-1.473zM13.5 10a3.5 3.5 0 11-7 0 3.5 3.5 0 017 0z" clip-rule="evenodd" />
            </svg>
            Einstellungen
        </a>
    @endcan
    @can('contribute', $project)
        <a href="{{ route('projects.tasks.create', $project) }}"
           class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
            + Task
        </a>
    @endcan
</div>
