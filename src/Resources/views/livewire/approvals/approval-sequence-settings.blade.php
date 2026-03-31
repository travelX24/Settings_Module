@php
    $locale = app()->getLocale();
    $isRtl  = in_array(substr($locale, 0, 2), ['ar','fa','ur','he']);
    $dir    = $isRtl ? 'rtl' : 'ltr';

    $employees   = $lookups['employees']   ?? [];
    $departments = $lookups['departments'] ?? [];
    $jobTitles   = $lookups['job_titles']  ?? [];
    $branches    = $lookups['branches']    ?? [];

    $list = $employees;
    if (($scope_type ?? 'all') === 'department') {
        $list = $departments;
    } elseif (($scope_type ?? 'all') === 'job_title') {
        $list = $jobTitles;
    } elseif (($scope_type ?? 'all') === 'branch') {
        $list = $branches;
    }

    $modalTitle = ($editingId ?? null) ? tr('Edit Policy') : tr('Add Policy');
@endphp

@section('topbar-left-content')
    <x-ui.page-header
        :title="tr('Approval Sequence Settings')"
        :subtitle="tr('Set up approval workflows and sequences')"
        class="!flex-col {{ $isRtl ? '!items-end !text-right' : '!items-start !text-left' }} !justify-start !gap-1"
        titleSize="xl"
    />
@endsection

@section('topbar-actions')
    <x-ui.secondary-button
        href="{{ route('company-admin.settings.general') }}"
        :arrow="false"
        :fullWidth="false"
        class="!px-4 !py-2 !text-sm !rounded-xl !gap-2"
    >
        <i class="fas {{ $isRtl ? 'fa-arrow-right' : 'fa-arrow-left' }} text-xs"></i>
        <span>{{ tr('Back') }}</span>
    </x-ui.secondary-button>
@endsection

<div class="space-y-6 p-4 sm:p-6" dir="{{ $dir }}">

    <x-ui.flash-toast />

    {{-- Main Container Matching Organizational Structure --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">

        
        {{-- Tabs Header --}}
        <div class="border-b border-gray-200 bg-gray-50 flex flex-nowrap overflow-x-auto no-scrollbar justify-between">
            <div class="flex justify-start flex-1 min-w-max">
                @foreach($this->tabs as $key => $label)
                    @php($c = (int)($counts[$key] ?? 0))
                    <button
                        wire:click="$set('tab', '{{ $key }}')"
                        class="cursor-pointer px-6 py-4 font-semibold text-sm transition-all duration-200 flex items-center gap-2 {{ $tab === $key 
                            ? 'border-b-2 border-[color:var(--brand-via)] text-[color:var(--brand-via)] bg-white' 
                            : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' }}"
                    >
                        <span>{{ $label }}</span>
                        <span class="inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-semibold rounded-full {{ $tab === $key ? 'bg-brand/10 text-brand' : 'bg-gray-200 text-gray-700' }}">
                            {{ $c }}
                        </span>
                    </button>
                @endforeach
            </div>
            
            {{-- Add Button --}}
            <div class="px-4 border-l border-gray-200 flex items-center justify-center min-w-max">
                <button
                    type="button"
                    wire:click="openCreate"
                    class="cursor-pointer group relative overflow-hidden rounded-xl px-4 py-2 text-sm font-semibold text-white shadow-md bg-gradient-to-r from-[color:var(--brand-from)] via-[color:var(--brand-via)] to-[color:var(--brand-to)] hover:shadow-lg active:scale-[0.98] transition-all duration-300"
                >
                    <span class="relative flex items-center gap-2">
                        <i class="fas fa-plus text-xs"></i>
                        <span>{{ tr('Add Policy') }}</span>
                    </span>
                </button>
            </div>
        </div>

        {{-- Tab Content (Filters + Table) --}}
        <div
            x-data="{
                view: (localStorage.getItem('approval_policy_view') || 'table'),
                setView(v){ this.view=v; localStorage.setItem('approval_policy_view',v); }
            }"
            x-init="localStorage.setItem('approval_policy_view', view)"
            class="p-4 sm:p-5"
        >
            {{-- Filters + View Toggle --}}
            <div class="flex flex-col sm:flex-row sm:items-end gap-3 w-full border-b border-gray-100 pb-5 mb-5">

                {{-- Search --}}
                <div class="flex-1 min-w-0">
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1.5">
                        {{ tr('Search') }}
                    </label>
                    <x-ui.search-box wire:model.live.debounce.300ms="search" :placeholder="tr('Search by policy name...')" />
                </div>

                {{-- Branch Filter --}}
                <div class="sm:w-44">
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1.5">
                        {{ tr('Branch') }}
                    </label>
                    <x-ui.select wire:model.live="filterBranchId">
                        <option value="">{{ tr('All Branches') }}</option>
                        @foreach(($branches ?? []) as $br)
                            <option value="{{ $br['id'] }}">
                                {{ $br['name'] ?? ('#'.$br['id']) }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </div>

                {{-- Status Filter --}}
                <div class="sm:w-40">
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1.5">
                        {{ tr('Status') }}
                    </label>
                    <x-ui.select wire:model.live="filterStatus">
                        <option value="all">{{ tr('All Statuses') }}</option>
                        <option value="active">{{ tr('Active') }}</option>
                        <option value="inactive">{{ tr('Inactive') }}</option>
                    </x-ui.select>
                </div>

                {{-- Clear Filters Button --}}
                <div
                    x-data="{
                        hasFilters() {
                            return ($wire.search && $wire.search.trim() !== '') ||
                                   ($wire.filterBranchId && $wire.filterBranchId !== '') ||
                                   ($wire.filterStatus && $wire.filterStatus !== 'all');
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
                        class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-semibold text-gray-600 hover:text-gray-900 transition-colors disabled:opacity-50 border-e border-gray-100 pe-4 me-2 mb-1"
                    >
                        <i class="fas fa-times" wire:loading.remove wire:target="clearAllFilters"></i>
                        <i class="fas fa-spinner fa-spin" wire:loading wire:target="clearAllFilters"></i>
                        <span wire:loading.remove wire:target="clearAllFilters">{{ tr('Clear filters') }}</span>
                    </button>
                </div>

                {{-- View Toggle --}}
                <x-ui.view-toggle :label="tr('View')" />

            </div>

            {{-- ✅ CARDS VIEW --}}
            <div x-show="view === 'cards'" x-cloak>
                @if($policies->count() > 0)
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                        @foreach($policies as $p)
                            <div
                                wire:key="policy-card-{{ $p->id }}"
                                class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm hover:border-[color:var(--brand-via)]/40 hover:shadow-md transition-all duration-200 flex flex-col gap-4"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="text-sm font-bold text-gray-900 truncate">{{ $p->name }}</div>
                                        <div class="mt-1">
                                            @if(($p->scope_type ?? 'all') === 'all')
                                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-blue-50 text-blue-700 border border-blue-200">{{ tr('All Employees') }}</span>
                                            @else
                                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-gray-100 text-gray-700 border border-gray-200">{{ tr('Type') }}: {{ $p->scope_type }} ({{ $p->scopes_count }})</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $p->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                            {{ $p->is_active ? tr('Active') : tr('Inactive') }}
                                        </span>
                                        <x-ui.actions-menu>
                                            <x-ui.dropdown-item wire:click="openEdit({{ $p->id }})" @click="$dispatch('close-actions-menu')">
                                                <i class="fas fa-edit me-2"></i><span>{{ tr('Edit') }}</span>
                                            </x-ui.dropdown-item>
                                            <x-ui.dropdown-item wire:click="confirmDelete({{ $p->id }})" @click="$dispatch('close-actions-menu')" danger>
                                                <i class="fas fa-trash me-2"></i><span>{{ tr('Delete') }}</span>
                                            </x-ui.dropdown-item>
                                        </x-ui.actions-menu>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between text-sm border-t border-gray-100 pt-3">
                                    <div class="text-gray-500 flex items-center gap-1.5">
                                        <i class="fas fa-list-ol text-gray-300"></i>
                                        <span>{{ tr('Approvers') }}</span>
                                    </div>
                                    <span class="font-bold text-gray-700 bg-gray-100 px-2 py-0.5 rounded-lg text-xs">{{ (int) $p->steps_count }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="p-12 text-center">
                        <i class="fas fa-sitemap text-gray-300 text-5xl mb-4"></i>
                        <p class="text-gray-500 text-lg mb-2">{{ tr('No policies found') }}</p>
                        <p class="text-gray-400 text-sm">{{ tr('Click "Add Policy" to create your first policy') }}</p>
                    </div>
                @endif
            </div>

            {{-- ✅ TABLE VIEW (Default) --}}
            <div x-show="view === 'table'" x-cloak>
                <div class="overflow-x-auto">
                    <x-ui.table
                        :headers="[
                            tr('Policy Name'),
                            tr('Applies To'),
                            tr('Approvers Count'),
                            tr('Status'),
                            tr('Actions')
                        ]"
                        :perPage="10"
                        :enablePagination="false"
                    >
                        @forelse($policies as $p)
                            <tr class="hover:bg-gray-50 transition-colors border-b border-gray-200">
                                <td class="py-4 px-6 align-top whitespace-nowrap">
                                    <span class="font-semibold text-gray-900">{{ $p->name }}</span>
                                </td>

                                <td class="py-4 px-6 align-top whitespace-nowrap text-sm text-gray-700">
                                    @if(($p->scope_type ?? 'all') === 'all')
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-50 text-blue-700 border border-blue-200">{{ tr('All Employees') }}</span>
                                    @else
                                        <div class="flex items-center gap-2">
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-50 text-gray-700 border border-gray-200">
                                                {{ tr('Type') }}: {{ $p->scope_type }}
                                            </span>
                                            <span class="text-gray-500 text-xs">
                                                ({{ $p->scopes_count }})
                                            </span>
                                        </div>
                                    @endif
                                </td>

                                <td class="py-4 px-6 align-top whitespace-nowrap text-sm">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-50 text-gray-700 border border-gray-200">{{ (int) $p->steps_count }}</span>
                                </td>

                                <td class="py-4 px-6 align-top whitespace-nowrap text-sm">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $p->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                        {{ $p->is_active ? tr('Active') : tr('Inactive') }}
                                    </span>
                                </td>

                                <td class="py-4 px-6 align-top whitespace-nowrap text-sm font-medium">
                                    <x-ui.actions-menu>
                                        <x-ui.dropdown-item
                                            class="cursor-pointer"
                                            wire:click="openEdit({{ $p->id }})"
                                            @click="$dispatch('close-actions-menu')"
                                        >
                                            <i class="fas fa-edit me-2"></i>
                                            <span>{{ tr('Edit') }}</span>
                                        </x-ui.dropdown-item>

                                        <x-ui.dropdown-item
                                            class="cursor-pointer"
                                            wire:click="confirmDelete({{ $p->id }})"
                                            @click="$dispatch('close-actions-menu')"
                                            danger
                                        >
                                            <i class="fas fa-trash me-2"></i>
                                            <span>{{ tr('Delete') }}</span>
                                        </x-ui.dropdown-item>
                                    </x-ui.actions-menu>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-gray-500">
                                    {{ tr('No policies found') }}
                                </td>
                            </tr>
                        @endforelse
                    </x-ui.table>
                </div>
            </div>

            @if($policies->hasPages())
                <div class="mt-4 pt-4 border-t border-gray-100">
                    {{ $policies->links() }}
                </div>

            @endif
        </div>
    </div>

    {{-- ✅ Add/Edit Modal --}}
    <x-ui.modal wire:model="showModal" maxWidth="3xl">
        <x-slot:title>
            <div class="space-y-1">
                <div>{{ $modalTitle }}</div>
                <div class="text-sm font-normal text-gray-500 mt-0.5">
                    {{ tr('Operation') }}:
                    <span class="font-semibold">{{ $this->tabs[$tab] ?? $tab }}</span>
                </div>
            </div>
        </x-slot:title>

        <x-slot:icon>
            <i class="fas fa-sitemap text-white text-lg"></i>
        </x-slot:icon>

        <x-slot:content>
            <div class="space-y-6">

                {{-- Basic --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <x-ui.input
                            name="name"
                            :label="tr('Policy Name')"
                            wire:model.defer="name"
                            :required="true"
                            :disabled="!auth()->user()->can('settings.approval.manage')"
                        />
                    </div>

                    <div class="flex items-center gap-2 md:pt-8">
                        <label class="group relative flex items-center gap-3 p-3 rounded-xl border border-gray-200 cursor-pointer hover:border-gray-300 hover:bg-gray-50 transition w-full">
                            <div class="flex items-center h-5">
                                <input type="checkbox" wire:model.defer="is_active" class="w-5 h-5 rounded border-gray-300 text-[color:var(--brand-via)] focus:ring-[color:var(--brand-via)]" @cannot('settings.approval.manage') disabled @endcannot>
                            </div>
                            <div class="flex flex-col">
                                <span class="text-sm font-semibold text-gray-900">{{ tr('Active') }}</span>
                                <span class="text-xs text-gray-500">{{ tr('Enable or disable this policy') }}</span>
                            </div>
                        </label>
                    </div>
                </div>

                {{-- Scope --}}
                <div class="bg-gray-50 p-5 rounded-xl border border-gray-200">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-4">
                        <h4 class="font-bold text-gray-900 text-base"><i class="fas fa-filter text-gray-400 me-2 text-sm"></i>{{ tr('Application Scope') }}</h4>
                        <div class="text-xs text-gray-500">
                            {{ tr('You can select multiple values inside the chosen scope type') }}
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <x-ui.select
                                name="scope_type"
                                :label="tr('Scope Type')"
                                wire:model.live="scope_type"
                                :required="true"
                                :disabled="!auth()->user()->can('settings.approval.manage')"
                            >
                                <option value="all">{{ tr('All Employees') }}</option>
                                <option value="department">{{ tr('Department') }}</option>
                                <option value="job_title">{{ tr('Job Title') }}</option>
                                <option value="branch">{{ tr('Branch') }}</option>
                                <option value="employee">{{ tr('Specific Employees') }}</option>
                            </x-ui.select>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                {{ tr('Values') }}
                            </label>

                            @if($scope_type === 'all')
                                <div class="flex items-center h-[42px] px-4 rounded-xl border border-dashed border-gray-300 bg-white text-sm text-gray-500 italic">
                                    {{ tr('Applies to all employees') }}
                                </div>
                            @else
                                <select
                                    wire:model.defer="scope_ids"
                                    multiple
                                    class="w-full rounded-xl border bg-white px-4 py-2.5 text-sm shadow-sm
                                        border-gray-200 text-gray-900
                                        focus:outline-none focus:ring-2 focus:ring-[color:var(--brand-via)]/20
                                        focus:border-[color:var(--brand-via)] transition
                                        min-h-[110px]"
                                    @cannot('settings.approval.manage') disabled @endcannot
                                >
                                    @foreach($list as $item)
                                        <option value="{{ $item['id'] }}">{{ $item['name'] }}</option>
                                    @endforeach
                                </select>

                                @error('scope_ids')
                                    <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                                @enderror
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Steps --}}
                <div class="bg-white border border-gray-200 rounded-xl mt-6" style="overflow: visible;">
                    <div class="p-4 bg-gray-50 border-b border-gray-200 flex items-center justify-between rounded-t-xl">
                        <h4 class="font-bold text-gray-900 text-base"><i class="fas fa-list-ol text-gray-400 me-2 text-sm"></i>{{ tr('Approval Sequence') }}</h4>
                        
                        @can('settings.approval.manage')
                            <x-ui.secondary-button
                                type="button"
                                wire:click="addStep"
                                :fullWidth="false"
                                class="!px-4 !py-2 !text-sm !rounded-xl !gap-2"
                            >
                                <i class="fas fa-plus text-xs"></i>
                                <span>{{ tr('Add Approver') }}</span>
                            </x-ui.secondary-button>
                        @endcan
                    </div>

                    <div class="p-4 space-y-3 bg-gray-50/30 rounded-b-xl" style="overflow: visible;">
                        @foreach($steps as $i => $s)
                            @php($t = $steps[$i]['approver_type'] ?? 'direct_manager')

                            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm hover:border-[color:var(--brand-via)]/30 transition" wire:key="step-{{ $steps[$i]['_key'] ?? $i }}">

                                {{-- ✅ صف واحد مرتب + الأسهم فوق/تحت --}}
                                <div class="flex flex-col lg:flex-row lg:items-center gap-4">
                                    {{-- Step Number --}}
                                    <div class="flex items-center justify-center shrink-0 w-8 h-8 rounded-full bg-gray-100 text-xs font-black text-gray-500 border border-gray-200 shadow-inner">
                                        {{ $i + 1 }}
                                    </div>

                                    {{-- Approver Type --}}
                                    <div class="shrink-0 lg:w-48">
                                        <x-ui.select 
                                            wire:model.defer="steps.{{ $i }}.approver_type" 
                                            :disabled="!auth()->user()->can('settings.approval.manage')"
                                            align="up"
                                        >
                                            <option value="direct_manager">{{ tr('Direct Manager') }}</option>
                                            <option value="user">{{ tr('Specific User') }}</option>
                                        </x-ui.select>
                                    </div>

                                    {{-- Approver Target --}}
                                    <div class="flex-1 w-full">
                                        @if($t === 'user')
                                            <x-ui.select 
                                                wire:model.defer="steps.{{ $i }}.approver_id" 
                                                :disabled="!auth()->user()->can('settings.approval.manage')" 
                                                searchable="true"
                                                align="up"
                                                class="w-full"
                                            >
                                                <option value="0">—</option>
                                                @foreach(($t === 'user' ? ($lookups['users'] ?? []) : $employees) as $e)
                                                    <option value="{{ is_array($e) ? $e['id'] : $e->id }}">{{ is_array($e) ? $e['name'] : $e->name }}</option>
                                                @endforeach
                                            </x-ui.select>
                                        @else
                                            <div class="w-full h-[42px] flex items-center justify-start rounded-xl border border-dashed border-gray-300 bg-gray-50/50 px-4 text-sm text-gray-500 italic">
                                                <i class="fas fa-user-tie mr-2 opacity-50"></i>
                                                {{ tr('Direct Manager') }}
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Features & Actions --}}
                                    <div class="shrink-0 flex items-center gap-4 border-t lg:border-t-0 lg:border-l border-gray-100 pt-3 lg:pt-0 lg:pl-4 mt-3 lg:mt-0 w-full lg:w-auto justify-between lg:justify-end">
                                        @if($tab === 'leave_exceptions')
                                            <div class="flex items-center">
                                                <label class="group relative flex items-center gap-2 px-3 py-1.5 rounded-full bg-white border border-gray-200 shadow-sm cursor-pointer hover:border-indigo-300 hover:bg-indigo-50/30 transition-all duration-200">
                                                    <input type="checkbox" wire:model.defer="steps.{{ $i }}.follow_standard" class="w-4 h-4 rounded border-gray-300 text-[color:var(--brand-via)] focus:ring-[color:var(--brand-via)]/20 transition-all">
                                                    <span class="text-xs font-bold text-gray-600 group-hover:text-indigo-700 whitespace-nowrap">{{ tr('Follow Standard') }}</span>
                                                </label>
                                            </div>
                                        @endif

                                        <div class="flex items-center gap-2">
                                            <div class="flex flex-col items-center bg-gray-50 border border-gray-200 rounded-lg overflow-hidden shrink-0">
                                                <button
                                                    type="button"
                                                    wire:click="moveStepUp({{ $i }})"
                                                    class="w-8 h-5 flex items-center justify-center text-gray-400 hover:text-white hover:bg-[color:var(--brand-via)] transition border-b border-gray-200"
                                                    title="{{ tr('Move Up') }}"
                                                ><i class="fas fa-chevron-up text-[10px]"></i></button>

                                                <button
                                                    type="button"
                                                    wire:click="moveStepDown({{ $i }})"
                                                    class="w-8 h-5 flex items-center justify-center text-gray-400 hover:text-white hover:bg-[color:var(--brand-via)] transition"
                                                    title="{{ tr('Move Down') }}"
                                                ><i class="fas fa-chevron-down text-[10px]"></i></button>
                                            </div>

                                            <button
                                                type="button"
                                                wire:click="removeStep({{ $i }})"
                                                class="w-10 h-10 flex items-center justify-center rounded-xl border border-red-100 bg-red-50 text-red-500 hover:bg-red-500 hover:text-white shadow-sm transition-all duration-200 shrink-0"
                                                title="{{ tr('Remove') }}"
                                            ><i class="fas fa-trash-alt text-sm"></i></button>
                                        </div>
                                    </div>
                                </div>

                                @error("steps.$i.approver_type")
                                    <div class="text-xs text-red-600 mt-2">{{ $message }}</div>
                                @enderror

                                @error("steps.$i.approver_id")
                                    <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        @endforeach

                        @if(empty($steps))
                            <div class="text-center py-6 text-gray-500 text-sm border-2 border-dashed border-gray-200 rounded-xl">
                                <i class="fas fa-info-circle text-gray-400 mb-2 block text-xl"></i>
                                {{ tr('No approvers added yet. Click "Add Approver" to build the sequence.') }}
                            </div>
                        @endif
                        
                        @error("steps")
                            <div class="text-sm font-semibold text-red-600 mt-2 text-center">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

            </div>
        </x-slot:content>

        <x-slot:footer>
            <x-ui.secondary-button type="button" wire:click="$set('showModal', false)" :fullWidth="false" class="cursor-pointer w-full sm:w-auto">
                {{ tr('Cancel') }}
            </x-ui.secondary-button>

            @can('settings.approval.manage')
            <x-ui.primary-button type="button" wire:click="save" :fullWidth="false" class="cursor-pointer w-full sm:w-auto min-w-[140px]" loading="save">
                <i class="fas fa-save me-2"></i>
                {{ tr('Save Policy') }}
            </x-ui.primary-button>
            @endcan
        </x-slot:footer>
    </x-ui.modal>

    {{-- ✅ إخفاء سهم الـ select الخاص بفلتر الحالة فقط --}}
    @once
        <style>
            #filter_status + div.relative > div.absolute {
                display: none !important;
            }
        </style>
    @endonce

    <x-ui.confirm-dialog
        id="delete-policy-dialog"
        :title="tr('Confirm Deletion')"
        :message="tr('Are you sure you want to delete this policy? This action cannot be undone.')"
        type="danger"
        confirm-action="wire:deletePolicy"
    />
</div>
