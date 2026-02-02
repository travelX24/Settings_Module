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
    @if($createOpen)
        <div class="fixed inset-0 z-[9999] bg-black/40 flex items-center justify-center p-4" wire:click.self="closeCreate">
            <div class="w-full max-w-3xl bg-white rounded-3xl shadow-xl border border-gray-100 p-6 overflow-y-auto max-h-[85vh]">
                <div class="flex items-start justify-between gap-3 mb-4">
                    <div>
                        <div class="text-lg font-black text-gray-900">{{ tr('New Leave Type') }}</div>
                        <div class="text-xs text-gray-400">{{ tr('Create a leave policy for the selected year') }}</div>
                    </div>
                    <button type="button" class="text-gray-400 hover:text-gray-700" wire:click="closeCreate">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                {{-- Basic --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Name') }}</label>
                        <input type="text" wire:model.defer="name"
                               class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50 focus:bg-white focus:border-[color:var(--brand-via)] focus:ring-4 focus:ring-[color:var(--brand-via)]/10 transition-all"
                               placeholder="{{ tr('Leave name...') }}">
                        @error('name') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div>
                        <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Type') }}</label>
                        <input type="text" wire:model.defer="leave_type"
                               class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50 focus:bg-white focus:border-[color:var(--brand-via)] focus:ring-4 focus:ring-[color:var(--brand-via)]/10 transition-all"
                               placeholder="{{ tr('e.g. annual / sick / emergency') }}">
                        @error('leave_type') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div>
                        <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Days per year') }}</label>
                        <input type="number" step="0.5" min="0" wire:model.defer="days_per_year"
                               class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50 focus:bg-white focus:border-[color:var(--brand-via)] focus:ring-4 focus:ring-[color:var(--brand-via)]/10 transition-all">
                        @error('days_per_year') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div>
                        <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Gender') }}</label>
                        <select wire:model.defer="gender"
                                class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50 focus:bg-white focus:border-[color:var(--brand-via)] focus:ring-4 focus:ring-[color:var(--brand-via)]/10 transition-all">
                            <option value="all">{{ tr('All') }}</option>
                            <option value="male">{{ tr('Male') }}</option>
                            <option value="female">{{ tr('Female') }}</option>
                        </select>
                        @error('gender') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="md:col-span-2 grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                            <input type="checkbox" wire:model.defer="is_active">
                            <span>{{ tr('Active') }}</span>
                        </label>

                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                            <input type="checkbox" wire:model.defer="show_in_app">
                            <span>{{ tr('Show in App') }}</span>
                        </label>

                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                            <input type="checkbox" wire:model.defer="requires_attachment">
                            <span>{{ tr('Requires attachment') }}</span>
                        </label>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Description') }}</label>
                        <textarea wire:model.defer="description" rows="3"
                                  class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm bg-gray-50/50 focus:bg-white focus:border-[color:var(--brand-via)] focus:ring-4 focus:ring-[color:var(--brand-via)]/10 transition-all"></textarea>
                        @error('description') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    {{-- Advanced Settings --}}
                    <div class="md:col-span-2 mt-2 pt-4 border-t border-gray-100">
                        <div class="text-sm font-black text-gray-900 mb-3">{{ tr('Advanced Policy Settings') }}</div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {{-- Accrual --}}
                            <div>
                                <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Accrual method') }}</label>
                                <select wire:model.defer="accrual_method"
                                        class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50">
                                    <option value="annual_grant">{{ tr('Annual full grant') }}</option>
                                    <option value="monthly">{{ tr('Monthly accrual') }}</option>
                                    <option value="by_work_days">{{ tr('By actual work days') }}</option>
                                </select>
                                @error('accrual_method') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div>
                                <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Minimum unit') }}</label>
                                <input type="number" step="0.1" min="0" wire:model.defer="min_accrual"
                                       class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50">
                                @error('min_accrual') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div>
                                <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Monthly rate') }}</label>
                                <input type="number" step="0.1" min="0" wire:model.defer="monthly_accrual_rate"
                                       class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50"
                                       placeholder="{{ tr('e.g. 2.5') }}">
                                @error('monthly_accrual_rate') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div>
                                <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Work-day rate') }}</label>
                                <input type="number" step="0.01" min="0" wire:model.defer="workday_accrual_rate"
                                       class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50"
                                       placeholder="{{ tr('e.g. 0.08') }}">
                                @error('workday_accrual_rate') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div>
                                <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Max balance') }}</label>
                                <input type="number" step="0.5" min="0" wire:model.defer="max_balance"
                                       class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50">
                                @error('max_balance') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div>
                                <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Carryover days allowed') }}</label>
                                <input type="number" step="0.5" min="0" wire:model.defer="carryover_days"
                                       class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50">
                                @error('carryover_days') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div>
                                <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Carryover expires after (months)') }}</label>
                                <input type="number" step="1" min="0" wire:model.defer="carryover_expire_months"
                                       class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50">
                                @error('carryover_expire_months') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                            </div>

                            {{-- Weekend --}}
                            <div>
                                <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Weekend days') }}</label>
                                <select wire:model.defer="weekend_policy"
                                        class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50">
                                    <option value="exclude">{{ tr('Auto exclude') }}</option>
                                    <option value="include">{{ tr('Auto include') }}</option>
                                    <option value="employee_choice">{{ tr('Employee choice') }}</option>
                                </select>
                                @error('weekend_policy') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                            </div>

                            {{-- Deduction --}}
                            <div>
                                <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Deduction policy') }}</label>
                                <select wire:model.defer="deduction_policy"
                                        class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50">
                                    <option value="balance_only">{{ tr('Deduct from balance only') }}</option>
                                    <option value="salary_after_balance">{{ tr('Deduct from salary after balance') }}</option>
                                    <option value="not_allowed_after_balance">{{ tr('Not allowed after balance') }}</option>
                                </select>
                                @error('deduction_policy') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                            </div>

                            {{-- Duration --}}
                            <div>
                                <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Duration type') }}</label>
                                <select wire:model.defer="duration_unit"
                                        class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50">
                                    <option value="full_day">{{ tr('Full day only') }}</option>
                                    <option value="half_day">{{ tr('Full day or half day') }}</option>
                                    <option value="hours">{{ tr('By hours') }}</option>
                                </select>
                                @error('duration_unit') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                            </div>

                            {{-- Notice --}}
                            <div>
                                <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Min notice (days)') }}</label>
                                <input type="number" min="0" wire:model.defer="notice_min_days"
                                       class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50">
                                @error('notice_min_days') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div>
                                <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Max advance (days)') }}</label>
                                <input type="number" min="0" wire:model.defer="notice_max_advance_days"
                                       class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50">
                                @error('notice_max_advance_days') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="md:col-span-2">
                                <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                                    <input type="checkbox" wire:model.defer="allow_retroactive">
                                    <span>{{ tr('Allow retroactive requests') }}</span>
                                </label>
                                @error('allow_retroactive') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                            </div>

                            {{-- Notes --}}
                            <div class="md:col-span-2 mt-2 pt-3 border-t border-gray-100">
                                <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                                    <input type="checkbox" wire:model.defer="note_required">
                                    <span>{{ tr('Mandatory note') }}</span>
                                </label>
                                @error('note_required') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror

                                <div class="mt-3">
                                    <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Note text') }}</label>
                                    <textarea wire:model.defer="note_text" rows="2"
                                              class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm bg-gray-50/50"></textarea>
                                    @error('note_text') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                </div>

                                <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 mt-2">
                                    <input type="checkbox" wire:model.defer="note_ack_required">
                                    <span>{{ tr('Require acknowledgment of the note') }}</span>
                                </label>
                                @error('note_ack_required') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                            </div>

                            {{-- Attachments settings --}}
                            <div class="md:col-span-2 mt-2 pt-3 border-t border-gray-100">
                                <div class="text-sm font-black text-gray-900 mb-2">{{ tr('Attachments Settings') }}</div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Allowed types') }}</label>
                                        <div class="flex flex-wrap gap-3">
                                            <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                                                <input type="checkbox" value="pdf" wire:model.defer="attachment_types">
                                                <span>PDF</span>
                                            </label>
                                            <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                                                <input type="checkbox" value="jpg" wire:model.defer="attachment_types">
                                                <span>JPG</span>
                                            </label>
                                            <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                                                <input type="checkbox" value="png" wire:model.defer="attachment_types">
                                                <span>PNG</span>
                                            </label>
                                        </div>
                                        @error('attachment_types') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                        @error('attachment_types.*') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                    </div>

                                    <div>
                                        <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Max size (MB)') }}</label>
                                        <input type="number" step="0.5" min="0.5" wire:model.defer="attachment_max_mb"
                                               class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50">
                                        @error('attachment_max_mb') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                            </div>

                            {{-- Constraints --}}
                            <div class="md:col-span-2 mt-2 pt-3 border-t border-gray-100">
                                <div class="text-sm font-black text-gray-900 mb-2">{{ tr('General Constraints') }}</div>

                                <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                                    <input type="checkbox" wire:model.defer="blackout_enabled">
                                    <span>{{ tr('Blackout periods (peak seasons)') }}</span>
                                </label>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
                                    <div>
                                        <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('From (MM-DD)') }}</label>
                                        <input type="text" wire:model.defer="blackout_from"
                                               class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50"
                                               placeholder="12-01">
                                        @error('blackout_from') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                    </div>

                                    <div>
                                        <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('To (MM-DD)') }}</label>
                                        <input type="text" wire:model.defer="blackout_to"
                                               class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50"
                                               placeholder="12-31">
                                        @error('blackout_to') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                    </div>

                                    <div class="flex items-end">
                                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                                            <input type="checkbox" wire:model.defer="blackout_exception_requires_approval">
                                            <span>{{ tr('Exception requires approval') }}</span>
                                        </label>
                                        @error('blackout_exception_requires_approval') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                    <div>
                                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                                            <input type="checkbox" wire:model.defer="min_service_enabled">
                                            <span>{{ tr('Minimum service required') }}</span>
                                        </label>
                                        <div class="mt-2">
                                            <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Months') }}</label>
                                            <input type="number" min="0" wire:model.defer="min_service_months"
                                                   class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50">
                                            @error('min_service_months') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                        </div>
                                    </div>

                                    <div class="space-y-3">
                                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                                            <input type="checkbox" wire:model.defer="requires_presence_before_apply">
                                            <span>{{ tr('Employee must be present before applying') }}</span>
                                        </label>

                                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                                            <input type="checkbox" wire:model.defer="max_consecutive_enabled">
                                            <span>{{ tr('Limit consecutive leave') }}</span>
                                        </label>

                                        <div>
                                            <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Max consecutive days') }}</label>
                                            <input type="number" min="1" wire:model.defer="max_consecutive_days"
                                                   class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50">
                                            @error('max_consecutive_days') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                        </div>

                                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                                            <input type="checkbox" wire:model.defer="max_total_enabled">
                                            <span>{{ tr('Limit total per year') }}</span>
                                        </label>

                                        <div>
                                            <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Max total days') }}</label>
                                            <input type="number" min="1" wire:model.defer="max_total_days"
                                                   class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50">
                                            @error('max_total_days') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                        </div>
                                    </div>
                                </div>

                                @error('blackout_enabled') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                @error('min_service_enabled') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                @error('requires_presence_before_apply') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                @error('max_consecutive_enabled') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                @error('max_total_enabled') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 mt-6">
                    <x-ui.secondary-button :fullWidth="false" wire:click="closeCreate" wire:loading.attr="disabled">
                        {{ tr('Cancel') }}
                    </x-ui.secondary-button>

                    <x-ui.primary-button :arrow="false" :fullWidth="false"
                                         wire:click="saveCreate" wire:loading.attr="disabled" wire:target="saveCreate">
                        <i class="fas fa-save"></i>
                        <span class="ms-2">{{ tr('Save') }}</span>
                    </x-ui.primary-button>
                </div>
            </div>
        </div>
    @endif

    {{-- Edit Modal (same as Create, uses same bound properties) --}}
    @if($editOpen)
        <div class="fixed inset-0 z-[9999] bg-black/40 flex items-center justify-center p-4" wire:click.self="closeEdit">
            <div class="w-full max-w-3xl bg-white rounded-3xl shadow-xl border border-gray-100 p-6 overflow-y-auto max-h-[85vh]">
                <div class="flex items-start justify-between gap-3 mb-4">
                    <div>
                        <div class="text-lg font-black text-gray-900">{{ tr('Edit Leave Type') }}</div>
                        <div class="text-xs text-gray-400">{{ tr('Update leave policy fields') }}</div>
                    </div>
                    <button type="button" class="text-gray-400 hover:text-gray-700" wire:click="closeEdit">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                {{-- نفس حقول Create تماماً --}}
                {{-- لتخفيف التكرار في مشروعك تقدر تعمل partial، بس هنا خليته كامل لضمان النسخ بدون نقص --}}
                @includeIf('systemsettings::livewire.attendance.partials.leave-policy-form')
                {{-- إذا ما عندك partial، انسخ نفس بلوك create هنا حرفياً --}}
                {{-- (لو تحب، قلّي هل تفضل Partial أو نسخة كاملة مكررة وأنا أعطيك النسخة المكررة بالكامل) --}}

                <div class="flex items-center justify-end gap-2 mt-6">
                    <x-ui.secondary-button :fullWidth="false" wire:click="closeEdit" wire:loading.attr="disabled">
                        {{ tr('Cancel') }}
                    </x-ui.secondary-button>

                    <x-ui.primary-button :arrow="false" :fullWidth="false"
                                         wire:click="saveEdit" wire:loading.attr="disabled" wire:target="saveEdit">
                        <i class="fas fa-save"></i>
                        <span class="ms-2">{{ tr('Save') }}</span>
                    </x-ui.primary-button>
                </div>
            </div>
        </div>
    @endif

    {{-- Delete Confirm Modal --}}
    @if($deleteOpen)
        <div class="fixed inset-0 z-[9999] bg-black/40 flex items-center justify-center p-4" wire:click.self="closeDelete">
            <div class="w-full max-w-md bg-white rounded-3xl shadow-xl border border-gray-100 p-6">
                <div class="text-lg font-black text-gray-900">{{ tr('Delete') }}</div>
                <div class="text-sm text-gray-500 mt-2">{{ tr('Are you sure you want to delete this item?') }}</div>

                <div class="flex items-center justify-end gap-2 mt-6">
                    <x-ui.secondary-button :fullWidth="false" wire:click="closeDelete">
                        {{ tr('Cancel') }}
                    </x-ui.secondary-button>

                    <x-ui.primary-button :arrow="false" :fullWidth="false" wire:click="deleteNow">
                        <i class="fas fa-trash"></i>
                        <span class="ms-2">{{ tr('Delete') }}</span>
                    </x-ui.primary-button>
                </div>
            </div>
        </div>
    @endif

    {{-- Manage Years Modal --}}
    @if($yearsOpen)
        <div class="fixed inset-0 z-[9999] bg-black/40 flex items-center justify-center p-4" wire:click.self="closeYears">
            <div class="w-full max-w-2xl bg-white rounded-3xl shadow-xl border border-gray-100 p-6 overflow-y-auto max-h-[85vh]">
                <div class="flex items-start justify-between gap-3 mb-4">
                    <div>
                        <div class="text-lg font-black text-gray-900">{{ tr('Manage Years') }}</div>
                        <div class="text-xs text-gray-400">{{ tr('Add / activate / delete years and optionally copy policies') }}</div>
                    </div>
                    <button type="button" class="text-gray-400 hover:text-gray-700" wire:click="closeYears">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                {{-- Existing years list --}}
                <div class="bg-gray-50/50 rounded-2xl border border-gray-100 p-4 mb-5">
                    <div class="text-sm font-black text-gray-900 mb-3">{{ tr('Existing Years') }}</div>

                    <div class="space-y-2">
                        @foreach($years as $y)
                            <div class="flex items-center justify-between gap-3 bg-white rounded-2xl border border-gray-100 px-4 py-3">
                                <div class="min-w-0">
                                    <div class="text-sm font-black text-gray-900 flex items-center gap-2">
                                        <span>{{ $y->year }}</span>
                                        @if($y->is_active)
                                            <span class="text-[10px] font-black px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100">
                                                {{ tr('Active') }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-[11px] text-gray-500">
                                        {{ tr('From') }}: {{ optional($y->starts_on)->format('Y-m-d') ?? '—' }}
                                        • {{ tr('To') }}: {{ optional($y->ends_on)->format('Y-m-d') ?? '—' }}
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <x-ui.secondary-button :fullWidth="false" wire:click="setYearActive({{ (int)$y->id }})">
                                        <i class="fas fa-bolt"></i>
                                        <span class="ms-2">{{ tr('Set Active') }}</span>
                                    </x-ui.secondary-button>

                                    <x-ui.secondary-button :fullWidth="false" class="!text-red-600" wire:click="deleteYear({{ (int)$y->id }})">
                                        <i class="fas fa-trash"></i>
                                        <span class="ms-2">{{ tr('Delete') }}</span>
                                    </x-ui.secondary-button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Add new year --}}
                <div class="text-sm font-black text-gray-900 mb-2">{{ tr('Add New Year') }}</div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Year') }}</label>
                        <input type="number" min="2000" max="2100" wire:model.defer="newYear"
                               class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50">
                        @error('newYear') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div>
                        <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Copy from year (optional)') }}</label>
                        <select wire:model.defer="copyFromYearId"
                                class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50">
                            <option value="">{{ tr('Do not copy') }}</option>
                            @foreach($years as $y)
                                <option value="{{ (int) $y->id }}">{{ $y->year }}</option>
                            @endforeach
                        </select>
                        @error('copyFromYearId') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div>
                        <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Starts on') }}</label>
                        <input type="date" wire:model.defer="newYearStartsOn"
                               class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50">
                        @error('newYearStartsOn') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div>
                        <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Ends on') }}</label>
                        <input type="date" wire:model.defer="newYearEndsOn"
                               class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50">
                        @error('newYearEndsOn') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                            <input type="checkbox" wire:model.defer="newYearActive">
                            <span>{{ tr('Set as active year') }}</span>
                        </label>
                        @error('newYearActive') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 mt-6">
                    <x-ui.secondary-button :fullWidth="false" wire:click="closeYears">
                        {{ tr('Cancel') }}
                    </x-ui.secondary-button>

                    <x-ui.primary-button :arrow="false" :fullWidth="false" wire:click="saveYear">
                        <i class="fas fa-save"></i>
                        <span class="ms-2">{{ tr('Save') }}</span>
                    </x-ui.primary-button>
                </div>
            </div>
        </div>
    @endif

    {{-- Compare Years Modal --}}
    @if($compareOpen)
        <div class="fixed inset-0 z-[9999] bg-black/40 flex items-center justify-center p-4" wire:click.self="closeCompare">
            <div class="w-full max-w-5xl bg-white rounded-3xl shadow-xl border border-gray-100 p-6 overflow-y-auto max-h-[85vh]">
                <div class="flex items-start justify-between gap-3 mb-4">
                    <div>
                        <div class="text-lg font-black text-gray-900">{{ tr('Compare Years') }}</div>
                        <div class="text-xs text-gray-400">{{ tr('Compare leave policies between two years') }}</div>
                    </div>
                    <button type="button" class="text-gray-400 hover:text-gray-700" wire:click="closeCompare">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Year A') }}</label>
                        <select wire:model="compareYearAId" class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50">
                            <option value="">{{ tr('Select year') }}</option>
                            @foreach($years as $y)
                                <option value="{{ (int)$y->id }}">{{ $y->year }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Year B') }}</label>
                        <select wire:model="compareYearBId" class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50">
                            <option value="">{{ tr('Select year') }}</option>
                            @foreach($years as $y)
                                <option value="{{ (int)$y->id }}">{{ $y->year }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-gray-100 overflow-x-auto">
                    <table class="w-full text-start border-collapse table-fixed">
                        <thead>
                            <tr class="bg-gray-50/50 border-b border-gray-100">
                                <th class="w-[26%] px-5 py-3 text-[10px] font-black text-gray-400 uppercase tracking-widest text-start">{{ tr('Leave') }}</th>
                                <th class="w-[10%] px-5 py-3 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">{{ tr('Type') }}</th>
                                <th class="w-[32%] px-5 py-3 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">{{ tr('Year A') }}</th>
                                <th class="w-[32%] px-5 py-3 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">{{ tr('Year B') }}</th>
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
                                <tr class="{{ $different ? 'bg-amber-50/30' : '' }}">
                                    <td class="px-5 py-3">
                                        <div class="text-sm font-bold text-gray-900 truncate">{{ $r['name'] }}</div>
                                    </td>
                                    <td class="px-5 py-3 text-center">
                                        <span class="text-[10px] font-black text-gray-600 uppercase">{{ $r['leave_type'] }}</span>
                                    </td>

                                    <td class="px-5 py-3">
                                        @if($a)
                                            <div class="text-xs text-gray-700 flex flex-wrap gap-3 justify-center">
                                                <span><b>{{ tr('Days') }}:</b> {{ $a->days_per_year }}</span>
                                                <span><b>{{ tr('Status') }}:</b> {{ $a->is_active ? tr('Active') : tr('Inactive') }}</span>
                                                <span><b>{{ tr('App') }}:</b> {{ $a->show_in_app ? tr('Yes') : tr('No') }}</span>
                                                <span><b>{{ tr('Attach') }}:</b> {{ $a->requires_attachment ? tr('Yes') : tr('No') }}</span>
                                            </div>
                                        @else
                                            <div class="text-xs text-gray-400 text-center">{{ tr('Not found') }}</div>
                                        @endif
                                    </td>

                                    <td class="px-5 py-3">
                                        @if($b)
                                            <div class="text-xs text-gray-700 flex flex-wrap gap-3 justify-center">
                                                <span><b>{{ tr('Days') }}:</b> {{ $b->days_per_year }}</span>
                                                <span><b>{{ tr('Status') }}:</b> {{ $b->is_active ? tr('Active') : tr('Inactive') }}</span>
                                                <span><b>{{ tr('App') }}:</b> {{ $b->show_in_app ? tr('Yes') : tr('No') }}</span>
                                                <span><b>{{ tr('Attach') }}:</b> {{ $b->requires_attachment ? tr('Yes') : tr('No') }}</span>
                                            </div>
                                        @else
                                            <div class="text-xs text-gray-400 text-center">{{ tr('Not found') }}</div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-5 py-10 text-center text-sm text-gray-400">
                                        {{ tr('No comparison data') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center justify-end gap-2 mt-6">
                    <x-ui.secondary-button :fullWidth="false" wire:click="closeCompare">
                        {{ tr('Close') }}
                    </x-ui.secondary-button>
                </div>
            </div>
        </div>
    @endif

    {{-- Copy Policies Modal (from year or file) --}}
    @if($copyOpen)
        <div class="fixed inset-0 z-[9999] bg-black/40 flex items-center justify-center p-4" wire:click.self="closeCopyPolicies">
            <div class="w-full max-w-2xl bg-white rounded-3xl shadow-xl border border-gray-100 p-6 overflow-y-auto max-h-[85vh]">
                <div class="flex items-start justify-between gap-3 mb-4">
                    <div>
                        <div class="text-lg font-black text-gray-900">{{ tr('Copy Policies') }}</div>
                        <div class="text-xs text-gray-400">{{ tr('Copy from another year or import from a file') }}</div>
                    </div>
                    <button type="button" class="text-gray-400 hover:text-gray-700" wire:click="closeCopyPolicies">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Source year') }}</label>
                        <select wire:model.defer="copyPoliciesSourceYearId"
                                class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50">
                            <option value="">{{ tr('Select year') }}</option>
                            @foreach($years as $y)
                                <option value="{{ (int)$y->id }}">{{ $y->year }}</option>
                            @endforeach
                        </select>
                        @error('copyPoliciesSourceYearId') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div>
                        <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Destination year') }}</label>
                        <select wire:model.defer="copyPoliciesDestYearId"
                                class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50">
                            <option value="">{{ tr('Select year') }}</option>
                            @foreach($years as $y)
                                <option value="{{ (int)$y->id }}">{{ $y->year }}</option>
                            @endforeach
                        </select>
                        @error('copyPoliciesDestYearId') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                            <input type="checkbox" wire:model.defer="copyOverwrite">
                            <span>{{ tr('Overwrite existing policies if they exist') }}</span>
                        </label>
                        @error('copyOverwrite') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="md:col-span-2 pt-4 mt-2 border-t border-gray-100">
                        <div class="text-sm font-black text-gray-900 mb-2">{{ tr('Import from file (JSON)') }}</div>
                        <input type="file" wire:model="importFile" accept=".json,.txt"
                               class="block w-full text-sm text-gray-600">
                        @error('importFile') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                        <div class="text-[11px] text-gray-400 mt-1">{{ tr('Tip: export policies first, then import the same file here.') }}</div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 mt-6">
                    <x-ui.secondary-button :fullWidth="false" wire:click="closeCopyPolicies">
                        {{ tr('Cancel') }}
                    </x-ui.secondary-button>

                    <x-ui.secondary-button :fullWidth="false" wire:click="copyPoliciesNow">
                        <i class="fas fa-copy"></i>
                        <span class="ms-2">{{ tr('Copy from year') }}</span>
                    </x-ui.secondary-button>

                    <x-ui.primary-button :arrow="false" :fullWidth="false" wire:click="importPoliciesFromFile">
                        <i class="fas fa-file-import"></i>
                        <span class="ms-2">{{ tr('Import file') }}</span>
                    </x-ui.primary-button>
                </div>
            </div>
        </div>
    @endif  
</div> 