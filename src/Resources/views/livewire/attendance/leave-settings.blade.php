@php
    $locale = app()->getLocale();
    $isRtl = in_array(substr($locale, 0, 2), ['ar', 'fa', 'ur', 'he']);
    $selectedYear = $years->firstWhere('id', $selectedYearId);

    $contractList = [
        'permanent' => tr('Permanent'),
        'temporary' => tr('Temporary'),
        'probation' => tr('Probation'),
        'contractor' => tr('Contractor'),
        'training' => tr('Training'),
        'freelancer_remote' => tr('Freelancer (Remote)'),
    ];
    $canManageAttendance = auth()->user()?->canAny(['settings.attendance.leaves.manage']) ?? false;
@endphp
<div class="space-y-6" wire:poll.15s>

    <x-ui.toast />
    @section('topbar-left-content')
        <div class="flex flex-col gap-3 {{ $isRtl ? 'items-end text-right' : 'items-start text-left' }}">
            <x-ui.page-header :title="tr('Leave Settings')" :subtitle="tr('Define leave types, yearly policies, and rules')"
                class="!flex-col {{ $isRtl ? '!items-end !text-right' : '!items-start !text-left' }} !justify-start !gap-1"
                titleSize="xl" />

            {{-- ✅ Tabs: Leave Settings | Permission Settings --}}
            <div class="flex w-full sm:inline-flex overflow-x-auto no-scrollbar rounded-2xl border border-gray-200 bg-white/70 p-1 shadow-sm">
                <a href="{{ request()->fullUrlWithQuery(['tab' => 'leaves']) }}" wire:navigate
                    class="whitespace-nowrap flex-1 sm:flex-none text-center px-4 py-2 text-xs font-black rounded-xl transition-all cursor-pointer
                    {{ $tab === 'leaves'
                        ? 'text-white shadow-sm bg-[color:var(--accent-orange)]'
                        : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100/50' }}">
                    {{ tr('Leave Settings') }}
                </a>

                <a href="{{ request()->fullUrlWithQuery(['tab' => 'permissions']) }}" wire:navigate
                    class="whitespace-nowrap flex-1 sm:flex-none text-center px-4 py-2 text-xs font-black rounded-xl transition-all cursor-pointer
                    {{ $tab === 'permissions'
                        ? 'text-white shadow-sm bg-[color:var(--accent-orange)]'
                        : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100/50' }}">
                    {{ tr('Permission Settings') }}
                </a>
            </div>
        </div>
    @endsection


    @section('topbar-actions')
        <x-ui.secondary-button href="{{ route('company-admin.settings.attendance') }}" :arrow="false" :fullWidth="false"
            class="!px-4 !py-2 !text-sm !rounded-xl !gap-2 cursor-pointer">
            <i class="fas {{ $isRtl ? 'fa-arrow-right' : 'fa-arrow-left' }} text-xs"></i>
            <span>{{ tr('Back') }}</span>
        </x-ui.secondary-button>
    @endsection

    @if (($tab ?? 'leaves') === 'leaves')
        <x-ui.card>
            <div class="space-y-4">

                {{-- Year Selector --}}
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                        <div class="flex items-center justify-between sm:justify-start gap-2">
                            <x-ui.secondary-button :fullWidth="false" wire:click="prevYear" class="cursor-pointer">
                                <i class="fas {{ $isRtl ? 'fa-chevron-right' : 'fa-chevron-left' }}"></i>
                            </x-ui.secondary-button>

                            <div
                                class="flex-1 sm:flex-none justify-center px-4 py-2 rounded-2xl bg-gray-50 border border-gray-200 text-sm font-black text-gray-800 flex items-center gap-2">
                                @if ($showAllYears)
                                    <span>{{ tr('All Years') }}</span>
                                @else
                                    <span>{{ $selectedYear?->year ?? '—' }}</span>
                                @endif

                                @if (!$showAllYears && $selectedYear?->is_active)
                                    <span
                                        class="text-[10px] font-black px-2 py-0.5 rounded-full bg-[color:var(--success)]/10 text-[color:var(--success)] border border-[color:var(--success)]/20">
                                        {{ tr('Active') }}
                                    </span>
                                @endif
                            </div>

                            <x-ui.secondary-button :fullWidth="false" wire:click="nextYear" class="cursor-pointer">
                                <i class="fas {{ $isRtl ? 'fa-chevron-left' : 'fa-chevron-right' }}"></i>
                            </x-ui.secondary-button>
                        </div>

                        <button type="button" wire:click="toggleAllYears"
                            class="w-full sm:w-auto flex justify-center items-center gap-2 text-xs font-bold transition-all px-3 py-2 rounded-xl border {{ $showAllYears ? 'bg-[color:var(--accent-orange)]/10 text-[color:var(--accent-orange)] border-[color:var(--accent-orange)]/30 hover:bg-[color:var(--accent-orange)]/20' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300 hover:bg-gray-50 hover:text-gray-900 shadow-sm' }} cursor-pointer">
                            <i class="fas {{ $showAllYears ? 'fa-calendar-day' : 'fa-bars-staggered' }}"></i>
                            <span>{{ $showAllYears ? tr('Show single year') : tr('Show all years') }}</span>
                        </button>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:flex xl:flex-nowrap xl:items-center gap-2">
                        @if($canManageAttendance)
                            <x-ui.secondary-button :fullWidth="false" wire:click="openYears" class="cursor-pointer">
                                <i class="fas fa-calendar"></i>
                                <span class="ms-2">{{ tr('Manage Years') }}</span>
                            </x-ui.secondary-button>

                            <x-ui.secondary-button :fullWidth="false" wire:click="openCompare" class="cursor-pointer">
                                <i class="fas fa-scale-balanced"></i>
                                <span class="ms-2">{{ tr('Compare Years') }}</span>
                            </x-ui.secondary-button>

                            <x-ui.secondary-button :fullWidth="false" wire:click="exportPolicies" class="cursor-pointer">
                                <i class="fas fa-file-export"></i>
                                <span class="ms-2">{{ tr('Export Policies') }}</span>
                            </x-ui.secondary-button>

                            <x-ui.primary-button :arrow="false" :fullWidth="false" wire:click="openCreate"
                                class="cursor-pointer">
                                <i class="fas fa-plus"></i>
                                <span class="ms-2">{{ tr('New Leave Type') }}</span>
                            </x-ui.primary-button>
                        @endif
                    </div>
                </div>

                {{-- Search & Filters --}}
                <div class="grid grid-cols-1 xl:grid-cols-12 gap-4 items-end">
                    {{-- Filters --}}
                    <div class="xl:col-span-8 {{ $isRtl ? 'xl:order-2' : '' }}">
                        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                            <x-ui.filter-select model="filterStatus" :label="tr('Status')" :placeholder="tr('All Statuses')"
                                :options="[
                                    ['value' => 'all', 'label' => tr('All Statuses')],
                                    ['value' => 'active', 'label' => tr('Active')],
                                    ['value' => 'inactive', 'label' => tr('Inactive')],
                                ]" width="full" :defer="false" :applyOnChange="true"
                                class="cursor-pointer" />

                            <x-ui.filter-select model="filterGender" :label="tr('Gender')" :placeholder="tr('All')"
                                :options="[
                                    ['value' => 'all', 'label' => tr('All')],
                                    ['value' => 'male', 'label' => tr('Male')],
                                    ['value' => 'female', 'label' => tr('Female')],
                                ]" width="full" :defer="false" :applyOnChange="true"
                                class="cursor-pointer" />

                            <x-ui.filter-select model="filterShowInApp" :label="tr('Show in App')" :placeholder="tr('All')"
                                :options="[
                                    ['value' => 'all', 'label' => tr('All')],
                                    ['value' => 'yes', 'label' => tr('Yes')],
                                    ['value' => 'no', 'label' => tr('No')],
                                ]" width="full" :defer="false" :applyOnChange="true"
                                class="cursor-pointer" />

                            <x-ui.filter-select model="filterAttachments" :label="tr('Attachments')" :placeholder="tr('All')"
                                :options="[
                                    ['value' => 'all', 'label' => tr('All')],
                                    ['value' => 'yes', 'label' => tr('Required')],
                                    ['value' => 'no', 'label' => tr('Not required')],
                                ]" width="full" :defer="false" :applyOnChange="true"
                                class="cursor-pointer" />
                        </div>
                    </div>

                    {{-- Search --}}
                    <div class="xl:col-span-4 min-w-0 {{ $isRtl ? 'xl:order-1' : '' }}">
                        <x-ui.search-box model="search" placeholder="{{ tr('Search by leave name...') }}"
                            :debounce="300" />
                    </div>
                </div>

                {{-- Clear Filters Button --}}
                <div x-data="{
                    hasFilters() {
                        return ($wire.filterStatus && $wire.filterStatus !== 'all') ||
                            ($wire.filterGender && $wire.filterGender !== 'all') ||
                            ($wire.filterShowInApp && $wire.filterShowInApp !== 'all') ||
                            ($wire.filterAttachments && $wire.filterAttachments !== 'all') ||
                            ($wire.filterYearId && $wire.filterYearId !== 'all') ||
                            ($wire.search && $wire.search.trim() !== '');
                    }
                }" x-show="hasFilters()" x-transition class="flex items-center justify-end mt-2">
                    <button type="button" wire:click="clearAllFilters" wire:loading.attr="disabled"
                        wire:target="clearAllFilters"
                        class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-semibold text-gray-600 hover:text-gray-900 transition-colors disabled:opacity-50 cursor-pointer">
                        <i class="fas fa-times" wire:loading.remove wire:target="clearAllFilters"></i>
                        <i class="fas fa-spinner fa-spin" wire:loading wire:target="clearAllFilters"></i>
                        <span wire:loading.remove wire:target="clearAllFilters">{{ tr('Clear all filters') }}</span>
                        <span wire:loading wire:target="clearAllFilters">{{ tr('Clearing...') }}</span>
                    </button>
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
                                {{ tr('Leave') }}</th>
                            <th
                                class="min-w-[100px] px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center whitespace-nowrap">
                                {{ tr('Days') }}</th>
                            <th
                                class="min-w-[100px] px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center whitespace-nowrap">
                                {{ tr('Year') }}</th>
                            <th
                                class="min-w-[100px] px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center whitespace-nowrap">
                                {{ tr('Gender') }}</th>
                            <th
                                class="min-w-[100px] px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center whitespace-nowrap">
                                {{ tr('Status') }}</th>
                            <th
                                class="min-w-[120px] px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center whitespace-nowrap">
                                {{ tr('Show in App') }}</th>
                            <th
                                class="min-w-[120px] px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center whitespace-nowrap">
                                {{ tr('Attachments') }}</th>
                            <th
                                class="min-w-[100px] px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-end whitespace-nowrap">
                                {{ tr('Actions') }}</th>
                        </tr>
                    </thead>
    
                    <tbody class="divide-y divide-gray-50">
                        @forelse($rows as $row)
                            <tr wire:key="leave-policy-{{ $row->id }}"
                                class="hover:bg-gray-50/40 transition-colors cursor-default">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-bold text-gray-800 truncate">{{ $row->name }}</div>
                                    <div class="text-[10px] text-gray-400 truncate">{{ $row->description ?? '' }}</div>
                                </td>
    
                                <td class="px-6 py-4 text-center whitespace-nowrap">
                                    <span class="text-xs font-bold text-gray-800">{{ $row->days_per_year }}</span>
                                </td>
    
                                <td class="px-6 py-4 text-center whitespace-nowrap">
                                    <span class="text-xs font-semibold text-gray-700">{{ $row->year?->year ?? '—' }}</span>
                                </td>
    
                                <td class="px-6 py-4 text-center whitespace-nowrap">
                                    <span class="text-xs font-semibold text-gray-700">{{ $row->gender }}</span>
                                </td>
    
                                <td class="px-6 py-4 text-center whitespace-nowrap">
                                    <span
                                        class="text-xs font-bold {{ $row->is_active ? 'text-[color:var(--success)]' : 'text-gray-400' }}">
                                        {{ $row->is_active ? tr('Active') : tr('Inactive') }}
                                    </span>
                                </td>
    
                                <td class="px-6 py-4 text-center whitespace-nowrap">
                                    <span
                                        class="text-xs font-bold {{ $row->show_in_app ? 'text-[color:var(--success)]' : 'text-gray-400' }}">
                                        {{ $row->show_in_app ? tr('Yes') : tr('No') }}
                                    </span>
                                </td>
    
                                <td class="px-6 py-4 text-center whitespace-nowrap">
                                    <span
                                        class="text-xs font-bold {{ $row->requires_attachment ? 'text-[color:var(--warning)]' : 'text-gray-400' }}">
                                        {{ $row->requires_attachment ? tr('Yes') : tr('No') }}
                                    </span>
                                </td>
    
                                @php
                                    $isAnnualDefault =
                                        (string) data_get($row->settings ?? [], 'meta.system_key', '') ===
                                            'annual_default' || trim((string) $row->name) === 'سنوية' || trim((string) $row->name) === 'إجازة سنوية';
@endphp
    
                                <td class="px-6 py-4 text-end whitespace-nowrap">
                                    @if($canManageAttendance)
                                        <x-ui.actions-menu>
                                            <x-ui.dropdown-item wire:click="openEdit({{ (int) $row->id }})"
                                                class="cursor-pointer">
                                                <i class="fas fa-edit me-2 text-[color:var(--accent-orange)]"></i> {{ tr('Edit') }}
                                            </x-ui.dropdown-item>
    
                                            @if (!$isAnnualDefault)
                                                <x-ui.dropdown-item danger
                                                    @click.stop="$dispatch('open-confirm-delete-leave', { id: '{{ (int) $row->id }}' })"
                                                    class="cursor-pointer">
                                                    <i class="fas fa-trash-alt me-2 text-[color:var(--error)]"></i> {{ tr('Delete') }}
                                                </x-ui.dropdown-item>
                                            @endif
                                        </x-ui.actions-menu>
                                    @endif
                                </td>
                            </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-24 text-center">
                                <div class="opacity-20 flex flex-col items-center">
                                    <div
                                        class="w-24 h-24 bg-gray-50 rounded-full flex items-center justify-center text-4xl mb-4 border border-gray-100">
                                        <i class="fas fa-calendar-times"></i>
                                    </div>
                                    <h4 class="text-base font-bold text-gray-800">{{ tr('No Leave Policies Found') }}
                                    </h4>
                                    <p class="text-xs max-w-[360px] mt-2 leading-relaxed">
                                        {{ tr('No leave policies have been defined yet. Start by creating the first leave type for the selected year.') }}
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



        {{-- Create Modal --}}
        <template x-teleport="body">
            <x-ui.modal wire:model="createOpen" maxWidth="4xl">
                <x-slot:title>
                    <div class="flex items-center gap-3">
                        <div
                            class="w-10 h-10 bg-[color:var(--accent-orange)]/10 text-[color:var(--accent-orange)] rounded-xl flex items-center justify-center text-lg border border-[color:var(--accent-orange)]/20 shadow-sm">
                            <i class="fas fa-umbrella-beach"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-900 text-lg leading-tight">{{ tr('New Leave Type') }}</h3>
                            <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">
                                {{ tr('Create a leave policy for the selected year') }}</p>
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
                                <x-ui.input label="{{ tr('Name') }}" wire:model.defer="name"
                                    placeholder="{{ tr('Leave name...') }}" :disabled="!$canManageAttendance" />
                            </div>

                            <div class="md:col-span-2">
                                <x-ui.input label="{{ tr('Description') }}" wire:model.defer="description"
                                    placeholder="{{ tr('Optional description...') }}" :disabled="!$canManageAttendance" />
                            </div>

                            <div>
                                <x-ui.input type="number" step="0.5" min="0"
                                    label="{{ tr('Days per year') }}" wire:model.defer="days_per_year"
                                    :disabled="!$canManageAttendance" />
                            </div>

                            <div>
                                <x-ui.select label="{{ tr('Gender') }}" wire:model.defer="gender"
                                    :disabled="!$canManageAttendance">
                                    <option value="all">{{ tr('All') }}</option>
                                    <option value="male">{{ tr('Male') }}</option>
                                    <option value="female">{{ tr('Female') }}</option>
                                </x-ui.select>
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
                                <x-ui.select label="{{ tr('Deduction policy') }}" wire:model.defer="deduction_policy"
                                    :disabled="!$canManageAttendance">
                                    <option value="balance_only">{{ tr('Deduct from balance only') }}</option>
                                    <option value="salary_after_balance">{{ tr('Deduct from salary after balance') }}
                                    </option>
                                </x-ui.select>
                            </div>

                            {{-- Duration --}}
                            <div>
                                <x-ui.select label="{{ tr('Duration type') }}" wire:model.defer="duration_unit"
                                    :disabled="!$canManageAttendance">
                                    <option value="full_day">{{ tr('Full day only') }}</option>
                                    <option value="half_day">{{ tr('Full day or half day') }}</option>
                                </x-ui.select>
                            </div>

                            {{-- Notice --}}
                            <div>
                                <x-ui.input type="number" min="0" label="{{ tr('Min notice (days)') }}"
                                    wire:model.defer="notice_min_days" :disabled="!$canManageAttendance" />
                            </div>

                            <div>
                                <x-ui.input type="number" min="0" label="{{ tr('Max advance (days)') }}"
                                    wire:model.defer="notice_max_advance_days" :disabled="!$canManageAttendance" />
                            </div>

                            <div class="md:col-span-2">
                                <label
                                    class="flex items-center gap-2 text-sm font-semibold text-gray-700 cursor-pointer">
                                    <input type="checkbox" wire:model.defer="allow_retroactive"
                                        class="cursor-pointer"
                                        @if(!$canManageAttendance) disabled @endif>
                                    <span>{{ tr('Allow retroactive requests') }}</span>
                                </label>
                                @error('allow_retroactive')
                                    <div class="text-xs text-[color:var(--error)] mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Contract Exclusions --}}
                            <div class="md:col-span-2 pt-3 border-t border-gray-100">
                                <div class="text-sm font-black text-gray-900 mb-2">
                                    {{ tr('Contract Type Exclusions') }}</div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                                    @foreach ($contractList as $value => $label)
                                        <label
                                            class="flex items-center gap-2 p-2 bg-gray-50/50 rounded-xl border border-gray-100 cursor-pointer hover:bg-gray-100 transition-all select-none">
                                            <input type="checkbox" value="{{ $value }}"
                                                wire:model.defer="selected_leave_excluded_contract_types"
                                                class="w-4 h-4 text-[color:var(--accent-orange)] rounded border-gray-300 focus:ring-[color:var(--accent-orange)]"
                                                @if(!$canManageAttendance) disabled @endif>
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

                                <label
                                    class="flex items-center gap-2 text-sm font-semibold text-gray-700 cursor-pointer">
                                    <input type="checkbox" wire:model.defer="note_required" class="cursor-pointer"
                                        @if(!$canManageAttendance) disabled @endif>
                                    <span>{{ tr('Mandatory note') }}</span>
                                </label>
                                @error('note_required')
                                    <div class="text-xs text-[color:var(--error)] mt-1">{{ $message }}</div>
                                @enderror

                                <div class="mt-3">
                                    <x-ui.textarea label="{{ tr('Note text') }}" wire:model.defer="note_text"
                                        rows="2" :disabled="!$canManageAttendance" />
                                </div>

                                <label
                                    class="flex items-center gap-2 text-sm font-semibold text-gray-700 mt-2 cursor-pointer">
                                    <input type="checkbox" wire:model.defer="note_ack_required"
                                        class="cursor-pointer"
                                        @if(!$canManageAttendance) disabled @endif>
                                    <span>{{ tr('Require acknowledgment of the note') }}</span>
                                </label>
                                @error('note_ack_required')
                                    <div class="text-xs text-[color:var(--error)] mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Attachments --}}
                            <div class="md:col-span-2 pt-3 border-t border-gray-100">
                                <div class="text-sm font-black text-gray-900 mb-2">{{ tr('Attachments Settings') }}
                                </div>

                                <div class="mb-3">
                                    <label
                                        class="flex items-center gap-2 text-sm font-semibold text-gray-700 cursor-pointer">
                                        <input type="checkbox" wire:model.defer="requires_attachment"
                                            class="cursor-pointer"
                                            @if(!$canManageAttendance) disabled @endif>
                                        <span>{{ tr('Requires attachment') }}</span>
                                    </label>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label
                                            class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Allowed types') }}</label>
                                        <div class="flex flex-wrap gap-3">
                                            <label
                                                class="flex items-center gap-2 text-sm font-semibold text-gray-700 cursor-pointer">
                                                <input type="checkbox" value="pdf"
                                                    wire:model.defer="attachment_types" class="cursor-pointer"
                                                    @if(!$canManageAttendance) disabled @endif>
                                                <span>PDF</span>
                                            </label>
                                            <label
                                                class="flex items-center gap-2 text-sm font-semibold text-gray-700 cursor-pointer">
                                                <input type="checkbox" value="jpg"
                                                    wire:model.defer="attachment_types" class="cursor-pointer"
                                                    @if(!$canManageAttendance) disabled @endif>
                                                <span>JPG</span>
                                            </label>
                                            <label
                                                class="flex items-center gap-2 text-sm font-semibold text-gray-700 cursor-pointer">
                                                <input type="checkbox" value="png"
                                                    wire:model.defer="attachment_types" class="cursor-pointer"
                                                    @if(!$canManageAttendance) disabled @endif>
                                                <span>PNG</span>
                                            </label>
                                        </div>
                                        @error('attachment_types')
                                            <div class="text-xs text-[color:var(--error)] mt-1">{{ $message }}</div>
                                        @enderror
                                        @error('attachment_types.*')
                                            <div class="text-xs text-[color:var(--error)] mt-1">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div>
                                        <label
                                            class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Max size (MB)') }}</label>
                                        <input type="text" value="2 MB" readonly
                                            class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-100/70 text-gray-700 cursor-not-allowed">
                                        <div class="text-[10px] text-gray-400 mt-1">
                                            {{ tr('Fixed size for all leave attachments.') }}
                                        </div>
                                    </div>

                                </div>
                            </div>

                            {{-- General Settings --}}
                            <div class="md:col-span-2 pt-3 border-t border-gray-100">
                                <div class="text-sm font-black text-gray-900 mb-2">{{ tr('General Settings') }}</div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <label
                                        class="flex items-center gap-2 text-sm font-semibold text-gray-700 cursor-pointer">
                                        <input type="checkbox" wire:model.defer="is_active" class="cursor-pointer"
                                            @if(!$canManageAttendance) disabled @endif>
                                        <span>{{ tr('Active') }}</span>
                                    </label>

                                    <label
                                        class="flex items-center gap-2 text-sm font-semibold text-gray-700 cursor-pointer">
                                        <input type="checkbox" wire:model.defer="show_in_app" class="cursor-pointer"
                                            @if(!$canManageAttendance) disabled @endif>
                                        <span>{{ tr('Show in App') }}</span>
                                    </label>
                                </div>
                            </div>

                            {{-- ✅ General Constraints REMOVED بالكامل --}}
                        </div>
                    </div>
                </x-slot:content>

                <x-slot:footer>
                    <div class="flex items-center justify-end gap-3 w-full">
                        <x-ui.secondary-button wire:click="closeCreate" class="!px-6 !rounded-xl cursor-pointer">
                            {{ tr('Cancel') }}
                        </x-ui.secondary-button>
                        @if($canManageAttendance)
                            <x-ui.primary-button wire:click="saveCreate" loading="saveCreate"
                                class="!px-6 !rounded-xl shadow-lg cursor-pointer">
                                <i class="fas fa-save me-2" wire:loading.remove wire:target="saveCreate"></i>
                                {{ tr('Save Policy') }}
                            </x-ui.primary-button>
                        @endif
                    </div>
                </x-slot:footer>
            </x-ui.modal>
        </template>

        {{-- Edit Modal --}}
        <template x-teleport="body">
            <x-ui.modal wire:model="editOpen" maxWidth="4xl">
                <x-slot:title>
                    <div class="flex items-center gap-3">
                        <div
                            class="w-10 h-10 bg-[color:var(--accent-orange)]/10 text-[color:var(--accent-orange)] rounded-xl flex items-center justify-center text-lg border border-[color:var(--accent-orange)]/20 shadow-sm">
                            <i class="fas fa-edit"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-900 text-lg leading-tight">{{ tr('Edit Leave Type') }}</h3>
                            <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">
                                {{ tr('Update leave policy fields') }}</p>
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
                                <x-ui.input label="{{ tr('Name') }}" wire:model.defer="name" :readonly="$editingNameLocked"
                                    :disabled="!$canManageAttendance" placeholder="{{ tr('Leave name...') }}" :hint="$editingNameLocked
                                        ? tr('This is a system annual policy name and cannot be changed.')
                                        : null" />
                            </div>

                            <div class="md:col-span-2">
                                <x-ui.input label="{{ tr('Description') }}" wire:model.defer="description"
                                    placeholder="{{ tr('Optional description...') }}" :disabled="!$canManageAttendance" />
                            </div>

                            @if (!$editingNameLocked)
                                <div>
                                    <x-ui.input type="number" step="0.5" min="0"
                                        label="{{ tr('Days per year') }}" wire:model.defer="days_per_year"
                                        :disabled="!$canManageAttendance" />
                                </div>
                            @endif

                            @if (!$editingNameLocked)
                                <div>
                                    <x-ui.select label="{{ tr('Gender') }}" wire:model.defer="gender"
                                        :disabled="!$canManageAttendance">
                                        <option value="all">{{ tr('All') }}</option>
                                        <option value="male">{{ tr('Male') }}</option>
                                        <option value="female">{{ tr('Female') }}</option>
                                    </x-ui.select>
                                </div>
                            @else
                                <div>
                                    <x-ui.input label="{{ tr('Gender') }}" value="{{ tr('All') }}" readonly
                                        disabled :hint="tr('Annual policy gender is fixed to All.')" />
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- ========================= --}}
                    {{-- ✅ Section 2: Annual Leave Settings (ONLY for السنوية) --}}
                    {{-- ========================= --}}
                    @if ($editingNameLocked)
                        <div class="mt-2 pt-4 border-t border-gray-100">
                            <div class="text-sm font-black text-gray-900 mb-3">{{ tr('Annual Leave Settings') }}
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <x-ui.input type="number" step="0.5" min="0"
                                        label="{{ tr('Days per year') }}"
                                        wire:model.live.debounce.150ms="days_per_year" :disabled="!$canManageAttendance" />
                                </div>

                                <div>
                                    <x-ui.select label="{{ tr('Accrual method') }}" wire:model.live="accrual_method"
                                        :disabled="!$canManageAttendance">
                                        <option value="annual_grant">{{ tr('Annual full grant') }}</option>
                                        <option value="monthly">{{ tr('Monthly accrual') }}</option>
                                    </x-ui.select>
                                </div>

                                {{-- Monthly rate --}}
                                <div>
                                    <x-ui.input label="{{ tr('Monthly rate') }}" wire:model="monthly_accrual_rate"
                                        readonly disabled placeholder="{{ tr('Auto calculated') }}"
                                        :hint="tr('Auto: Days per year ÷ 12')" />
                                </div>

                                <div>
                                    <x-ui.input type="number" step="0.5" min="0"
                                        label="{{ tr('Max balance') }}" wire:model.defer="max_balance"
                                        :disabled="!$canManageAttendance" />
                                </div>

                                <div>
                                    <label
                                        class="flex items-center gap-2 text-sm font-semibold text-gray-700 mb-2 cursor-pointer">
                                        <input type="checkbox" wire:model.live="allow_carryover"
                                            class="cursor-pointer"
                                            @if(!$canManageAttendance) disabled @endif>
                                        <span>{{ tr('Allow carryover') }}</span>
                                    </label>

                                    <x-ui.input type="number" step="0.5" min="0"
                                        label="{{ tr('Carryover days allowed') }}" wire:model.defer="carryover_days"
                                        :disabled="!$allow_carryover ||
                                            !$canManageAttendance" :hint="$allow_carryover
                                            ? tr('Set how many days can be carried over.')
                                            : tr('Carryover is disabled.')" />
                                </div>

                                <div class="md:col-span-2">
                                    <x-ui.select label="{{ tr('Weekend days') }}" wire:model.defer="weekend_policy"
                                        :disabled="!$canManageAttendance">
                                        <option value="exclude">{{ tr('Auto exclude') }}</option>
                                        <option value="include">{{ tr('Auto include') }}</option>
                                    </x-ui.select>
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
                                <x-ui.select label="{{ tr('Deduction policy') }}"
                                    wire:model.defer="deduction_policy" :disabled="!$canManageAttendance">
                                    <option value="balance_only">{{ tr('Deduct from balance only') }}</option>
                                    <option value="salary_after_balance">{{ tr('Deduct from salary after balance') }}
                                    </option>
                                </x-ui.select>
                            </div>

                            {{-- Duration --}}
                            <div>
                                <x-ui.select label="{{ tr('Duration type') }}" wire:model.defer="duration_unit"
                                    :disabled="!$canManageAttendance">
                                    <option value="full_day">{{ tr('Full day only') }}</option>
                                    <option value="half_day">{{ tr('Full day or half day') }}</option>
                                </x-ui.select>
                            </div>
                            {{-- Notice --}}
                            <div>
                                <x-ui.input type="number" min="0" label="{{ tr('Min notice (days)') }}"
                                    wire:model.defer="notice_min_days" :disabled="!$canManageAttendance" />
                            </div>

                            <div>
                                <x-ui.input type="number" min="0" label="{{ tr('Max advance (days)') }}"
                                    wire:model.defer="notice_max_advance_days" :disabled="!$canManageAttendance" />
                            </div>

                            <div class="md:col-span-2">
                                <label
                                    class="flex items-center gap-2 text-sm font-semibold text-gray-700 cursor-pointer">
                                    <input type="checkbox" wire:model.defer="allow_retroactive"
                                        class="cursor-pointer"
                                        @if(!$canManageAttendance) disabled @endif>
                                    <span>{{ tr('Allow retroactive requests') }}</span>
                                </label>
                                @error('allow_retroactive')
                                    <div class="text-xs text-[color:var(--error)] mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Contract Exclusions --}}
                            <div class="md:col-span-2 pt-3 border-t border-gray-100">
                                <div class="text-sm font-black text-gray-900 mb-2">
                                    {{ tr('Contract Type Exclusions') }}</div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                                    @foreach ($contractList as $value => $label)
                                        <label
                                            class="flex items-center gap-2 p-2 bg-gray-50/50 rounded-xl border border-gray-100 cursor-pointer hover:bg-gray-100 transition-all select-none">
                                            <input type="checkbox" value="{{ $value }}"
                                                wire:model.defer="selected_leave_excluded_contract_types"
                                                class="w-4 h-4 text-[color:var(--accent-orange)] rounded border-gray-300 focus:ring-[color:var(--accent-orange)]"
                                                @if(!$canManageAttendance) disabled @endif>
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

                                <label
                                    class="flex items-center gap-2 text-sm font-semibold text-gray-700 cursor-pointer">
                                    <input type="checkbox" wire:model.defer="note_required" class="cursor-pointer"
                                        @if(!$canManageAttendance) disabled @endif>
                                    <span>{{ tr('Mandatory note') }}</span>
                                </label>
                                @error('note_required')
                                    <div class="text-xs text-[color:var(--error)] mt-1">{{ $message }}</div>
                                @enderror

                                <div class="mt-3">
                                    <x-ui.textarea label="{{ tr('Note text') }}" wire:model.defer="note_text"
                                        rows="2" :disabled="!$canManageAttendance" />
                                </div>

                                <label
                                    class="flex items-center gap-2 text-sm font-semibold text-gray-700 mt-2 cursor-pointer">
                                    <input type="checkbox" wire:model.defer="note_ack_required"
                                        class="cursor-pointer"
                                        @if(!$canManageAttendance) disabled @endif>
                                    <span>{{ tr('Require acknowledgment of the note') }}</span>
                                </label>
                                @error('note_ack_required')
                                    <div class="text-xs text-[color:var(--error)] mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Attachments --}}
                            <div class="md:col-span-2 pt-3 border-t border-gray-100">
                                <div class="text-sm font-black text-gray-900 mb-2">{{ tr('Attachments Settings') }}
                                </div>

                                <div class="mb-3">
                                    <label
                                        class="flex items-center gap-2 text-sm font-semibold text-gray-700 cursor-pointer">
                                        <input type="checkbox" wire:model.defer="requires_attachment"
                                            class="cursor-pointer"
                                            @if(!$canManageAttendance) disabled @endif>
                                        <span>{{ tr('Requires attachment') }}</span>
                                    </label>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label
                                            class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Allowed types') }}</label>
                                        <div class="flex flex-wrap gap-3">
                                            <label
                                                class="flex items-center gap-2 text-sm font-semibold text-gray-700 cursor-pointer">
                                                <input type="checkbox" value="pdf"
                                                    wire:model.defer="attachment_types" class="cursor-pointer"
                                                    @if(!$canManageAttendance) disabled @endif>
                                                <span>PDF</span>
                                            </label>
                                            <label
                                                class="flex items-center gap-2 text-sm font-semibold text-gray-700 cursor-pointer">
                                                <input type="checkbox" value="jpg"
                                                    wire:model.defer="attachment_types" class="cursor-pointer"
                                                    @if(!$canManageAttendance) disabled @endif>
                                                <span>JPG</span>
                                            </label>
                                            <label
                                                class="flex items-center gap-2 text-sm font-semibold text-gray-700 cursor-pointer">
                                                <input type="checkbox" value="png"
                                                    wire:model.defer="attachment_types" class="cursor-pointer"
                                                    @if(!$canManageAttendance) disabled @endif>
                                                <span>PNG</span>
                                            </label>
                                        </div>
                                        @error('attachment_types')
                                            <div class="text-xs text-[color:var(--error)] mt-1">{{ $message }}</div>
                                        @enderror
                                        @error('attachment_types.*')
                                            <div class="text-xs text-[color:var(--error)] mt-1">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div>
                                        <label
                                            class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Max size (MB)') }}</label>
                                        <input type="text" value="2 MB" readonly
                                            class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-100/70 text-gray-700 cursor-not-allowed">
                                        <div class="text-[10px] text-gray-400 mt-1">
                                            {{ tr('Fixed size for all leave attachments.') }}
                                        </div>
                                    </div>

                                </div>
                            </div>

                            {{-- General Settings --}}
                            <div class="md:col-span-2 pt-3 border-t border-gray-100">
                                <div class="text-sm font-black text-gray-900 mb-2">{{ tr('General Settings') }}</div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <label
                                        class="flex items-center gap-2 text-sm font-semibold text-gray-700 cursor-pointer">
                                        <input type="checkbox" wire:model.defer="is_active" class="cursor-pointer"
                                            @if(!$canManageAttendance) disabled @endif>
                                        <span>{{ tr('Active') }}</span>
                                    </label>

                                    <label
                                        class="flex items-center gap-2 text-sm font-semibold text-gray-700 cursor-pointer">
                                        <input type="checkbox" wire:model.defer="show_in_app" class="cursor-pointer"
                                            @if(!$canManageAttendance) disabled @endif>
                                        <span>{{ tr('Show in App') }}</span>
                                    </label>
                                </div>
                            </div>

                            {{-- ✅ General Constraints REMOVED بالكامل --}}
                        </div>
                    </div>
                </x-slot:content>

                <x-slot:footer>
                    <div class="flex items-center justify-end gap-3 w-full">
                        <x-ui.secondary-button wire:click="closeEdit" class="!px-6 !rounded-xl cursor-pointer">
                            {{ tr('Cancel') }}
                        </x-ui.secondary-button>
                        @if($canManageAttendance)
                            <x-ui.primary-button wire:click="saveEdit" loading="saveEdit"
                                class="!px-6 !rounded-xl shadow-lg cursor-pointer">
                                <i class="fas fa-save me-2" wire:loading.remove wire:target="saveEdit"></i>
                                {{ tr('Update') }}
                            </x-ui.primary-button>
                        @endif
                    </div>
                </x-slot:footer>
            </x-ui.modal>
        </template>

        {{-- Delete Confirm Dialog --}}
        <template x-teleport="body">
            <x-ui.confirm-dialog id="delete-leave" confirmText="{{ tr('Yes, Delete') }}"
                cancelText="{{ tr('Cancel') }}" confirmAction="wire:deletePolicy(__ID__)"
                title="{{ tr('Delete Leave') }}"
                message="{{ tr('Are you sure you want to delete this leave type? This action cannot be reversed.') }}"
                type="danger" />
        </template>
        <template x-teleport="body">
            <x-ui.confirm-dialog id="delete-leave-year" confirmText="{{ tr('Yes, Delete') }}"
                cancelText="{{ tr('Cancel') }}" confirmAction="wire:deleteYear(__ID__)"
                title="{{ tr('Delete Year') }}"
                message="{{ tr('Are you sure you want to delete this year? This action cannot be reversed.') }}"
                type="danger" />
        </template>
        {{-- Manage Years Modal --}}
        <template x-teleport="body">
            <x-ui.modal wire:model="yearsOpen" maxWidth="2xl">
                <x-slot:title>
                    <div class="flex items-center gap-3">
                        <div
                            class="w-10 h-10 bg-[color:var(--accent-orange)]/10 text-[color:var(--accent-orange)] rounded-xl flex items-center justify-center text-lg border border-[color:var(--accent-orange)]/20 shadow-sm">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-900 text-lg leading-tight">{{ tr('Manage Years') }}</h3>
                            <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">
                                {{ tr('Add / activate / delete years') }}</p>
                        </div>
                    </div>
                </x-slot:title>

                <x-slot:content>
                    <div class="py-2 space-y-6">
                        {{-- Add new year --}}
                        <div class="space-y-4">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="w-1 h-3 bg-[color:var(--accent-orange)] rounded-full"></span>
                                <h4 class="text-xs font-black text-gray-700 uppercase tracking-wider">
                                    {{ tr('Add New Year') }}</h4>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <x-ui.select label="{{ tr('Year') }}" wire:model.defer="newYear"
                                    :disabled="!$canManageAttendance">
                                    <option value="">{{ tr('Select year') }}</option>
                                    @foreach ($this->availableYears as $y)
                                        <option value="{{ $y['value'] }}">{{ $y['label'] }}</option>
                                    @endforeach
                                </x-ui.select>

                                <x-ui.select label="{{ tr('Copy from year (optional)') }}"
                                    wire:model.defer="copyFromYearId" :disabled="!$canManageAttendance">
                                    <option value="">{{ tr('Do not copy') }}</option>
                                    @foreach ($years as $y)
                                        <option value="{{ (int) $y->id }}">{{ $y->year }}</option>
                                    @endforeach
                                </x-ui.select>

                                <div class="md:col-span-2 text-[11px] text-gray-500 font-semibold">
                                    {{ $this->yearRangeHint }}
                                </div>
                            </div>
                        </div>

                        {{-- Existing years list --}}
                        <div class="space-y-3 pt-4 border-t border-gray-100">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="w-1 h-3 bg-gray-500 rounded-full"></span>
                                <h4 class="text-xs font-black text-gray-700 uppercase tracking-wider">
                                    {{ tr('Existing Years') }}</h4>
                            </div>

                            <div
                                class="bg-gray-50/50 rounded-3xl border border-gray-100 p-4 space-y-2 max-h-[200px] overflow-y-auto custom-scrollbar">
                                @foreach ($years as $y)
                                    <div
                                        class="flex items-center justify-between gap-3 bg-white rounded-2xl border border-gray-100 px-4 py-3 shadow-sm hover:shadow-md transition-all">
                                        <div class="min-w-0">
                                            <div class="text-sm font-black text-gray-900 flex items-center gap-2">
                                                <span>{{ $y->year }}</span>
                                                @if ($y->is_active)
                                                    <span
                                                        class="text-[10px] font-black px-2 py-0.5 rounded-full bg-[color:var(--success)]/10 text-[color:var(--success)] border border-[color:var(--success)]/20">
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
                                            @if (!$y->is_active && (int) $y->year === (int) $this->currentCalendarYear)
                                                <button wire:click="setYearActive({{ (int) $y->id }})"
                                                    class="w-8 h-8 rounded-xl bg-gray-50 text-gray-400 hover:bg-[color:var(--success)]/10 hover:text-[color:var(--success)] border border-gray-100 transition-all flex items-center justify-center disabled:opacity-50 cursor-pointer"
                                                    title="{{ tr('Set Active') }}"
                                                    @if(!$canManageAttendance) disabled @endif>
                                                    <i class="fas fa-bolt text-xs"></i>
                                                </button>
                                            @endif

                                            <button type="button"
                                                @click.stop="$dispatch('open-confirm-delete-leave-year', { id: {{ (int) $y->id }} })"
                                                class="w-8 h-8 rounded-xl bg-gray-50 text-gray-400 hover:bg-[color:var(--error)]/10 hover:text-[color:var(--error)] border border-gray-100 transition-all flex items-center justify-center disabled:opacity-50 cursor-pointer"
                                                title="{{ tr('Delete') }}"
                                                @if(!$canManageAttendance) disabled @endif>
                                                <i class="fas fa-trash text-xs"></i>
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </x-slot:content>

                <x-slot:footer>
                    <div class="flex items-center justify-end gap-3 w-full">
                        <x-ui.secondary-button wire:click="closeYears" class="!px-6 !rounded-xl cursor-pointer">
                            {{ tr('Cancel') }}
                        </x-ui.secondary-button>

                        @if($canManageAttendance)
                            <x-ui.primary-button wire:click="saveYear" loading="saveYear"
                                class="!px-6 !rounded-xl shadow-lg cursor-pointer">
                                <i class="fas fa-save me-2" wire:loading.remove wire:target="saveYear"></i>
                                {{ tr('Save') }}
                            </x-ui.primary-button>
                        @endif
                    </div>
                </x-slot:footer>
            </x-ui.modal>
        </template>

        {{-- Compare Years Modal --}}
        <template x-teleport="body">
            <x-ui.modal wire:model="compareOpen" maxWidth="4xl">
                <x-slot:title>
                    <div class="flex items-center gap-3">
                        <div
                            class="w-10 h-10 bg-[color:var(--accent-orange)]/10 text-[color:var(--accent-orange)] rounded-xl flex items-center justify-center text-lg border border-[color:var(--accent-orange)]/20 shadow-sm">
                            <i class="fas fa-exchange-alt"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-900 text-lg leading-tight">{{ tr('Compare Years') }}</h3>
                            <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">
                                {{ tr('Compare leave policies between two years') }}</p>
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
                            <x-ui.select id="select_compare_year_a" label="{{ tr('Year A') }}"
                                wire:model.live="compareYearAId" :disabled="!$canManageAttendance">
                                <option value="">{{ tr('Select year') }}</option>
                                @foreach ($years as $y)
                                    @if (!$compareYearBId || $y->id != $compareYearBId)
                                        <option value="{{ (int) $y->id }}">{{ $y->year }}</option>
                                    @endif
                                @endforeach
                            </x-ui.select>

                            <x-ui.select id="select_compare_year_b" label="{{ tr('Year B') }}"
                                wire:model.live="compareYearBId" :disabled="!$canManageAttendance">
                                <option value="">{{ tr('Select year') }}</option>
                                @foreach ($years as $y)
                                    @if (!$compareYearAId || $y->id != $compareYearAId)
                                        <option value="{{ (int) $y->id }}">{{ $y->year }}</option>
                                    @endif
                                @endforeach
                            </x-ui.select>
                        </div>

                        <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden shadow-sm">
                            @php
                                $enumToTr = function ($val) {
                                    if (!$val || $val === '—') {
                                        return '—';
                                    }
                                    $valStr = strtolower((string) $val);
                                    $map = [
                                        'annual_grant' => tr('Annual full grant'),
                                        'monthly' => tr('Monthly accrual'),
                                        'balance_only' => tr('Deduct from balance only'),
                                        'allow_without_deduction' => tr('Allow without deduction'),
                                        'salary_after_balance' => tr('Deduct from salary after balance'),
                                        'exclude' => tr('Auto exclude'),
                                        'include' => tr('Auto include'),
                                        'employee_choice' => tr('Employee choice'),
                                        'full_day' => tr('Full day'),
                                        'half_day' => tr('Half day'),
                                        'hourly' => tr('Hourly'),
                                    ];
                                    return $map[$valStr] ?? tr((string) $val);
                                };
@endphp
                            <div class="overflow-x-auto">
                                <table class="w-full text-start border-collapse table-fixed">
                                    <thead>
                                        <tr class="bg-gray-50/50 border-b border-gray-100">
                                            <th
                                                class="w-[20%] px-5 py-3 text-[10px] font-black text-gray-400 uppercase tracking-widest text-start">
                                                {{ tr('Leave') }}</th>
                                            <th
                                                class="w-[35%] px-5 py-3 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">
                                                {{ $yearA ? (string) $yearA : tr('Year') }}</th>
                                            <th
                                                class="w-[35%] px-5 py-3 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">
                                                {{ $yearB ? (string) $yearB : tr('Year') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-50">
                                        @forelse($compareRows as $r)
                                            @php
                                                $a = $r['a'];
                                                $b = $r['b'];
                                                $different = false;
                                                if ($a && $b) {
                                                    $different =
                                                        (string) $a->days_per_year !== (string) $b->days_per_year ||
                                                        (bool) $a->is_active !== (bool) $b->is_active ||
                                                        (bool) $a->show_in_app !== (bool) $b->show_in_app ||
                                                        (bool) $a->requires_attachment !==
                                                            (bool) $b->requires_attachment;
                                                } else {
                                                    $different = true;
                                                }
@endphp
                                            <tr
                                                class="{{ $different ? 'bg-[color:var(--warning)]/10' : 'hover:bg-gray-50/50' }} transition-colors">
                                                <td class="px-5 py-3 border-e border-gray-50">
                                                    <div class="text-sm font-bold text-gray-900 truncate">
                                                        {{ $r['name'] }}</div>
                                                </td>

                                                <td class="px-5 py-3 border-e border-gray-50">
                                                    @if ($a)
                                                        <div
                                                            class="text-xs text-gray-700 flex flex-wrap gap-2 justify-center">
                                                            <span
                                                                class="px-2 py-1 bg-gray-100 rounded-md"><b>{{ tr('Days') }}:</b>
                                                                {{ $a->days_per_year }}</span>

                                                            <span
                                                                class="px-2 py-1 {{ $a->is_active ? 'bg-[color:var(--success)]/10 text-[color:var(--success)]' : 'bg-[color:var(--error)]/10 text-[color:var(--error)]' }} rounded-md">
                                                                <b>{{ tr('Status') }}:</b>
                                                                {{ $a->is_active ? tr('Active') : tr('Inactive') }}
                                                            </span>


                                                        </div>
                                                    @else
                                                        <div class="text-xs text-gray-400 text-center italic">
                                                            {{ tr('Not found') }}</div>
                                                    @endif
                                                </td>
                                                <td class="px-5 py-3">
                                                    @if ($b)
                                                        <div
                                                            class="text-xs text-gray-700 flex flex-wrap gap-2 justify-center">
                                                            <span
                                                                class="px-2 py-1 bg-gray-100 rounded-md"><b>{{ tr('Days') }}:</b>
                                                                {{ $b->days_per_year }}</span>

                                                            <span
                                                                class="px-2 py-1 {{ $b->is_active ? 'bg-[color:var(--success)]/10 text-[color:var(--success)]' : 'bg-[color:var(--error)]/10 text-[color:var(--error)]' }} rounded-md">
                                                                <b>{{ tr('Status') }}:</b>
                                                                {{ $b->is_active ? tr('Active') : tr('Inactive') }}
                                                            </span>

                                                            {{-- ✅ Eye icon بدل App --}}
                                                            <button type="button"
                                                                wire:click="toggleCompareDetails('{{ $r['key'] }}')"
                                                                class="px-2 py-1 bg-white rounded-md border border-gray-200 hover:bg-gray-50 text-gray-700 inline-flex items-center gap-2"
                                                                title="{{ tr('View details') }}"
                                                                @if(!$canManageAttendance) disabled @endif>
                                                                <i class="fas fa-eye text-[11px]"></i>
                                                                <span
                                                                    class="text-[11px] font-bold">{{ tr('Details') }}</span>
                                                            </button>
                                                        </div>
                                                    @else
                                                        <div class="text-xs text-gray-400 text-center italic">
                                                            {{ tr('Not found') }}</div>
                                                    @endif
                                                </td>

                                            </tr>

                                            @if (!empty($compareExpanded[$r['key']] ?? false))
                                                <tr class="bg-white">
                                                    <td colspan="3" class="px-5 py-4">
                                                        <div
                                                            class="rounded-2xl border border-gray-100 bg-gray-50/40 p-4">
                                                            {{-- ... expanded content ... --}}
                                                            <div class="flex items-center justify-between gap-3 mb-3">
                                                                <div class="text-sm font-black text-gray-900">
                                                                    <i
                                                                        class="fas fa-circle-info text-gray-400 me-2"></i>
                                                                    {{ tr('Leave Details') }} — {{ $r['name'] }}
                                                                </div>
                                                                <button type="button"
                                                                    wire:click="toggleCompareDetails('{{ $r['key'] }}')"
                                                                    class="text-xs font-black text-gray-500 hover:text-gray-900"
                                                                    @if(!$canManageAttendance) disabled @endif>
                                                                    {{ tr('Close') }}
                                                                </button>
                                                            </div>
                                                            {{-- Details Grid --}}
                                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                                <div
                                                                    class="rounded-xl bg-white border border-gray-100 p-3">
                                                                    <div
                                                                        class="text-[11px] font-black text-gray-500 mb-2">
                                                                        {{ $yearA ? (string) $yearA : tr('Year') }}
                                                                    </div>
                                                                    @if ($a)
                                                                        <div
                                                                            class="grid grid-cols-2 gap-2 text-[12px]">
                                                                            <div><b>{{ tr('Show in App') }}:</b>
                                                                                {{ $a->show_in_app ? tr('Yes') : tr('No') }}
                                                                            </div>
                                                                            <div>
                                                                                <b>{{ tr('Requires attachment') }}:</b>
                                                                                {{ $a->requires_attachment ? tr('Yes') : tr('No') }}
                                                                            </div>

                                                                            <div>
                                                                                <b>{{ tr('Gender') }}:</b>
                                                                                {{ $a->gender === 'male' ? tr('Male') : ($a->gender === 'female' ? tr('Female') : tr('All')) }}
                                                                            </div>

                                                                            <div class="col-span-2">
                                                                                <b>{{ tr('Description') }}:</b>
                                                                                {{ $a->description ?: '—' }}
                                                                            </div>

                                                                            @php $sA = $a->settings ?? [];
@endphp
                                                                            @if ($a->leave_type === 'annual')
                                                                                <div>
                                                                                    <b>{{ tr('Accrual method') }}:</b>
                                                                                    {{ $enumToTr($sA['accrual_method'] ?? '—') }}
                                                                                </div>
                                                                                <div><b>{{ tr('Monthly rate') }}:</b>
                                                                                    {{ $sA['monthly_accrual_rate'] ?? '—' }}
                                                                                </div>
                                                                                <div><b>{{ tr('Max balance') }}:</b>
                                                                                    {{ $sA['max_balance'] ?? '—' }}
                                                                                </div>
                                                                                <div>
                                                                                    <b>{{ tr('Carryover days allowed') }}:</b>
                                                                                    {{ $sA['allow_carryover'] ?? false ? $sA['carryover_days'] ?? '—' : tr('No') }}
                                                                                </div>
                                                                                <div><b>{{ tr('Weekend days') }}:</b>
                                                                                    {{ $enumToTr($sA['weekend_policy'] ?? '—') }}
                                                                                </div>
                                                                            @endif
                                                                            <div><b>{{ tr('Deduction policy') }}:</b>
                                                                                {{ $enumToTr($sA['deduction_policy'] ?? '—') }}
                                                                            </div>
                                                                            <div><b>{{ tr('Duration type') }}:</b>
                                                                                {{ $enumToTr($sA['duration_type'] ?? 'full_day') }}
                                                                            </div>
                                                                            <div><b>{{ tr('Min notice (days)') }}:</b>
                                                                                {{ $sA['notice_min_days'] ?? '0' }}
                                                                            </div>
                                                                            <div>
                                                                                <b>{{ tr('Max advance (days)') }}:</b>
                                                                                {{ $sA['notice_max_advance_days'] ?? '0' }}
                                                                            </div>
                                                                            <div>
                                                                                <b>{{ tr('Allow retroactive requests') }}:</b>
                                                                                {{ $sA['allow_retroactive'] ?? false ? tr('Yes') : tr('No') }}
                                                                            </div>
                                                                            <div><b>{{ tr('Mandatory note') }}:</b>
                                                                                {{ $sA['note_required'] ?? false ? tr('Yes') : tr('No') }}
                                                                            </div>

                                                                            <div class="col-span-2">
                                                                                <b>{{ tr('Contract Type Exclusions') }}:</b>
                                                                                @php
                                                                                    $exclA = $a->excluded_contract_types ?? [];
                                                                                    $exclLabsA = array_intersect_key($contractList, array_flip($exclA));
@endphp
                                                                                {{ count($exclLabsA) > 0 ? implode(', ', $exclLabsA) : tr('None') }}
                                                                            </div>

                                                                            <div class="col-span-2">
                                                                                <b>{{ tr('Note text') }}:</b>
                                                                                {{ data_get($a->settings, 'note_text', '—') ?: '—' }}
                                                                            </div>

                                                                            <div class="col-span-2">
                                                                                <b>{{ tr('Attachment types') }}:</b>
                                                                                @php $tA = $sA['attachments.types'] ?? $sA['attachment_types'] ?? [];
@endphp
                                                                                {{ is_array($tA) ? implode(', ', $tA) : (string) $tA }}
                                                                            </div>
                                                                        </div>
                                                                    @else
                                                                        <div class="text-xs text-gray-400 italic">
                                                                            {{ tr('Not found') }}</div>
                                                                    @endif
                                                                </div>
                                                                <div
                                                                    class="rounded-xl bg-white border border-gray-100 p-3">
                                                                    <div
                                                                        class="text-[11px] font-black text-gray-500 mb-2">
                                                                        {{ $yearB ? (string) $yearB : tr('Year') }}
                                                                    </div>
                                                                    @if ($b)
                                                                        <div
                                                                            class="grid grid-cols-2 gap-2 text-[12px]">
                                                                            <div><b>{{ tr('Show in App') }}:</b>
                                                                                {{ $b->show_in_app ? tr('Yes') : tr('No') }}
                                                                            </div>
                                                                            <div>
                                                                                <b>{{ tr('Requires attachment') }}:</b>
                                                                                {{ $b->requires_attachment ? tr('Yes') : tr('No') }}
                                                                            </div>

                                                                            <div>
                                                                                <b>{{ tr('Gender') }}:</b>
                                                                                {{ $b->gender === 'male' ? tr('Male') : ($b->gender === 'female' ? tr('Female') : tr('All')) }}
                                                                            </div>

                                                                            <div class="col-span-2">
                                                                                <b>{{ tr('Description') }}:</b>
                                                                                {{ $b->description ?: '—' }}
                                                                            </div>

                                                                            @php $sB = $b->settings ?? [];
@endphp
                                                                            @if ($b->leave_type === 'annual')
                                                                                <div>
                                                                                    <b>{{ tr('Accrual method') }}:</b>
                                                                                    {{ $enumToTr($sB['accrual_method'] ?? '—') }}
                                                                                </div>
                                                                                <div><b>{{ tr('Monthly rate') }}:</b>
                                                                                    {{ $sB['monthly_accrual_rate'] ?? '—' }}
                                                                                </div>
                                                                                <div><b>{{ tr('Max balance') }}:</b>
                                                                                    {{ $sB['max_balance'] ?? '—' }}
                                                                                </div>
                                                                                <div>
                                                                                    <b>{{ tr('Carryover days allowed') }}:</b>
                                                                                    {{ $sB['allow_carryover'] ?? false ? $sB['carryover_days'] ?? '—' : tr('No') }}
                                                                                </div>
                                                                                <div><b>{{ tr('Weekend days') }}:</b>
                                                                                    {{ $enumToTr($sB['weekend_policy'] ?? '—') }}
                                                                                </div>
                                                                            @endif
                                                                            <div><b>{{ tr('Deduction policy') }}:</b>
                                                                                {{ $enumToTr($sB['deduction_policy'] ?? '—') }}
                                                                            </div>
                                                                            <div><b>{{ tr('Duration type') }}:</b>
                                                                                {{ $enumToTr($sB['duration_type'] ?? 'full_day') }}
                                                                            </div>
                                                                            <div><b>{{ tr('Min notice (days)') }}:</b>
                                                                                {{ $sB['notice_min_days'] ?? '0' }}
                                                                            </div>
                                                                            <div>
                                                                                <b>{{ tr('Max advance (days)') }}:</b>
                                                                                {{ $sB['notice_max_advance_days'] ?? '0' }}
                                                                            </div>
                                                                            <div>
                                                                                <b>{{ tr('Allow retroactive requests') }}:</b>
                                                                                {{ $sB['allow_retroactive'] ?? false ? tr('Yes') : tr('No') }}
                                                                            </div>
                                                                            <div><b>{{ tr('Mandatory note') }}:</b>
                                                                                {{ $sB['note_required'] ?? false ? tr('Yes') : tr('No') }}
                                                                            </div>

                                                                            <div class="col-span-2">
                                                                                <b>{{ tr('Contract Type Exclusions') }}:</b>
                                                                                @php
                                                                                    $exclB = $b->excluded_contract_types ?? [];
                                                                                    $exclLabsB = array_intersect_key($contractList, array_flip($exclB));
@endphp
                                                                                {{ count($exclLabsB) > 0 ? implode(', ', $exclLabsB) : tr('None') }}
                                                                            </div>

                                                                            <div class="col-span-2">
                                                                                <b>{{ tr('Note text') }}:</b>
                                                                                {{ data_get($b->settings, 'note_text', '—') ?: '—' }}
                                                                            </div>

                                                                            <div class="col-span-2">
                                                                                <b>{{ tr('Attachment types') }}:</b>
                                                                                @php $tB = $sB['attachments.types'] ?? $sB['attachment_types'] ?? [];
@endphp
                                                                                {{ is_array($tB) ? implode(', ', $tB) : (string) $tB }}
                                                                            </div>
                                                                        </div>
                                                                    @else
                                                                        <div class="text-xs text-gray-400 italic">
                                                                            {{ tr('Not found') }}</div>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endif
                                        @empty
                                            <tr>
                                                <td colspan="3"
                                                    class="px-5 py-10 text-center text-sm text-gray-400">
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
        </template>
    @else
        <x-ui.card>
            <div class="p-6 space-y-6">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div
                            class="w-10 h-10 bg-[color:var(--warning)]/10 text-[color:var(--warning)] rounded-xl flex items-center justify-center border border-[color:var(--warning)]/20">
                            <i class="fas fa-user-clock"></i>
                        </div>

                        <div>
                            <div class="text-base font-black text-gray-900">{{ tr('Permission Settings') }}</div>
                            <div class="text-[11px] text-gray-500 font-semibold">
                                {{ tr('Configure approval, monthly hours limit, and deduction rules.') }}
                            </div>
                        </div>
                    </div>

                    @if($canManageAttendance)
                        <x-ui.primary-button :arrow="false" :fullWidth="false" wire:click="savePermissionSettings"
                            loading="savePermissionSettings" class="!rounded-xl cursor-pointer">
                            <i class="fas fa-save" wire:loading.remove wire:target="savePermissionSettings"></i>
                            <span class="ms-2">{{ tr('Save') }}</span>
                        </x-ui.primary-button>
                    @endif
                </div>

                {{-- ✅ Dynamic Status Banner for UX --}}
                @php
                    $isPermConfigured = ((float)$perm_monthly_limit_hours > 0 || (float)$perm_max_request_hours > 0);
@endphp

                @if(!$isPermConfigured)
                    <div class="p-4 bg-[color:var(--warning)]/10 border border-[color:var(--warning)]/25 rounded-2xl flex items-start gap-4 animate-pulse">
                        <div class="w-10 h-10 rounded-xl bg-[color:var(--warning)]/15 text-[color:var(--warning)] flex items-center justify-center shrink-0 border border-[color:var(--warning)]/25">
                             <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div>
                            <h4 class="text-sm font-black text-[color:var(--warning)]">{{ tr('Permission settings are not configured') }}</h4>
                            <p class="text-[11px] text-[color:var(--warning)] mt-1 leading-relaxed">
                                {{ tr('Permission requests are currently disabled in the app because hourly limits are not configured. Enter a monthly limit or a maximum request limit to activate this feature.') }}
                            </p>
                        </div>
                    </div>
                @else
                    <div class="p-4 bg-[color:var(--success)]/10 border border-[color:var(--success)]/25 rounded-2xl flex items-start gap-4 transition-all duration-500">
                        <div class="w-10 h-10 rounded-xl bg-[color:var(--success)]/15 text-[color:var(--success)] flex items-center justify-center shrink-0 border border-[color:var(--success)]/25">
                             <i class="fas fa-check-circle"></i>
                        </div>
                        <div>
                            <h4 class="text-sm font-black text-[color:var(--success)]">{{ tr('Settings configured successfully') }}</h4>
                            <p class="text-[11px] text-[color:var(--success)] mt-1 leading-relaxed">
                                {{ tr('Permission settings are active now. Employees can submit requests from the app based on the configured limits.') }}
                            </p>
                        </div>
                    </div>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    <div>
                        <x-ui.input type="number" step="0.25" min="0"
                            label="{{ tr('Monthly limit (hours)') }}" wire:model.defer="perm_monthly_limit_hours"
                            :disabled="!$canManageAttendance" placeholder="0" 
                            :hint="tr('Total hours an employee is allowed to request during one month.')" />
                    </div>

                    <div>
                        <x-ui.input type="number" step="0.25" min="0"
                            label="{{ tr('Maximum per request (hours)') }}" wire:model.defer="perm_max_request_hours"
                            :disabled="!$canManageAttendance" placeholder="0" 
                            :hint="tr('Maximum allowed hours for one permission request.')" />
                    </div>

                    <div class="md:col-span-2">
                        <div class="p-3 bg-[rgb(var(--accent-orange-rgb)/0.08)] border border-[rgb(var(--accent-orange-rgb)/0.16)] rounded-xl text-[10px] text-[color:var(--accent-orange)] italic flex items-center gap-2">
                            <i class="fas fa-info-circle"></i>
                            <span>{{ tr('Note: At least one of the limits above must be greater than zero to allow requests from the mobile app.') }}</span>
                        </div>
                    </div>

                    <div class="md:col-span-2">
                        <x-ui.select label="{{ tr('Deduction policy') }}" wire:model.defer="perm_deduction_policy"
                            :disabled="!$canManageAttendance">
                            <option value="not_allowed_after_limit">{{ tr('Not allowed after limit') }}</option>
                            <option value="salary_after_limit">{{ tr('Deduct from salary after limit') }}</option>
                            <option value="allow_without_deduction">{{ tr('Allow without deduction') }}</option>
                        </x-ui.select>
                    </div>

                    {{-- Attachments section --}}
                    <div class="md:col-span-2 pt-3 border-t border-gray-100">
                        <div class="text-sm font-black text-gray-900 mb-2">{{ tr('Attachment settings') }}</div>

                        <div class="mb-3">
                            <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 cursor-pointer">
                                <input type="checkbox" wire:model.live="perm_requires_attachment" class="cursor-pointer"
                                    @if(!$canManageAttendance) disabled @endif>
                                <span>{{ tr('Requires attachment') }}</span>
                            </label>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label
                                    class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Allowed types') }}</label>

                                <div class="flex flex-wrap gap-3">
                                    <label
                                        class="flex items-center gap-2 text-sm font-semibold text-gray-700 cursor-pointer">
                                        <input type="checkbox" value="pdf"
                                            wire:model.defer="perm_attachment_types" class="cursor-pointer"
                                            @disabled(!$perm_requires_attachment || !$canManageAttendance)>
                                        <span>PDF</span>
                                    </label>
                                    <label
                                        class="flex items-center gap-2 text-sm font-semibold text-gray-700 cursor-pointer">
                                        <input type="checkbox" value="jpg"
                                            wire:model.defer="perm_attachment_types" class="cursor-pointer"
                                            @disabled(!$perm_requires_attachment || !$canManageAttendance)>
                                        <span>JPG</span>
                                    </label>
                                    <label
                                        class="flex items-center gap-2 text-sm font-semibold text-gray-700 cursor-pointer">
                                        <input type="checkbox" value="png"
                                            wire:model.defer="perm_attachment_types" class="cursor-pointer"
                                            @disabled(!$perm_requires_attachment || !$canManageAttendance)>
                                        <span>PNG</span>
                                    </label>
                                </div>

                                @error('perm_attachment_types')
                                    <div class="text-xs text-[color:var(--error)] mt-1">{{ $message }}</div>
                                @enderror
                                @error('perm_attachment_types.*')
                                    <div class="text-xs text-[color:var(--error)] mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <div>
                                <label
                                    class="block text-[11px] font-black text-gray-500 mb-1">{{ tr('Maximum size (MB)') }}</label>
                                <input type="text" value="2 MB" readonly
                                    class="w-full h-[40px] px-3 rounded-xl border border-gray-200 text-sm bg-gray-100/70 text-gray-700 cursor-not-allowed">
                                <div class="text-[10px] text-gray-400 mt-1">
                                    {{ tr('Fixed size for all permission attachments.') }}
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- General Settings --}}
                    <div class="md:col-span-2 pt-3 border-t border-gray-100">
                        <div class="text-sm font-black text-gray-900 mb-2">{{ tr('General settings') }}</div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 cursor-pointer">
                                <input type="checkbox" wire:model.defer="perm_is_active" class="cursor-pointer"
                                    @if(!$canManageAttendance) disabled @endif>
                                <span>{{ tr('Active') }}</span>
                            </label>

                            <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 cursor-pointer">
                                <input type="checkbox" wire:model.defer="perm_approval_required" class="cursor-pointer"
                                    @if(!$canManageAttendance) disabled @endif>
                                <span>{{ tr('Requires approval') }}</span>
                            </label>

                            <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 cursor-pointer">
                                <input type="checkbox" wire:model.defer="perm_show_in_app" class="cursor-pointer"
                                    @if(!$canManageAttendance) disabled @endif>
                                <span>{{ tr('Show in app') }}</span>
                            </label>
                        </div>
                    </div>

                </div>
            </div>
        </x-ui.card>
    @endif

</div>
