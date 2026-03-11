@php
    $locale = app()->getLocale();
    $isRtl  = in_array(substr($locale, 0, 2), ['ar','fa','ur','he']);
    $selectedYear = $years->firstWhere('id', $selectedYearId);

    $contractList = [
        'permanent'  => tr('Permanent'),
        'temporary'  => tr('Temporary'),
        'probation'  => tr('Probation'),
        'contractor' => tr('Contractor'),
        'training'   => tr('Training'),
        'freelancer_remote' => tr('Freelancer (Remote)'),
    ];
@endphp

@section('topbar-left-content')
    <div class="flex flex-col gap-3 {{ $isRtl ? 'items-end text-right' : 'items-start text-left' }}">
        <x-ui.page-header
            :title="tr('Leave Settings')"
            :subtitle="tr('Define leave types, yearly policies, and rules')"
            class="!flex-col {{ $isRtl ? '!items-end !text-right' : '!items-start !text-left' }} !justify-start !gap-1"
            titleSize="xl"
        />

        {{-- ✅ Tabs: Leave Settings | Permission Settings --}}
        <div class="inline-flex rounded-2xl border border-gray-200 bg-white/70 p-1 shadow-sm">
         <a
                href="{{ request()->fullUrlWithQuery(['tab' => 'leaves']) }}"
                class="px-4 py-2 text-xs font-black rounded-xl transition-all
                    {{ ($tab ?? 'leaves') === 'leaves'
                        ? 'text-white shadow-sm bg-[color:var(--brand-via)]'
                        : 'text-gray-600 hover:text-gray-900' }}"
            >
                {{ tr('Leave Settings') }}
            </a>

            <a
                href="{{ request()->fullUrlWithQuery(['tab' => 'permissions']) }}"
                class="px-4 py-2 text-xs font-black rounded-xl transition-all
                    {{ ($tab ?? 'leaves') === 'permissions'
                        ? 'text-white shadow-sm bg-[color:var(--brand-via)]'
                        : 'text-gray-600 hover:text-gray-900' }}"
            >
                {{ tr('Permission Settings') }}
            </a>

        </div>
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
    @if(($tab ?? 'leaves') === 'leaves')
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
                <div class="xl:col-span-8 {{ $isRtl ? 'xl:order-2' : '' }}">
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
                <div class="xl:col-span-4 min-w-0 {{ $isRtl ? 'xl:order-1' : '' }}">
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

                        @php
                            $isAnnualDefault = (string) data_get($row->settings ?? [], 'meta.system_key', '') === 'annual_default'
                                || trim((string) $row->name) === 'سنوية';
                        @endphp

                        <td class="px-6 py-4 text-end">
                            @can('settings.attendance.manage')
                            <x-ui.actions-menu>
                                <x-ui.dropdown-item wire:click="openEdit({{ (int) $row->id }})">
                                    <i class="fas fa-edit me-2 text-blue-500"></i> {{ tr('Edit') }}
                                </x-ui.dropdown-item>

                                @if(! $isAnnualDefault)
                                    <x-ui.dropdown-item danger wire:click="confirmDelete({{ (int) $row->id }})">
                                        <i class="fas fa-trash-alt me-2 text-red-500"></i> {{ tr('Delete') }}
                                    </x-ui.dropdown-item>
                                @endif
                            </x-ui.actions-menu>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-6 py-24 text-center">
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
            {{-- ========================= --}}
            {{-- Section 1: Basic Information --}}
            {{-- ========================= --}}
            <div class="mt-2 pt-4 border-t border-gray-100">
                <div class="text-sm font-black text-gray-900 mb-3">{{ tr('Basic Information') }}</div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Name') }}</label>
                        <input type="text" wire:model.defer="name"
                            class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50 focus:bg-white focus:border-[color:var(--brand-via)] focus:ring-4 focus:ring-[color:var(--brand-via)]/10 transition-all"
                            placeholder="{{ tr('Leave name...') }}"
                            @cannot('settings.attendance.manage') disabled @endcannot>
                        @error('name') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    {{-- ✅ Days per year (for non-annual policies create) --}}
                    <div class="md:col-span-2">
                            <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Description') }}</label>
                            <input type="text" wire:model.defer="description"
                                class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50 focus:bg-white focus:border-[color:var(--brand-via)] focus:ring-4 focus:ring-[color:var(--brand-via)]/10 transition-all"
                                placeholder="{{ tr('Optional description...') }}"
                                @cannot('settings.attendance.manage') disabled @endcannot>
                            @error('description') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                        </div>

                        {{-- ✅ Days per year (moved under Description) --}}
                        <div>
                            <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Days per year') }}</label>
                            <input type="number" step="0.5" min="0" wire:model.defer="days_per_year"
                                class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50 focus:bg-white focus:border-[color:var(--brand-via)] focus:ring-4 focus:ring-[color:var(--brand-via)]/10 transition-all"
                                @cannot('settings.attendance.manage') disabled @endcannot>
                            @error('days_per_year') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div>
                            <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Gender') }}</label>
                            <select wire:model.defer="gender"
                                    class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50 focus:bg-white focus:border-[color:var(--brand-via)] focus:ring-4 focus:ring-[color:var(--brand-via)]/10 transition-all"
                                    @cannot('settings.attendance.manage') disabled @endcannot>
                                <option value="all">{{ tr('All') }}</option>
                                <option value="male">{{ tr('Male') }}</option>
                                <option value="female">{{ tr('Female') }}</option>
                            </select>
                            @error('gender') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                        </div>


                    <div class="md:col-span-2 grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                            <input type="checkbox" wire:model.defer="is_active" @cannot('settings.attendance.manage') disabled @endcannot>
                            <span>{{ tr('Active') }}</span>
                        </label>

                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                            <input type="checkbox" wire:model.defer="show_in_app" @cannot('settings.attendance.manage') disabled @endcannot>
                            <span>{{ tr('Show in App') }}</span>
                        </label>

                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                            <input type="checkbox" wire:model.defer="requires_attachment" @cannot('settings.attendance.manage') disabled @endcannot>
                            <span>{{ tr('Requires attachment') }}</span>
                        </label>
                    </div>
                </div>
            </div>

            {{-- ========================= --}}
            {{-- Section 2: Additional Settings --}}
            {{-- ========================= --}}
            <div class="mt-2 pt-4 border-t border-gray-100">
                <div class="text-sm font-black text-gray-900 mb-3">{{ tr('Additional Settings') }}</div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Deduction --}}
                    <div>
                        <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Deduction policy') }}</label>
                       <select wire:model.defer="deduction_policy"
                                class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50"
                                @cannot('settings.attendance.manage') disabled @endcannot>
                            <option value="balance_only">{{ tr('Deduct from balance only') }}</option>
                            <option value="salary_after_balance">{{ tr('Deduct from salary after balance') }}</option>
                        </select>

                        @error('deduction_policy') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    {{-- Duration --}}
                    <div>
                        <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Duration type') }}</label>
                       <select wire:model.defer="duration_unit"
                            class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50"
                            @cannot('settings.attendance.manage') disabled @endcannot>
                        <option value="full_day">{{ tr('Full day only') }}</option>
                        <option value="half_day">{{ tr('Full day or half day') }}</option>
                    </select>

                        @error('duration_unit') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    {{-- Notice --}}
                    <div>
                        <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Min notice (days)') }}</label>
                        <input type="number" min="0" wire:model.defer="notice_min_days"
                            class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50"
                            @cannot('settings.attendance.manage') disabled @endcannot>
                        @error('notice_min_days') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div>
                        <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Max advance (days)') }}</label>
                        <input type="number" min="0" wire:model.defer="notice_max_advance_days"
                            class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50"
                            @cannot('settings.attendance.manage') disabled @endcannot>
                        @error('notice_max_advance_days') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                            <input type="checkbox" wire:model.defer="allow_retroactive" @cannot('settings.attendance.manage') disabled @endcannot>
                            <span>{{ tr('Allow retroactive requests') }}</span>
                        </label>
                        @error('allow_retroactive') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    {{-- Contract Exclusions --}}
                    <div class="md:col-span-2 pt-3 border-t border-gray-100">
                        <div class="text-sm font-black text-gray-900 mb-2">{{ tr('Contract Type Exclusions') }}</div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                            @foreach($contractList as $value => $label)
                                <label class="flex items-center gap-2 p-2 bg-gray-50/50 rounded-xl border border-gray-100 cursor-pointer hover:bg-gray-100 transition-all select-none">
                                    <input type="checkbox" 
                                           value="{{ $value }}" 
                                           wire:model.defer="selected_leave_excluded_contract_types"
                                           class="w-4 h-4 text-[color:var(--brand-via)] rounded border-gray-300 focus:ring-[color:var(--brand-via)]"
                                           @cannot('settings.attendance.manage') disabled @endcannot>
                                    <span class="text-xs font-bold text-gray-700">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                        <div class="text-[10px] text-gray-400 mt-2 italic">
                            {{ tr('Selected contract types will not be able to request this leave type and will have 0 balance.') }}
                        </div>
                    </div>

                    {{-- Notes --}}
                    <div class="md:col-span-2 pt-3 border-t border-gray-100">
                        <div class="text-sm font-black text-gray-900 mb-2">{{ tr('Notes') }}</div>

                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                            <input type="checkbox" wire:model.defer="note_required" @cannot('settings.attendance.manage') disabled @endcannot>
                            <span>{{ tr('Mandatory note') }}</span>
                        </label>
                        @error('note_required') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror

                        <div class="mt-3">
                            <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Note text') }}</label>
                            <textarea wire:model.defer="note_text" rows="2"
                                    class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm bg-gray-50/50"
                                    @cannot('settings.attendance.manage') disabled @endcannot></textarea>
                            @error('note_text') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                        </div>

                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 mt-2">
                            <input type="checkbox" wire:model.defer="note_ack_required" @cannot('settings.attendance.manage') disabled @endcannot>
                            <span>{{ tr('Require acknowledgment of the note') }}</span>
                        </label>
                        @error('note_ack_required') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    {{-- Attachments --}}
                    <div class="md:col-span-2 pt-3 border-t border-gray-100">
                        <div class="text-sm font-black text-gray-900 mb-2">{{ tr('Attachments Settings') }}</div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Allowed types') }}</label>
                                <div class="flex flex-wrap gap-3">
                                    <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                                        <input type="checkbox" value="pdf" wire:model.defer="attachment_types" @cannot('settings.attendance.manage') disabled @endcannot>
                                        <span>PDF</span>
                                    </label>
                                    <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                                        <input type="checkbox" value="jpg" wire:model.defer="attachment_types" @cannot('settings.attendance.manage') disabled @endcannot>
                                        <span>JPG</span>
                                    </label>
                                    <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                                        <input type="checkbox" value="png" wire:model.defer="attachment_types" @cannot('settings.attendance.manage') disabled @endcannot>
                                        <span>PNG</span>
                                    </label>
                                </div>
                                @error('attachment_types') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                @error('attachment_types.*') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                            </div>

                          <div>
                                <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Max size (MB)') }}</label>
                                <input
                                    type="text"
                                    value="2 MB"
                                    readonly
                                    class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-100/70 text-gray-700 cursor-not-allowed"
                                >
                                <div class="text-[10px] text-gray-400 mt-1">
                                    {{ tr('Fixed size for all leave attachments.') }}
                                </div>
                            </div>

                        </div>
                    </div>

                    {{-- ✅ General Constraints REMOVED بالكامل --}}
                </div>
            </div>
        </x-slot:content>

        <x-slot:footer>
            <div class="flex items-center justify-end gap-3 w-full">
                <x-ui.secondary-button wire:click="closeCreate" class="!px-6 !rounded-xl">
                    {{ tr('Cancel') }}
                </x-ui.secondary-button>
                @can('settings.attendance.manage')
                <x-ui.primary-button wire:click="saveCreate" class="!px-6 !rounded-xl shadow-lg">
                    <i class="fas fa-save me-2"></i>
                    {{ tr('Save Policy') }}
                </x-ui.primary-button>
                @endcan
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
            {{-- ========================= --}}
            {{-- Section 1: Basic Information --}}
            {{-- ========================= --}}
            <div class="mt-2 pt-4 border-t border-gray-100">
                <div class="text-sm font-black text-gray-900 mb-3">{{ tr('Basic Information') }}</div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Name') }}</label>

                        <input
                            type="text"
                            wire:model.defer="name"
                            {{ $editingNameLocked ? 'readonly' : '' }}
                            class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm
                                {{ $editingNameLocked ? 'bg-gray-100/70 text-gray-700 cursor-not-allowed' : 'bg-gray-50/50 focus:bg-white focus:border-[color:var(--brand-via)] focus:ring-4 focus:ring-[color:var(--brand-via)]/10 transition-all' }}"
                            placeholder="{{ tr('Leave name...') }}"
                            @cannot('settings.attendance.manage') disabled @endcannot
                        >

                        @if($editingNameLocked)
                            <div class="text-[10px] text-gray-400 mt-1">
                                {{ tr('This is a system annual policy name and cannot be changed.') }}
                            </div>
                        @endif

                        @error('name') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    {{-- ✅ Days per year يظهر هنا فقط لغير السنوية --}}
                        <div class="md:col-span-2">
                            <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Description') }}</label>
                            <input type="text" wire:model.defer="description"
                                class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50 focus:bg-white focus:border-[color:var(--brand-via)] focus:ring-4 focus:ring-[color:var(--brand-via)]/10 transition-all"
                                placeholder="{{ tr('Optional description...') }}"
                                @cannot('settings.attendance.manage') disabled @endcannot>
                            @error('description') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                        </div>

                        {{-- ✅ Days per year (moved under Description) - only for non-annual --}}
                        @if(! $editingNameLocked)
                            <div>
                                <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Days per year') }}</label>
                                <input type="number" step="0.5" min="0" wire:model.defer="days_per_year"
                                    class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50 focus:bg-white focus:border-[color:var(--brand-via)] focus:ring-4 focus:ring-[color:var(--brand-via)]/10 transition-all"
                                    @cannot('settings.attendance.manage') disabled @endcannot>
                                @error('days_per_year') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                            </div>
                        @endif

                @if(! $editingNameLocked)
                        <div>
                            <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Gender') }}</label>
                            <select wire:model.defer="gender"
                                    class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50 focus:bg-white focus:border-[color:var(--brand-via)] focus:ring-4 focus:ring-[color:var(--brand-via)]/10 transition-all"
                                    @cannot('settings.attendance.manage') disabled @endcannot>
                                <option value="all">{{ tr('All') }}</option>
                                <option value="male">{{ tr('Male') }}</option>
                                <option value="female">{{ tr('Female') }}</option>
                            </select>
                            @error('gender') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                        </div>
                    @else
                        <div>
                            <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Gender') }}</label>
                            <input
                                type="text"
                                value="{{ tr('All') }}"
                                readonly
                                class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-100/70 text-gray-700 cursor-not-allowed"
                            >
                            <div class="text-[10px] text-gray-400 mt-1">
                                {{ tr('Annual policy gender is fixed to All.') }}
                            </div>
                        </div>
                    @endif


                  

                    <div class="md:col-span-2 grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                            <input type="checkbox" wire:model.defer="is_active" @cannot('settings.attendance.manage') disabled @endcannot>
                            <span>{{ tr('Active') }}</span>
                        </label>

                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                            <input type="checkbox" wire:model.defer="show_in_app" @cannot('settings.attendance.manage') disabled @endcannot>
                            <span>{{ tr('Show in App') }}</span>
                        </label>

                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                            <input type="checkbox" wire:model.defer="requires_attachment" @cannot('settings.attendance.manage') disabled @endcannot>
                            <span>{{ tr('Requires attachment') }}</span>
                        </label>
                    </div>
                </div>
            </div>

            {{-- ========================= --}}
            {{-- ✅ Section 2: Annual Leave Settings (ONLY for السنوية) --}}
            {{-- ========================= --}}
            @if($editingNameLocked)
                <div class="mt-2 pt-4 border-t border-gray-100">
                    <div class="text-sm font-black text-gray-900 mb-3">{{ tr('Annual Leave Settings') }}</div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Days per year') }}</label>
                            <input type="number" step="0.5" min="0" wire:model.live.debounce.150ms="days_per_year"
                                class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50 focus:bg-white focus:border-[color:var(--brand-via)] focus:ring-4 focus:ring-[color:var(--brand-via)]/10 transition-all"
                                @cannot('settings.attendance.manage') disabled @endcannot>
                            @error('days_per_year') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div>
                            <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Accrual method') }}</label>
                            <select wire:model.live="accrual_method"
                                    class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50"
                                    @cannot('settings.attendance.manage') disabled @endcannot>
                                <option value="annual_grant">{{ tr('Annual full grant') }}</option>
                                <option value="monthly">{{ tr('Monthly accrual') }}</option>
                            </select>
                            @error('accrual_method') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                        </div>

                        {{-- Monthly rate --}}
                        <div>
                            <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Monthly rate') }}</label>
                            <input type="number" step="0.01" min="0"
                                wire:model="monthly_accrual_rate"
                                readonly
                                class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-100/70 text-gray-700 cursor-not-allowed"
                                placeholder="{{ tr('Auto calculated') }}">
                            <div class="text-[10px] text-gray-400 mt-1">
                                {{ tr('Auto: Days per year ÷ 12') }}
                            </div>
                            @error('monthly_accrual_rate') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div>
                            <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Max balance') }}</label>
                            <input type="number" step="0.5" min="0" wire:model.defer="max_balance"
                                class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50"
                                @cannot('settings.attendance.manage') disabled @endcannot>
                            @error('max_balance') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                        </div>
                      <div>
                    <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 mb-2">
                        <input type="checkbox" wire:model.live="allow_carryover" @cannot('settings.attendance.manage') disabled @endcannot>
                        <span>{{ tr('Allow carryover') }}</span>
                    </label>
                    @error('allow_carryover') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror

                    <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Carryover days allowed') }}</label>
                    <input
                        type="number"
                        step="0.5"
                        min="0"
                        wire:model.defer="carryover_days"
                        @disabled(! $allow_carryover)
                        class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm
                            {{ $allow_carryover
                                ? 'bg-gray-50/50 focus:bg-white focus:border-[color:var(--brand-via)] focus:ring-4 focus:ring-[color:var(--brand-via)]/10 transition-all'
                                : 'bg-gray-100/70 text-gray-700 cursor-not-allowed'
                            }}"
                            @cannot('settings.attendance.manage') disabled @endcannot
                    >
                    @error('carryover_days') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror

                    <div class="text-[10px] text-gray-400 mt-1">
                        {{ $allow_carryover ? tr('Set how many days can be carried over.') : tr('Carryover is disabled.') }}
                    </div>
                </div>


                        <div class="md:col-span-2">
                            <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Weekend days') }}</label>
                            <select wire:model.defer="weekend_policy"
                                    class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50"
                                    @cannot('settings.attendance.manage') disabled @endcannot>
                                <option value="exclude">{{ tr('Auto exclude') }}</option>
                                <option value="include">{{ tr('Auto include') }}</option>
                                <option value="employee_choice">{{ tr('Employee choice') }}</option>
                            </select>
                            @error('weekend_policy') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>
            @endif

            {{-- ========================= --}}
            {{-- Section 3: Additional Settings --}}
            {{-- ========================= --}}
            <div class="mt-2 pt-4 border-t border-gray-100">
                <div class="text-sm font-black text-gray-900 mb-3">{{ tr('Additional Settings') }}</div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Deduction --}}
                    <div>
                        <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Deduction policy') }}</label>
                     <select wire:model.defer="deduction_policy"
                                class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50"
                                @cannot('settings.attendance.manage') disabled @endcannot>
                            <option value="balance_only">{{ tr('Deduct from balance only') }}</option>
                            <option value="salary_after_balance">{{ tr('Deduct from salary after balance') }}</option>
                        </select>

                        @error('deduction_policy') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    {{-- Duration --}}
                    <div>
                        <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Duration type') }}</label>
                      <select wire:model.defer="duration_unit"
                                class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50"
                                @cannot('settings.attendance.manage') disabled @endcannot>
                            <option value="full_day">{{ tr('Full day only') }}</option>
                            <option value="half_day">{{ tr('Full day or half day') }}</option>
                        </select>

                        @error('duration_unit') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    {{-- Notice --}}
                    <div>
                        <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Min notice (days)') }}</label>
                        <input type="number" min="0" wire:model.defer="notice_min_days"
                            class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50"
                            @cannot('settings.attendance.manage') disabled @endcannot>
                        @error('notice_min_days') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div>
                        <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Max advance (days)') }}</label>
                        <input type="number" min="0" wire:model.defer="notice_max_advance_days"
                            class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50"
                            @cannot('settings.attendance.manage') disabled @endcannot>
                        @error('notice_max_advance_days') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                            <input type="checkbox" wire:model.defer="allow_retroactive" @cannot('settings.attendance.manage') disabled @endcannot>
                            <span>{{ tr('Allow retroactive requests') }}</span>
                        </label>
                        @error('allow_retroactive') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    {{-- Contract Exclusions --}}
                    <div class="md:col-span-2 pt-3 border-t border-gray-100">
                        <div class="text-sm font-black text-gray-900 mb-2">{{ tr('Contract Type Exclusions') }}</div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                            @foreach($contractList as $value => $label)
                                <label class="flex items-center gap-2 p-2 bg-gray-50/50 rounded-xl border border-gray-100 cursor-pointer hover:bg-gray-100 transition-all select-none">
                                    <input type="checkbox" 
                                           value="{{ $value }}" 
                                           wire:model.defer="selected_leave_excluded_contract_types"
                                           class="w-4 h-4 text-[color:var(--brand-via)] rounded border-gray-300 focus:ring-[color:var(--brand-via)]"
                                           @cannot('settings.attendance.manage') disabled @endcannot>
                                    <span class="text-xs font-bold text-gray-700">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                        <div class="text-[10px] text-gray-400 mt-2 italic">
                            {{ tr('Selected contract types will not be able to request this leave type and will have 0 balance.') }}
                        </div>
                    </div>

                    {{-- Notes --}}
                    <div class="md:col-span-2 pt-3 border-t border-gray-100">
                        <div class="text-sm font-black text-gray-900 mb-2">{{ tr('Notes') }}</div>

                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                            <input type="checkbox" wire:model.defer="note_required" @cannot('settings.attendance.manage') disabled @endcannot>
                            <span>{{ tr('Mandatory note') }}</span>
                        </label>
                        @error('note_required') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror

                        <div class="mt-3">
                            <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Note text') }}</label>
                            <textarea wire:model.defer="note_text" rows="2"
                                    class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm bg-gray-50/50"
                                    @cannot('settings.attendance.manage') disabled @endcannot></textarea>
                            @error('note_text') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                        </div>

                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 mt-2">
                            <input type="checkbox" wire:model.defer="note_ack_required" @cannot('settings.attendance.manage') disabled @endcannot>
                            <span>{{ tr('Require acknowledgment of the note') }}</span>
                        </label>
                        @error('note_ack_required') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    {{-- Attachments --}}
                    <div class="md:col-span-2 pt-3 border-t border-gray-100">
                        <div class="text-sm font-black text-gray-900 mb-2">{{ tr('Attachments Settings') }}</div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Allowed types') }}</label>
                                <div class="flex flex-wrap gap-3">
                                    <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                                        <input type="checkbox" value="pdf" wire:model.defer="attachment_types" @cannot('settings.attendance.manage') disabled @endcannot>
                                        <span>PDF</span>
                                    </label>
                                    <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                                        <input type="checkbox" value="jpg" wire:model.defer="attachment_types" @cannot('settings.attendance.manage') disabled @endcannot>
                                        <span>JPG</span>
                                    </label>
                                    <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                                        <input type="checkbox" value="png" wire:model.defer="attachment_types" @cannot('settings.attendance.manage') disabled @endcannot>
                                        <span>PNG</span>
                                    </label>
                                </div>
                                @error('attachment_types') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                @error('attachment_types.*') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                            </div>

                          <div>
                                <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Max size (MB)') }}</label>
                                <input
                                    type="text"
                                    value="2 MB"
                                    readonly
                                    class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-100/70 text-gray-700 cursor-not-allowed"
                                >
                                <div class="text-[10px] text-gray-400 mt-1">
                                    {{ tr('Fixed size for all leave attachments.') }}
                                </div>
                            </div>

                        </div>
                    </div>

                    {{-- ✅ General Constraints REMOVED بالكامل --}}
                </div>
            </div>
        </x-slot:content>

        <x-slot:footer>
            <div class="flex items-center justify-end gap-3 w-full">
                <x-ui.secondary-button wire:click="closeEdit" class="!px-6 !rounded-xl">
                    {{ tr('Cancel') }}
                </x-ui.secondary-button>
                @can('settings.attendance.manage')
                <x-ui.primary-button wire:click="saveEdit" class="!px-6 !rounded-xl shadow-lg">
                    <i class="fas fa-save me-2"></i>
                    {{ tr('Update') }}
                </x-ui.primary-button>
                @endcan
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

                @can('settings.attendance.manage')
                <x-ui.primary-button
                    wire:click="deleteNow"
                    class="!bg-red-600 hover:!bg-red-700 !px-6 !rounded-xl shadow-lg shadow-red-200"
                >
                    <i class="fas fa-trash me-2"></i>
                    {{ tr('Delete') }}
                </x-ui.primary-button>
                @endcan
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
                               @if(!$y->is_active && (int) $y->year === (int) now()->year)
                                    <button 
                                        wire:click="setYearActive({{ (int)$y->id }})"
                                        class="w-8 h-8 rounded-xl bg-gray-50 text-gray-400 hover:bg-emerald-50 hover:text-emerald-600 border border-gray-100 transition-all flex items-center justify-center disabled:opacity-50"
                                        title="{{ tr('Set Active') }}"
                                        @cannot('settings.attendance.manage') disabled @endcannot
                                    >
                                        <i class="fas fa-bolt text-xs"></i>
                                    </button>
                                @endif


                                    <button 
                                        wire:click="deleteYear({{ (int)$y->id }})"
                                        class="w-8 h-8 rounded-xl bg-gray-50 text-gray-400 hover:bg-red-50 hover:text-red-600 border border-gray-100 transition-all flex items-center justify-center disabled:opacity-50"
                                        title="{{ tr('Delete') }}"
                                        @cannot('settings.attendance.manage') disabled @endcannot
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
                            :disabled="!auth()->user()->can('settings.attendance.manage')"
                        />

                        <x-ui.select label="{{ tr('Copy from year (optional)') }}" wire:model.defer="copyFromYearId" :disabled="!auth()->user()->can('settings.attendance.manage')">
                             <option value="">{{ tr('Do not copy') }}</option>
                             @foreach($years as $y)
                                 <option value="{{ (int) $y->id }}">{{ $y->year }}</option>
                             @endforeach
                        </x-ui.select>

                        <div class="md:col-span-2 text-[11px] text-gray-500 font-semibold">
                            {{ tr('Dates are auto-set to Jan 1 → Dec 31. Only the current year can be active.') }}
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

                @can('settings.attendance.manage')
                <x-ui.primary-button wire:click="saveYear" class="!px-6 !rounded-xl shadow-lg">
                    <i class="fas fa-save me-2"></i>
                    {{ tr('Save') }}
                </x-ui.primary-button>
                @endcan
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
            @php
                $yearA = $years->firstWhere('id', $compareYearAId)?->year;
                $yearB = $years->firstWhere('id', $compareYearBId)?->year;
            @endphp

            <div class="space-y-4 py-2">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                     <x-ui.select
                        label="{{ tr('Year A') }}"
                        wire:model="compareYearAId"
                        :disabled="!auth()->user()->can('settings.attendance.manage')"
                    >
                        <option value="">{{ tr('Select year') }}</option>
                        @foreach($years as $y)
                            <option value="{{ (int) $y->id }}">{{ $y->year }}</option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.select
                        label="{{ tr('Year B') }}"
                        wire:model="compareYearBId"
                        :disabled="!auth()->user()->can('settings.attendance.manage')"
                    >
                        <option value="">{{ tr('Select year') }}</option>
                        @foreach($years as $y)
                            <option value="{{ (int) $y->id }}">{{ $y->year }}</option>
                        @endforeach
                    </x-ui.select>
                </div>

                <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full text-start border-collapse table-fixed">
                            <thead>
                                <tr class="bg-gray-50/50 border-b border-gray-100">
                                    <th class="w-[20%] px-5 py-3 text-[10px] font-black text-gray-400 uppercase tracking-widest text-start">{{ tr('Leave') }}</th>
                                    <th class="w-[35%] px-5 py-3 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center"> {{ $yearA ? (string) $yearA : tr('Year') }}</th>
                                    <th class="w-[35%] px-5 py-3 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">  {{ $yearB ? (string) $yearB : tr('Year') }}</th>
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
                                       
                                        <td class="px-5 py-3 border-e border-gray-50">
                                            @if($a)
                                                <div class="text-xs text-gray-700 flex flex-wrap gap-2 justify-center">
                                                        <span class="px-2 py-1 bg-gray-100 rounded-md"><b>{{ tr('Days') }}:</b> {{ $a->days_per_year }}</span>

                                                        <span class="px-2 py-1 {{ $a->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' }} rounded-md">
                                                            <b>{{ tr('Status') }}:</b> {{ $a->is_active ? tr('Active') : tr('Inactive') }}
                                                        </span>

                                                    
                                                    </div>

                                            @else
                                                <div class="text-xs text-gray-400 text-center italic">{{ tr('Not found') }}</div>
                                            @endif
                                        </td>
                                      <td class="px-5 py-3">
                                    @if($b)
                                        <div class="text-xs text-gray-700 flex flex-wrap gap-2 justify-center">
                                            <span class="px-2 py-1 bg-gray-100 rounded-md"><b>{{ tr('Days') }}:</b> {{ $b->days_per_year }}</span>

                                            <span class="px-2 py-1 {{ $b->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' }} rounded-md">
                                                <b>{{ tr('Status') }}:</b> {{ $b->is_active ? tr('Active') : tr('Inactive') }}
                                            </span>

                                            {{-- ✅ Eye icon بدل App --}}
                                            <button
                                                type="button"
                                                wire:click="toggleCompareDetails('{{ $r['key'] }}')"
                                                class="px-2 py-1 bg-white rounded-md border border-gray-200 hover:bg-gray-50 text-gray-700 inline-flex items-center gap-2"
                                                title="{{ tr('View details') }}"
                                                @cannot('settings.attendance.manage') disabled @endcannot
                                            >
                                                <i class="fas fa-eye text-[11px]"></i>
                                                <span class="text-[11px] font-bold">{{ tr('Details') }}</span>
                                            </button>
                                        </div>
                                    @else
                                        <div class="text-xs text-gray-400 text-center italic">{{ tr('Not found') }}</div>
                                    @endif
                                </td>

                                    </tr>

                        @if(!empty($compareExpanded[$r['key']] ?? false))
                            <tr class="bg-white">
                                <td colspan="3" class="px-5 py-4">
                                    <div class="rounded-2xl border border-gray-100 bg-gray-50/40 p-4">
                                        {{-- ... expanded content ... --}}
                                        <div class="flex items-center justify-between gap-3 mb-3">
                                            <div class="text-sm font-black text-gray-900">
                                                <i class="fas fa-circle-info text-gray-400 me-2"></i>
                                                {{ tr('Leave Details') }} — {{ $r['name'] }}
                                            </div>
                                            <button type="button" wire:click="toggleCompareDetails('{{ $r['key'] }}')"
                                                class="text-xs font-black text-gray-500 hover:text-gray-900"
                                                @cannot('settings.attendance.manage') disabled @endcannot>
                                                {{ tr('Close') }}
                                            </button>
                                        </div>
                                        {{-- Details Grid --}}
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div class="rounded-xl bg-white border border-gray-100 p-3">
                                                <div class="text-[11px] font-black text-gray-500 mb-2">{{ $yearA ? (string)$yearA : tr('Year') }}</div>
                                                @if($a)
                                                    <div class="grid grid-cols-2 gap-2 text-[12px]">
                                                        <div><b>{{ tr('Show in App') }}:</b> {{ $a->show_in_app ? tr('Yes') : tr('No') }}</div>
                                                        <div><b>{{ tr('Requires attachment') }}:</b> {{ $a->requires_attachment ? tr('Yes') : tr('No') }}</div>
                                                        <div class="col-span-2">
                                                            <b>{{ tr('Attachment types') }}:</b>
                                                            @php $tA = data_get($a->settings ?? [], 'attachments.types', []); @endphp
                                                            {{ is_array($tA) ? implode(', ', $tA) : (string)$tA }}
                                                        </div>
                                                    </div>
                                                @else
                                                    <div class="text-xs text-gray-400 italic">{{ tr('Not found') }}</div>
                                                @endif
                                            </div>
                                            <div class="rounded-xl bg-white border border-gray-100 p-3">
                                                <div class="text-[11px] font-black text-gray-500 mb-2">{{ $yearB ? (string)$yearB : tr('Year') }}</div>
                                                @if($b)
                                                    <div class="grid grid-cols-2 gap-2 text-[12px]">
                                                        <div><b>{{ tr('Show in App') }}:</b> {{ $b->show_in_app ? tr('Yes') : tr('No') }}</div>
                                                        <div><b>{{ tr('Requires attachment') }}:</b> {{ $b->requires_attachment ? tr('Yes') : tr('No') }}</div>
                                                        <div class="col-span-2">
                                                            <b>{{ tr('Attachment types') }}:</b>
                                                            @php $tB = data_get($b->settings ?? [], 'attachments.types', []); @endphp
                                                            {{ is_array($tB) ? implode(', ', $tB) : (string)$tB }}
                                                        </div>
                                                    </div>
                                                @else
                                                    <div class="text-xs text-gray-400 italic">{{ tr('Not found') }}</div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="3" class="px-5 py-10 text-center text-sm text-gray-400">
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


        @else
            <x-ui.card>
                <div class="p-6 space-y-6">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-amber-50 text-amber-700 rounded-xl flex items-center justify-center border border-amber-100">
                                <i class="fas fa-user-clock"></i>
                            </div>

                            <div>
                                <div class="text-base font-black text-gray-900">{{ tr('Permission Settings') }}</div>
                                <div class="text-[11px] text-gray-500 font-semibold">
                                    {{ tr('Configure approval, monthly hours limit, and deduction rules.') }}
                                </div>
                            </div>
                        </div>

                        @can('settings.attendance.manage')
                            <x-ui.primary-button :arrow="false" :fullWidth="false" wire:click="savePermissionSettings" class="!rounded-xl">
                                <i class="fas fa-save"></i>
                                <span class="ms-2">{{ tr('Save') }}</span>
                            </x-ui.primary-button>
                        @endcan
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2 grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                                <input type="checkbox" wire:model.defer="perm_approval_required" @cannot('settings.attendance.manage') disabled @endcannot>
                                <span>{{ tr('Approval required') }}</span>
                            </label>

                            <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                                <input type="checkbox" wire:model.defer="perm_show_in_app" @cannot('settings.attendance.manage') disabled @endcannot>
                                <span>{{ tr('Show in App') }}</span>
                            </label>

                            <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                                <input type="checkbox" wire:model.defer="perm_requires_attachment" @cannot('settings.attendance.manage') disabled @endcannot>
                                <span>{{ tr('Requires attachment') }}</span>
                            </label>
                        </div>

                        <div>
                            <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Monthly limit (hours)') }}</label>
                            <input type="number" step="0.25" min="0" wire:model.defer="perm_monthly_limit_hours"
                                class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50 focus:bg-white focus:border-[color:var(--brand-via)] focus:ring-4 focus:ring-[color:var(--brand-via)]/10 transition-all"
                                placeholder="0"
                                @cannot('settings.attendance.manage') disabled @endcannot>
                            <div class="text-[10px] text-gray-400 mt-1">{{ tr('0 means unlimited') }}</div>
                            @error('perm_monthly_limit_hours') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div>
                            <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Max per request (hours)') }}</label>
                            <input type="number" step="0.25" min="0" wire:model.defer="perm_max_request_hours"
                                class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50 focus:bg-white focus:border-[color:var(--brand-via)] focus:ring-4 focus:ring-[color:var(--brand-via)]/10 transition-all"
                                placeholder="0"
                                @cannot('settings.attendance.manage') disabled @endcannot>
                            <div class="text-[10px] text-gray-400 mt-1">{{ tr('0 means unlimited') }}</div>
                            @error('perm_max_request_hours') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Deduction policy') }}</label>
                            <select wire:model.defer="perm_deduction_policy"
                                class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-50/50"
                                @cannot('settings.attendance.manage') disabled @endcannot>
                                <option value="not_allowed_after_limit">{{ tr('Not allowed after limit') }}</option>
                                <option value="salary_after_limit">{{ tr('Deduct from salary after limit') }}</option>
                                <option value="allow_without_deduction">{{ tr('Allow without deduction') }}</option>
                            </select>
                            @error('perm_deduction_policy') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                        </div>

                        {{-- Attachments section --}}
                        <div class="md:col-span-2 pt-3 border-t border-gray-100">
                            <div class="text-sm font-black text-gray-900 mb-2">{{ tr('Attachments Settings') }}</div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Allowed types') }}</label>

                                    <div class="flex flex-wrap gap-3">
                                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                                            <input type="checkbox" value="pdf" wire:model.defer="perm_attachment_types" @disabled(! $perm_requires_attachment || !auth()->user()->can('settings.attendance.manage'))>
                                            <span>PDF</span>
                                        </label>
                                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                                            <input type="checkbox" value="jpg" wire:model.defer="perm_attachment_types" @disabled(! $perm_requires_attachment || !auth()->user()->can('settings.attendance.manage'))>
                                            <span>JPG</span>
                                        </label>
                                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                                            <input type="checkbox" value="png" wire:model.defer="perm_attachment_types" @disabled(! $perm_requires_attachment || !auth()->user()->can('settings.attendance.manage'))>
                                            <span>PNG</span>
                                        </label>
                                    </div>

                                    @error('perm_attachment_types') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                    @error('perm_attachment_types.*') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                </div>

                                <div>
                                    <label class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Max size (MB)') }}</label>
                                    <input
                                        type="text"
                                        value="2 MB"
                                        readonly
                                        class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-100/70 text-gray-700 cursor-not-allowed"
                                    >
                                    <div class="text-[10px] text-gray-400 mt-1">
                                        {{ tr('Fixed size for all permission attachments.') }}
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </x-ui.card>
        @endif

</div>
