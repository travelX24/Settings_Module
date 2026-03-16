@php
    $locale = app()->getLocale();
    $isRtl  = in_array(substr($locale, 0, 2), ['ar','fa','ur','he']);
@endphp

@section('topbar-left-content')
    <x-ui.page-header
        :title="tr('Work Schedules Management')"
        :subtitle="tr('Define and manage flexible working hour templates for your organization')"
        class="!flex-col {{ $isRtl ? '!items-end !text-right' : '!items-start !text-left' }} !justify-start !gap-1"
        titleSize="xl"
    />
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
    {{-- Floating Elements Teleported to Body --}}
    <template x-teleport="body">
        <div>
            @include('systemsettings::livewire.attendance.partials.work-schedule-modal')
            
            <x-ui.confirm-dialog 
                id="delete-work-schedule" 
                confirmText="{{ tr('Yes, Delete') }}"
                cancelText="{{ tr('Cancel') }}"
                confirmAction="wire:deleteSchedule(__ID__)"
                title="{{ tr('Confirm Permanent Deletion') }}"
                message="{{ tr('Are you sure you want to delete this schedule template? This action cannot be reversed.') }}"
                type="danger"
            />
        </div>
    </template>

    <x-ui.card>
        <div class="space-y-4">
            {{-- Search Box & Main Actions --}}
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
                        wire:click="exportSchedules"
                        :fullWidth="false"
                        class="!border-amber-200 !bg-amber-50/50 !text-amber-700 hover:!bg-amber-100 cursor-pointer"
                    >
                        <i class="fas fa-file-export"></i>
                        <span class="ms-2">{{ tr('Export CSV') }}</span>
                    </x-ui.secondary-button>

                    @can('settings.attendance.manage')
                    <x-ui.primary-button
                        wire:click="openScheduleModal"
                        :arrow="false"
                        :fullWidth="false"
                        class="cursor-pointer"
                    >
                        <i class="fas fa-plus"></i>
                        <span class="ms-2">{{ tr('New Schedule') }}</span>
                    </x-ui.primary-button>
                    @endcan
                </div>
            </div>

            {{-- Collapsible Filters --}}
            <div x-data="{ open: true }" class="space-y-3">
                <div class="flex items-center justify-between">
                    <button
                        type="button"
                        @click="open = !open"
                        class="flex items-center justify-between text-sm font-semibold text-gray-700 hover:text-gray-900 transition-colors cursor-pointer"
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
                        model="filterType"
                        :label="tr('Schedule Type')"
                        :placeholder="tr('All Types')"
                        :options="[
                            ['value' => 'full_time', 'label' => tr('Full-time')],
                            ['value' => 'part_time', 'label' => tr('Part-time')],
                            ['value' => 'shifts', 'label' => tr('Shifts')],
                            ['value' => 'custom', 'label' => tr('Custom')],
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
                            ['value' => 'active', 'label' => tr('Active')],
                            ['value' => 'inactive', 'label' => tr('Inactive')],
                        ]"
                        width="full"
                        :defer="false"
                        :applyOnChange="true"
                    />

                    <x-ui.filter-select
                        model="filterPeriod"
                        :label="tr('Work Period')"
                        :placeholder="tr('All Periods')"
                        :options="[
                            ['value' => 'morning', 'label' => tr('Morning Shift')],
                            ['value' => 'night', 'label' => tr('Night Shift')],
                            ['value' => 'mixed', 'label' => tr('Mixed Shift')],
                        ]"
                        width="full"
                        :defer="false"
                        :applyOnChange="true"
                    />

                    <div class="grid grid-cols-2 gap-2">
                        <x-ui.company-date-picker
                            model="filterDateStart"
                            :label="tr('From')"
                            :placeholder="tr('Select date...')"
                        />

                        <x-ui.company-date-picker
                            model="filterDateEnd"
                            :label="tr('To')"
                            :placeholder="tr('Select date...')"
                        />
                    </div>
                </div>

                {{-- Clear Filters Button --}}
                <div
                    x-show="$wire.search.trim() !== '' || $wire.filterType !== 'all' || $wire.filterStatus !== 'all' || $wire.filterPeriod !== 'all' || $wire.filterDateStart !== '' || $wire.filterDateEnd !== ''"
                    x-transition
                    class="flex items-center justify-end"
                >
                    <button
                        type="button"
                        wire:click="clearAllFilters"
                        wire:loading.attr="disabled"
                        wire:target="clearAllFilters"
                        class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-semibold text-gray-600 hover:text-gray-900 transition-colors disabled:opacity-50 cursor-pointer"
                    >
                        <i class="fas fa-times" wire:loading.remove wire:target="clearAllFilters"></i>
                        <i class="fas fa-spinner fa-spin" wire:loading wire:target="clearAllFilters"></i>
                        <span wire:loading.remove wire:target="clearAllFilters">{{ tr('Clear all filters') }}</span>
                        <span wire:loading wire:target="clearAllFilters">{{ tr('Clearing...') }}</span>
                    </button>
                </div>
            </div>
        </div>
    </x-ui.card>

    {{-- Table Collection --}}
    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-visible relative mt-2">
        <table class="w-full text-start border-collapse table-fixed">
            <thead>
                <tr class="bg-gray-50/50 border-b border-gray-100">
                    <th class="w-[30%] px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-start">{{ tr('Schedule Profiling') }}</th>
                    <th class="w-[25%] px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">{{ tr('Timing Slot') }}</th>
                    <th class="w-[25%] px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">{{ tr('Week Matrix') }}</th>
                    <th class="w-[10%] px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">{{ tr('State') }}</th>
                    <th class="w-[10%] px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-end">{{ tr('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($schedules as $schedule)
                <tr class="hover:bg-gray-50/40 transition-colors group/row">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-gray-50 text-gray-400 flex items-center justify-center text-sm border border-gray-100 group-hover/row:bg-[color:var(--brand-via)] group-hover/row:text-white group-hover/row:border-[color:var(--brand-via)] transition-all flex-shrink-0">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div class="overflow-hidden">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-bold text-gray-800 truncate">{{ $schedule->name }}</span>
                                    @if($schedule->is_default)
                                        <span class="flex-shrink-0 px-1.5 py-0.5 rounded-md bg-amber-50 text-[9px] font-black text-amber-600 border border-amber-100 uppercase">{{ tr('Default') }}</span>
                                    @endif
                                </div>
                                <p class="text-[10px] text-gray-400 truncate">{{ $schedule->description ?: tr('No description provided') }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <div class="flex flex-col items-center justify-center gap-1.5">
                            @foreach($schedule->periods as $period)
                                <div class="flex items-center justify-center gap-2 text-[10px] font-black text-gray-600 bg-white px-2.5 py-1 rounded-xl border border-gray-100 shadow-sm w-fit whitespace-nowrap">
                                    <span>{{ substr($period->start_time, 0, 5) }}</span>
                                    <i class="fas fa-long-arrow-alt-{{ $isRtl ? 'left' : 'right' }} text-[9px] text-[color:var(--brand-via)]/40"></i>
                                    <span>{{ substr($period->end_time, 0, 5) }}</span>
                                    @if($period->is_night_shift)
                                        <i class="fas fa-moon text-[10px] text-indigo-400 ms-1" title="{{ tr('Night Shift') }}"></i>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <div class="flex flex-col items-center justify-center">
                            <div class="flex items-center justify-center -space-x-1.5 {{ $isRtl ? 'space-x-reverse' : '' }}">
                                @foreach(['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday'] as $day)
                                    <div class="w-6 h-6 rounded-full border-2 border-white flex items-center justify-center text-[9px] font-black shadow-sm flex-shrink-0 {{ in_array($day, $schedule->work_days ?? []) ? 'bg-[color:var(--brand-via)] text-white' : 'bg-gray-100 text-gray-300' }}">
                                        {{ mb_substr(tr(ucfirst($day)), 0, 1) }}
                                    </div>
                                @endforeach
                            </div>
                            @if(($schedule->exceptions ?? collect())->count() > 0)
                                <div class="mt-2 text-center" x-data="{ open: false }">
                                    <div class="inline-block relative">
                                        <span 
                                            x-ref="anchor"
                                            @mouseenter="open = true" 
                                            @mouseleave="open = false"
                                            class="px-2 py-1 rounded-lg bg-indigo-50/50 text-[9px] font-bold text-indigo-600 border border-indigo-100/50 uppercase inline-flex items-center gap-1.5 cursor-pointer transition-all hover:bg-indigo-100/50"
                                        >
                                            <i class="fas fa-magic text-[8px]"></i>
                                            {{ $schedule->exceptions->count() }} {{ tr('Exceptions') }}
                                        </span>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <div class="flex justify-center">
                            @can('settings.attendance.manage')
                            <button wire:click="toggleStatus({{ $schedule->id }})" class="w-9 h-4.5 rounded-full p-1 transition-all relative cursor-pointer {{ $schedule->is_active ? 'bg-green-500' : 'bg-gray-200' }}">
                                <div class="w-2.5 h-2.5 bg-white rounded-full shadow-sm transition-all {{ $schedule->is_active ? ($isRtl ? 'mr-4.5' : 'ml-4.5') : '' }}"></div>
                            </button>
                            @else
                            <button disabled class="w-9 h-4.5 rounded-full p-1 transition-all relative cursor-not-allowed opacity-50 {{ $schedule->is_active ? 'bg-green-500' : 'bg-gray-200' }}">
                                <div class="w-2.5 h-2.5 bg-white rounded-full shadow-sm {{ $schedule->is_active ? ($isRtl ? 'mr-4.5' : 'ml-4.5') : '' }}"></div>
                            </button>
                            @endcan
                        </div>
                    </td>
                    <td class="px-6 py-4 text-end">
                        @can('settings.attendance.manage')
                        <x-ui.actions-menu>
                            <x-ui.dropdown-item wire:click="copySchedule({{ $schedule->id }})" class="cursor-pointer">
                                <i class="fas fa-copy me-2 text-amber-500"></i> {{ tr('Duplicate') }}
                            </x-ui.dropdown-item>
                            <x-ui.dropdown-item wire:click="openScheduleModal({{ $schedule->id }})" class="cursor-pointer">
                                <i class="fas fa-edit me-2 text-blue-500"></i> {{ tr('Edit Details') }}
                            </x-ui.dropdown-item>
                            <x-ui.dropdown-item 
                                danger
                                @click.stop="$dispatch('open-confirm-delete-work-schedule', { id: '{{ $schedule->id }}' })"
                                class="cursor-pointer"
                            >
                                <i class="fas fa-trash-alt me-2 text-red-500"></i> {{ tr('Delete Permanently') }}
                            </x-ui.dropdown-item>
                        </x-ui.actions-menu>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-6 py-24 text-center">
                        <div class="opacity-20 flex flex-col items-center">
                            <div class="w-24 h-24 bg-gray-50 rounded-full flex items-center justify-center text-4xl mb-4 border border-gray-100"><i class="fas fa-calendar-alt"></i></div>
                            <h4 class="text-base font-bold text-gray-800">{{ tr('Database Empty') }}</h4>
                            <p class="text-xs max-w-[250px] mt-2 leading-relaxed">{{ tr('No work schedules have been defined yet. Get started by creating your first template.') }}</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        
        @if($schedules->hasPages())
        <div class="px-6 py-4 bg-gray-50/30 border-t border-gray-100">
            {{ $schedules->links() }}
        </div>
        @endif
    </div>
</div>
