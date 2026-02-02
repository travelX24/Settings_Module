@php
    $locale = app()->getLocale();
    $isRtl  = in_array(substr($locale, 0, 2), ['ar','fa','ur','he']);

    $hasFilters =
        trim($search ?? '') !== '' ||
        ($filterCalendar ?? 'all') !== 'all' ||
        ($filterStatus ?? 'all') !== 'all' ||
        ($filterDateStart ?? '') !== '' ||
        ($filterDateEnd ?? '') !== '';
@endphp

@section('topbar-left-content')
    <x-ui.page-header
        :title="tr('Official Holidays')"
        :subtitle="tr('Manage official holidays and non-working days')"
        class="!flex-col {{ $isRtl ? '!items-end !text-right' : '!items-start !text-left' }} !justify-start !gap-1"
        titleSize="xl"
    />

    <div class="text-xs text-gray-400">
        calendar_type: {{ config('company.calendar_type') }}
    </div>
@endsection

@section('topbar-actions')
    <x-ui.secondary-button
        href="{{ route('company-admin.settings.attendance') }}"
        :arrow="false"
        :fullWidth="false"
        class="!px-4 !py-2 !text-sm !rounded-xl !gap-2"
    >
        <i class="fas {{ $isRtl ? 'fa-arrow-right' : 'fa-arrow-left' }} text-xs"></i>
        <span>{{ tr('Back') }}</span>
    </x-ui.secondary-button>
@endsection

<div class="space-y-6">
    <x-ui.card>
        <div class="space-y-4">
            {{-- Search & Actions --}}
            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                <div class="flex-1 min-w-0">
                    <x-ui.search-box
                        model="search"
                        placeholder="{{ tr('Search by name...') }}"
                        :debounce="300"
                    />
                </div>

                <div class="flex flex-wrap items-center gap-2 sm:shrink-0">
                    <x-ui.secondary-button
                        :fullWidth="false"
                        class="!border-amber-200 !bg-amber-50/50 !text-amber-700 hover:!bg-amber-100"
                    >
                        <i class="fas fa-file-export"></i>
                        <span class="ms-2">{{ tr('Export') }}</span>
                    </x-ui.secondary-button>

                    @can('settings.attendance.manage')
                        <x-ui.primary-button
                            :arrow="false"
                            :fullWidth="false"
                            wire:click="openCreate"
                        >
                            <i class="fas fa-plus"></i>
                            <span class="ms-2">{{ tr('New Holiday') }}</span>
                        </x-ui.primary-button>
                    @endcan
                </div>
            </div>

            {{-- Filters --}}
            <div x-data="{ open: true }" class="space-y-3">
                <div class="flex items-center justify-between">
                    <button
                        type="button"
                        @click="open = !open"
                        class="flex items-center justify-between text-sm font-semibold text-gray-700 hover:text-gray-900 transition-colors"
                    >
                        <span class="flex items-center gap-2">
                            <i class="fas fa-filter text-xs"></i>
                            <span>{{ tr('Filters') }}</span>
                        </span>
                        <i class="fas fa-chevron-down transition-transform ms-2 text-[10px]" :class="open ? 'rotate-180' : ''"></i>
                    </button>
                </div>

                <div
                    x-show="open"
                    x-transition
                    class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 items-end"
                >
                    <x-ui.filter-select
                        model="filterCalendar"
                        :label="tr('Calendar Type')"
                        :placeholder="tr('All Types')"
                        :options="[
                            ['value' => 'all', 'label' => tr('All Types')],
                            ['value' => 'gregorian', 'label' => tr('Gregorian')],
                            ['value' => 'hijri', 'label' => tr('Hijri')],
                        ]"
                        width="full"
                        :defer="false"
                        :applyOnChange="true"
                    />

                    <x-ui.filter-select
                        model="filterStatus"
                        :label="tr('Status')"
                        :placeholder="tr('All Statuses')"
                        :options="[
                            ['value' => 'all', 'label' => tr('All Statuses')],
                            ['value' => 'active', 'label' => tr('Active')],
                            ['value' => 'inactive', 'label' => tr('Inactive')],
                        ]"
                        width="full"
                        :defer="false"
                        :applyOnChange="true"
                    />

                    <div class="md:col-span-2">
                        <div class="grid grid-cols-2 gap-2">
                            <x-ui.company-date-picker
                                model="filterDateStart"
                                :label="tr('From')"
                            />

                            <x-ui.company-date-picker
                                model="filterDateEnd"
                                :label="tr('To')"
                            />
                        </div>
                    </div>

                    {{-- Clear Filters --}}
                    @if($hasFilters)
                        <div class="md:col-span-4 flex items-center justify-end">
                            <button
                                type="button"
                                wire:click="clearAllFilters"
                                wire:loading.attr="disabled"
                                wire:target="clearAllFilters"
                                class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-semibold text-gray-600 hover:text-gray-900 transition-colors disabled:opacity-50"
                            >
                                <i class="fas fa-times" wire:loading.remove wire:target="clearAllFilters"></i>
                                <i class="fas fa-spinner fa-spin" wire:loading wire:target="clearAllFilters"></i>
                                <span wire:loading.remove wire:target="clearAllFilters">{{ tr('Clear all filters') }}</span>
                                <span wire:loading wire:target="clearAllFilters">{{ tr('Clearing...') }}</span>
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </x-ui.card>

    {{-- Table --}}
    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-x-auto overflow-y-visible relative mt-2">
        <table class="w-full text-start border-collapse table-fixed">
            <thead>
                <tr class="bg-gray-50/50 border-b border-gray-100">
                    <th class="w-[28%] px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-start">{{ tr('Holiday') }}</th>
                    <th class="w-[12%] px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">{{ tr('Calendar') }}</th>
                    <th class="w-[20%] px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">{{ tr('Hijri Date') }}</th>
                    <th class="w-[20%] px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">{{ tr('Gregorian Date') }}</th>
                    <th class="w-[10%] px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">{{ tr('Days') }}</th>
                    <th class="w-[10%] px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-end">{{ tr('Actions') }}</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-gray-50">
                @forelse($rows as $row)
                    <tr class="hover:bg-gray-50/40 transition-colors">
                        <td class="px-6 py-4">
                            <div class="text-sm font-bold text-gray-800 truncate">{{ $row->template?->name ?? '-' }}</div>
                            <div class="text-[10px] text-gray-400 truncate">{{ $row->template?->description ?? '' }}</div>
                        </td>

                        <td class="px-6 py-4 text-center">
                            <span class="text-[10px] font-black text-gray-600 uppercase">
                                {{ $row->template?->calendar_type ?? '-' }}
                            </span>
                        </td>

                        <td class="px-6 py-4 text-center">
                            <span class="text-xs font-semibold text-gray-700">
                                {{ $row->display_hijri ?: '—' }}
                            </span>
                        </td>

                        <td class="px-6 py-4 text-center">
                            <span class="text-xs font-semibold text-gray-700">
                                {{ $row->start_date }}
                                @if($row->end_date && $row->end_date !== $row->start_date)
                                    - {{ $row->end_date }}
                                @endif
                            </span>
                        </td>

                        <td class="px-6 py-4 text-center">
                            <span class="text-xs font-bold text-gray-700">{{ $row->duration_days ?? 1 }}</span>
                        </td>

                        <td class="px-6 py-4 text-end">
                            <x-ui.actions-menu>
                                <x-ui.dropdown-item wire:click.stop="openEdit({{ (int) $row->id }})">
                                    <i class="fas fa-edit me-2 text-blue-500"></i> {{ tr('Edit') }}
                                </x-ui.dropdown-item>

                                <x-ui.dropdown-item danger wire:click.stop="confirmDelete({{ (int) $row->id }})">
                                    <i class="fas fa-trash-alt me-2 text-red-500"></i> {{ tr('Delete') }}
                                </x-ui.dropdown-item>
                            </x-ui.actions-menu>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-24 text-center">
                            <div class="opacity-20 flex flex-col items-center">
                                <div class="w-24 h-24 bg-gray-50 rounded-full flex items-center justify-center text-4xl mb-4 border border-gray-100">
                                    <i class="fas fa-calendar-times"></i>
                                </div>
                                <h4 class="text-base font-bold text-gray-800">{{ tr('Database Empty') }}</h4>
                                <p class="text-xs max-w-[320px] mt-2 leading-relaxed">
                                    {{ tr('No official holidays have been defined yet. Start by creating the first holiday template.') }}
                                </p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if($rows->hasPages())
            <div class="px-6 py-4 bg-gray-50/30 border-t border-gray-100">
                {{ $rows->links() }}
            </div>
        @endif
    </div>

    {{-- ========================= --}}
    {{-- Create Holiday Modal --}}
    {{-- ========================= --}}
    @if($createOpen)
        <div class="fixed inset-0 z-[9999] bg-black/40 flex items-center justify-center p-4" wire:click.self="closeCreate">
            <div class="w-full max-w-xl bg-white rounded-3xl shadow-xl border border-gray-100 p-6">
                <div class="flex items-start justify-between gap-3 mb-4">
                    <div>
                        <div class="text-lg font-black text-gray-900">{{ tr('New Holiday') }}</div>
                        <div class="text-xs text-gray-400">{{ tr('Create a holiday template and occurrence') }}</div>
                    </div>

                    <button type="button" class="text-gray-400 hover:text-gray-700" wire:click="closeCreate">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Name') }}</label>
                        <input
                            type="text"
                            wire:model.defer="newName"
                            class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50 focus:bg-white focus:border-[color:var(--brand-via)] focus:ring-4 focus:ring-[color:var(--brand-via)]/10 transition-all"
                            placeholder="{{ tr('Holiday name...') }}"
                        >
                        @error('newName') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div>
                        <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Calendar Type') }}</label>

                        <div class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50 flex items-center justify-between">
                            <span class="font-bold text-gray-800">
                                {{ $companyCalendarType === 'hijri' ? tr('Hijri') : tr('Gregorian') }}
                            </span>
                            <span class="text-[10px] text-gray-400">{{ tr('From settings') }}</span>
                        </div>

                        <input type="hidden" wire:model.defer="newCalendarType">
                        @error('newCalendarType') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div>
                        <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Duration (days)') }}</label>
                        <input
                            type="number"
                            min="1"
                            wire:model.defer="newDurationDays"
                            class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50 focus:bg-white focus:border-[color:var(--brand-via)] focus:ring-4 focus:ring-[color:var(--brand-via)]/10 transition-all"
                        >
                        @error('newDurationDays') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="md:col-span-2">
                        <x-ui.company-date-picker model="newStartDate" :label="tr('Start Date')" />
                        @error('newStartDate') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    @if($companyCalendarType === 'hijri')
                        <div class="md:col-span-2">
                            <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Gregorian Date (auto)') }}</label>
                            <input type="text" readonly value="{{ $newGregorianAuto }}" class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50">
                        </div>
                    @endif

                    <div class="md:col-span-2">
                        <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Hijri Date (auto)') }}</label>
                        <input type="text" readonly value="{{ $newDisplayHijriAuto }}" class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Hijri display (optional)') }}</label>
                        <input
                            type="text"
                            wire:model.defer="newDisplayHijri"
                            class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50 focus:bg-white focus:border-[color:var(--brand-via)] focus:ring-4 focus:ring-[color:var(--brand-via)]/10 transition-all"
                            placeholder="{{ tr('e.g. 1447/10/03') }}"
                        >
                        <div class="text-[10px] text-gray-400 mt-1">{{ tr('Leave empty to use auto value if available.') }}</div>
                        @error('newDisplayHijri') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 mt-6">
                    <x-ui.secondary-button :fullWidth="false" wire:click="closeCreate" wire:loading.attr="disabled">
                        {{ tr('Cancel') }}
                    </x-ui.secondary-button>

                    <x-ui.primary-button :arrow="false" :fullWidth="false" wire:click="saveNewHoliday" wire:loading.attr="disabled" wire:target="saveNewHoliday">
                        <i class="fas fa-save"></i>
                        <span class="ms-2">{{ tr('Save') }}</span>
                    </x-ui.primary-button>
                </div>
            </div>
        </div>
    @endif

    {{-- ========================= --}}
    {{-- Edit Holiday Modal --}}
    {{-- ========================= --}}
    @if($editOpen)
        <div class="fixed inset-0 z-[9999] bg-black/40 flex items-center justify-center p-4" wire:click.self="closeEdit">
            <div class="w-full max-w-xl bg-white rounded-3xl shadow-xl border border-gray-100 p-6">
                <div class="flex items-start justify-between gap-3 mb-4">
                    <div>
                        <div class="text-lg font-black text-gray-900">{{ tr('Edit Holiday') }}</div>
                        <div class="text-xs text-gray-400">{{ tr('Update holiday information') }}</div>
                    </div>

                    <button type="button" class="text-gray-400 hover:text-gray-700" wire:click="closeEdit">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Name') }}</label>
                        <input
                            type="text"
                            wire:model.defer="editName"
                            class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50 focus:bg-white focus:border-[color:var(--brand-via)] focus:ring-4 focus:ring-[color:var(--brand-via)]/10 transition-all"
                        >
                        @error('editName') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div>
                        <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Calendar Type') }}</label>
                        <div class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50 flex items-center justify-between">
                            <span class="font-bold text-gray-800">
                                {{ $companyCalendarType === 'hijri' ? tr('Hijri') : tr('Gregorian') }}
                            </span>
                            <span class="text-[10px] text-gray-400">{{ tr('From settings') }}</span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Duration (days)') }}</label>
                        <input
                            type="number"
                            min="1"
                            wire:model.defer="editDurationDays"
                            class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50 focus:bg-white focus:border-[color:var(--brand-via)] focus:ring-4 focus:ring-[color:var(--brand-via)]/10 transition-all"
                        >
                        @error('editDurationDays') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="md:col-span-2">
                        <x-ui.company-date-picker model="editStartDate" :label="tr('Start Date')" />
                        @error('editStartDate') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    @if($companyCalendarType === 'hijri')
                        <div class="md:col-span-2">
                            <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Gregorian Date (auto)') }}</label>
                            <input type="text" readonly value="{{ $editGregorianAuto }}" class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50">
                        </div>
                    @endif

                    <div class="md:col-span-2">
                        <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Hijri Date (auto)') }}</label>
                        <input type="text" readonly value="{{ $editDisplayHijriAuto }}" class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Hijri display (optional)') }}</label>
                        <input
                            type="text"
                            wire:model.defer="editDisplayHijri"
                            class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50 focus:bg-white focus:border-[color:var(--brand-via)] focus:ring-4 focus:ring-[color:var(--brand-via)]/10 transition-all"
                            placeholder="{{ tr('e.g. 1447/10/03') }}"
                        >
                        <div class="text-[10px] text-gray-400 mt-1">{{ tr('Leave empty to use auto value if available.') }}</div>
                        @error('editDisplayHijri') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 mt-6">
                    <x-ui.secondary-button :fullWidth="false" wire:click="closeEdit" wire:loading.attr="disabled">
                        {{ tr('Cancel') }}
                    </x-ui.secondary-button>

                    <x-ui.primary-button :arrow="false" :fullWidth="false" wire:click="saveEditHoliday" wire:loading.attr="disabled" wire:target="saveEditHoliday">
                        <i class="fas fa-save"></i>
                        <span class="ms-2">{{ tr('Update') }}</span>
                    </x-ui.primary-button>
                </div>
            </div>
        </div>
    @endif

    {{-- ========================= --}}
    {{-- Delete Confirm Modal --}}
    {{-- ========================= --}}
    @if($confirmDeleteOpen)
        <div class="fixed inset-0 z-[9999] bg-black/40 flex items-center justify-center p-4" wire:click.self="closeDelete">
            <div class="w-full max-w-md bg-white rounded-3xl shadow-xl border border-gray-100 p-6">
                <div class="flex items-start justify-between gap-3 mb-4">
                    <div>
                        <div class="text-lg font-black text-gray-900">{{ tr('Delete Holiday') }}</div>
                        <div class="text-xs text-gray-400">
                            {{ tr('Are you sure you want to delete') }}: <span class="font-black text-gray-800">{{ $deleteHolidayName }}</span>
                        </div>
                    </div>

                    <button type="button" class="text-gray-400 hover:text-gray-700" wire:click="closeDelete">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="text-sm text-gray-600 leading-relaxed">
                    {{ tr('This action cannot be undone.') }}
                </div>

                <div class="flex items-center justify-end gap-2 mt-6">
                    <x-ui.secondary-button :fullWidth="false" wire:click="closeDelete" wire:loading.attr="disabled">
                        {{ tr('Cancel') }}
                    </x-ui.secondary-button>

                    <x-ui.primary-button
                        :arrow="false"
                        :fullWidth="false"
                        class="!bg-red-600 hover:!bg-red-700"
                        wire:click="deleteHoliday"
                        wire:loading.attr="disabled"
                        wire:target="deleteHoliday"
                    >
                        <i class="fas fa-trash"></i>
                        <span class="ms-2">{{ tr('Delete') }}</span>
                    </x-ui.primary-button>
                </div>
            </div>
        </div>
    @endif
</div>
