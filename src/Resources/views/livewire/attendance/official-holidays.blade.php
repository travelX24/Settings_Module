@php
    $locale = app()->getLocale();
    $isRtl = in_array(substr($locale, 0, 2), ['ar', 'fa', 'ur', 'he']);
    $canManageAttendance = auth()->user()?->canAny(['settings.attendance.holidays.manage']) ?? false;
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
                    {{-- Clear Filters Button --}}
                    <div
                        x-data="{
                            hasFilters() {
                                return ($wire.search && $wire.search.trim() !== '') ||
                                       ($wire.filterCalendar && $wire.filterCalendar !== 'all') ||
                                       ($wire.filterDateStart && $wire.filterDateStart !== '') ||
                                       ($wire.filterDateEnd && $wire.filterDateEnd !== '');
                            }
                        }"
                        x-show="hasFilters()"
                        x-transition
                        class="flex items-center"
                    >
                        <button
                            type="button"
                            wire:click="clearAllFilters"
                            wire:loading.attr="disabled"
                            wire:target="clearAllFilters"
                            class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-semibold text-gray-600 hover:text-gray-900 transition-colors disabled:opacity-50 border-e border-gray-100 pe-4"
                        >
                            <i class="fas fa-times" wire:loading.remove wire:target="clearAllFilters"></i>
                            <i class="fas fa-spinner fa-spin" wire:loading wire:target="clearAllFilters"></i>
                            <span wire:loading.remove wire:target="clearAllFilters">{{ tr('Clear filters') }}</span>
                        </button>
                    </div>

                    <x-ui.secondary-button :fullWidth="false" wire:click="exportExcel" wire:loading.attr="disabled"
                        class="!border-[rgb(245_158_11/0.22)] !bg-[rgb(245_158_11/0.08)] !text-[color:var(--warning)] hover:!bg-[rgb(245_158_11/0.12)] cursor-pointer">
                        <i class="fas fa-file-export" wire:loading.remove wire:target="exportExcel"></i>
                        <i class="fas fa-circle-notch fa-spin" wire:loading wire:target="exportExcel"></i>
                        <span class="ms-2 leading-none">{{ tr('Export') }}</span>
                    </x-ui.secondary-button>

                    @if($canManageAttendance)
                        <x-ui.primary-button :arrow="false" :fullWidth="false" wire:click="openCreate"
                            class="cursor-pointer">
                            <i class="fas fa-plus"></i>
                            <span class="ms-2">{{ tr('New Holiday') }}</span>
                        </x-ui.primary-button>
                    @endif
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

                </div>
            </div>
        </div>
    </x-ui.card>

    {{-- Table --}}
    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-visible relative mt-2">
        <div class="w-full overflow-x-auto no-scrollbar">
            <table class="w-full text-start border-collapse">
                <thead>
                    <tr class="bg-gray-50/50 border-b border-gray-100">
                        <th
                            class="min-w-[200px] px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-start whitespace-nowrap">
                            {{ tr('Holiday') }}</th>
                        <th
                            class="min-w-[100px] px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center whitespace-nowrap">
                            {{ tr('Calendar') }}</th>
                        <th
                            class="min-w-[150px] px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center whitespace-nowrap">
                            {{ tr('Hijri Date') }}</th>
                        <th
                            class="min-w-[150px] px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center whitespace-nowrap">
                            {{ tr('Gregorian Date') }}</th>
                        <th
                            class="min-w-[80px] px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center whitespace-nowrap">
                            {{ tr('Days') }}</th>
                        <th
                            class="min-w-[100px] px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-end whitespace-nowrap">
                            {{ tr('Actions') }}</th>
                    </tr>
                </thead>
    
                <tbody class="divide-y divide-gray-50">
                    @forelse($rows as $row)
                        <tr class="hover:bg-gray-50/40 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-bold text-gray-800 truncate">{{ $row->template?->name ?? '-' }}
                                </div>
                                <div class="text-[10px] text-gray-400 truncate">{{ $row->template?->description ?? '' }}
                                </div>
                            </td>
    
                            <td class="px-6 py-4 text-center whitespace-nowrap">
                                <span class="text-[10px] font-black text-gray-600 uppercase">
                                    {{ $row->template?->calendar_type ?? '-' }}
                                </span>
                            </td>
    
                            <td class="px-6 py-4 text-center whitespace-nowrap">
                                <span class="text-xs font-semibold text-gray-700 tabular-nums">
                                    {{ $row->display_hijri ?: '—' }}
                                    @if ($row->end_date && $row->start_date && $row->end_date->format('Y-m-d') !== $row->start_date->format('Y-m-d'))
                                        @php
                                            $endHijri = app(\Athka\SystemSettings\Services\HolidayService::class)->hijriFromGregorian($row->end_date->format('Y-m-d'));
@endphp
                                        @if ($endHijri)
                                            <span class="mx-1 text-gray-300">→</span>
                                            {{ $endHijri }}
                                        @endif
                                    @endif
                                </span>
                            </td>
    
                            <td class="px-6 py-4 text-center whitespace-nowrap">
                                <span class="text-[11px] font-bold text-gray-700 tabular-nums">
                                    {{ $row->start_date ? $row->start_date->format('Y-m-d') : '—' }}
                                    @if ($row->end_date && $row->end_date->format('Y-m-d') !== $row->start_date?->format('Y-m-d'))
                                        <span class="mx-1 text-gray-300">→</span>
                                        {{ $row->end_date->format('Y-m-d') }}
                                    @endif
                                </span>
                            </td>
    
                            <td class="px-6 py-4 text-center whitespace-nowrap">
                                <span class="text-xs font-bold text-gray-700">{{ $row->duration_days ?? 1 }}</span>
                            </td>
    
                            <td class="px-6 py-4 text-end whitespace-nowrap">
                                @if($canManageAttendance)
                                    <x-ui.actions-menu>
                                        <x-ui.dropdown-item wire:click.stop="openEdit({{ (int) $row->id }})"
                                            @click="$dispatch('close-actions-menu')"
                                            class="cursor-pointer">
                                            <i class="fas fa-edit me-2 text-[color:var(--accent-orange)]"></i> {{ tr('Edit') }}
                                        </x-ui.dropdown-item>
    
                                        <x-ui.dropdown-item danger
                                            @click.stop="$dispatch('open-confirm-delete-holiday', { id: {{ $row->id }} }); $dispatch('close-actions-menu')"
                                            class="cursor-pointer">
                                            <i class="fas fa-trash-alt me-2 text-[color:var(--error)]"></i> {{ tr('Delete') }}
                                        </x-ui.dropdown-item>
                                    </x-ui.actions-menu>
                                @endif
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
                    class="w-10 h-10 bg-[color:var(--accent-orange)]/10 text-[color:var(--accent-orange)] rounded-xl flex items-center justify-center text-lg border border-[color:var(--accent-orange)]/20 shadow-sm">
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
                        placeholder="{{ tr('Holiday name...') }}" required :disabled="!$canManageAttendance" />
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
                        wire:model.defer="newDurationDays" required :disabled="!$canManageAttendance" />
                </div>

                {{-- Repeat Type --}}
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wider">
                        {{ trk('recurrence', 'Recurrence') }}
                    </label>
                    <div class="flex items-center gap-4">
                        <label class="flex items-center gap-2 cursor-pointer group">
                            <input type="radio" wire:model.live="newRepeatType" value="once"
                                class="w-4 h-4 text-[color:var(--accent-orange)] border-gray-300 focus:ring-[color:var(--accent-orange)]" />
                            <span class="text-sm font-medium text-gray-700 group-hover:text-gray-900">
                                {{ trk('one_time_only', 'One-time only') }}
                            </span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer group">
                            <input type="radio" wire:model.live="newRepeatType" value="annual"
                                class="w-4 h-4 text-[color:var(--accent-orange)] border-gray-300 focus:ring-[color:var(--accent-orange)]" />
                            <span class="text-sm font-medium text-gray-700 group-hover:text-gray-900">
                                {{ trk('repeat_annually', 'Repeat annually') }}
                            </span>
                        </label>
                    </div>
                    @if ($newRepeatType === 'annual')
                        <div class="mt-2 p-3 bg-[rgb(var(--accent-orange-rgb)/0.08)] border border-[rgb(var(--accent-orange-rgb)/0.16)] rounded-lg text-xs text-[color:var(--accent-orange)] leading-relaxed">
                            <i class="fas fa-info-circle me-1"></i>
                            @if ($newCalendarType === 'hijri')
                                {{ trk('holiday_auto_hijri_msg', 'The system will automatically generate this holiday for the next 5 Hijri years with the correct Gregorian date for each year.') }}
                            @else
                                {{ trk('holiday_auto_greg_msg', 'The system will automatically generate this holiday for the next 5 years on the same month and day.') }}
                            @endif
                        </div>
                    @endif
                </div>

                <div>
                    <x-ui.company-date-picker model="newStartDate" :label="tr('Start Date')" :calendarType="$newCalendarType" />
                    @error('newStartDate')
                        <div class="text-xs text-[color:var(--error)] mt-1">{{ \Athka\AuthKit\Support\UiMsg::toText($message) ?? $message }}</div>
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
                @if($canManageAttendance)
                    <x-ui.primary-button wire:click="saveNewHoliday" loading="saveNewHoliday"
                        class="!px-6 !rounded-xl shadow-lg cursor-pointer" :arrow="false">
                        <i class="fas fa-save me-2"></i>
                        {{ tr('Save') }}
                    </x-ui.primary-button>
                @endif
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
                    class="w-10 h-10 bg-[color:var(--accent-orange)]/10 text-[color:var(--accent-orange)] rounded-xl flex items-center justify-center text-lg border border-[color:var(--accent-orange)]/20 shadow-sm">
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
                        :disabled="!$canManageAttendance" />
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
                        wire:model.defer="editDurationDays" required :disabled="!$canManageAttendance" />
                </div>

                <div>
                    <x-ui.company-date-picker model="editStartDate" :label="tr('Start Date')" :calendarType="$editCalendarType" />
                    @error('editStartDate')
                        <div class="text-xs text-[color:var(--error)] mt-1">{{ \Athka\AuthKit\Support\UiMsg::toText($message) ?? $message }}</div>
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
                @if($canManageAttendance)
                    <x-ui.primary-button wire:click="saveEditHoliday" loading="saveEditHoliday"
                        class="!px-6 !rounded-xl shadow-lg cursor-pointer" :arrow="false">
                        <i class="fas fa-save me-2"></i>
                        {{ tr('Update') }}
                    </x-ui.primary-button>
                @endif
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
