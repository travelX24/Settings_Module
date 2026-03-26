{{-- systemsettings::livewire.attendance.exceptional-days.index --}}
<div>

    @section('topbar-left-content')
        <x-ui.page-header
            :title="tr('Exceptional Days')"
            :subtitle="tr('Define special days with custom deduction rules (late/absence)')"
            class="!flex-col !items-start !justify-start !gap-1"
            titleSize="xl"
        />
    @endsection

    @section('topbar-actions')
        <div class="flex items-center gap-2">
            <x-ui.secondary-button
                href="{{ \Illuminate\Support\Facades\Route::has('company-admin.settings.attendance')
                    ? route('company-admin.settings.attendance')
                    : ( \Illuminate\Support\Facades\Route::has('company-admin.settings.general')
                        ? route('company-admin.settings.general')
                        : url('/company-admin/settings') ) }}"
                :arrow="false"
                :fullWidth="false"
                class="!px-4 !py-2 !text-sm !rounded-xl !gap-2 cursor-pointer"
            >
                <i class="fas {{ app()->getLocale() == 'ar' ? 'fa-arrow-right' : 'fa-arrow-left' }} text-xs"></i>
                <span>{{ tr('Back') }}</span>
            </x-ui.secondary-button>
        </div>
    @endsection

    <div class="space-y-6">

        {{-- Toolbar --}}
        <x-ui.card class="!p-4">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">

                <div class="flex items-center gap-2">
                    @can('settings.attendance.manage')
                    <x-ui.brand-button
                        type="button"
                        wire:click="openCreate"
                        :arrow="false"
                        :fullWidth="false"
                        class="!px-4 !py-2 !text-sm !rounded-xl !gap-2 cursor-pointer"
                    >
                        <i class="fas fa-plus text-xs"></i>
                        <span>{{ tr('Add Exceptional Day') }}</span>
                    </x-ui.brand-button>

                    {{-- ✅ Compare Years --}}
                    <x-ui.secondary-button
                        type="button"
                        wire:click="openCopyModal"
                        :arrow="false"
                        :fullWidth="false"
                        class="!px-4 !py-2 !text-sm !rounded-xl !gap-2 cursor-pointer"
                    >
                        <i class="fas fa-copy text-xs"></i>
                        <span>{{ tr('Compare Years') }}</span>
                    </x-ui.secondary-button>
                    @endcan

                    <x-ui.secondary-button
                        type="button"
                        wire:click="exportCsv"
                        :arrow="false"
                        :fullWidth="false"
                        class="!px-4 !py-2 !text-sm !rounded-xl !gap-2 cursor-pointer"
                    >
                        <i class="fas fa-file-export text-xs"></i>
                        <span>{{ tr('Export CSV') }}</span>
                    </x-ui.secondary-button>
                </div>

                @if(!empty($selected))
                    @can('settings.attendance.manage')
                    <div class="flex items-center gap-2 justify-end">
                        <div class="text-xs text-gray-600">
                            {{ tr('Selected') }}: <span class="font-bold">{{ count($selected) }}</span>
                        </div>

                        <x-ui.secondary-button
                            type="button"
                            onclick="window.dispatchEvent(new CustomEvent('open-confirm-exceptional-day-bulk-enable'));"
                            :arrow="false"
                            :fullWidth="false"
                            class="!px-4 !py-2 !text-sm !rounded-xl !gap-2 cursor-pointer"
                        >
                            <i class="fas fa-toggle-on text-xs"></i>
                            <span>{{ tr('Enable') }}</span>
                        </x-ui.secondary-button>

                        <x-ui.secondary-button
                            type="button"
                            onclick="window.dispatchEvent(new CustomEvent('open-confirm-exceptional-day-bulk-disable'));"
                            :arrow="false"
                            :fullWidth="false"
                            class="!px-4 !py-2 !text-sm !rounded-xl !gap-2 cursor-pointer"
                        >
                            <i class="fas fa-toggle-off text-xs"></i>
                            <span>{{ tr('Disable') }}</span>
                        </x-ui.secondary-button>

                        <x-ui.secondary-button
                            type="button"
                            onclick="window.dispatchEvent(new CustomEvent('open-confirm-exceptional-day-bulk-delete'));"
                            :arrow="false"
                            :fullWidth="false"
                            class="!px-4 !py-2 !text-sm !rounded-xl !gap-2 !text-red-600 cursor-pointer"
                        >
                            <i class="fas fa-trash text-xs"></i>
                            <span>{{ tr('Delete Selected') }}</span>
                        </x-ui.secondary-button>
                    </div>
                    @endcan
                @endif

            </div>
        </x-ui.card>

        {{-- Filters --}}
        <x-ui.card class="!p-4">
            <div class="grid grid-cols-1 md:grid-cols-6 gap-3 w-full">

                <div>
                    <div class="mb-1 text-xs text-gray-500">{{ tr('Year') }}</div>
                    <x-ui.input type="number" :placeholder="tr('Year')" wire:model.live="year" min="2000" max="2100" />
                </div>

                <div>
                    <div class="mb-1 text-xs text-gray-500">{{ tr('Month') }}</div>
                    <x-ui.input type="number" :placeholder="tr('Month')" wire:model.live="month" min="1" max="12" />
                </div>

                <div>
                    <div class="mb-1 text-xs text-gray-500">{{ tr('Status') }}</div>
                    <x-ui.select wire:model.live="status">
                        <option value="all">{{ tr('All') }}</option>
                        <option value="current">{{ tr('Active (Current)') }}</option>
                        <option value="upcoming">{{ tr('Upcoming') }}</option>
                        <option value="ended">{{ tr('Ended') }}</option>
                    </x-ui.select>
                </div>

                <div>
                    <div class="mb-1 text-xs text-gray-500">{{ tr('Apply Type') }}</div>
                    <x-ui.select wire:model.live="deductionType">
                        <option value="all">{{ tr('All Types') }}</option>
                        <option value="absence">{{ tr('Absence') }}</option>
                        <option value="late">{{ tr('Late') }}</option>
                        <option value="without">{{ tr('Without Deduction') }}</option>
                    </x-ui.select>
                </div>

                <div>
                    <div class="mb-1 text-xs text-gray-500">{{ tr('Min Deduction %') }}</div>
                    <x-ui.input type="number" step="0.01" min="0" max="1000" wire:model.live="minMultiplier" :placeholder="tr('Min %')" />
                </div>

                <div>
                    <div class="mb-1 text-xs text-gray-500">{{ tr('Max Deduction %') }}</div>
                    <x-ui.input type="number" step="0.01" min="0" max="1000" wire:model.live="maxMultiplier" :placeholder="tr('Max %')" />
                </div>

                <div class="md:col-span-2">
                    <div class="mb-1 text-xs text-gray-500">{{ tr('Target Department') }}</div>
                    <x-ui.select wire:model.live="departmentId">
                        <option value="">{{ tr('All Departments') }}</option>
                        @foreach($departmentsOptions as $d)
                            <option value="{{ $d->id }}">{{ $d->name }}</option>
                        @endforeach
                    </x-ui.select>
                </div>

                <div class="md:col-span-2">
                    <div class="mb-1 text-xs text-gray-500">{{ tr('Target Branch') }}</div>
                    <x-ui.select wire:model.live="branchId">
                        <option value="">{{ tr('All Branches') }}</option>
                        @foreach($branchesOptions as $b)
                            <option value="{{ $b->id }}">{{ $b->name }}</option>
                        @endforeach
                    </x-ui.select>
                </div>

                <div class="md:col-span-2">
                    <div class="mb-1 text-xs text-gray-500">{{ tr('Target Contract Type') }}</div>
                    <x-ui.select wire:model.live="contractType">
                        <option value="">{{ tr('All Contract Types') }}</option>
                        @foreach($contractTypesOptions as $ct)
                            <option value="{{ $ct->id }}">{{ $ct->name }}</option>
                        @endforeach
                    </x-ui.select>
                </div>

                <div class="md:col-span-6">
                    <div class="mb-1 text-xs text-gray-500">{{ tr('Search') }}</div>
                    <x-ui.search-box wire:model.live.debounce.300ms="search" :placeholder="tr('Search by name/description...')" />
                </div>

            </div>
        </x-ui.card>

        {{-- Stats --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <x-ui.card class="!p-4">
                <div class="text-xs text-gray-500">{{ tr('Total (Year)') }}</div>
                <div class="text-2xl font-bold">{{ $stats['total_year'] ?? 0 }}</div>
            </x-ui.card>

            <x-ui.card class="!p-4">
                <div class="text-xs text-gray-500">{{ tr('Active Now') }}</div>
                <div class="text-2xl font-bold">{{ $stats['active_now'] ?? 0 }}</div>
            </x-ui.card>

            <x-ui.card class="!p-4">
                <div class="text-xs text-gray-500">{{ tr('Upcoming This Month') }}</div>
                <div class="text-2xl font-bold">{{ $stats['upcoming_month'] ?? 0 }}</div>
            </x-ui.card>

            <x-ui.card class="!p-4">
                <div class="text-xs text-gray-500">{{ tr('Estimated Cost') }}</div>
                <div class="text-2xl font-bold">
                    {{ is_null($stats['cost_estimate'] ?? null) ? '—' : number_format((float) $stats['cost_estimate'], 2) }}
                </div>
            </x-ui.card>
        </div>

        {{-- Table --}}
        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-visible relative mt-2">
            <table class="w-full text-start border-collapse table-fixed">
                <thead>
                    <tr class="bg-gray-50/50 border-b border-gray-100">
                        <th class="w-10 px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-right">
                            <input type="checkbox" wire:model.live="selectPage" class="rounded cursor-pointer">
                        </th>
                        <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-right">{{ tr('Name') }}</th>
                        <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">{{ tr('Period') }}</th>
                        <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">{{ tr('Start') }}</th>
                        <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">{{ tr('End') }}</th>
                        <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">{{ tr('Apply') }}</th>
                        <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">{{ tr('Deduction %') }}</th>
                        <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">{{ tr('Grace Hours') }}</th>
                        <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">{{ tr('Scope') }}</th>
                        <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">{{ tr('Notified') }}</th>
                        <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">{{ tr('Created By') }}</th>
                        <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">{{ tr('Created At') }}</th>
                        <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">{{ tr('Active') }}</th>
                        <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-right">{{ tr('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                        @forelse($rows as $row)
                            @php
                                $apply = (string) ($row->apply_on ?? 'absence');

                                $percent = 0.0;
                                if($apply === 'absence') $percent = (float) $row->absence_multiplier * 100.0;
                                if($apply === 'late') $percent = (float) $row->late_multiplier * 100.0;

                                if($apply === 'none') {
                                    $percent = 0.0;
                                }

                                $applyLabel = $apply === 'absence'
                                    ? tr('Absence')
                                    : ($apply === 'late'
                                        ? tr('Late')
                                        : tr('Without Deduction'));

                                $scope = (string) ($row->scope_type ?? 'all');
                                $scopeLabel = $scope === 'all'
                                    ? tr('All Employees')
                                    : ($scope === 'departments'
                                        ? tr('Departments')
                                        : ($scope === 'branches'
                                            ? tr('Branches')
                                            : ($scope === 'contract_types'
                                                ? tr('Contract Types')
                                                : tr('Employees'))));

                                $isWithout = ($apply === 'none') || ($percent <= 0);
                            @endphp

                            <tr class="hover:bg-gray-50/40 transition-colors cursor-default border-t">
                                <td class="px-6 py-4">
                                    <input type="checkbox" wire:model.live="selected" value="{{ $row->id }}" class="rounded cursor-pointer">
                                </td>

                                <td class="px-6 py-4 text-right">
                                    <div class="text-sm font-bold text-gray-800">{{ $row->name }}</div>
                                    @if(!empty($row->description))
                                        <div class="text-[10px] text-gray-400 truncate">{{ $row->description }}</div>
                                    @endif
                                </td>

                                <td class="px-6 py-4 text-center">
                                    <span class="text-xs font-semibold text-gray-700">
                                        {{ ($row->period_type === 'single') ? tr('Single Day') : tr('Range') }}
                                    </span>
                                </td>

                                <td class="px-6 py-4 text-center text-xs font-semibold text-gray-700">{{ optional($row->start_date)->format('Y-m-d') }}</td>
                                <td class="px-6 py-4 text-center text-xs font-semibold text-gray-700">{{ optional($row->end_date ?? $row->start_date)->format('Y-m-d') }}</td>

                                <td class="px-6 py-4 text-center text-xs font-semibold text-gray-700">{{ $applyLabel }}</td>

                                <td class="px-6 py-4 text-center text-xs font-semibold text-gray-700">
                                    {{ $isWithout ? '—' : number_format($percent, 2) . '%' }}
                                </td>

                                <td class="px-6 py-4 text-center text-xs font-semibold text-gray-700">
                                    {{ ($apply === 'late' && !$isWithout) ? (int) $row->grace_hours : '—' }}
                                </td>

                                <td class="px-6 py-4 text-center text-xs font-semibold text-gray-700">{{ $scopeLabel }}</td>

                                <td class="px-6 py-4 text-center text-xs font-semibold text-gray-700">
                                    {{ $row->notified_at ? tr('Sent') : tr('Not Sent') }}
                                </td>

                                <td class="px-6 py-4 text-center text-xs font-semibold text-gray-700">
                                    {{ $createdByMap[$row->created_by] ?? ($row->created_by ?? '—') }}
                                </td>

                                <td class="px-6 py-4 text-center text-xs font-semibold text-gray-700">
                                    {{ optional($row->created_at)->format('Y-m-d') ?? '—' }}
                                </td>

                                <td class="px-6 py-4 text-center">
                                    <div class="flex justify-center">
                                        @can('settings.attendance.manage')
                                        <button wire:click="toggleActive({{ $row->id }})" class="w-9 h-4.5 rounded-full p-1 transition-all relative cursor-pointer {{ $row->is_active ? 'bg-green-500' : 'bg-gray-200' }}">
                                            <div class="w-2.5 h-2.5 bg-white rounded-full shadow-sm transition-all {{ $row->is_active ? (app()->getLocale() == 'ar' ? 'mr-4.5' : 'ml-4.5') : '' }}"></div>
                                        </button>
                                        @else
                                        <button disabled class="w-9 h-4.5 rounded-full p-1 transition-all relative cursor-not-allowed opacity-50 {{ $row->is_active ? 'bg-green-500' : 'bg-gray-200' }}">
                                            <div class="w-2.5 h-2.5 bg-white rounded-full shadow-sm transition-all {{ $row->is_active ? (app()->getLocale() == 'ar' ? 'mr-4.5' : 'ml-4.5') : '' }}"></div>
                                        </button>
                                        @endcan
                                    </div>
                                </td>

                                <td class="px-6 py-4 text-right">
                                    @can('settings.attendance.manage')
                                            <x-ui.actions-menu>
                                                <x-ui.dropdown-item wire:click="openEdit({{ $row->id }})" class="cursor-pointer">
                                                    <i class="fas fa-edit me-2 text-blue-500"></i> {{ tr('Edit') }}
                                                </x-ui.dropdown-item>
                                                <x-ui.dropdown-item danger onclick="window.dispatchEvent(new CustomEvent('open-confirm-exceptional-day-delete', { detail: { id: {{ $row->id }} } }));" class="cursor-pointer">
                                                    <i class="fas fa-trash-alt me-2 text-red-500"></i> {{ tr('Delete') }}
                                                </x-ui.dropdown-item>
                                            </x-ui.actions-menu>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="14" class="px-6 py-24 text-center border-t border-gray-100">
                                    <div class="opacity-20 flex flex-col items-center">
                                        <div class="w-24 h-24 bg-gray-50 rounded-full flex items-center justify-center text-4xl mb-4 border border-gray-100">
                                            <i class="fas fa-calendar-times"></i>
                                        </div>
                                        <h4 class="text-base font-bold text-gray-800">{{ tr('No Exceptional Days Found') }}</h4>
                                        <p class="text-xs max-w-[250px] mt-2 leading-relaxed">{{ tr('No exceptional days have been defined yet. Start by creating the first exceptional day.') }}</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                </tbody>
            </table>

            <div class="border-t p-4">
                {{ $rows->links() }}
            </div>
        </div>

        {{-- Modal Create/Edit --}}
        <x-ui.modal wire:model="showModal" maxWidth="4xl">
            <x-slot name="title">
                {{ $editingId ? tr('Edit Exceptional Day') : tr('Add Exceptional Day') }}
            </x-slot>

            <x-slot name="content">
                <div class="space-y-5">

                    {{-- ✅ Name + Period --}}
                    <x-ui.card class="!p-4 !bg-gray-50/40">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">

                            <div class="md:col-span-2">
                                <div class="text-xs text-gray-600 mb-1">{{ tr('Name') }}</div>
                                <x-ui.input wire:model.defer="form.name" :disabled="!auth()->user()->can('settings.attendance.manage')" />
                                @error('form.name') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div>
                                <div class="text-xs text-gray-600 mb-1">{{ tr('Period') }}</div>
                                <x-ui.select wire:model.live="form.period_type" :disabled="!auth()->user()->can('settings.attendance.manage')">
                                    <option value="single">{{ tr('Single Day') }}</option>
                                    <option value="range">{{ tr('Date Range') }}</option>
                                </x-ui.select>
                            </div>

                            @if(($form['period_type'] ?? 'single') === 'single')
                                <div>
                                    <div class="text-xs text-gray-600 mb-1">{{ tr('Date') }}</div>
                                    <x-ui.company-date-picker model="form.start_date" />
                                    @error('form.start_date') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                </div>
                            @else
                                <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <div>
                                        <div class="text-xs text-gray-600 mb-1">{{ tr('From') }}</div>
                                        <x-ui.company-date-picker model="form.start_date" />
                                        @error('form.start_date') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                    </div>

                                    <div>
                                        <div class="text-xs text-gray-600 mb-1">{{ tr('To') }}</div>
                                        <x-ui.company-date-picker model="form.end_date" />
                                        @error('form.end_date') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                            @endif

                        </div>
                    </x-ui.card>

                    {{-- ✅ Apply + Deduction Mode + Percent --}}
                    <x-ui.card class="!p-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">

                            <div>
                                <div class="text-xs text-gray-600 mb-1">{{ tr('Apply') }}</div>
                                <x-ui.select wire:model.live="form.apply_on" :disabled="!auth()->user()->can('settings.attendance.manage')">
                                    <option value="absence">{{ tr('Absence') }}</option>
                                    <option value="late">{{ tr('Late') }}</option>
                                </x-ui.select>
                                @error('form.apply_on') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div>
                                <div class="text-xs text-gray-600 mb-1">{{ tr('Deduction') }}</div>
                                <x-ui.select wire:model.live="form.deduction_mode">
                                    <option value="with">{{ tr('With Deduction') }}</option>
                                    <option value="without">{{ tr('Without Deduction') }}</option>
                                </x-ui.select>
                                @error('form.deduction_mode') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                            </div>

                            @if(($form['deduction_mode'] ?? 'with') === 'with')
                                <div class="md:col-span-2">
                                    <div class="text-xs text-gray-600 mb-2">
                                        {{ (($form['apply_on'] ?? 'absence') === 'absence')
                                            ? tr('Deduction % (from day wage)')
                                            : tr('Deduction % (from minute wage)') }}
                                    </div>

                                    <x-ui.input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="1000"
                                        wire:model.defer="form.deduction_percent"
                                        :disabled="!auth()->user()->can('settings.attendance.manage')"
                                    />
                                    @error('form.deduction_percent') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror

                                    <div class="mt-1 text-xs text-gray-500">
                                        {{ tr('Example: 25% = 0.25 factor, 100% = 1.0, 200% = 2.0') }}
                                    </div>
                                </div>

                                @if(($form['apply_on'] ?? 'absence') === 'late')
                                    <div class="md:col-span-2">
                                        <div class="text-xs text-gray-600 mb-1">{{ tr('Grace Hours') }}</div>
                                        <x-ui.input type="number" min="0" max="24" wire:model.defer="form.grace_hours" />
                                        @error('form.grace_hours') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                    </div>
                                @endif
                            @else
                                <div class="md:col-span-2 text-xs text-gray-500">
                                    {{ tr('This exceptional day will be saved without any deduction.') }}
                                </div>
                            @endif

                        </div>
                    </x-ui.card>

                    {{-- ✅ Scope --}}
                    <x-ui.card class="!p-4 !bg-gray-50/40">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">

                            <div>
                                <div class="text-xs text-gray-600 mb-1">{{ tr('Scope Type') }}</div>
                                <x-ui.select wire:model.live="form.scope_type">
                                    <option value="all">{{ tr('All Employees') }}</option>
                                    <option value="departments">{{ tr('Specific Departments') }}</option>
                                    <option value="branches">{{ tr('Specific Branches') }}</option>
                                    <option value="contract_types">{{ tr('Specific Contract Types') }}</option>
                                    <option value="employees">{{ tr('Specific Employees') }}</option>
                                </x-ui.select>
                                @error('form.scope_type') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div>
                                <div class="text-xs text-gray-600 mb-1">{{ tr('Notification Policy') }}</div>
                                <x-ui.select wire:model.defer="form.notify_policy">
                                    <option value="none">{{ tr('No Notification') }}</option>
                                    <option value="after_deduction">{{ tr('Notify After Deduction') }}</option>
                                </x-ui.select>
                            </div>

                            {{-- ✅ Departments mode --}}
                             @if(($form['scope_type'] ?? 'all') === 'departments')
                                <div>
                                    <div class="text-xs text-gray-600 mb-1">{{ tr('Departments') }}</div>
                                    <x-ui.select multiple wire:model.defer="form.include.departments">
                                        @foreach($departmentsOptions as $d)
                                            <option value="{{ $d->id }}">{{ $d->name }}</option>
                                        @endforeach
                                    </x-ui.select>
                                    @error('form.include.departments') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                </div>

                                <div>
                                    <div class="text-xs text-gray-600 mb-1">{{ tr('Include Sub Departments / Sections') }}</div>
                                    <x-ui.select multiple wire:model.defer="form.include.sections">
                                        @foreach($sectionsOptions as $s)
                                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                                        @endforeach
                                    </x-ui.select>
                                </div>
                            @endif

                            {{-- ✅ Branches mode --}}
                            @if(($form['scope_type'] ?? 'all') === 'branches')
                                <div class="md:col-span-2">
                                    <div class="text-xs text-gray-600 mb-1">{{ tr('Branches') }}</div>
                                    <x-ui.select multiple wire:model.defer="form.include.branches">
                                        @foreach($branchesOptions as $b)
                                            <option value="{{ $b->id }}">{{ $b->name }}</option>
                                        @endforeach
                                    </x-ui.select>
                                    @error('form.include.branches') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                </div>
                            @endif

                            {{-- ✅ Contract Types mode --}}
                            @if(($form['scope_type'] ?? 'all') === 'contract_types')
                                <div class="md:col-span-2">
                                    <div class="text-xs text-gray-600 mb-1">{{ tr('Contract Types') }}</div>
                                    <x-ui.select multiple wire:model.defer="form.include.contract_types">
                                        @foreach($contractTypesOptions as $ct)
                                            <option value="{{ $ct->name }}">{{ $ct->name }}</option>
                                        @endforeach
                                    </x-ui.select>
                                    @error('form.include.contract_types') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                </div>
                            @endif

                            {{-- ✅ Employees mode --}}
                            @if(($form['scope_type'] ?? 'all') === 'employees')
                                <div class="md:col-span-2">
                                    <div class="text-xs text-gray-600 mb-1">{{ tr('Employees') }}</div>
                                    <x-ui.select multiple wire:model.defer="form.include.employees">
                                        @foreach($employeesOptions as $e)
                                            <option value="{{ $e->id }}">{{ $e->name }}</option>
                                        @endforeach
                                    </x-ui.select>
                                    @error('form.include.employees') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                </div>
                            @endif

                            <div class="md:col-span-2">
                                <div class="text-xs text-gray-600 mb-1">{{ tr('Employee Alert Message') }}</div>
                                <x-ui.textarea rows="2" wire:model.defer="form.notify_message" :disabled="!auth()->user()->can('settings.attendance.manage')" />
                                @error('form.notify_message') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="md:col-span-2">
                                <div class="text-xs text-gray-600 mb-1">{{ tr('Description') }}</div>
                                <x-ui.textarea rows="3" wire:model.defer="form.description" />
                            </div>

                            <div class="md:col-span-2 flex items-center gap-2">
                                <input type="checkbox" wire:model.defer="form.is_active" id="is_active" class="rounded cursor-pointer">
                                <label for="is_active" class="text-sm cursor-pointer">{{ tr('Enabled') }}</label>
                            </div>

                        </div>
                    </x-ui.card>

                </div>
            </x-slot>

            <x-slot name="footer">
                <x-ui.secondary-button type="button" wire:click="$set('showModal', false)">
                    {{ tr('Cancel') }}
                </x-ui.secondary-button>

                @can('settings.attendance.manage')
                <x-ui.primary-button type="button" wire:click="save" loading="save" class="cursor-pointer">
                    <i class="fas fa-save me-2" wire:loading.remove wire:target="save"></i>
                    {{ tr('Save') }}
                </x-ui.primary-button>
                @endcan
            </x-slot>
        </x-ui.modal>

        {{-- ✅ Compare/Copy Modal --}}
        <x-ui.modal wire:model="showCopyModal" maxWidth="5xl">
            <x-slot name="title">{{ tr('Compare Years') }}</x-slot>

            <x-slot name="content">
                <div class="space-y-4">

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <div class="text-xs text-gray-600 mb-1">{{ tr('From Year') }}</div>
                            <x-ui.input type="number" min="2000" max="2100" wire:model.live="copyFromYear" />
                            <div class="mt-1 text-xs text-gray-500">
                                {{ tr('Records found') }}:
                                <span class="font-bold">{{ $copyFromCount ?? '—' }}</span>
                            </div>
                        </div>

                        <div>
                            <div class="text-xs text-gray-600 mb-1">{{ tr('To Year') }}</div>
                            <x-ui.input type="number" :value="$copyToYear" disabled />
                            <div class="mt-1 text-xs text-gray-500">
                                {{ tr('Copy target is the selected/current year in filters.') }}
                            </div>
                        </div>

                        <div class="flex items-end gap-2">
                            <div class="w-full">
                                @error('copySelected')
                                    <div class="text-xs text-red-600 mb-2">{{ $message }}</div>
                                @enderror

                                @can('settings.attendance.manage')
                                <x-ui.primary-button
                                    type="button"
                                    wire:click="copySelectedDays"
                                    class="w-full justify-center cursor-pointer"
                                >
                                    <i class="fas fa-copy text-xs"></i>
                                    <span>{{ tr('Copy Selected') }}</span>
                                </x-ui.primary-button>
                                @endcan

                                <div class="mt-2 text-xs text-gray-500">
                                    {{ tr('Copied days will be created as Disabled by default.') }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <x-ui.card class="!p-0 overflow-hidden">
                        <div class="overflow-x-auto">
                            <x-ui.table>
                                <x-slot name="head">
                                    <tr>
                                        <th class="p-3 text-right w-10">
                                            <input type="checkbox" wire:model.live="copySelectAll" class="rounded cursor-pointer">
                                        </th>
                                        <th class="p-3 text-right">{{ tr('Name') }}</th>
                                        <th class="p-3 text-right">{{ tr('Start') }}</th>
                                        <th class="p-3 text-right">{{ tr('End') }}</th>
                                        <th class="p-3 text-right">{{ tr('Apply') }}</th>
                                        <th class="p-3 text-right">{{ tr('Deduction %') }}</th>
                                        <th class="p-3 text-right">{{ tr('Actions') }}</th>
                                    </tr>
                                </x-slot>

                                <x-slot name="body">
                                    @forelse($copyRows as $r)
                                        @php
                                            $apply = (string) ($r->apply_on ?? 'absence');
                                            $percent = 0.0;
                                            if($apply === 'absence') $percent = (float) $r->absence_multiplier * 100.0;
                                            if($apply === 'late') $percent = (float) $r->late_multiplier * 100.0;
                                            $isWithout = ($apply === 'none') || ($percent <= 0);

                                            $applyLabel = $apply === 'absence'
                                                ? tr('Absence')
                                                : ($apply === 'late'
                                                    ? tr('Late')
                                                    : tr('Without Deduction'));
                                        @endphp

                                        <tr class="border-t">
                                            <td class="p-3">
                                                <input
                                                    type="checkbox"
                                                    class="rounded cursor-pointer"
                                                    wire:model.live="copySelected"
                                                    value="{{ $r->id }}"
                                                >
                                            </td>

                                            <td class="p-3">
                                                <div class="font-semibold">{{ $r->name }}</div>
                                                @if(!empty($r->description))
                                                    <div class="text-xs text-gray-500 line-clamp-1">{{ $r->description }}</div>
                                                @endif
                                            </td>

                                            <td class="p-3">{{ optional($r->start_date)->format('Y-m-d') }}</td>
                                            <td class="p-3">{{ optional($r->end_date ?? $r->start_date)->format('Y-m-d') }}</td>

                                            <td class="p-3">{{ $applyLabel }}</td>

                                            <td class="p-3">
                                                {{ $isWithout ? '—' : number_format($percent, 2) . '%' }}
                                            </td>

                                            <td class="p-3 text-right">
                                                <x-ui.secondary-button
                                                    type="button"
                                                    wire:click="copyOneDay({{ $r->id }})"
                                                    :arrow="false"
                                                    :fullWidth="false"
                                                    class="!px-3 !py-1.5 !text-xs !rounded-lg !gap-2 cursor-pointer"
                                                >
                                                    <i class="fas fa-copy text-xs"></i>
                                                    <span>{{ tr('Copy') }}</span>
                                                </x-ui.secondary-button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td class="p-8 text-center text-gray-500" colspan="7">
                                                {{ tr('No records found for selected year.') }}
                                            </td>
                                        </tr>
                                    @endforelse
                                </x-slot>
                            </x-ui.table>
                        </div>
                    </x-ui.card>

                </div>
            </x-slot>

            <x-slot name="footer">
                <x-ui.secondary-button type="button" wire:click="$set('showCopyModal', false)" class="cursor-pointer">
                    {{ tr('Close') }}
                </x-ui.secondary-button>
            </x-slot>
        </x-ui.modal>

    </div>

    {{-- Confirm Dialogs --}}
    <x-ui.confirm-dialog
        id="exceptional-day-delete"
        type="danger"
        icon="fa-trash"
        :title="tr('Delete Exceptional Day')"
        :message="tr('Are you sure you want to delete this exceptional day?')"
        :confirmText="tr('Delete')"
        :cancelText="tr('Cancel')"
        confirmAction="wire:deleteRow(__ID__)"
    />

    <x-ui.confirm-dialog
        id="exceptional-day-bulk-delete"
        type="danger"
        icon="fa-trash"
        :title="tr('Delete Selected')"
        :message="tr('Are you sure you want to delete selected records?')"
        :confirmText="tr('Delete')"
        :cancelText="tr('Cancel')"
        confirmAction="wire:deleteSelected()"
    />

    <x-ui.confirm-dialog
        id="exceptional-day-bulk-enable"
        type="success"
        icon="fa-toggle-on"
        :title="tr('Enable Selected')"
        :message="tr('Enable selected records?')"
        :confirmText="tr('Enable')"
        :cancelText="tr('Cancel')"
        confirmAction="wire:setSelectedActive(true)"
    />

    <x-ui.confirm-dialog
        id="exceptional-day-bulk-disable"
        type="warning"
        icon="fa-toggle-off"
        :title="tr('Disable Selected')"
        :message="tr('Disable selected records?')"
        :confirmText="tr('Disable')"
        :cancelText="tr('Cancel')"
        confirmAction="wire:setSelectedActive(false)"
    />

</div>