 @php
    $locale = app()->getLocale();
    $isRtl  = in_array(substr($locale, 0, 2), ['ar','fa','ur','he']);
    $selectedYear = $years->firstWhere('id', $selectedYearId);
@endphp

@section('topbar-left-content')
    <x-ui.page-header
        :title="tr('Leave Settings')"
        :subtitle="tr('Define leave types, yearly policies, and rules')"
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
    <x-ui.card>
        <div class="space-y-4">

            {{-- Year Selector --}}
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex items-center gap-2">
                    <x-ui.secondary-button :fullWidth="false" wire:click="prevYear">
                        <i class="fas {{ $isRtl ? 'fa-chevron-right' : 'fa-chevron-left' }}"></i>
                    </x-ui.secondary-button>

                    <div class="px-4 py-2 rounded-2xl bg-gray-50 border border-gray-200 text-sm font-black text-gray-800 flex items-center gap-2">
                        @if($showAllYears)
                            <span>{{ tr('All Years') }}</span>
                        @else
                            <span>{{ $selectedYear?->year ?? '—' }}</span>
                        @endif

                        @if(!$showAllYears && $selectedYear?->is_active)
                            <span class="text-[10px] font-black px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100">
                                {{ tr('Active') }}
                            </span>
                        @endif
                    </div>

                    <x-ui.secondary-button :fullWidth="false" wire:click="nextYear">
                        <i class="fas {{ $isRtl ? 'fa-chevron-left' : 'fa-chevron-right' }}"></i>
                    </x-ui.secondary-button>

                    <button
                        type="button"
                        wire:click="toggleAllYears"
                        class="text-xs font-bold text-gray-500 hover:text-gray-900 transition-colors ms-2"
                    >
                        {{ $showAllYears ? tr('Show single year') : tr('Show all years') }}
                    </button>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    @can('settings.attendance.manage')
                        <x-ui.secondary-button :fullWidth="false" wire:click="openYears">
                            <i class="fas fa-calendar"></i>
                            <span class="ms-2">{{ tr('Manage Years') }}</span>
                        </x-ui.secondary-button>

                        <x-ui.secondary-button :fullWidth="false" wire:click="openCompare">
                            <i class="fas fa-scale-balanced"></i>
                            <span class="ms-2">{{ tr('Compare Years') }}</span>
                        </x-ui.secondary-button>

                        <x-ui.secondary-button :fullWidth="false" wire:click="openCopyPolicies">
                            <i class="fas fa-copy"></i>
                            <span class="ms-2">{{ tr('Copy Policies') }}</span>
                        </x-ui.secondary-button>

                        <x-ui.secondary-button :fullWidth="false" wire:click="exportPolicies">
                            <i class="fas fa-file-export"></i>
                            <span class="ms-2">{{ tr('Export Policies') }}</span>
                        </x-ui.secondary-button>

                        <x-ui.primary-button :arrow="false" :fullWidth="false" wire:click="openCreate">
                            <i class="fas fa-plus"></i>
                            <span class="ms-2">{{ tr('New Leave Type') }}</span>
                        </x-ui.primary-button>
                    @endcan
                </div>
            </div>

            {{-- Search & Filters --}}
            <div class="grid grid-cols-1 xl:grid-cols-12 gap-4 items-end">
                {{-- Filters --}}
                <div class="xl:col-span-8">
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4">
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

                        <x-ui.filter-select
                            model="filterGender"
                            :label="tr('Gender')"
                            :placeholder="tr('All')"
                            :options="[
                                ['value' => 'all', 'label' => tr('All')],
                                ['value' => 'male', 'label' => tr('Male')],
                                ['value' => 'female', 'label' => tr('Female')],
                            ]"
                            width="full"
                            :defer="false"
                            :applyOnChange="true"
                        />

                        <x-ui.filter-select
                            model="filterShowInApp"
                            :label="tr('Show in App')"
                            :placeholder="tr('All')"
                            :options="[
                                ['value' => 'all', 'label' => tr('All')],
                                ['value' => 'yes', 'label' => tr('Yes')],
                                ['value' => 'no', 'label' => tr('No')],
                            ]"
                            width="full"
                            :defer="false"
                            :applyOnChange="true"
                        />

                        <x-ui.filter-select
                            model="filterAttachments"
                            :label="tr('Attachments')"
                            :placeholder="tr('All')"
                            :options="[
                                ['value' => 'all', 'label' => tr('All')],
                                ['value' => 'yes', 'label' => tr('Required')],
                                ['value' => 'no', 'label' => tr('Not required')],
                            ]"
                            width="full"
                            :defer="false"
                            :applyOnChange="true"
                        />

                        <x-ui.filter-select
                            model="filterYearId"
                            :label="tr('Year')"
                            :placeholder="tr('All Years')"
                            :options="collect($years)->map(fn($y) => ['value' => (string)$y->id, 'label' => (string)$y->year])->prepend(['value' => 'all', 'label' => tr('All Years')])->values()->all()"
                            width="full"
                            :defer="false"
                            :applyOnChange="true"
                        />
                    </div>
                </div>

                {{-- Search --}}
                <div class="xl:col-span-4 min-w-0">
                    <x-ui.search-box
                        model="search"
                        placeholder="{{ tr('Search by leave name...') }}"
                        :debounce="300"
                    />
                </div>
            </div>

        </div>
    </x-ui.card>

    {{-- Table --}}
    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-x-auto overflow-y-visible relative mt-2">
        <table class="w-full text-start border-collapse table-fixed">
            <thead>
                <tr class="bg-gray-50/50 border-b border-gray-100">
                    <th class="w-[24%] px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-start">{{ tr('Leave') }}</th>
                    <th class="w-[10%] px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">{{ tr('Type') }}</th>
                    <th class="w-[10%] px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">{{ tr('Days') }}</th>
                    <th class="w-[10%] px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">{{ tr('Year') }}</th>
                    <th class="w-[10%] px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">{{ tr('Gender') }}</th>
                    <th class="w-[10%] px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">{{ tr('Status') }}</th>
                    <th class="w-[10%] px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">{{ tr('Show in App') }}</th>
                    <th class="w-[10%] px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">{{ tr('Attachments') }}</th>
                    <th class="w-[6%] px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-end">{{ tr('Actions') }}</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-gray-50">
                @forelse($rows as $row)
                    <tr class="hover:bg-gray-50/40 transition-colors">
                        <td class="px-6 py-4">
                            <div class="text-sm font-bold text-gray-800 truncate">{{ $row->name }}</div>
                            <div class="text-[10px] text-gray-400 truncate">{{ $row->description ?? '' }}</div>
                        </td>

                        <td class="px-6 py-4 text-center">
                            <span class="text-[10px] font-black text-gray-600 uppercase">{{ $row->leave_type }}</span>
                        </td>

                        <td class="px-6 py-4 text-center">
                            <span class="text-xs font-bold text-gray-800">{{ $row->days_per_year }}</span>
                        </td>

                        <td class="px-6 py-4 text-center">
                            <span class="text-xs font-semibold text-gray-700">{{ $row->year?->year ?? '—' }}</span>
                        </td>

                        <td class="px-6 py-4 text-center">
                            <span class="text-xs font-semibold text-gray-700">{{ $row->gender }}</span>
                        </td>

                        <td class="px-6 py-4 text-center">
                            <span class="text-xs font-bold {{ $row->is_active ? 'text-emerald-600' : 'text-gray-400' }}">
                                {{ $row->is_active ? tr('Active') : tr('Inactive') }}
                            </span>
                        </td>

                        <td class="px-6 py-4 text-center">
                            <span class="text-xs font-bold {{ $row->show_in_app ? 'text-emerald-600' : 'text-gray-400' }}">
                                {{ $row->show_in_app ? tr('Yes') : tr('No') }}
                            </span>
                        </td>

                        <td class="px-6 py-4 text-center">
                            <span class="text-xs font-bold {{ $row->requires_attachment ? 'text-amber-600' : 'text-gray-400' }}">
                                {{ $row->requires_attachment ? tr('Yes') : tr('No') }}
                            </span>
                        </td>

                        <td class="px-6 py-4 text-end">
                            <x-ui.actions-menu>
                                <x-ui.dropdown-item wire:click="openEdit({{ (int) $row->id }})">
                                    <i class="fas fa-edit me-2 text-blue-500"></i> {{ tr('Edit') }}
                                </x-ui.dropdown-item>

                                <x-ui.dropdown-item danger wire:click="confirmDelete({{ (int) $row->id }})">
                                    <i class="fas fa-trash-alt me-2 text-red-500"></i> {{ tr('Delete') }}
                                </x-ui.dropdown-item>
                            </x-ui.actions-menu>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-6 py-24 text-center">
                            <div class="opacity-20 flex flex-col items-center">
                                <div class="w-24 h-24 bg-gray-50 rounded-full flex items-center justify-center text-4xl mb-4 border border-gray-100">
                                    <i class="fas fa-calendar-times"></i>
                                </div>
                                <h4 class="text-base font-bold text-gray-800">{{ tr('Database Empty') }}</h4>
                                <p class="text-xs max-w-[360px] mt-2 leading-relaxed">
                                    {{ tr('No leave policies found. Start by creating the first leave type for the selected year.') }}
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

    {{-- Create Modal --}}
    {{-- Create Modal --}}
    <x-ui.modal wire:model="createOpen" maxWidth="4xl">
        <x-slot:title>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-brand-50 text-brand-600 rounded-xl flex items-center justify-center text-lg border border-brand-100 shadow-sm">
                    <i class="fas fa-umbrella-beach"></i>
                </div>
                <div>
                    <h3 class="font-bold text-gray-900 text-lg leading-tight">{{ tr('New Leave Type') }}</h3>
                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">{{ tr('Create a leave policy for the selected year') }}</p>
                </div>
            </div>
        </x-slot:title>

        <x-slot:content>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 py-2">
                {{-- Left Column: Basic Info --}}
                <div class="space-y-4">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="w-1 h-3 bg-brand-500 rounded-full"></span>
                        <h4 class="text-xs font-black text-gray-700 uppercase tracking-wider">{{ tr('Basic Information') }}</h4>
                    </div>

                    <div class="bg-gray-50/50 p-4 rounded-3xl border border-gray-100 space-y-4">
                        <x-ui.input
                            label="{{ tr('Name') }}"
                            wire:model.defer="name"
                            placeholder="{{ tr('Leave name...') }}"
                        />

                        <div class="grid grid-cols-2 gap-4">
                            <x-ui.input
                                label="{{ tr('Type') }}"
                                wire:model.defer="leave_type"
                                placeholder="{{ tr('e.g. annual') }}"
                            />
                            <x-ui.input
                                type="number" step="0.5" min="0"
                                label="{{ tr('Days per year') }}"
                                wire:model.defer="days_per_year"
                            />
                        </div>

                        <x-ui.select label="{{ tr('Gender') }}" wire:model.defer="gender">
                            <option value="all">{{ tr('All') }}</option>
                            <option value="male">{{ tr('Male') }}</option>
                            <option value="female">{{ tr('Female') }}</option>
                        </x-ui.select>

                        <x-ui.textarea
                            label="{{ tr('Description') }}"
                            wire:model.defer="description"
                            rows="2"
                        />

                        <div class="flex flex-wrap gap-4 pt-2">
                            <label class="flex items-center gap-2 text-sm font-bold text-gray-700 cursor-pointer">
                                <input type="checkbox" wire:model.defer="is_active" class="rounded-lg border-gray-300 text-brand-600 focus:ring-brand-500">
                                <span>{{ tr('Active') }}</span>
                            </label>

                            <label class="flex items-center gap-2 text-sm font-bold text-gray-700 cursor-pointer">
                                <input type="checkbox" wire:model.defer="show_in_app" class="rounded-lg border-gray-300 text-brand-600 focus:ring-brand-500">
                                <span>{{ tr('Show in App') }}</span>
                            </label>

                            <label class="flex items-center gap-2 text-sm font-bold text-gray-700 cursor-pointer">
                                <input type="checkbox" wire:model.defer="requires_attachment" class="rounded-lg border-gray-300 text-brand-600 focus:ring-brand-500">
                                <span>{{ tr('Requires attachment') }}</span>
                            </label>
                        </div>
                    </div>
                </div>

                {{-- Right Column: Advanced Settings --}}
                <div class="space-y-4">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="w-1 h-3 bg-purple-500 rounded-full"></span>
                        <h4 class="text-xs font-black text-gray-700 uppercase tracking-wider">{{ tr('Advanced Rules') }}</h4>
                    </div>

                    <div class="bg-purple-50/20 p-4 rounded-3xl border border-purple-50 space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                             <x-ui.select label="{{ tr('Accrual method') }}" wire:model.defer="accrual_method">
                                <option value="annual_grant">{{ tr('Annual full grant') }}</option>
                                <option value="monthly">{{ tr('Monthly accrual') }}</option>
                                <option value="by_work_days">{{ tr('By actual work days') }}</option>
                            </x-ui.select>
                            
                            <x-ui.input type="number" step="0.1" min="0" label="{{ tr('Monthly rate') }}" wire:model.defer="monthly_accrual_rate" />
                        </div>
                        
                        <div class="grid grid-cols-3 gap-3">
                            <x-ui.input type="number" step="0.5" min="0" label="{{ tr('Max balance') }}" wire:model.defer="max_balance" />
                            <x-ui.input type="number" step="0.5" min="0" label="{{ tr('Carryover days') }}" wire:model.defer="carryover_days" />
                            <x-ui.input type="number" step="1" min="0" label="{{ tr('Expiry') }}" wire:model.defer="carryover_expire_months" />
                        </div>

                         <div class="grid grid-cols-2 gap-4">
                            <x-ui.select label="{{ tr('Weekend Policy') }}" wire:model.defer="weekend_policy">
                                <option value="exclude">{{ tr('Auto exclude') }}</option>
                                <option value="include">{{ tr('Auto include') }}</option>
                                <option value="employee_choice">{{ tr('Employee choice') }}</option>
                            </x-ui.select>

                            <x-ui.select label="{{ tr('Duration Type') }}" wire:model.defer="duration_unit">
                                <option value="full_day">{{ tr('Full day only') }}</option>
                                <option value="half_day">{{ tr('Full day or half day') }}</option>
                                <option value="hours">{{ tr('By hours') }}</option>
                            </x-ui.select>
                        </div>

                        {{-- Collapsible More Options --}}
                         <div x-data="{ expanded: false }">
                            <button type="button" @click="expanded = !expanded" class="w-full flex items-center justify-between text-xs font-bold text-purple-600 hover:text-purple-800 py-2">
                                <span>{{ tr('Show More Constraints') }}</span>
                                <i class="fas" :class="expanded ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                            </button>
                            
                            <div x-show="expanded" x-collapse class="space-y-4 pt-2 border-t border-purple-100 mt-2">
                                <div class="grid grid-cols-2 gap-4">
                                     <x-ui.input type="number" min="0" label="{{ tr('Min notice (days)') }}" wire:model.defer="notice_min_days" />
                                     <x-ui.input type="number" min="0" label="{{ tr('Max advance (days)') }}" wire:model.defer="notice_max_advance_days" />
                                </div>
                                
                                <label class="flex items-center gap-2 text-xs font-semibold text-gray-700">
                                    <input type="checkbox" wire:model.defer="allow_retroactive" class="rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                                    <span>{{ tr('Allow retroactive requests') }}</span>
                                </label>
                                
                                <div class="grid grid-cols-2 gap-4">
                                    <x-ui.input type="text" label="{{ tr('Blackout From (MM-DD)') }}" wire:model.defer="blackout_from" placeholder="12-01" />
                                    <x-ui.input type="text" label="{{ tr('Blackout To (MM-DD)') }}" wire:model.defer="blackout_to" placeholder="12-31" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </x-slot:content>

        <x-slot:footer>
            <div class="flex items-center justify-end gap-3 w-full">
                <x-ui.secondary-button wire:click="closeCreate" class="!px-6 !rounded-xl">
                    {{ tr('Cancel') }}
                </x-ui.secondary-button>
                <x-ui.primary-button wire:click="saveCreate" class="!px-6 !rounded-xl shadow-lg">
                    <i class="fas fa-save me-2"></i>
                    {{ tr('Save Policy') }}
                </x-ui.primary-button>
            </div>
        </x-slot:footer>
    </x-ui.modal>

    {{-- Edit Modal --}}
    <x-ui.modal wire:model="editOpen" maxWidth="4xl">
        <x-slot:title>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center text-lg border border-blue-100 shadow-sm">
                    <i class="fas fa-edit"></i>
                </div>
                <div>
                    <h3 class="font-bold text-gray-900 text-lg leading-tight">{{ tr('Edit Leave Type') }}</h3>
                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">{{ tr('Update leave policy fields') }}</p>
                </div>
            </div>
        </x-slot:title>

        <x-slot:content>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 py-2">
                {{-- Left Column: Basic Info --}}
                <div class="space-y-4">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="w-1 h-3 bg-blue-500 rounded-full"></span>
                        <h4 class="text-xs font-black text-gray-700 uppercase tracking-wider">{{ tr('Basic Information') }}</h4>
                    </div>

                    <div class="bg-gray-50/50 p-4 rounded-3xl border border-gray-100 space-y-4">
                        <x-ui.input
                            label="{{ tr('Name') }}"
                            wire:model.defer="name"
                        />

                        <div class="grid grid-cols-2 gap-4">
                            <x-ui.input
                                label="{{ tr('Type') }}"
                                wire:model.defer="leave_type"
                            />
                            <x-ui.input
                                type="number" step="0.5" min="0"
                                label="{{ tr('Days per year') }}"
                                wire:model.defer="days_per_year"
                            />
                        </div>

                        <x-ui.select label="{{ tr('Gender') }}" wire:model.defer="gender">
                            <option value="all">{{ tr('All') }}</option>
                            <option value="male">{{ tr('Male') }}</option>
                            <option value="female">{{ tr('Female') }}</option>
                        </x-ui.select>

                        <x-ui.textarea
                            label="{{ tr('Description') }}"
                            wire:model.defer="description"
                            rows="2"
                        />

                        <div class="flex flex-wrap gap-4 pt-2">
                            <label class="flex items-center gap-2 text-sm font-bold text-gray-700 cursor-pointer">
                                <input type="checkbox" wire:model.defer="is_active" class="rounded-lg border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span>{{ tr('Active') }}</span>
                            </label>

                            <label class="flex items-center gap-2 text-sm font-bold text-gray-700 cursor-pointer">
                                <input type="checkbox" wire:model.defer="show_in_app" class="rounded-lg border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span>{{ tr('Show in App') }}</span>
                            </label>

                            <label class="flex items-center gap-2 text-sm font-bold text-gray-700 cursor-pointer">
                                <input type="checkbox" wire:model.defer="requires_attachment" class="rounded-lg border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span>{{ tr('Requires attachment') }}</span>
                            </label>
                        </div>
                    </div>
                </div>

                {{-- Right Column: Advanced Settings --}}
                <div class="space-y-4">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="w-1 h-3 bg-purple-500 rounded-full"></span>
                        <h4 class="text-xs font-black text-gray-700 uppercase tracking-wider">{{ tr('Advanced Rules') }}</h4>
                    </div>

                    <div class="bg-purple-50/20 p-4 rounded-3xl border border-purple-50 space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                             <x-ui.select label="{{ tr('Accrual method') }}" wire:model.defer="accrual_method">
                                <option value="annual_grant">{{ tr('Annual full grant') }}</option>
                                <option value="monthly">{{ tr('Monthly accrual') }}</option>
                                <option value="by_work_days">{{ tr('By actual work days') }}</option>
                            </x-ui.select>
                            
                            <x-ui.input type="number" step="0.1" min="0" label="{{ tr('Monthly rate') }}" wire:model.defer="monthly_accrual_rate" />
                        </div>
                        
                        <div class="grid grid-cols-3 gap-3">
                            <x-ui.input type="number" step="0.5" min="0" label="{{ tr('Max balance') }}" wire:model.defer="max_balance" />
                            <x-ui.input type="number" step="0.5" min="0" label="{{ tr('Carryover days') }}" wire:model.defer="carryover_days" />
                            <x-ui.input type="number" step="1" min="0" label="{{ tr('Expiry') }}" wire:model.defer="carryover_expire_months" />
                        </div>

                         <div class="grid grid-cols-2 gap-4">
                            <x-ui.select label="{{ tr('Weekend Policy') }}" wire:model.defer="weekend_policy">
                                <option value="exclude">{{ tr('Auto exclude') }}</option>
                                <option value="include">{{ tr('Auto include') }}</option>
                                <option value="employee_choice">{{ tr('Employee choice') }}</option>
                            </x-ui.select>

                            <x-ui.select label="{{ tr('Duration Type') }}" wire:model.defer="duration_unit">
                                <option value="full_day">{{ tr('Full day only') }}</option>
                                <option value="half_day">{{ tr('Full day or half day') }}</option>
                                <option value="hours">{{ tr('By hours') }}</option>
                            </x-ui.select>
                        </div>

                        {{-- Collapsible More Options --}}
                         <div x-data="{ expanded: false }">
                            <button type="button" @click="expanded = !expanded" class="w-full flex items-center justify-between text-xs font-bold text-purple-600 hover:text-purple-800 py-2">
                                <span>{{ tr('Show More Constraints') }}</span>
                                <i class="fas" :class="expanded ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                            </button>
                            
                            <div x-show="expanded" x-collapse class="space-y-4 pt-2 border-t border-purple-100 mt-2">
                                <div class="grid grid-cols-2 gap-4">
                                     <x-ui.input type="number" min="0" label="{{ tr('Min notice (days)') }}" wire:model.defer="notice_min_days" />
                                     <x-ui.input type="number" min="0" label="{{ tr('Max advance (days)') }}" wire:model.defer="notice_max_advance_days" />
                                </div>
                                
                                <label class="flex items-center gap-2 text-xs font-semibold text-gray-700">
                                    <input type="checkbox" wire:model.defer="allow_retroactive" class="rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                                    <span>{{ tr('Allow retroactive requests') }}</span>
                                </label>
                                
                                <div class="grid grid-cols-2 gap-4">
                                    <x-ui.input type="text" label="{{ tr('Blackout From (MM-DD)') }}" wire:model.defer="blackout_from" placeholder="12-01" />
                                    <x-ui.input type="text" label="{{ tr('Blackout To (MM-DD)') }}" wire:model.defer="blackout_to" placeholder="12-31" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </x-slot:content>

        <x-slot:footer>
            <div class="flex items-center justify-end gap-3 w-full">
                <x-ui.secondary-button wire:click="closeEdit" class="!px-6 !rounded-xl">
                    {{ tr('Cancel') }}
                </x-ui.secondary-button>
                <x-ui.primary-button wire:click="saveEdit" class="!px-6 !rounded-xl shadow-lg">
                    <i class="fas fa-save me-2"></i>
                    {{ tr('Update') }}
                </x-ui.primary-button>
            </div>
        </x-slot:footer>
    </x-ui.modal>

    {{-- Delete Confirm Modal --}}
    <x-ui.modal wire:model="deleteOpen" maxWidth="md">
        <x-slot:title>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-red-50 text-red-600 rounded-xl flex items-center justify-center text-lg border border-red-100 shadow-sm">
                    <i class="fas fa-trash-alt"></i>
                </div>
                <div>
                    <h3 class="font-bold text-gray-900 text-lg leading-tight">{{ tr('Delete') }}</h3>
                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">{{ tr('Confirm action') }}</p>
                </div>
            </div>
        </x-slot:title>

        <x-slot:content>
            <div class="py-6 text-center">
                <p class="text-sm text-gray-600 font-medium">{{ tr('Are you sure you want to delete this item?') }}</p>
                <p class="text-xs text-red-500 mt-2">{{ tr('This action cannot be undone.') }}</p>
            </div>
        </x-slot:content>

        <x-slot:footer>
            <div class="flex items-center justify-end gap-3 w-full">
                <x-ui.secondary-button wire:click="closeDelete" class="!px-6 !rounded-xl">
                    {{ tr('Cancel') }}
                </x-ui.secondary-button>

                <x-ui.primary-button
                    wire:click="deleteNow"
                    class="!bg-red-600 hover:!bg-red-700 !px-6 !rounded-xl shadow-lg shadow-red-200"
                >
                    <i class="fas fa-trash me-2"></i>
                    {{ tr('Delete') }}
                </x-ui.primary-button>
            </div>
        </x-slot:footer>
    </x-ui.modal>

    {{-- Manage Years Modal --}}
    <x-ui.modal wire:model="yearsOpen" maxWidth="2xl">
        <x-slot:title>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-brand-50 text-brand-600 rounded-xl flex items-center justify-center text-lg border border-brand-100 shadow-sm">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div>
                    <h3 class="font-bold text-gray-900 text-lg leading-tight">{{ tr('Manage Years') }}</h3>
                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">{{ tr('Add / activate / delete years') }}</p>
                </div>
            </div>
        </x-slot:title>

        <x-slot:content>
            <div class="py-2 space-y-6">
                {{-- Existing years list --}}
                <div class="space-y-3">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="w-1 h-3 bg-gray-500 rounded-full"></span>
                        <h4 class="text-xs font-black text-gray-700 uppercase tracking-wider">{{ tr('Existing Years') }}</h4>
                    </div>

                    <div class="bg-gray-50/50 rounded-3xl border border-gray-100 p-4 space-y-2 max-h-[200px] overflow-y-auto custom-scrollbar">
                        @foreach($years as $y)
                            <div class="flex items-center justify-between gap-3 bg-white rounded-2xl border border-gray-100 px-4 py-3 shadow-sm hover:shadow-md transition-all">
                                <div class="min-w-0">
                                    <div class="text-sm font-black text-gray-900 flex items-center gap-2">
                                        <span>{{ $y->year }}</span>
                                        @if($y->is_active)
                                            <span class="text-[10px] font-black px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100">
                                                {{ tr('Active') }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-[10px] text-gray-400 mt-1">
                                        <i class="fas fa-clock me-1 text-gray-300"></i>
                                        {{ optional($y->starts_on)->format('Y-m-d') ?? '—' }}
                                        <i class="fas fa-arrow-right mx-1 text-gray-300"></i>
                                        {{ optional($y->ends_on)->format('Y-m-d') ?? '—' }}
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    @if(!$y->is_active)
                                        <button 
                                            wire:click="setYearActive({{ (int)$y->id }})"
                                            class="w-8 h-8 rounded-xl bg-gray-50 text-gray-400 hover:bg-emerald-50 hover:text-emerald-600 border border-gray-100 transition-all flex items-center justify-center"
                                            title="{{ tr('Set Active') }}"
                                        >
                                            <i class="fas fa-bolt text-xs"></i>
                                        </button>
                                    @endif

                                    <button 
                                        wire:click="deleteYear({{ (int)$y->id }})"
                                        class="w-8 h-8 rounded-xl bg-gray-50 text-gray-400 hover:bg-red-50 hover:text-red-600 border border-gray-100 transition-all flex items-center justify-center"
                                        title="{{ tr('Delete') }}"
                                    >
                                        <i class="fas fa-trash text-xs"></i>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Add new year --}}
                <div class="space-y-4 pt-4 border-t border-gray-100">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="w-1 h-3 bg-brand-500 rounded-full"></span>
                        <h4 class="text-xs font-black text-gray-700 uppercase tracking-wider">{{ tr('Add New Year') }}</h4>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <x-ui.input 
                            type="number" min="2000" max="2100" 
                            label="{{ tr('Year') }}" 
                            wire:model.defer="newYear" 
                        />

                        <x-ui.select label="{{ tr('Copy from year (optional)') }}" wire:model.defer="copyFromYearId">
                             <option value="">{{ tr('Do not copy') }}</option>
                             @foreach($years as $y)
                                 <option value="{{ (int) $y->id }}">{{ $y->year }}</option>
                             @endforeach
                        </x-ui.select>

                        <x-ui.input type="date" label="{{ tr('Starts on') }}" wire:model.defer="newYearStartsOn" />
                        <x-ui.input type="date" label="{{ tr('Ends on') }}" wire:model.defer="newYearEndsOn" />

                        <div class="md:col-span-2">
                             <label class="flex items-center gap-2 text-sm font-bold text-gray-700 cursor-pointer">
                                <input type="checkbox" wire:model.defer="newYearActive" class="rounded-lg border-gray-300 text-brand-600 focus:ring-brand-500">
                                <span>{{ tr('Set as active year') }}</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </x-slot:content>

        <x-slot:footer>
            <div class="flex items-center justify-end gap-3 w-full">
                <x-ui.secondary-button wire:click="closeYears" class="!px-6 !rounded-xl">
                    {{ tr('Cancel') }}
                </x-ui.secondary-button>

                <x-ui.primary-button wire:click="saveYear" class="!px-6 !rounded-xl shadow-lg">
                    <i class="fas fa-save me-2"></i>
                    {{ tr('Save') }}
                </x-ui.primary-button>
            </div>
        </x-slot:footer>
    </x-ui.modal>

    {{-- Compare Years Modal --}}
    <x-ui.modal wire:model="compareOpen" maxWidth="4xl">
        <x-slot:title>
             <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center text-lg border border-indigo-100 shadow-sm">
                    <i class="fas fa-exchange-alt"></i>
                </div>
                <div>
                    <h3 class="font-bold text-gray-900 text-lg leading-tight">{{ tr('Compare Years') }}</h3>
                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">{{ tr('Compare leave policies between two years') }}</p>
                </div>
            </div>
        </x-slot:title>

        <x-slot:content>
            <div class="space-y-4 py-2">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                     <x-ui.select label="{{ tr('Year A') }}" wire:model="compareYearAId">
                        <option value="">{{ tr('Select year') }}</option>
                        @foreach($years as $y)
                            <option value="{{ (int)$y->id }}">{{ $y->year }}</option>
                        @endforeach
                    </x-ui.select>
                    
                    <x-ui.select label="{{ tr('Year B') }}" wire:model="compareYearBId">
                        <option value="">{{ tr('Select year') }}</option>
                        @foreach($years as $y)
                            <option value="{{ (int)$y->id }}">{{ $y->year }}</option>
                        @endforeach
                    </x-ui.select>
                </div>

                <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full text-start border-collapse table-fixed">
                            <thead>
                                <tr class="bg-gray-50/50 border-b border-gray-100">
                                    <th class="w-[20%] px-5 py-3 text-[10px] font-black text-gray-400 uppercase tracking-widest text-start">{{ tr('Leave') }}</th>
                                    <th class="w-[10%] px-5 py-3 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">{{ tr('Type') }}</th>
                                    <th class="w-[35%] px-5 py-3 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">{{ tr('Year A') }}</th>
                                    <th class="w-[35%] px-5 py-3 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">{{ tr('Year B') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @forelse($compareRows as $r)
                                    @php
                                        $a = $r['a'];
                                        $b = $r['b'];
                                        $different = false;
                                        if ($a && $b) {
                                            $different = (
                                                (string)$a->days_per_year !== (string)$b->days_per_year ||
                                                (bool)$a->is_active !== (bool)$b->is_active ||
                                                (bool)$a->show_in_app !== (bool)$b->show_in_app ||
                                                (bool)$a->requires_attachment !== (bool)$b->requires_attachment
                                            );
                                        } else {
                                            $different = true;
                                        }
                                    @endphp
                                    <tr class="{{ $different ? 'bg-amber-50/40' : 'hover:bg-gray-50/50' }} transition-colors">
                                        <td class="px-5 py-3 border-e border-gray-50">
                                            <div class="text-sm font-bold text-gray-900 truncate">{{ $r['name'] }}</div>
                                        </td>
                                        <td class="px-5 py-3 text-center border-e border-gray-50">
                                            <span class="text-[10px] font-black text-gray-600 uppercase">{{ $r['leave_type'] }}</span>
                                        </td>
                                        <td class="px-5 py-3 border-e border-gray-50">
                                            @if($a)
                                                <div class="text-xs text-gray-700 flex flex-wrap gap-2 justify-center">
                                                    <span class="px-2 py-1 bg-gray-100 rounded-md"><b>{{ tr('Days') }}:</b> {{ $a->days_per_year }}</span>
                                                    <span class="px-2 py-1 {{ $a->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' }} rounded-md"><b>{{ tr('Status') }}:</b> {{ $a->is_active ? tr('Active') : tr('Inactive') }}</span>
                                                    <span class="px-2 py-1 bg-gray-100 rounded-md"><b>{{ tr('App') }}:</b> {{ $a->show_in_app ? tr('Yes') : tr('No') }}</span>
                                                </div>
                                            @else
                                                <div class="text-xs text-gray-400 text-center italic">{{ tr('Not found') }}</div>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3">
                                            @if($b)
                                                <div class="text-xs text-gray-700 flex flex-wrap gap-2 justify-center">
                                                    <span class="px-2 py-1 bg-gray-100 rounded-md"><b>{{ tr('Days') }}:</b> {{ $b->days_per_year }}</span>
                                                    <span class="px-2 py-1 {{ $b->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' }} rounded-md"><b>{{ tr('Status') }}:</b> {{ $b->is_active ? tr('Active') : tr('Inactive') }}</span>
                                                    <span class="px-2 py-1 bg-gray-100 rounded-md"><b>{{ tr('App') }}:</b> {{ $b->show_in_app ? tr('Yes') : tr('No') }}</span>
                                                </div>
                                            @else
                                                <div class="text-xs text-gray-400 text-center italic">{{ tr('Not found') }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-5 py-10 text-center text-sm text-gray-400">
                                            <div class="flex flex-col items-center">
                                                <i class="fas fa-inbox text-2xl mb-2 text-gray-300"></i>
                                                {{ tr('No comparison data available') }}
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </x-slot:content>

        <x-slot:footer>
            <div class="flex items-center justify-end gap-3 w-full">
                <x-ui.secondary-button wire:click="closeCompare" class="!px-6 !rounded-xl">
                    {{ tr('Close') }}
                </x-ui.secondary-button>
            </div>
        </x-slot:footer>
    </x-ui.modal>

    {{-- Copy Policies Modal (from year or file) --}}
    <x-ui.modal wire:model="copyOpen" maxWidth="3xl">
        <x-slot:title>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-amber-50 text-amber-600 rounded-xl flex items-center justify-center text-lg border border-amber-100 shadow-sm">
                    <i class="fas fa-copy"></i>
                </div>
                <div>
                    <h3 class="font-bold text-gray-900 text-lg leading-tight">{{ tr('Copy Policies') }}</h3>
                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">{{ tr('Copy from another year or import from a file') }}</p>
                </div>
            </div>
        </x-slot:title>

        <x-slot:content>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 py-2">
                 <div class="md:col-span-2 space-y-4">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="w-1 h-3 bg-amber-500 rounded-full"></span>
                        <h4 class="text-xs font-black text-gray-700 uppercase tracking-wider">{{ tr('Copy Options') }}</h4>
                    </div>
                    
                    <div class="bg-amber-50/30 p-4 rounded-3xl border border-amber-100 space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                             <x-ui.select label="{{ tr('Source year') }}" wire:model.defer="copyPoliciesSourceYearId">
                                <option value="">{{ tr('Select year') }}</option>
                                @foreach($years as $y)
                                    <option value="{{ (int)$y->id }}">{{ $y->year }}</option>
                                @endforeach
                            </x-ui.select>
                            
                            <x-ui.select label="{{ tr('Destination year') }}" wire:model.defer="copyPoliciesDestYearId">
                                <option value="">{{ tr('Select year') }}</option>
                                @foreach($years as $y)
                                    <option value="{{ (int)$y->id }}">{{ $y->year }}</option>
                                @endforeach
                            </x-ui.select>
                        </div>
                        
                        <label class="flex items-center gap-2 text-sm font-bold text-gray-700 cursor-pointer">
                            <input type="checkbox" wire:model.defer="copyOverwrite" class="rounded-lg border-gray-300 text-amber-600 focus:ring-amber-500">
                            <span>{{ tr('Overwrite existing policies if they exist') }}</span>
                        </label>
                    </div>
                 </div>

                 <div class="md:col-span-2 pt-4 border-t border-gray-100 space-y-4">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="w-1 h-3 bg-gray-500 rounded-full"></span>
                        <h4 class="text-xs font-black text-gray-700 uppercase tracking-wider">{{ tr('Or Import from File') }}</h4>
                    </div>

                    <div class="bg-gray-50/50 p-4 rounded-3xl border border-gray-100">
                        <x-ui.input 
                            type="file" 
                            label="{{ tr('Import from file (JSON)') }}" 
                            wire:model="importFile" 
                            accept=".json,.txt"
                        />
                        <div class="text-[10px] text-gray-400 mt-2 flex items-center gap-2">
                            <i class="fas fa-info-circle"></i>
                            {{ tr('Tip: export policies first, then import the same file here.') }}
                        </div>
                    </div>
                 </div>
            </div>
        </x-slot:content>

        <x-slot:footer>
             <div class="flex items-center justify-end gap-2 w-full">
                <x-ui.secondary-button wire:click="closeCopyPolicies" class="!px-3 !py-1.5 !text-xs !rounded-lg">
                    {{ tr('Cancel') }}
                </x-ui.secondary-button>

                <div class="flex gap-2">
                    <x-ui.secondary-button wire:click="copyPoliciesNow" class="!px-3 !py-1.5 !text-xs !rounded-lg !bg-amber-50 !text-amber-700 border-amber-200">
                        <i class="fas fa-copy me-1"></i>
                        {{ tr('Copy') }}
                    </x-ui.secondary-button>

                    <x-ui.primary-button wire:click="importPoliciesFromFile" class="!px-3 !py-1.5 !text-xs !rounded-lg shadow-sm">
                        <i class="fas fa-file-import me-1"></i>
                        {{ tr('Import') }}
                    </x-ui.primary-button>
                </div>
            </div>
        </x-slot:footer>
    </x-ui.modal>  
</div> 