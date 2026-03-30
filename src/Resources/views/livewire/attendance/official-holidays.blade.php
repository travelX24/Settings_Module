@php
    $locale = app()->getLocale();
    $isRtl = in_array(substr($locale, 0, 2), ['ar', 'fa', 'ur', 'he']);
@endphp

@section('topbar-left-content')
    <x-ui.page-header :title="tr('Official Holidays')" :subtitle="tr('Manage official holidays and non-working days')"
        class="!flex-col {{ $isRtl ? '!items-end !text-right' : '!items-start !text-left' }} !justify-start !gap-1"
        titleSize="xl" />

    <div class="text-xs text-gray-400">
        calendar_type: {{ config('company.calendar_type') }}
    </div>
@endsection

@section('topbar-actions')
    <x-ui.secondary-button href="{{ route('company-admin.settings.attendance') }}" :arrow="false" :fullWidth="false"
        class="!px-4 !py-2 !text-sm !rounded-xl !gap-2 cursor-pointer">
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
                    <x-ui.search-box model="search" placeholder="{{ tr('Search by name...') }}" :debounce="300" />
                </div>

                <div class="flex flex-wrap items-center gap-2 sm:shrink-0">
                    <x-ui.secondary-button :fullWidth="false" wire:click="exportCsv" wire:loading.attr="disabled"
                        class="!border-amber-200 !bg-amber-50/50 !text-amber-700 hover:!bg-amber-100 cursor-pointer">
                        <i class="fas fa-file-export" wire:loading.remove wire:target="exportCsv"></i>
                        <i class="fas fa-circle-notch fa-spin" wire:loading wire:target="exportCsv"></i>
                        <span class="ms-2 leading-none">{{ tr('Export') }}</span>
                    </x-ui.secondary-button>

                    @can('settings.attendance.manage')
                        <x-ui.primary-button :arrow="false" :fullWidth="false" wire:click="openCreate"
                            class="cursor-pointer">
                            <i class="fas fa-plus"></i>
                            <span class="ms-2">{{ tr('New Holiday') }}</span>
                        </x-ui.primary-button>
                    @endcan
                </div>
            </div>

            {{-- Filters --}}
            <div x-data="{ open: true }" class="space-y-3">
                <div class="flex items-center justify-between">
                    <button type="button" @click="open = !open"
                        class="flex items-center justify-between text-sm font-semibold text-gray-700 hover:text-gray-900 transition-colors cursor-pointer">
                        <span class="flex items-center gap-2">
                            <i class="fas fa-filter text-xs"></i>
                            <span>{{ tr('Filters') }}</span>
                        </span>
                        <i class="fas fa-chevron-down transition-transform ms-2 text-[10px]"
                            :class="open ? 'rotate-180' : ''"></i>
                    </button>
                </div>

                <div x-show="open" x-transition class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 items-end">
                    <x-ui.filter-select model="filterCalendar" :label="tr('Calendar Type')" :placeholder="tr('All Types')" :options="[
                        ['value' => 'all', 'label' => tr('All Types')],
                        ['value' => 'gregorian', 'label' => tr('Gregorian')],
                        ['value' => 'hijri', 'label' => tr('Hijri')],
                    ]"
                        width="full" :defer="false" :applyOnChange="true" />

                    <div>
                        <div class="mb-1 text-xs text-gray-500">{{ tr('Year') }}</div>
                        <x-ui.select wire:model.live="filterYear">
                            @foreach ($this->availableYears as $year)
                                <option value="{{ $year }}"
                                    {{ (int) $filterYear === (int) $year ? 'selected' : '' }}>
                                    {{ $year }}
                                </option>
                            @endforeach
                        </x-ui.select>
                    </div>

                    <div class="md:col-span-2">
                        <div class="grid grid-cols-2 gap-2">
                            <x-ui.company-date-picker model="filterDateStart" :label="tr('From')" />

                            <x-ui.company-date-picker model="filterDateEnd" :label="tr('To')" />
                        </div>
                    </div>

                    {{-- Clear Filters --}}
                    <div x-data="{
                        hasFilters() {
                            return ($wire.search && $wire.search.trim() !== '') ||
                                $wire.filterCalendar !== 'all' ||
                                ($wire.filterDateStart && $wire.filterDateStart !== '') ||
                                ($wire.filterDateEnd && $wire.filterDateEnd !== '');
                        }
                    }" x-show="hasFilters()" x-transition
                        class="md:col-span-4 flex items-center justify-end">
                        <button type="button" wire:click="clearAllFilters" wire:loading.attr="disabled"
                            wire:target="clearAllFilters"
                            class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-semibold text-gray-600 hover:text-gray-900 transition-colors disabled:opacity-50 cursor-pointer">
                            <i class="fas fa-times" wire:loading.remove wire:target="clearAllFilters"></i>
                            <i class="fas fa-spinner fa-spin" wire:loading wire:target="clearAllFilters"></i>
                            <span wire:loading.remove
                                wire:target="clearAllFilters">{{ tr('Clear all filters') }}</span>
                            <span wire:loading wire:target="clearAllFilters">{{ tr('Clearing...') }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </x-ui.card>

    {{-- Table --}}
    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-x-auto overflow-y-visible relative mt-2">
        <table class="w-full text-start border-collapse table-fixed">
            <thead>
                <tr class="bg-gray-50/50 border-b border-gray-100">
                    <th
                        class="w-[28%] px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-start">
                        {{ tr('Holiday') }}</th>
                    <th
                        class="w-[12%] px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">
                        {{ tr('Calendar') }}</th>
                    <th
                        class="w-[20%] px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">
                        {{ tr('Hijri Date') }}</th>
                    <th
                        class="w-[20%] px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">
                        {{ tr('Gregorian Date') }}</th>
                    <th
                        class="w-[10%] px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">
                        {{ tr('Days') }}</th>
                    <th
                        class="w-[10%] px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-end">
                        {{ tr('Actions') }}</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-gray-50">
                @forelse($rows as $row)
                    <tr class="hover:bg-gray-50/40 transition-colors">
                        <td class="px-6 py-4">
                            <div class="text-sm font-bold text-gray-800 truncate">{{ $row->template?->name ?? '-' }}
                            </div>
                            <div class="text-[10px] text-gray-400 truncate">{{ $row->template?->description ?? '' }}
                            </div>
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
                            <span class="text-[11px] font-bold text-gray-700 tabular-nums">
                                {{ $row->start_date ? $row->start_date->format('Y-m-d') : '—' }}
                                @if ($row->end_date && $row->end_date->format('Y-m-d') !== $row->start_date?->format('Y-m-d'))
                                    <span class="mx-1 text-gray-300">→</span>
                                    {{ $row->end_date->format('Y-m-d') }}
                                @endif
                            </span>
                        </td>

                        <td class="px-6 py-4 text-center">
                            <span class="text-xs font-bold text-gray-700">{{ $row->duration_days ?? 1 }}</span>
                        </td>

                        <td class="px-6 py-4 text-end">
                            @can('settings.attendance.manage')
                                <x-ui.actions-menu>
                                    <x-ui.dropdown-item wire:click.stop="openEdit({{ (int) $row->id }})"
                                        class="cursor-pointer">
                                        <i class="fas fa-edit me-2 text-blue-500"></i> {{ tr('Edit') }}
                                    </x-ui.dropdown-item>

                                    <x-ui.dropdown-item danger
                                        @click.stop="$dispatch('open-confirm-delete-holiday', { id: {{ $row->id }} })"
                                        class="cursor-pointer">
                                        <i class="fas fa-trash-alt me-2 text-red-500"></i> {{ tr('Delete') }}
                                    </x-ui.dropdown-item>
                                </x-ui.actions-menu>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-24 text-center">
                            <div class="opacity-20 flex flex-col items-center">
                                <div
                                    class="w-24 h-24 bg-gray-50 rounded-full flex items-center justify-center text-4xl mb-4 border border-gray-100">
                                    <i class="fas fa-calendar-times"></i>
                                </div>
                                <h4 class="text-base font-bold text-gray-800">{{ tr('No Official Holidays Found') }}
                                </h4>
                                <p class="text-xs max-w-[320px] mt-2 leading-relaxed">
                                    {{ tr('No official holidays have been defined yet. Start by creating the first holiday template.') }}
                                </p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($rows->hasPages())
            <div class="px-6 py-4 bg-gray-50/30 border-t border-gray-100">
                {{ $rows->links() }}
            </div>
        @endif
    </div>

    {{-- ========================= --}}
    {{-- Create Holiday Modal --}}
    {{-- ========================= --}}
    {{-- Create Holiday Modal --}}
    <x-ui.modal wire:model="createOpen" maxWidth="lg">
        <x-slot:title>
            <div class="flex items-center gap-3">
                <div
                    class="w-10 h-10 bg-brand-50 text-brand-600 rounded-xl flex items-center justify-center text-lg border border-brand-100 shadow-sm">
                    <i class="fas fa-umbrella-beach"></i>
                </div>
                <div>
                    <h3 class="font-bold text-gray-900 text-lg leading-tight">{{ tr('New Holiday') }}</h3>
                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">
                        {{ tr('Create a holiday template and occurrence') }}</p>
                </div>
            </div>
        </x-slot:title>

        <x-slot:content>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 py-4">
                <div class="md:col-span-2">
                    <x-ui.input label="{{ tr('Name') }}" wire:model.defer="newName"
                        placeholder="{{ tr('Holiday name...') }}" required :disabled="!auth()->user()->can('settings.attendance.manage')" />
                </div>

                <div>
                    <x-ui.filter-select model="newCalendarType" :label="tr('Calendar Type')" :options="[
                        ['value' => 'gregorian', 'label' => tr('Gregorian')],
                        ['value' => 'hijri', 'label' => tr('Hijri')],
                    ]" width="full"
                        :defer="false" :applyOnChange="true" />

                </div>

                <div>
                    <x-ui.input type="number" min="1" label="{{ tr('Duration (days)') }}"
                        wire:model.defer="newDurationDays" required :disabled="!auth()->user()->can('settings.attendance.manage')" />
                </div>

                <div>
                    <x-ui.company-date-picker model="newStartDate" :label="tr('Start Date')" :calendarType="$newCalendarType" />
                    @error('newStartDate')
                        <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <x-ui.input
                        label="{{ $newCalendarType === 'hijri' ? tr('Gregorian Date (auto)') : tr('Hijri Date (auto)') }}"
                        value="{{ $newCalendarType === 'hijri' ? $newGregorianAuto : $newDisplayHijriAuto }}" readonly
                        class="!bg-gray-50" />
                </div>

            </div>
        </x-slot:content>

        <x-slot:footer>
            <div class="flex items-center justify-end gap-3 w-full">
                <x-ui.secondary-button wire:click="closeCreate" class="!px-6 !rounded-xl cursor-pointer">
                    {{ tr('Cancel') }}
                </x-ui.secondary-button>
                @can('settings.attendance.manage')
                    <x-ui.primary-button wire:click="saveNewHoliday" loading="saveNewHoliday"
                        class="!px-6 !rounded-xl shadow-lg cursor-pointer" :arrow="false">
                        <i class="fas fa-save me-2"></i>
                        {{ tr('Save') }}
                    </x-ui.primary-button>
                @endcan
            </div>
        </x-slot:footer>
    </x-ui.modal>

    {{-- ========================= --}}
    {{-- Edit Holiday Modal --}}
    {{-- ========================= --}}
    {{-- Edit Holiday Modal --}}
    <x-ui.modal wire:model="editOpen" maxWidth="lg">
        <x-slot:title>
            <div class="flex items-center gap-3">
                <div
                    class="w-10 h-10 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center text-lg border border-blue-100 shadow-sm">
                    <i class="fas fa-edit"></i>
                </div>
                <div>
                    <h3 class="font-bold text-gray-900 text-lg leading-tight">{{ tr('Edit Holiday') }}</h3>
                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">
                        {{ tr('Update holiday information') }}</p>
                </div>
            </div>
        </x-slot:title>

        <x-slot:content>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 py-4">
                <div class="md:col-span-2">
                    <x-ui.input label="{{ tr('Name') }}" wire:model.defer="editName" required
                        :disabled="!auth()->user()->can('settings.attendance.manage')" />
                </div>

                <div>
                    <x-ui.filter-select model="editCalendarType" :label="tr('Calendar Type')" :options="[
                        ['value' => 'gregorian', 'label' => tr('Gregorian')],
                        ['value' => 'hijri', 'label' => tr('Hijri')],
                    ]" width="full"
                        :defer="false" :applyOnChange="true" />

                </div>

                <div>
                    <x-ui.input type="number" min="1" label="{{ tr('Duration (days)') }}"
                        wire:model.defer="editDurationDays" required :disabled="!auth()->user()->can('settings.attendance.manage')" />
                </div>

                <div>
                    <x-ui.company-date-picker model="editStartDate" :label="tr('Start Date')" :calendarType="$editCalendarType" />
                    @error('editStartDate')
                        <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <x-ui.input
                        label="{{ $editCalendarType === 'hijri' ? tr('Gregorian Date (auto)') : tr('Hijri Date (auto)') }}"
                        value="{{ $editCalendarType === 'hijri' ? $editGregorianAuto : $editDisplayHijriAuto }}"
                        readonly class="!bg-gray-50" />
                </div>

            </div>
        </x-slot:content>

        <x-slot:footer>
            <div class="flex items-center justify-end gap-3 w-full">
                <x-ui.secondary-button wire:click="closeEdit" class="!px-6 !rounded-xl cursor-pointer">
                    {{ tr('Cancel') }}
                </x-ui.secondary-button>
                @can('settings.attendance.manage')
                    <x-ui.primary-button wire:click="saveEditHoliday" loading="saveEditHoliday"
                        class="!px-6 !rounded-xl shadow-lg cursor-pointer" :arrow="false">
                        <i class="fas fa-save me-2"></i>
                        {{ tr('Update') }}
                    </x-ui.primary-button>
                @endcan
            </div>
        </x-slot:footer>
    </x-ui.modal>

    <template x-teleport="body">
        <div>
            <x-ui.confirm-dialog id="delete-holiday" confirmText="{{ tr('Delete') }}"
                cancelText="{{ tr('Cancel') }}" confirmAction="wire:deleteHoliday(__ID__)"
                title="{{ tr('Delete Holiday') }}"
                message="{{ tr('Are you sure you want to delete this holiday? This action cannot be undone.') }}"
                type="danger" />
        </div>
    </template>
</div>
