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
                class="!px-4 !py-2 !text-sm !rounded-xl !gap-2"
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
                        class="!px-4 !py-2 !text-sm !rounded-xl !gap-2"
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
                        class="!px-4 !py-2 !text-sm !rounded-xl !gap-2"
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
                        class="!px-4 !py-2 !text-sm !rounded-xl !gap-2"
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
                            class="!px-4 !py-2 !text-sm !rounded-xl !gap-2"
                        >
                            <i class="fas fa-toggle-on text-xs"></i>
                            <span>{{ tr('Enable') }}</span>
                        </x-ui.secondary-button>

                        <x-ui.secondary-button
                            type="button"
                            onclick="window.dispatchEvent(new CustomEvent('open-confirm-exceptional-day-bulk-disable'));"
                            :arrow="false"
                            :fullWidth="false"
                            class="!px-4 !py-2 !text-sm !rounded-xl !gap-2"
                        >
                            <i class="fas fa-toggle-off text-xs"></i>
                            <span>{{ tr('Disable') }}</span>
                        </x-ui.secondary-button>

                        <x-ui.secondary-button
                            type="button"
                            onclick="window.dispatchEvent(new CustomEvent('open-confirm-exceptional-day-bulk-delete'));"
                            :arrow="false"
                            :fullWidth="false"
                            class="!px-4 !py-2 !text-sm !rounded-xl !gap-2 !text-red-600"
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
                    <x-ui.input type="number" :placeholder="tr('Year')" wire:model.live="year" min="2000" max="2100" />
                    <div class="mt-1 text-xs text-gray-500">{{ tr('Year') }}</div>
                </div>

                <div>
                    <x-ui.input type="number" :placeholder="tr('Month')" wire:model.live="month" min="1" max="12" />
                    <div class="mt-1 text-xs text-gray-500">{{ tr('Month') }}</div>
                </div>

                <div>
                    <x-ui.select wire:model.live="status">
                        <option value="all">{{ tr('All') }}</option>
                        <option value="current">{{ tr('Active (Current)') }}</option>
                        <option value="upcoming">{{ tr('Upcoming') }}</option>
                        <option value="ended">{{ tr('Ended') }}</option>
                    </x-ui.select>
                    <div class="mt-1 text-xs text-gray-500">{{ tr('Status') }}</div>
                </div>

                <div>
                    <x-ui.select wire:model.live="deductionType">
                        <option value="all">{{ tr('All Types') }}</option>
                        <option value="absence">{{ tr('Absence') }}</option>
                        <option value="late">{{ tr('Late') }}</option>
                        <option value="without">{{ tr('Without Deduction') }}</option>
                    </x-ui.select>
                    <div class="mt-1 text-xs text-gray-500">{{ tr('Apply Type') }}</div>
                </div>

                <div>
                    <x-ui.input type="number" step="0.01" min="0" max="1000" wire:model.live="minMultiplier" :placeholder="tr('Min %')" />
                    <div class="mt-1 text-xs text-gray-500">{{ tr('Min Deduction %') }}</div>
                </div>

                <div>
                    <x-ui.input type="number" step="0.01" min="0" max="1000" wire:model.live="maxMultiplier" :placeholder="tr('Max %')" />
                    <div class="mt-1 text-xs text-gray-500">{{ tr('Max Deduction %') }}</div>
                </div>

                <div class="md:col-span-2">
                    <x-ui.select wire:model.live="departmentId">
                        <option value="">{{ tr('All Departments') }}</option>
                        @foreach($departmentsOptions as $d)
                            <option value="{{ $d->id }}">{{ $d->name }}</option>
                        @endforeach
                    </x-ui.select>
                    <div class="mt-1 text-xs text-gray-500">{{ tr('Target Department') }}</div>
                </div>

                <div class="md:col-span-2">
                    <x-ui.select wire:model.live="branchId">
                        <option value="">{{ tr('All Branches') }}</option>
                        @foreach($branchesOptions as $b)
                            <option value="{{ $b->id }}">{{ $b->name }}</option>
                        @endforeach
                    </x-ui.select>
                    <div class="mt-1 text-xs text-gray-500">{{ tr('Target Branch') }}</div>
                </div>

                <div class="md:col-span-2">
                    <x-ui.select wire:model.live="contractType">
                        <option value="">{{ tr('All Contract Types') }}</option>
                        @foreach($contractTypesOptions as $ct)
                            <option value="{{ $ct->id }}">{{ $ct->name }}</option>
                        @endforeach
                    </x-ui.select>
                    <div class="mt-1 text-xs text-gray-500">{{ tr('Target Contract Type') }}</div>
                </div>

                <div class="md:col-span-6">
                    <x-ui.search-box wire:model.live.debounce.300ms="search" :placeholder="tr('Search by name/description...')" />
                    <div class="mt-1 text-xs text-gray-500">{{ tr('Search') }}</div>
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
        <x-ui.card class="!p-0 overflow-hidden">
            <div class="overflow-x-auto">
                <x-ui.table>
                    <x-slot name="head">
                        <tr>
                            <th class="p-3 text-right w-10">
                                <input type="checkbox" wire:model.live="selectPage" class="rounded">
                            </th>
                            <th class="p-3 text-right">{{ tr('Name') }}</th>
                            <th class="p-3 text-right">{{ tr('Period') }}</th>
                            <th class="p-3 text-right">{{ tr('Start') }}</th>
                            <th class="p-3 text-right">{{ tr('End') }}</th>
                            <th class="p-3 text-right">{{ tr('Apply') }}</th>
                            <th class="p-3 text-right">{{ tr('Deduction %') }}</th>
                            <th class="p-3 text-right">{{ tr('Grace Hours') }}</th>
                            <th class="p-3 text-right">{{ tr('Scope') }}</th>
                            <th class="p-3 text-right">{{ tr('Notified') }}</th>
                            <th class="p-3 text-right">{{ tr('Created By') }}</th>
                            <th class="p-3 text-right">{{ tr('Created At') }}</th>
                            <th class="p-3 text-right">{{ tr('Active') }}</th>
                            <th class="p-3 text-right">{{ tr('Actions') }}</th>
                        </tr>
                    </x-slot>

                    <x-slot name="body">
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

                            <tr class="border-t">
                                <td class="p-3">
                                    <input type="checkbox" wire:model.live="selected" value="{{ $row->id }}" class="rounded">
                                </td>

                                <td class="p-3">
                                    <div class="font-semibold">{{ $row->name }}</div>
                                    @if(!empty($row->description))
                                        <div class="text-xs text-gray-500 line-clamp-2">{{ $row->description }}</div>
                                    @endif
                                </td>

                                <td class="p-3">
                                    {{ ($row->period_type === 'single') ? tr('Single Day') : tr('Range') }}
                                </td>

                                <td class="p-3">{{ optional($row->start_date)->format('Y-m-d') }}</td>
                                <td class="p-3">{{ optional($row->end_date ?? $row->start_date)->format('Y-m-d') }}</td>

                                <td class="p-3">{{ $applyLabel }}</td>

                                <td class="p-3">
                                    {{ $isWithout ? '—' : number_format($percent, 2) . '%' }}
                                </td>

                                <td class="p-3">
                                    {{ ($apply === 'late' && !$isWithout) ? (int) $row->grace_hours : '—' }}
                                </td>

                                <td class="p-3">{{ $scopeLabel }}</td>

                                <td class="p-3">
                                    {{ $row->notified_at ? tr('Sent') : tr('Not Sent') }}
                                </td>

                                <td class="p-3">
                                    {{ $createdByMap[$row->created_by] ?? ($row->created_by ?? '—') }}
                                </td>

                                <td class="p-3">
                                    {{ optional($row->created_at)->format('Y-m-d') ?? '—' }}
                                </td>

                                <td class="p-3">
                                    @can('settings.attendance.manage')
                                    <button
                                        type="button"
                                        wire:click="toggleActive({{ $row->id }})"
                                        class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs border
                                            {{ $row->is_active ? 'bg-green-50 border-green-200 text-green-700' : 'bg-gray-50 border-gray-200 text-gray-700' }}"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full {{ $row->is_active ? 'bg-green-500' : 'bg-gray-400' }}"></span>
                                        {{ $row->is_active ? tr('Enabled') : tr('Disabled') }}
                                    </button>
                                    @else
                                    <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs border
                                            {{ $row->is_active ? 'bg-green-50 border-green-200 text-green-700' : 'bg-gray-50 border-gray-200 text-gray-700' }}"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full {{ $row->is_active ? 'bg-green-500' : 'bg-gray-400' }}"></span>
                                        {{ $row->is_active ? tr('Enabled') : tr('Disabled') }}
                                    </span>
                                    @endcan
                                </td>

                                {{-- ✅ FIXED Actions: Teleport dropdown خارج الجدول (بدون حاوية داخل حاوية) --}}
                                <td class="p-3 text-right">
                                    @can('settings.attendance.manage')
                                    <div
                                        class="inline-flex"
                                        x-data="{
                                            open: false,
                                            top: 0,
                                            left: 0,
                                            toggle() {
                                                this.open = !this.open;
                                                if (this.open) this.$nextTick(() => this.reposition());
                                            },
                                            close() { this.open = false; },
                                            reposition() {
                                                const btn = this.$refs.btn;
                                                const menu = this.$refs.menu;
                                                if (!btn || !menu) return;

                                                const r = btn.getBoundingClientRect();
                                                const gap = 8;

                                                const mw = menu.offsetWidth || 200;
                                                const mh = menu.offsetHeight || 160;

                                                let left = r.right - mw;

                                                left = Math.max(8, Math.min(left, window.innerWidth - mw - 8));

                                                let top = r.bottom + gap;

                                                if (top + mh > window.innerHeight - 8) {
                                                    top = Math.max(8, r.top - mh - gap);
                                                }

                                                this.left = Math.round(left);
                                                this.top  = Math.round(top);
                                            },
                                        }"
                                        @keydown.escape.window="close()"
                                        @scroll.window="open && reposition()"
                                        @resize.window="open && reposition()"
                                        @click.window="
                                            if (!open) return;
                                            const t = $event.target;
                                            if ($refs.btn && $refs.btn.contains(t)) return;
                                            if ($refs.menu && $refs.menu.contains(t)) return;
                                            close();
                                        "
                                    >
                                        {{-- زر النقاط --}}
                                        <button
                                            type="button"
                                            x-ref="btn"
                                            @click="toggle()"
                                            class="inline-flex items-center justify-center w-9 h-9 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-purple-200"
                                            aria-label="{{ tr('Actions') }}"
                                        >
                                            <i class="fas fa-ellipsis-v text-sm opacity-80"></i>
                                        </button>

                                        {{-- القائمة (Teleported) --}}
                                        <template x-teleport="body">
                                            <div
                                                x-cloak
                                                x-show="open"
                                                x-transition.origin.top.right
                                                x-ref="menu"
                                                class="fixed z-[9999] min-w-[180px] rounded-xl border border-gray-200 bg-white shadow-lg overflow-hidden"
                                                :style="`top:${top}px; left:${left}px;`"
                                                role="menu"
                                                aria-label="{{ tr('Actions') }}"
                                            >
                                                <button
                                                    type="button"
                                                    role="menuitem"
                                                    class="w-full flex items-center gap-2 px-3 py-2 text-sm hover:bg-gray-50"
                                                    @click="close(); $wire.openEdit({{ $row->id }})"
                                                >
                                                    <i class="fas fa-pen text-xs opacity-80"></i>
                                                    <span>{{ tr('Edit') }}</span>
                                                </button>

                                                <button
                                                    type="button"
                                                    role="menuitem"
                                                    class="w-full flex items-center gap-2 px-3 py-2 text-sm text-red-600 hover:bg-red-50"
                                                    @click="close(); window.dispatchEvent(new CustomEvent('open-confirm-exceptional-day-delete', { detail: { id: {{ $row->id }} } }));"
                                                >
                                                    <i class="fas fa-trash text-xs opacity-80"></i>
                                                    <span>{{ tr('Delete') }}</span>
                                                </button>
                                            </div>
                                        </template>
                                    </div>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="p-8 text-center text-gray-500" colspan="14">
                                    {{ tr('No records found.') }}
                                </td>
                            </tr>
                        @endforelse
                    </x-slot>
                </x-ui.table>
            </div>

            <div class="border-t p-4">
                {{ $rows->links() }}
            </div>
        </x-ui.card>

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
                                <input type="checkbox" wire:model.defer="form.is_active" id="is_active" class="rounded">
                                <label for="is_active" class="text-sm">{{ tr('Enabled') }}</label>
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
                <x-ui.primary-button type="button" wire:click="save">
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
                                    class="w-full justify-center"
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
                                            <input type="checkbox" wire:model.live="copySelectAll" class="rounded">
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
                                                    class="rounded"
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
                                                    class="!px-3 !py-1.5 !text-xs !rounded-lg !gap-2"
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
                <x-ui.secondary-button type="button" wire:click="$set('showCopyModal', false)">
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