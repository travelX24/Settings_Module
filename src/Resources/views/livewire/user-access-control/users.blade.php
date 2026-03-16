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
                            <span class="text-xs font-bold text-indigo-600">
                                {{ $roleNames->isNotEmpty() ? $roleNames->join(', ') : '-' }}
                            </span>
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
                        @elseif($roleNames->isNotEmpty())
                            <span class="text-sm font-medium text-gray-900">{{ $roleNames->join(', ') }}</span>
                        @else
                            <span class="text-xs text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap align-middle">
                        <div class="flex items-center" x-data="{ showTooltip: false, pos: { top: 0, left: 0 } }">
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
</div>
