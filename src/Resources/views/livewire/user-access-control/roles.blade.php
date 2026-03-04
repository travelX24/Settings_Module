<div class="space-y-6">
    {{-- Top Controls --}}
    <div class="flex flex-col sm:flex-row justify-between items-center gap-4 mb-6">
        <div class="w-full sm:w-1/3">
            <x-ui.input
                wire:model.live.debounce.300ms="search"
                type="search"
                icon="fa-search"
                placeholder="{{ tr('Search roles...') }}"
            />
        </div>

        @if(!empty($branches))
            <div class="w-full sm:w-1/4">
                <x-ui.select
                    label="{{ tr('Branch') }}"
                    wire:model.live="filterBranchId"
                    :disabled="$lockBranchFilter"
                >
                    <option value="">{{ tr('All Branches') }}</option>
                    @foreach($branches as $br)
                        <option value="{{ $br['id'] }}">{{ $br['name'] }}</option>
                    @endforeach
                </x-ui.select>

                @if($lockBranchFilter)
                    <div class="text-[10px] text-gray-500 mt-1">
                        {{ tr('Access scope is limited to your branch.') }}
                    </div>
                @endif
            </div>
        @endif
    </div>

    {{-- Roles Table --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <x-ui.table
            :headers="[
                tr('Role Name'),
                tr('Permissions'),
                tr('Users Count'),
                tr('Created At'),
                tr('Actions')
            ]"
            :headerAlign="['start','center','center','center','end']"
            :enablePagination="false"
        >
            @forelse($roles as $role)
                <tr class="hover:bg-gray-50/50 transition-colors border-b border-gray-50 last:border-0">
                    <td class="px-6 py-4 whitespace-nowrap align-middle text-start">
                        <div class="flex flex-col">
                            <span class="text-sm font-bold text-gray-900">{{ $role->name }}</span>
                            <span class="text-[10px] text-gray-400 font-mono uppercase tracking-tighter">{{ $role->guard_name }}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap align-middle text-center">
                        <div class="flex items-center justify-center gap-1.5" x-data="{ 
                            showPerms: false,
                            pos: { top: 0, left: 0 }
                        }">
                            <div class="relative inline-block">
                                @php $permsCount = $role->permissions->count(); @endphp
                                <button 
                                    type="button" 
                                    x-ref="trigger"
                                    @click="
                                        showPerms = !showPerms;
                                        if(showPerms) {
                                            const rect = $refs.trigger.getBoundingClientRect();
                                            pos = { top: rect.top, left: rect.left + rect.width / 2 };
                                        }
                                    "
                                    class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-100/50 hover:bg-indigo-100 transition-all group"
                                >
                                    <i class="fas fa-shield-alt text-indigo-400 group-hover:scale-110 transition-transform"></i>
                                    <span class="text-xs font-bold">{{ $permsCount }}</span>
                                </button>

                                {{-- Permissions Tooltip --}}
                                <template x-teleport="body">
                                    <div 
                                        x-show="showPerms" 
                                        x-cloak
                                        @click.away="showPerms = false"
                                        x-transition:enter="transition ease-out duration-200"
                                        x-transition:enter-start="opacity-0 translate-y-1"
                                        x-transition:enter-end="opacity-100 translate-y-0"
                                        class="fixed z-[9999]"
                                        :style="`top: ${pos.top}px; left: ${pos.left}px; transform: translate(-50%, -110%);`"
                                    >
                                        <div class="bg-white border border-gray-100 rounded-xl shadow-2xl p-3 w-64 text-start">
                                            <h5 class="font-bold text-gray-900 text-[10px] uppercase tracking-wider mb-2 border-b border-gray-50 pb-1 flex items-center gap-2">
                                                <i class="fas fa-shield-alt text-indigo-500"></i>
                                                {{ tr('Role Permissions') }}
                                            </h5>
                                            <div class="flex flex-wrap gap-1 max-h-48 overflow-y-auto custom-scrollbar">
                                                @forelse($role->permissions as $perm)
                                                    <span class="text-[9px] bg-gray-50 text-gray-600 px-1.5 py-0.5 rounded border border-gray-100">{{ tr($permissionsMap[$perm->name] ?? $perm->name) }}</span>
                                                @empty
                                                    <span class="text-[10px] text-gray-400 italic">{{ tr('No permissions assigned') }}</span>
                                                @endforelse
                                            </div>
                                            <div class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-2 h-2 bg-white border-b border-r border-gray-100 transform rotate-45"></div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap align-middle text-center">
                        <div class="flex items-center justify-center" x-data="{ 
                            showUsers: false,
                            pos: { top: 0, left: 0 }
                        }">
                            <div class="relative inline-block">
                                <button 
                                    type="button"
                                    x-ref="trigger"
                                    @click="
                                        showUsers = !showUsers;
                                        if(showUsers) {
                                            const rect = $refs.trigger.getBoundingClientRect();
                                            pos = { top: rect.top, left: rect.left + rect.width / 2 };
                                        }
                                    "
                                    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-50 text-slate-700 border border-slate-100/60 hover:bg-slate-100 transition-colors"
                                >
                                    <i class="fas fa-users text-slate-400 text-xs"></i>
                                    <span class="text-xs font-bold">{{ $role->users_count }}</span>
                                </button>

                                {{-- Users Tooltip --}}
                                <template x-teleport="body">
                                    <div 
                                        x-show="showUsers" 
                                        x-cloak
                                        @click.away="showUsers = false"
                                        x-transition:enter="transition ease-out duration-200"
                                        x-transition:enter-start="opacity-0 translate-y-1"
                                        x-transition:enter-end="opacity-100 translate-y-0"
                                        class="fixed z-[9999]"
                                        :style="`top: ${pos.top}px; left: ${pos.left}px; transform: translate(-50%, -110%);`"
                                    >
                                        <div class="bg-white border border-gray-100 rounded-xl shadow-2xl p-3 w-48 text-start">
                                            <h5 class="font-bold text-gray-900 text-[10px] uppercase tracking-wider mb-2 border-b border-gray-50 pb-1 flex items-center gap-2">
                                                <i class="fas fa-user-friends text-slate-500"></i>
                                                {{ tr('Assigned Users') }}
                                            </h5>
                                            <div class="space-y-1.5 max-h-40 overflow-y-auto custom-scrollbar">
                                                @php
                                                    $roleUsersQ = \App\Models\User::role($role->name)
                                                        ->where('saas_company_id', auth()->user()->saas_company_id);

                                                    if(($filterBranchId ?? '') !== '' && ($employeeBranchCol ?? null)) {
                                                        $bid = (int)$filterBranchId;
                                                        $col = $employeeBranchCol;

                                                        $roleUsersQ->whereHas('employee', fn($q) => $q->where($col, $bid));
                                                    }

                                                    $roleUsers = $roleUsersQ->take(10)->get();
                                                @endphp      
                                                                                          @forelse($roleUsers as $u)
                                                    <div class="text-[10px] text-gray-700 flex items-center gap-2">
                                                        <div class="w-1 h-1 rounded-full bg-slate-300"></div>
                                                        <span class="font-medium">{{ $u->name }}</span>
                                                    </div>
                                                @empty
                                                    <div class="text-[10px] text-gray-400 italic text-center py-2">{{ tr('No users assigned') }}</div>
                                                @endforelse
                                                @if($role->users_count > 10)
                                                    <div class="text-[9px] text-indigo-500 font-bold border-t border-gray-50 pt-1 text-center mt-1">
                                                        +{{ $role->users_count - 10 }} {{ tr('more users') }}
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-2 h-2 bg-white border-b border-r border-gray-100 transform rotate-45"></div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap align-middle text-center">
                        <div class="flex flex-col">
                            <span class="text-xs text-gray-600 font-medium">{{ $role->created_at->format('Y/m/d') }}</span>
                            <span class="text-[10px] text-gray-400">{{ $role->created_at->format('h:i A') }}</span>
                        </div>
                    </td>
<td class="px-6 py-4 whitespace-nowrap align-middle {{ app()->isLocale('ar') ? 'text-left' : 'text-right' }}">
                        <x-ui.actions-menu>
                            @can('uac.roles.manage')
                            <x-ui.dropdown-item wire:click="openEditModal({{ $role->id }})">
                                <i class="fas fa-edit mr-2 w-5 text-blue-500"></i>
                                {{ tr('Edit Role') }}
                            </x-ui.dropdown-item>

                            <x-ui.dropdown-item wire:click="copyRole({{ $role->id }})">
                                <i class="fas fa-copy mr-2 w-5 text-emerald-500"></i>
                                {{ tr('Duplicate Role') }}
                            </x-ui.dropdown-item>
                            @endcan

                            @if($role->name !== 'company-admin' && $role->name !== 'saas-admin')
                                @can('uac.roles.manage')
                                <div class="h-px bg-gray-100 my-1"></div>
                                <x-ui.dropdown-item 
                                    wire:click="deleteRole({{ $role->id }})" 
                                    danger
                                    onclick="confirm('{{ tr('Are you sure you want to delete this role?') }}') || event.stopImmediatePropagation()"
                                >
                                    <i class="fas fa-trash-alt mr-2 w-5"></i>
                                    {{ tr('Delete Role') }}
                                </x-ui.dropdown-item>
                                @endcan
                            @endif
                        </x-ui.actions-menu>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-6 py-16 text-center">
                        <div class="flex flex-col items-center justify-center max-w-xs mx-auto">
                            <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mb-4">
                                <i class="fas fa-user-shield text-3xl text-gray-200"></i>
                            </div>
                            <h3 class="text-sm font-bold text-gray-900 mb-1">{{ tr('No roles found') }}</h3>
                            <p class="text-xs text-gray-500">{{ tr('Try adjusting your search or create a new role to get started.') }}</p>
                        </div>
                    </td>
                </tr>
            @endforelse
        </x-ui.table>
    </div>

    {{-- Pagination --}}
    <div class="mt-4">
        {{ $roles->links() }}
    </div>

    {{-- Add/Edit Role Modal --}}
    <x-ui.modal wire:model="showModal" maxWidth="5xl">
        <x-slot name="title">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center {{ $editingId ? 'bg-blue-50' : 'bg-emerald-50' }}">
                    <i class="fas {{ $editingId ? 'fa-edit text-blue-500' : 'fa-plus-circle text-emerald-500' }} text-lg"></i>
                </div>
                <div class="flex flex-col">
                    <span class="text-base font-extrabold text-gray-900">{{ $editingId ? tr('Edit Role') : tr('Add New Role') }}</span>
                    <span class="text-[10px] text-gray-400 font-medium uppercase tracking-widest">{{ $editingId ? tr('Update permissions & settings') : tr('Define a new access group') }}</span>
                </div>
            </div>
        </x-slot>

        <x-slot name="content">
            <div class="space-y-6 py-2" x-data="{ permSearch: '', activeGroup: null }">
                {{-- Error Messages --}}
                @if($errors->any())
                    <div class="bg-red-50 border-r-4 border-red-500 p-4 rounded-xl mb-4">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-exclamation-triangle text-red-500"></i>
                            <span class="text-sm font-bold text-red-800">{{ tr('Validation Error') }}</span>
                        </div>
                        <ul class="mt-2 text-xs text-red-700 list-disc list-inside">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Role Information --}}
                <div class="bg-gray-50/50 p-5 rounded-2xl border border-gray-100">
                    <x-ui.input 
                        label="{{ tr('Role Name') }}" 
                        wire:model="name" 
                        required 
                        placeholder="{{ tr('e.g. Sales Manager...') }}"
                        class="!bg-white"
                        error="name"
                        :disabled="!auth()->user()->can('uac.roles.manage')"
                    />
                </div>

                {{-- Permissions Accordion Section --}}
                <div class="space-y-4">
                    <div class="flex items-center justify-between border-b border-gray-100 pb-2">
                        <h3 class="text-sm font-extrabold text-gray-900 flex items-center gap-2">
                            <i class="fas fa-shield-alt text-[color:var(--brand-via)]"></i>
                            {{ tr('Interface Permissions') }}
                        </h3>
                        <div class="relative w-48 sm:w-64">
                            <input 
                                type="text" 
                                x-model="permSearch"
                                placeholder="{{ tr('Filter permissions...') }}"
                                class="w-full pr-8 pl-3 py-1.5 text-[11px] border border-gray-200 rounded-xl focus:ring-2 focus:ring-[color:var(--brand-via)]/20 focus:border-[color:var(--brand-via)] outline-none transition-all"
                            >
                            <span class="absolute inset-y-0 right-0 pr-2.5 flex items-center pointer-events-none">
                                <i class="fas fa-search text-gray-400 text-[10px]"></i>
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 max-h-[500px] overflow-y-auto custom-scrollbar px-1">
                        @foreach($permissionGroups as $groupName => $permissions)
                            <div 
                                class="bg-white border rounded-xl transition-all duration-200 self-start"
                                :class="activeGroup === '{{ $groupName }}' ? 'border-[color:var(--brand-via)] shadow-sm' : 'border-gray-100'"
                                x-show="!permSearch || '{{ strtolower(tr($groupName)) }}'.includes(permSearch.toLowerCase()) || @js(array_values($permissions)).some(p => p.toLowerCase().includes(permSearch.toLowerCase()))"
                            >
                                {{-- Group Title / Header --}}
                                <div 
                                    class="px-4 py-3 flex items-center justify-between cursor-pointer group select-none"
                                    @click="activeGroup = (activeGroup === '{{ $groupName }}' ? null : '{{ $groupName }}')"
                                >
                                    <div class="flex items-center gap-3">
                                        <div class="relative flex items-center">
                                            <input 
                                                type="checkbox" 
                                                class="w-4 h-4 rounded border-gray-300 text-[color:var(--brand-via)] focus:ring-[color:var(--brand-via)] cursor-pointer"
                                                wire:click.stop="toggleGroup('{{ $groupName }}')"
                                                @php
                                                    $groupKeys = array_keys($permissions);
                                                    $allSelected = count(array_intersect($groupKeys, $selectedPermissions)) === count($groupKeys);
                                                @endphp
                                                {{ $allSelected ? 'checked' : '' }}
                                                @cannot('uac.roles.manage') disabled @endcannot
                                            >
                                        </div>
                                        <span class="text-xs font-bold text-gray-800 group-hover:text-[color:var(--brand-via)] transition-colors">{{ tr($groupName) }}</span>
                                    </div>
                                    <div class="flex items-center gap-3 text-gray-400">
                                        <span class="text-[10px] font-bold bg-gray-50 px-2 py-0.5 rounded-full border border-gray-100">{{ count($permissions) }}</span>
                                        <i class="fas fa-chevron-down text-[10px] transition-transform duration-300" :class="activeGroup === '{{ $groupName }}' ? 'rotate-180 text-[color:var(--brand-via)]' : ''"></i>
                                    </div>
                                </div>

                                {{-- Permissions Body (Collapsible) --}}
                                <div 
                                    x-show="activeGroup === '{{ $groupName }}' || permSearch.length > 0"
                                    x-transition:enter="transition ease-out duration-200"
                                    x-transition:enter-start="opacity-0 -translate-y-2"
                                    x-transition:enter-end="opacity-100 translate-y-0"
                                    class="px-4 pb-4 pt-1 grid grid-cols-1 gap-1.5 border-t border-gray-50"
                                >
                                    @foreach($permissions as $permKey => $permLabel)
                                        <label 
                                            class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 transition-colors cursor-pointer group/item select-none border border-transparent hover:border-gray-100"
                                            x-show="!permSearch || '{{ strtolower(tr($permLabel)) }}'.includes(permSearch.toLowerCase()) || '{{ strtolower($permKey) }}'.includes(permSearch.toLowerCase())"
                                        >
                                            <input 
                                                type="checkbox" 
                                                wire:model="selectedPermissions" 
                                                value="{{ $permKey }}"
                                                class="w-3.5 h-3.5 rounded border-gray-300 text-[color:var(--brand-via)] focus:ring-[color:var(--brand-via)] cursor-pointer"
                                                @cannot('uac.roles.manage') disabled @endcannot
                                            >
                                            <div class="flex flex-col min-w-0 overflow-hidden">
                                                <span class="text-[11px] font-semibold text-gray-700 group-hover/item:text-[color:var(--brand-via)] transition-colors break-words leading-tight">{{ tr($permLabel) }}</span>
                                                <span class="text-[8px] text-gray-400 font-mono tracking-tighter truncate">{{ $permKey }}</span>
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </x-slot>

        <x-slot name="footer">
            <div class="flex items-center justify-between w-full">
                <div class="hidden sm:flex items-center gap-2">
                     <div class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">{{ tr('Permissions') }}:</div>
                     <div class="text-xs font-extrabold text-[color:var(--brand-via)] bg-[color:var(--brand-via)]/10 px-3 py-1 rounded-full border border-[color:var(--brand-via)]/20">
                        {{ count($selectedPermissions) }}
                     </div>
                </div>
                <div class="flex items-center gap-3">
                    <x-ui.secondary-button wire:click="$set('showModal', false)" class="!px-6 !text-xs">
                        {{ tr('Cancel') }}
                    </x-ui.secondary-button>
                    
                    @can('uac.roles.manage')
                    <x-ui.primary-button type="button" wire:click="save" wire:loading.attr="disabled" :arrow="false" class="!px-8">
                        <span wire:loading.remove class="flex items-center gap-2">
                            <i class="fas fa-check-circle"></i>
                            {{ $editingId ? tr('Update Role') : tr('Create Role') }}
                        </span>
                        <span wire:loading class="flex items-center gap-2">
                            <i class="fas fa-spinner fa-spin"></i>
                            {{ tr('Saving...') }}
                        </span>
                    </x-ui.primary-button>
                    @endcan
                </div>
            </div>
        </x-slot>
    </x-ui.modal>

    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #d1d5db; }
    </style>
</div>





