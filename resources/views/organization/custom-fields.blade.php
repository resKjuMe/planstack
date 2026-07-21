@php
    $inputClass = 'rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm';
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">{{ __('custom_fields.title') }}</h2>
    </x-slot>

    <x-slot name="subheader">
        <x-organization-tabs active="custom-fields" />
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-flash />

            <p class="max-w-3xl text-sm text-gray-500 dark:text-gray-400">
                {{ __('custom_fields.intro', ['field' => __('custom_fields.field_placeholder')]) }}
            </p>

            {{-- Presets: legen ein Feld mit festem Schlüssel/Typ/Validierung an. --}}
            @if ($presets->isNotEmpty())
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('custom_fields.presets_label') }}</span>
                    @foreach ($presets as $id => $preset)
                        <form method="POST" action="{{ route('organization.custom-fields.preset') }}">
                            @csrf
                            <input type="hidden" name="preset" value="{{ $id }}">
                            <button type="submit" title="{{ $preset['key'] }}"
                                    class="inline-flex items-center gap-1 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <span class="text-indigo-500">＋</span> {{ $preset['label'] }}
                            </button>
                        </form>
                    @endforeach
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 overflow-x-auto">
                <x-input-error :messages="$errors->get('label')" class="mb-2" />

                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">
                            <th class="py-2 pe-4 text-left font-medium">{{ __('custom_fields.col_key') }}</th>
                            <th class="py-2 pe-4 text-left font-medium">{{ __('custom_fields.col_label') }}</th>
                            <th class="py-2 pe-4 text-left font-medium">{{ __('custom_fields.col_label_en') }}</th>
                            <th class="py-2 pe-4 text-left font-medium">{{ __('custom_fields.col_type') }}</th>
                            <th class="py-2 pe-4 text-left font-medium">{{ __('custom_fields.col_validation') }}</th>
                            <th class="py-2 text-left font-medium"></th>
                        </tr>
                    </thead>

                    {{-- Bestehende Felder: eine Sammel-Form mit einem Speichern-Button.
                         Der Schlüssel ist unveränderlich (API-Feldname). --}}
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($fields as $field)
                            @php $p = 'fields['.$field->id.']'; @endphp
                            <tr class="align-top">
                                <td class="py-2 pe-4">
                                    <span class="font-mono text-xs text-gray-600 dark:text-gray-300">{{ $field->key }}</span>
                                </td>
                                <td class="py-2 pe-4">
                                    <input type="text" form="custom-fields-bulk" name="{{ $p }}[label]" value="{{ $field->label }}"
                                           required maxlength="255" class="{{ $inputClass }} w-full min-w-[8rem]">
                                </td>
                                <td class="py-2 pe-4">
                                    <input type="text" form="custom-fields-bulk" name="{{ $p }}[label_en]" value="{{ $field->label_en }}"
                                           maxlength="255" class="{{ $inputClass }} w-full min-w-[8rem]">
                                </td>
                                <td class="py-2 pe-4">
                                    <select form="custom-fields-bulk" name="{{ $p }}[type]" class="{{ $inputClass }}">
                                        @foreach ($types as $type)
                                            <option value="{{ $type }}" @selected($field->type === $type)>{{ __('custom_fields.type_'.$type) }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="py-2 pe-4">
                                    <input type="text" form="custom-fields-bulk" name="{{ $p }}[validation]" value="{{ $field->validation }}"
                                           maxlength="255" placeholder="{{ __('custom_fields.validation_placeholder') }}"
                                           class="{{ $inputClass }} w-full min-w-[10rem] font-mono text-xs">
                                </td>
                                <td class="py-2 text-right">
                                    <button type="submit" form="delete-cf-{{ $field->id }}" title="{{ __('custom_fields.delete') }}"
                                            class="text-rose-500 hover:text-rose-700 dark:text-rose-400 dark:hover:text-rose-300">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-4 text-sm text-gray-400 dark:text-gray-500">{{ __('custom_fields.no_fields') }}</td>
                            </tr>
                        @endforelse

                        {{-- Neues Feld anlegen --}}
                        <tr class="border-t-2 border-dashed border-gray-200 dark:border-gray-700 align-top">
                            <td class="py-3 pe-4 text-center text-lg leading-none text-indigo-500" aria-hidden>＋</td>
                            <td class="py-3 pe-4">
                                <input type="text" form="custom-field-create" name="label" required maxlength="255"
                                       placeholder="{{ __('custom_fields.col_label') }}" class="{{ $inputClass }} w-full min-w-[8rem]">
                            </td>
                            <td class="py-3 pe-4">
                                <input type="text" form="custom-field-create" name="label_en" maxlength="255"
                                       placeholder="{{ __('custom_fields.col_label_en') }}" class="{{ $inputClass }} w-full min-w-[8rem]">
                            </td>
                            <td class="py-3 pe-4">
                                <select form="custom-field-create" name="type" class="{{ $inputClass }}">
                                    @foreach ($types as $type)
                                        <option value="{{ $type }}">{{ __('custom_fields.type_'.$type) }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="py-3 pe-4">
                                <input type="text" form="custom-field-create" name="validation" maxlength="255"
                                       placeholder="{{ __('custom_fields.validation_placeholder') }}"
                                       class="{{ $inputClass }} w-full min-w-[10rem] font-mono text-xs">
                            </td>
                            <td class="py-3 text-right">
                                <button type="submit" form="custom-field-create"
                                        class="whitespace-nowrap rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">
                                    {{ __('custom_fields.add_field') }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                @if ($fields->isNotEmpty())
                    <div class="mt-4 flex justify-end">
                        <button type="submit" form="custom-fields-bulk"
                                class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                            {{ __('custom_fields.save') }}
                        </button>
                    </div>
                @endif
            </div>

            {{-- Sammel-Form (Edit) und Anlege-Form liegen ausserhalb der Tabelle,
                 die Felder sind per form=… verknüpft (HTML erlaubt kein <form> als
                 direktes Kind von <table>/<tr>). --}}
            <form id="custom-fields-bulk" method="POST" action="{{ route('organization.custom-fields.update-all') }}" class="hidden">
                @csrf
                @method('PUT')
            </form>
            <form id="custom-field-create" method="POST" action="{{ route('organization.custom-fields.store') }}" class="hidden">
                @csrf
            </form>
            @foreach ($fields as $field)
                <form id="delete-cf-{{ $field->id }}" method="POST" action="{{ route('organization.custom-fields.destroy', $field) }}"
                      onsubmit="return confirm('{{ __('custom_fields.delete_confirm') }}');" class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
            @endforeach
        </div>
    </div>
</x-app-layout>
