@section('topbar-content')
    @can('uac.users.manage')
        <button
            type="button"
            @click="$dispatch('open-add-user-modal')"
            class="group inline-flex items-center gap-2.5 px-5 py-2.5 bg-gradient-to-r from-[color:var(--brand-from)] to-[color:var(--brand-to)] text-white rounded-xl shadow-lg shadow-[color:var(--brand-from)]/20 hover:shadow-xl hover:shadow-[color:var(--brand-from)]/30 hover:-translate-y-0.5 transition-all duration-300 cursor-pointer"
        >
            <div class="w-6 h-6 bg-white/20 rounded-lg flex items-center justify-center group-hover:rotate-90 transition-transform duration-500">
                <i class="fas fa-plus text-sm"></i>
            </div>
            <span class="font-bold tracking-wide text-sm">{{ tr('Add New User') }}</span>
        </button>
    @endcan
@endsection

<div
    class="space-y-6"
    x-data="{
        view: (localStorage.getItem('uac_users_view') || 'table'),
        setView(v){
            this.view = v;
            localStorage.setItem('uac_users_view', v);
        }
    }"
    x-init="localStorage.setItem('uac_users_view', view)"
>
    {{-- Search & Filters --}}
    <x-ui.card :padding="false" class="border-gray-200 p-4">
        <div class="flex flex-col md:flex-row gap-4 items-start md:items-end justify-between">
            <div class="flex-1 w-full">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1.5">
                            {{ tr('Search') }}
                        </label>
                        <x-ui.search-box wire:model.live.debounce.300ms="search" :placeholder="tr('Search by name or email...')" />
                    </div>

                    @if(!empty($branches))
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1.5">
                                {{ tr('Branch') }}
                            </label>
                            <x-ui.select wire:model.live="filterBranchId" :disabled="$lockBranchFilter">
                                <option value="">{{ tr('All Branches') }}</option>
                                @foreach($branches as $br)
                                    <option value="{{ $br['id'] }}">{{ $br['name'] }}</option>
                                @endforeach
                            </x-ui.select>
                        </div>
                    @endif
                </div>
            </div>
            <div class="flex items-center gap-3">
                {{-- Clear Filters Button --}}
                <div
                    x-data="{
                        hasFilters() {
                            return ($wire.search && $wire.search.trim() !== '') ||
                                   ($wire.filterBranchId && $wire.filterBranchId !== '');
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
                        class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-semibold text-gray-600 hover:text-gray-900 transition-colors disabled:opacity-50 border-e border-gray-100 pe-4 me-2"
                    >
                        <i class="fas fa-times" wire:loading.remove wire:target="clearAllFilters"></i>
                        <i class="fas fa-spinner fa-spin" wire:loading wire:target="clearAllFilters"></i>
                        <span wire:loading.remove wire:target="clearAllFilters">{{ tr('Clear filters') }}</span>
                    </button>
                </div>

                <x-ui.view-toggle />
            </div>
        </div>
    </x-ui.card>

    {{-- Cards Layout --}}
    <div x-show="view === 'cards'" x-cloak>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            @forelse($users as $user)
                <x-ui.card wire:key="user-card-{{ $user->id }}" :hover="true" :padding="false" class="rounded-2xl border-gray-200 p-5 group flex flex-col h-full">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white shadow-lg shrink-0">
                                <span class="text-lg font-bold">{{ substr($user->name, 0, 1) }}</span>
                            </div>
                            <div class="min-w-0">
                                <h4 class="text-base font-bold text-gray-900 truncate">{{ $user->name }}</h4>
                                <p class="text-xs text-gray-500 truncate">{{ $user->email }}</p>
                            </div>
                        </div>

                        <x-ui.actions-menu>
                            @can('uac.users.manage')
                                <x-ui.dropdown-item wire:click="sendPasswordReset({{ $user->id }})">
                                    <i class="fas fa-envelope mr-2 w-5 text-indigo-500"></i>
                                    {{ tr('Send Password Reset') }}
                                </x-ui.dropdown-item>
                                <x-ui.dropdown-item wire:click="openEditModal({{ $user->id }})">
                                    <i class="fas fa-edit mr-2 w-5 text-blue-500"></i>
                                    {{ tr('Edit') }}
                                </x-ui.dropdown-item>
                                @php 
                                    $isPrimary = ((int)$user->id === (int)($primaryUserId ?? 0));
                                    $isGlobalAdmin = $user->roles && $user->roles->whereIn('name', ['saas-admin', 'super-admin', 'system-admin'])->isNotEmpty();
                                    $isSysAdmin = $isPrimary || $isGlobalAdmin;
                                @endphp
                                @if(($user->access_type ?? 'system_and_app') !== 'hr_app_only' && !$isSysAdmin)
                                    <x-ui.dropdown-item wire:click="openPermModal({{ $user->id }})">
                                        <i class="fas fa-sliders-h mr-2 w-5 text-amber-500"></i>
                                        {{ tr('Custom Permissions') }}
                                    </x-ui.dropdown-item>
                                @endif
                                @if(!empty($user->device_id))
                                    <x-ui.dropdown-item @click="$dispatch('open-confirm-confirm-reset-device', {{ $user->id }})">
                                        <i class="fas fa-mobile-alt mr-2 w-5 text-purple-500"></i>
                                        {{ tr('Reset Device') }}
                                    </x-ui.dropdown-item>
                                @endif
                                <x-ui.dropdown-item
                                    @click="$dispatch('open-confirm-confirm-toggle-status', {{ $user->id }})"
                                    :danger="$user->is_active ?? true"
                                >
                                    @if($user->is_active ?? true)
                                        <i class="fas fa-user-slash mr-2 w-5 text-red-500"></i>
                                        {{ tr('Deactivate') }}
                                    @else
                                        <i class="fas fa-user-check mr-2 w-5 text-green-500"></i>
                                        {{ tr('Activate') }}
                                    @endif
                                </x-ui.dropdown-item>
                            @endcan
                        </x-ui.actions-menu>
                    </div>

                    <div class="space-y-3 flex-1">
                        <div class="flex items-center justify-between py-2 border-b border-gray-50">
                            <span class="text-xs font-medium text-gray-500">{{ tr('Employee') }}</span>
                            <span class="text-xs font-bold text-gray-800">
                                {{ $user->employee ? ($user->employee->name_ar ?? $user->employee->name_en) : '-' }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between py-2 border-b border-gray-50">
                            <span class="text-xs font-medium text-gray-500">{{ tr('Role') }}</span>
                            @php 
                                $roleNames = $user->roles ? $user->roles->pluck('name')->filter() : collect(); 
                            @endphp
                            @if($user->has_custom_permissions && $user->reference_role)
                                <div class="flex flex-col items-end gap-0.5">
                                    <span class="text-xs font-bold text-indigo-600">{{ $user->reference_role }}</span>
                                    <span class="text-[9px] bg-amber-50 text-amber-700 border border-amber-200 px-1.5 py-0.5 rounded-full font-bold">
                                        <i class="fas fa-sliders-h mr-0.5"></i> {{ tr('Custom') }}
                                    </span>
                                </div>
                            @else
                                <span class="text-xs font-bold text-indigo-600">
                                    {{ $roleNames->isNotEmpty() ? $roleNames->join(', ') : '-' }}
                                </span>
                            @endif
                        </div>
                        <div class="flex items-center justify-between py-2 border-b border-gray-50">
                            <span class="text-xs font-medium text-gray-500">{{ tr('Access Scope') }}</span>
                            <span class="text-[10px] px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 font-bold">
                                @php 
                                    $scope = $user->access_scope ?? 'all_branches'; 
                                @endphp
                                @if($scope === 'all_branches')
                                    {{ tr('All Branches') }}
                                @elseif($scope === 'selected_branches')
                                    {{ tr('Selected Branches') }}
                                @else
                                    {{ tr('My Branch') }}
                                @endif
                            </span>
                        </div>
                    </div>

                    <div class="mt-4 flex items-center justify-between">
                         @if(($user->access_type ?? 'system_and_app') === 'hr_app_only')
                            <span class="text-[10px] bg-slate-100 text-slate-700 px-2 py-1 rounded-lg font-bold border border-slate-200">
                                <i class="fas fa-mobile-alt mr-1"></i> {{ tr('HR App Only') }}
                            </span>
                        @else
                            <span class="text-[10px] bg-indigo-50 text-indigo-700 px-2 py-1 rounded-lg font-bold border border-indigo-100">
                                <i class="fas fa-laptop mr-1"></i> {{ tr('System & App') }}
                            </span>
                        @endif

                        @php $isActive = $user->is_active ?? true; @endphp
                        @if($isActive)
                            <span class="px-2.5 py-1 text-[10px] font-bold rounded-full bg-green-100 text-green-800">
                                {{ tr('Active') }}
                            </span>
                        @else
                            <span class="px-2.5 py-1 text-[10px] font-bold rounded-full bg-red-100 text-red-800">
                                {{ tr('Inactive') }}
                            </span>
                        @endif
                    </div>
                </x-ui.card>
            @empty
                <div class="col-span-full py-12 text-center text-gray-400">
                    <i class="fas fa-users-slash text-4xl mb-4 opacity-20"></i>
                    <p>{{ tr('No users found.') }}</p>
                </div>
            @endforelse
        </div>
        @if($users->hasPages())
            <div class="mt-6 border-t border-gray-100 pt-4">
                {{ $users->links() }}
            </div>
        @endif
    </div>

    {{-- Table Layout --}}
    <div x-show="view === 'table'" x-cloak class="overflow-hidden rounded-xl border border-gray-200 shadow-sm bg-white">
        <x-ui.table :headers="[tr('User'), tr('Employee Name'), tr('License'), tr('Role'), tr('Permissions'), tr('Access Scope'), tr('Status'), tr('Actions')]">
            @forelse($users as $user)
                <tr class="hover:bg-gray-50 transition-colors" wire:key="user-row-{{ $user->id }}">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex flex-col">
                            <span class="text-sm font-semibold text-gray-900">{{ $user->name }}</span>
                            <span class="text-xs text-gray-500">{{ $user->email }}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex flex-col">
                            <span class="text-sm text-gray-900">
                                {{ $user->employee ? ($user->employee->name_ar ?? $user->employee->name_en) : '-' }}
                            </span>
                            @php
                                $bid = ($user->employee && $employeeBranchCol) ? $user->employee->{$employeeBranchCol} : null;
                                $bname = $bid ? ($branchesById[$bid] ?? null) : null;
                            @endphp
                            @if($bname) 
                                <span class="text-[10px] text-gray-400">{{ $bname }}</span> 
                            @endif
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        @if(($user->access_type ?? 'system_and_app') === 'hr_app_only')
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-700">
                                {{ tr('HR App Only') }}
                            </span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                {{ tr('System & App') }}
                            </span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        @php 
                            $roleNames = $user->roles ? $user->roles->pluck('name')->filter()->values() : collect(); 
                        @endphp
                        @if(($user->access_type ?? 'system_and_app') === 'hr_app_only')
                            <span class="text-xs text-gray-400">—</span>
                        @elseif($user->has_custom_permissions && $user->reference_role)
                            <div class="flex flex-col gap-0.5">
                                <span class="text-sm font-medium text-gray-900">{{ $user->reference_role }}</span>
                                <span class="text-[9px] bg-amber-50 text-amber-700 border border-amber-200 px-1.5 py-0.5 rounded-full font-bold w-fit">
                                    <i class="fas fa-sliders-h mr-0.5"></i> {{ tr('Custom') }}
                                </span>
                            </div>
                        @elseif($roleNames->isNotEmpty())
                            <span class="text-sm font-medium text-gray-900">{{ $roleNames->join(', ') }}</span>
                        @else
                            <span class="text-xs text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap align-middle">
                        <div class="flex items-center gap-2" x-data="{ showTooltip: false, pos: { top: 0, left: 0 } }">
                            @php 
                                $perms = $user->getAllPermissions(); 
                                $permsCount = $perms->count(); 
                            @endphp
                            <div class="relative inline-block">
                                <button type="button" x-ref="trigger" @click="showTooltip = !showTooltip; if(showTooltip) { const rect = $refs.trigger.getBoundingClientRect(); pos = { top: rect.top, left: rect.left + rect.width / 2 }; }" class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-100/50 hover:bg-indigo-100 transition-all group cursor-pointer">
                                    <i class="fas fa-shield-alt text-indigo-400 group-hover:scale-110 transition-transform"></i>
                                    <span class="text-xs font-bold">{{ $permsCount }}</span>
                                </button>
                                <template x-teleport="body">
                                    <div x-show="showTooltip" x-cloak @click.away="showTooltip = false" x-transition.opacity class="fixed z-[9999]" :style="'top: ' + pos.top + 'px; left: ' + pos.left + 'px; transform: translate(-50%, -110%);'">
                                        <div class="bg-white border border-gray-100 rounded-xl shadow-2xl p-3 w-64">
                                            <h5 class="font-bold text-gray-900 text-[10px] uppercase tracking-wider mb-2 border-b border-gray-50 pb-1 flex items-center gap-2">
                                                <i class="fas fa-shield-alt text-indigo-500"></i>
                                                {{ tr('User Permissions') }}
                                            </h5>
                                            <div class="flex flex-wrap gap-1 max-h-48 overflow-y-auto custom-scrollbar">
                                                @forelse($perms as $perm)
                                                    <span class="text-[9px] bg-gray-50 text-gray-600 px-1.5 py-0.5 rounded border border-gray-100">
                                                        {{ tr($permissionsMap[$perm->name] ?? $perm->name) }}
                                                    </span>
                                                @empty
                                                    <span class="text-[10px] text-gray-400 italic">{{ tr('No specific permissions') }}</span>
                                                @endforelse
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                            @if($user->has_custom_permissions)
                                <span class="text-[9px] bg-amber-50 text-amber-700 border border-amber-200 px-1.5 py-0.5 rounded-full font-bold whitespace-nowrap">
                                    <i class="fas fa-sliders-h mr-0.5"></i> {{ tr('Custom') }}
                                </span>
                            @endif
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="text-sm text-gray-700">
                            @php $scope = $user->access_scope ?? 'all_branches'; @endphp
                            @if($scope === 'all_branches')
                                {{ tr('All Branches') }}
                            @elseif($scope === 'selected_branches')
                                {{ tr('Selected Branches') }}
                            @else
                                {{ tr('My Branch') }}
                            @endif
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        @php $isActive = $user->is_active ?? true; @endphp
                        @if($isActive)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                {{ tr('Active') }}
                            </span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                {{ tr('Inactive') }}
                            </span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <x-ui.actions-menu>
                            @can('uac.users.manage')
                                <x-ui.dropdown-item wire:click="sendPasswordReset({{ $user->id }})">
                                    <i class="fas fa-envelope mr-2 w-5 text-indigo-500"></i>
                                    {{ tr('Send Password Reset') }}
                                </x-ui.dropdown-item>
                                <x-ui.dropdown-item wire:click="openEditModal({{ $user->id }})">
                                    <i class="fas fa-edit mr-2 w-5 text-blue-500"></i>
                                    {{ tr('Edit') }}
                                </x-ui.dropdown-item>
                                @php 
                                    $isPrimary = ((int)$user->id === (int)($primaryUserId ?? 0));
                                    $isGlobalAdmin = $user->roles && $user->roles->whereIn('name', ['saas-admin', 'super-admin', 'system-admin'])->isNotEmpty();
                                    $isSysAdmin = $isPrimary || $isGlobalAdmin;
                                @endphp
                                @if(($user->access_type ?? 'system_and_app') !== 'hr_app_only' && !$isSysAdmin)
                                    <x-ui.dropdown-item wire:click="openPermModal({{ $user->id }})">
                                        <i class="fas fa-sliders-h mr-2 w-5 text-amber-500"></i>
                                        {{ tr('Custom Permissions') }}
                                    </x-ui.dropdown-item>
                                @endif
                                @if(!empty($user->device_id))
                                    <x-ui.dropdown-item @click="$dispatch('open-confirm-confirm-reset-device', {{ $user->id }})">
                                        <i class="fas fa-mobile-alt mr-2 w-5 text-purple-500"></i>
                                        {{ tr('Reset Device') }}
                                    </x-ui.dropdown-item>
                                @endif
                                <x-ui.dropdown-item @click="$dispatch('open-confirm-confirm-toggle-status', {{ $user->id }})" :danger="$user->is_active ?? true">
                                    @if($user->is_active ?? true)
                                        <i class="fas fa-user-slash mr-2 w-5 text-red-500"></i>
                                        {{ tr('Deactivate') }}
                                    @else
                                        <i class="fas fa-user-check mr-2 w-5 text-green-500"></i>
                                        {{ tr('Activate') }}
                                    @endif
                                </x-ui.dropdown-item>
                            @endcan
                        </x-ui.actions-menu>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="px-6 py-12 text-center text-gray-500">{{ tr('No users found.') }}</td>
                </tr>
            @endforelse
        </x-ui.table>
        <div class="px-6 py-4 border-t border-gray-100">
            {{ $users->links() }}
        </div>
    </div>

    {{-- User Modal --}}
    <x-ui.modal wire:model="showModal" maxWidth="2xl">
        <x-slot name="title">
            {{ $editingId ? tr('Edit User') : tr('Add New User') }}
        </x-slot>
        <x-slot name="content">
            <form wire:submit.prevent="save">
                @if($errors->any())
                    <div class="mb-4 bg-red-50 border-s-4 border-red-500 p-3">
                        <ul class="list-disc list-inside text-xs text-red-600">
                            @foreach ($errors->all() as $error)
                                <li>{{ tr($error) }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if(!$editingId || $needs_employee_link)
                    <div class="mb-3 p-3 bg-gray-50 rounded-lg border border-gray-100">
                        <x-ui.select
                            wire:model.live="selectedEmployeeId"
                            wire:change="selectEmployee($event.target.value)"
                            label="{{ tr('Employee') }}"
                            searchable="true"
                            :disabled="!auth()->user()->can('uac.users.manage')"
                        >
                            <option value="">{{ tr('Select Employee') }}</option>
                            @foreach($foundEmployees as $emp)
                                <option value="{{ $emp->id }}" @selected((int) $selectedEmployeeId === (int) $emp->id)>
                                    {{ $emp->name_ar ?? $emp->name_en }} - {{ $emp->employee_no }}
                                </option>
                            @endforeach
                        </x-ui.select>
                        @if($selectedEmployeeId)
                            <div class="mt-2 grid grid-cols-2 gap-2 text-[10px] text-gray-600">
                                <div><span class="block text-gray-400">{{ tr('Name') }}</span> {{ $display_name }}</div>
                                <div><span class="block text-gray-400">{{ tr('Dept') }}</span> {{ $display_department }}</div>
                            </div>
                        @endif
                    </div>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <x-ui.input label="{{ tr('Name') }}" wire:model="name" required :disabled="$editingId || !auth()->user()->can('uac.users.manage')" />
                    <x-ui.input label="{{ tr('Email') }}" wire:model="email" type="email" required :disabled="!auth()->user()->can('uac.users.manage')" />
                    
                    <div>
                        <x-ui.select label="{{ tr('License') }}" wire:model="access_type" required :disabled="$is_locked_role || !auth()->user()->can('uac.users.manage')">
                            <option value="system_and_app">{{ tr('System & App') }}</option>
                            <option value="hr_app_only">{{ tr('HR App Only') }}</option>
                        </x-ui.select>
                    </div>

                    @if($access_type === 'system_and_app')
                        <div>
                            <x-ui.select label="{{ tr('Role') }}" wire:model="role" required :disabled="$is_locked_role || !auth()->user()->can('uac.users.manage')">
                                <option value="">{{ tr('Select Role') }}</option>
                                @foreach($roles as $r)
                                    <option value="{{ $r->name }}">{{ $r->name }}</option>
                                @endforeach
                            </x-ui.select>
                        </div>
                    @endif

                    <div>
                        <x-ui.select label="{{ tr('Scope') }}" wire:model="access_scope" required :disabled="!auth()->user()->can('uac.users.manage')" align="up">
                            <option value="my_branch">{{ tr('My Branch') }}</option>
                            <option value="all_branches">{{ tr('All Branches') }}</option>
                            <option value="selected_branches">{{ tr('Selected') }}</option>
                        </x-ui.select>
                    </div>

                    @if($access_scope === 'selected_branches')
                        <div class="md:col-span-2">
                            <x-ui.select
                                multiple="true"
                                wire:model="allowed_branch_ids"
                                label="{{ tr('Allowed Branches') }}"
                                :disabled="!auth()->user()->can('uac.users.manage')"
                                align="up"
                            >
                                @foreach($branches as $br)
                                    <option value="{{ $br['id'] }}">{{ $br['name'] }}</option>
                                @endforeach
                            </x-ui.select>
                        </div>
                    @endif
                </div>

                @if(!$editingId)
                    <div class="mt-4 bg-blue-50 p-2 rounded text-[10px] text-blue-700">
                        <i class="fas fa-info-circle mr-1"></i>
                        {{ tr('A password reset will be sent.') }}
                    </div>
                @endif

                <div class="mt-4 flex justify-end gap-3">
                    <x-ui.secondary-button wire:click="$set('showModal', false)" class="cursor-pointer">
                        {{ tr('Cancel') }}
                    </x-ui.secondary-button>
                    @can('uac.users.manage')
                        <x-ui.primary-button type="submit" wire:loading.attr="disabled" class="cursor-pointer">
                            <span wire:loading.remove>{{ tr('Save') }}</span>
                            <span wire:loading>
                                <i class="fas fa-spinner fa-spin mr-1"></i>
                                {{ tr('Saving...') }}
                            </span>
                        </x-ui.primary-button>
                    @endcan
                </div>
            </form>
        </x-slot>
    </x-ui.modal>

    {{-- Confirm Dialog --}}
    <x-ui.confirm-dialog
        id="confirm-toggle-status"
        :title="tr('Confirm Status Change')"
        :message="tr('Are you sure you want to change this user\'s status?')"
        :confirmText="tr('Confirm')"
        :cancelText="tr('Cancel')"
        confirmAction="wire:toggleStatus(__ID__)"
        type="warning"
    />

    <x-ui.confirm-dialog
        id="confirm-reset-device"
        :title="tr('Confirm Device Reset')"
        :message="tr('Are you sure you want to reset this user\'s device? This will allow them to log in from a different phone.')"
        :confirmText="tr('Reset')"
        :cancelText="tr('Cancel')"
        confirmAction="wire:resetDeviceId(__ID__)"
        type="danger"
    />

    {{-- Custom Permissions Modal --}}
    <x-ui.modal wire:model="showPermModal" maxWidth="5xl">
        <x-slot name="title">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-amber-50">
                    <i class="fas fa-sliders-h text-amber-500 text-lg"></i>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-gray-900 leading-none mb-1">
                        {{ tr('Custom Permissions') }} — {{ $permUserName }}
                    </h3>
                    <p class="text-[10px] text-gray-400 font-medium uppercase tracking-widest">
                        {{ tr('Reference Role') }}: <span class="text-indigo-600 font-bold">{{ $permReferencRole ?? '—' }}</span>
                        @if($permUserHasCustom)
                            &nbsp;·&nbsp; <span class="text-amber-600 font-bold"><i class="fas fa-exclamation-triangle mr-1"></i>{{ tr('Custom overrides active') }}</span>
                        @endif
                    </p>
                </div>
            </div>
        </x-slot>
        <x-slot name="content">
            <div class="space-y-5" x-data="{ permSearch: '', activeGroup: null }">

                {{-- Info banner when custom is active --}}
                @if($permUserHasCustom)
                    <div class="flex items-center justify-between bg-amber-50 border border-amber-200 rounded-xl p-3">
                        <div class="flex items-center gap-2 text-amber-700 text-xs font-semibold">
                            <i class="fas fa-sliders-h"></i>
                            {{ tr('This user has custom permissions that override their role defaults.') }}
                        </div>
                        <button
                            type="button"
                            wire:click="resetToRoleDefault"
                            wire:loading.attr="disabled"
                            class="flex items-center gap-2 px-3 py-1.5 bg-white border border-amber-300 text-amber-700 rounded-lg text-xs font-bold hover:bg-amber-100 transition cursor-pointer"
                        >
                            <i class="fas fa-undo"></i>
                            {{ tr('Reset to Role Default') }}
                        </button>
                    </div>
                @endif

                {{-- Permission Groups --}}
                <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                    <h4 class="text-sm font-extrabold text-gray-900 flex items-center gap-2">
                        <i class="fas fa-shield-alt text-indigo-500"></i>
                        {{ tr('Permissions') }}
                        <span class="text-xs bg-indigo-50 text-indigo-600 px-2 py-0.5 rounded-full font-bold border border-indigo-100">
                            {{ count($customPermissions) }}
                        </span>
                    </h4>
                    <div class="relative w-56">
                        <input
                            type="text"
                            x-model="permSearch"
                            placeholder="{{ tr('Filter permissions...') }}"
                            class="w-full pr-8 pl-3 py-1.5 text-xs border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all"
                        />
                        <span class="absolute inset-y-0 end-0 pe-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-300 text-[10px]"></i>
                        </span>
                    </div>
                </div>

                <div class="flex flex-col md:flex-row gap-6 min-h-[500px]" x-data="{ activeTab: 'core' }">
                    {{-- Tabs Navigation --}}
                    <div class="w-full md:w-64 flex flex-row md:flex-col gap-2 overflow-x-auto md:overflow-y-auto custom-scrollbar border-b md:border-b-0 md:border-e border-gray-100 pb-4 md:pb-0 md:pe-4 min-w-0">
                        @foreach($permissionTabs as $tabKey => $tab)
                            <button
                                type="button"
                                @click="activeTab = '{{ $tabKey }}'"
                                :class="activeTab === '{{ $tabKey }}' ? 'bg-indigo-600 text-white shadow-md scale-[1.02]' : 'bg-white text-gray-500 hover:bg-gray-50 border border-gray-100'"
                                class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 text-sm font-bold whitespace-nowrap group shrink-0"
                            >
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center transition-colors" :class="activeTab === '{{ $tabKey }}' ? 'bg-white/20' : 'bg-gray-50 text-gray-400 group-hover:text-indigo-500'">
                                    <i class="fas {{ $tab['icon'] }} text-xs"></i>
                                </div>
                                <span class="hidden md:inline">{{ $tab['label'] }}</span>
                                <div class="ms-auto flex items-center" x-show="activeTab === '{{ $tabKey }}'">
                                    <i class="fas fa-chevron-left text-[10px] opacity-50 ltr:rotate-180"></i>
                                </div>
                            </button>
                        @endforeach
                    </div>

                    {{-- Tabs Content --}}
                    <div class="flex-1 min-w-0">
                        @foreach($permissionTabs as $tabKey => $tab)
                            <div x-show="activeTab === '{{ $tabKey }}'" x-cloak class="space-y-6 animate-in fade-in slide-in-from-right-2 duration-300">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-sm font-extrabold text-gray-900 flex items-center gap-2">
                                        <i class="fas {{ $tab['icon'] }} text-indigo-500"></i>
                                        {{ $tab['label'] }}
                                    </h4>
                                    <button
                                        type="button"
                                        @php
                                            $allInTabKeys = collect($tab['groups'])->flatMap(fn($g) => array_keys($g))->toArray();
                                            $tabKeysInSelected = array_intersect($allInTabKeys, $customPermissions);
                                            $allSelectedInTab = count($allInTabKeys) > 0 && count($tabKeysInSelected) === count($allInTabKeys);
                                        @endphp
                                        wire:click="toggleTabCustom('{{ $tabKey }}')"
                                        class="text-[10px] font-bold {{ $allSelectedInTab ? 'text-red-600 bg-red-50 border-red-100' : 'text-indigo-600 bg-indigo-50 border-indigo-100' }} border px-3 py-1.5 rounded-lg hover:brightness-95 transition-all"
                                    >
                                        {{ $allSelectedInTab ? tr('Deselect All Section') : tr('Select All Section') }}
                                    </button>
                                </div>

                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 max-h-[450px] overflow-y-auto custom-scrollbar pe-2">
                                    @foreach($tab['groups'] as $groupName => $permissions)
                                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden flex flex-col group/card hover:border-indigo-200 transition-colors self-start">
                                            <div
                                                class="px-4 py-3 bg-gray-50/50 border-b border-gray-100 flex items-center justify-between cursor-pointer hover:bg-gray-50 transition-colors group"
                                                @click="activeGroup = activeGroup === '{{ $groupName }}' ? null : '{{ $groupName }}'"
                                            >
                                                <div class="flex items-center gap-3">
                                                    @php
                                                        $groupKeys = array_keys($permissions);
                                                        $allSelI = count(array_intersect($groupKeys, $customPermissions)) === count($groupKeys);
                                                    @endphp
                                                    <input
                                                        type="checkbox"
                                                        class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                                                        wire:click.stop="toggleGroupCustom('{{ $groupName }}')"
                                                        {{ $allSelI ? 'checked' : '' }}
                                                    />
                                                    <span class="text-xs font-extrabold text-gray-800 group-hover:text-indigo-600 transition-colors">{{ tr($groupName) }}</span>
                                                </div>
                                                <i class="fas fa-chevron-down text-[10px] text-gray-400 transition-transform duration-300" :class="activeGroup === '{{ $groupName }}' ? 'rotate-180 text-indigo-500' : ''"></i>
                                            </div>

                                            <div
                                                x-show="activeGroup === '{{ $groupName }}' || permSearch.length > 0"
                                                class="p-3 grid grid-cols-1 gap-1"
                                            >
                                                @foreach($permissions as $permKey => $permLabel)
                                                    <label
                                                        class="flex items-center gap-3 p-2 rounded-xl hover:bg-slate-50 transition-all cursor-pointer group/item select-none border border-transparent hover:border-slate-100"
                                                        x-show="!permSearch || '{{ strtolower(tr($permLabel)) }}'.includes(permSearch.toLowerCase()) || '{{ strtolower($permKey) }}'.includes(permSearch.toLowerCase())"
                                                    >
                                                        <input
                                                            type="checkbox"
                                                            wire:model="customPermissions"
                                                            value="{{ $permKey }}"
                                                            class="w-4 h-4 rounded-md border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer transition-all"
                                                        >
                                                        <div class="flex flex-col min-w-0">
                                                            <span class="text-xs font-bold text-slate-700 group-hover/item:text-indigo-600 transition-colors break-words leading-tight">{{ tr($permLabel) }}</span>
                                                            <span class="text-[9px] text-slate-400 font-mono tracking-tighter truncate mt-0.5">{{ $permKey }}</span>
                                                        </div>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-4 mt-2">
                    <x-ui.secondary-button wire:click="$set('showPermModal', false)" class="cursor-pointer">
                        {{ tr('Cancel') }}
                    </x-ui.secondary-button>
                    <x-ui.primary-button wire:click="saveCustomPermissions" wire:loading.attr="disabled" class="cursor-pointer">
                        <span wire:loading.remove><i class="fas fa-save mr-1"></i>{{ tr('Save Custom Permissions') }}</span>
                        <span wire:loading><i class="fas fa-spinner fa-spin mr-1"></i>{{ tr('Saving...') }}</span>
                    </x-ui.primary-button>
                </div>
            </div>
        </x-slot>
    </x-ui.modal>

</div>
