<div
    class="space-y-6"
    x-data="{
        view: (localStorage.getItem('uac_roles_view') || 'table'),
        setView(v){
            this.view = v;
            localStorage.setItem('uac_roles_view', v);
        }
    }"
    x-init="localStorage.setItem('uac_roles_view', view)"
>
    {{-- Search & Filters --}}
    <x-ui.card :padding="false" class="border-gray-200 p-4">
        <div class="flex flex-col md:flex-row gap-4 items-start md:items-end justify-between">
            <div class="flex-1 w-full">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1.5">
                            {{ tr('Search Roles') }}
                        </label>
                        <x-ui.search-box wire:model.live.debounce.300ms="search" :placeholder="tr('Search by role name...')" />
                    </div>

                    @if(!empty($branches))
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1.5">
                                {{ tr('Branch Filter') }}
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
            @forelse($roles as $role)
                <x-ui.card wire:key="role-card-{{ $role->id }}" :hover="true" :padding="false" class="rounded-2xl border-gray-200 p-5 group flex flex-col h-full">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white shadow-lg shrink-0">
                                <i class="fas fa-user-shield text-xl"></i>
                            </div>
                            <div class="min-w-0">
                                <h4 class="text-base font-bold text-gray-900 truncate">{{ $role->name }}</h4>
                                <p class="text-[10px] text-gray-400 font-mono uppercase tracking-tighter">{{ $role->guard_name }}</p>
                            </div>
                        </div>

                        <x-ui.actions-menu>
                            @can('uac.roles.manage')
                                @php 
                                    $isProtected = in_array($role->name, ['company-admin', 'saas-admin', 'super-admin', 'system-admin']) || is_null($role->saas_company_id);
                                @endphp

                                @if(!$isProtected)
                                    <x-ui.dropdown-item wire:click="openEditModal({{ $role->id }})">
                                        <i class="fas fa-edit mr-2 w-5 text-blue-500"></i>
                                        {{ tr('Edit Role') }}
                                    </x-ui.dropdown-item>
                                @endif
                                <x-ui.dropdown-item wire:click="copyRole({{ $role->id }})">
                                    <i class="fas fa-copy mr-2 w-5 text-emerald-500"></i>
                                    {{ tr('Duplicate Role') }}
                                </x-ui.dropdown-item>
                                
                                @if(!$isProtected)
                                    <div class="h-px bg-gray-100 my-1"></div>
                                    <x-ui.dropdown-item 
                                        @click="$dispatch('open-confirm-delete-role', {{ $role->id }})" 
                                        danger
                                    >
                                        <i class="fas fa-trash-alt mr-2 w-5"></i>
                                        {{ tr('Delete Role') }}
                                    </x-ui.dropdown-item>
                                @endif
                            @endcan
                        </x-ui.actions-menu>
                    </div>

                    <div class="space-y-3 flex-1">
                        <div class="flex items-center justify-between py-2 border-b border-gray-50">
                            <span class="text-xs font-medium text-gray-500">{{ tr('Users Count') }}</span>
                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-lg bg-slate-50 text-slate-700 border border-slate-100 text-[10px] font-bold">
                                <i class="fas fa-users text-[8px] text-slate-400"></i>
                                {{ $role->users_count }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between py-2 border-b border-gray-50">
                            <span class="text-xs font-medium text-gray-500">{{ tr('Permissions') }}</span>
                            @php $permsCount = $role->permissions->count(); @endphp
                            <span class="text-xs font-bold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-lg">
                                {{ $permsCount }} {{ tr('Permissions') }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between py-2 border-b border-gray-50">
                            <span class="text-xs font-medium text-gray-500">{{ tr('Created At') }}</span>
                            <span class="text-[10px] font-bold text-gray-700">
                                {{ company_date($role->created_at) }}
                            </span>
                        </div>
                    </div>

                    <div class="mt-4 flex items-center justify-between">
                         <span class="text-[10px] bg-indigo-50 text-indigo-700 px-2 py-1 rounded-lg font-bold border border-indigo-100">
                            <i class="fas fa-shield-alt mr-1"></i> {{ tr('Access Role') }}
                        </span>
                    </div>
                </x-ui.card>
            @empty
                <div class="col-span-full py-12 text-center text-gray-400">
                    <i class="fas fa-user-shield text-4xl mb-4 opacity-20"></i>
                    <p>{{ tr('No roles found.') }}</p>
                </div>
            @endforelse
        </div>
        @if($roles->hasPages())
            <div class="mt-6 border-t border-gray-100 pt-4">
                {{ $roles->links() }}
            </div>
        @endif
    </div>

    {{-- Table Layout --}}
    <div x-show="view === 'table'" x-cloak class="overflow-hidden rounded-xl border border-gray-200 shadow-sm bg-white">
        <x-ui.table :headers="[tr('Role Name'), tr('Guard'), tr('Permissions'), tr('Users'), tr('Created At'), tr('Actions')]">
            @forelse($roles as $role)
                <tr class="hover:bg-gray-50 transition-colors" wire:key="role-row-{{ $role->id }}">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="text-sm font-semibold text-gray-900">{{ $role->name }}</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-mono font-medium bg-slate-100 text-slate-700 uppercase">
                            {{ $role->guard_name }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap align-middle">
                        <div class="flex items-center" x-data="{ showTooltip: false, pos: { top: 0, left: 0 } }">
                            @php $permsCount = $role->permissions->count(); @endphp
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
                                                {{ tr('Role Permissions') }}
                                            </h5>
                                            <div class="flex flex-wrap gap-1 max-h-48 overflow-y-auto custom-scrollbar">
                                                @forelse($role->permissions as $perm)
                                                    <span class="text-[9px] bg-gray-50 text-gray-600 px-1.5 py-0.5 rounded border border-gray-100">
                                                        {{ tr($permissionsMap[$perm->name] ?? $perm->name) }}
                                                    </span>
                                                @empty
                                                    <span class="text-[10px] text-gray-400 italic">{{ tr('No permissions') }}</span>
                                                @endforelse
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-lg bg-slate-50 text-slate-700 border border-slate-100 text-xs font-bold">
                            <i class="fas fa-users text-[10px] text-slate-400"></i>
                            {{ $role->users_count }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex flex-col">
                            <span class="text-xs text-gray-700 font-medium">{{ company_date($role->created_at) }}</span>
                            <span class="text-[10px] text-gray-400">{{ $role->created_at->format('h:i A') }}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <x-ui.actions-menu>
                            @can('uac.roles.manage')
                                @php 
                                    $isProtected = in_array($role->name, ['company-admin', 'saas-admin', 'super-admin', 'system-admin']) || is_null($role->saas_company_id);
                                @endphp

                                @if(!$isProtected)
                                    <x-ui.dropdown-item wire:click="openEditModal({{ $role->id }})">
                                        <i class="fas fa-edit mr-2 w-5 text-blue-500"></i>
                                        {{ tr('Edit Role') }}
                                    </x-ui.dropdown-item>
                                @endif

                                <x-ui.dropdown-item wire:click="copyRole({{ $role->id }})">
                                    <i class="fas fa-copy mr-2 w-5 text-emerald-500"></i>
                                    {{ tr('Duplicate') }}
                                </x-ui.dropdown-item>
                                
                                @if(!$isProtected)
                                    <div class="h-px bg-gray-100 my-1"></div>
                                    <x-ui.dropdown-item @click="$dispatch('open-confirm-delete-role', {{ $role->id }})" danger>
                                        <i class="fas fa-trash-alt mr-2 w-5"></i>
                                        {{ tr('Delete') }}
                                    </x-ui.dropdown-item>
                                @endif
                            @endcan
                        </x-ui.actions-menu>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-gray-500">{{ tr('No roles found.') }}</td>
                </tr>
            @endforelse
        </x-ui.table>
        <div class="px-6 py-4 border-t border-gray-100">
            {{ $roles->links() }}
        </div>
    </div>

    {{-- Role Modal --}}
    <x-ui.modal wire:model="showModal" maxWidth="5xl">
        <x-slot name="title">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center {{ $editingId ? 'bg-blue-50' : 'bg-emerald-50' }}">
                    <i class="fas {{ $editingId ? 'fa-edit text-blue-500' : 'fa-plus text-emerald-500' }} text-lg"></i>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-gray-900 leading-none mb-1">
                        {{ $editingId ? tr('Edit Role') : tr('Add New Role') }}
                    </h3>
                    <p class="text-[10px] text-gray-400 font-medium uppercase tracking-widest">
                        {{ $editingId ? tr('Update role permissions') : tr('Set up a new access role') }}
                    </p>
                </div>
            </div>
        </x-slot>
        <x-slot name="content">
            <div class="space-y-6" x-data="{ permSearch: '', activeGroup: null }">
                @if($errors->any())
                    <div class="bg-red-50 border-s-4 border-red-500 p-3 rounded-lg">
                        <ul class="list-disc list-inside text-xs text-red-600">
                            @foreach ($errors->all() as $error)
                                <li>{{ tr($error) }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="bg-gray-50/50 p-4 rounded-2xl border border-gray-100">
                    <x-ui.input 
                        label="{{ tr('Role Name') }}" 
                        wire:model="name" 
                        required 
                        :disabled="!auth()->user()->can('uac.roles.manage')"
                        placeholder="{{ tr('e.g. Sales Manager, HR Admin...') }}"
                    />
                </div>

                <div class="space-y-4">
                    <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                        <h4 class="text-sm font-extrabold text-gray-900 flex items-center gap-2">
                             <i class="fas fa-shield-alt text-indigo-500"></i>
                             {{ tr('Role Permissions') }}
                        </h4>
                        <div class="relative w-64">
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
                                            $tabKeysInSelected = array_intersect($allInTabKeys, $selectedPermissions);
                                            $allSelectedInTab = count($allInTabKeys) > 0 && count($tabKeysInSelected) === count($allInTabKeys);
                                        @endphp
                                        wire:click="toggleTab('{{ $tabKey }}')"
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
                                                        $allSelectedInGroup = count(array_intersect($groupKeys, $selectedPermissions)) === count($groupKeys); 
                                                    @endphp
                                                    <input 
                                                        type="checkbox" 
                                                        class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                                                        wire:click.stop="toggleGroup('{{ $groupName }}')"
                                                        {{ $allSelectedInGroup ? 'checked' : '' }}
                                                        @cannot('uac.roles.manage') disabled @endcannot
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
                                                            wire:model="selectedPermissions" 
                                                            value="{{ $permKey }}"
                                                            class="w-4 h-4 rounded-md border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer transition-all"
                                                            @cannot('uac.roles.manage') disabled @endcannot
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

                <div class="flex items-center justify-between border-t border-gray-100 pt-4 mt-8">
                     <div class="flex items-center gap-2">
                         <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">{{ tr('Total Permissions') }}:</span>
                         <span class="text-xs font-extrabold text-indigo-600 bg-indigo-50 px-3 py-1 rounded-full border border-indigo-100">
                            {{ count($selectedPermissions) }}
                         </span>
                     </div>
                     <div class="flex items-center gap-3">
                        <x-ui.secondary-button wire:click="$set('showModal', false)">
                            {{ tr('Cancel') }}
                        </x-ui.secondary-button>
                        @can('uac.roles.manage')
                            <x-ui.primary-button type="button" wire:click="save" wire:loading.attr="disabled">
                                <span wire:loading.remove>{{ tr('Save Changes') }}</span>
                                <span wire:loading>
                                    <i class="fas fa-spinner fa-spin mr-1"></i> {{ tr('Saving...') }}
                                </span>
                            </x-ui.primary-button>
                        @endcan
                     </div>
                </div>
            </div>
        </x-slot>
    </x-ui.modal>

    {{-- Confirm Dialog --}}
    <x-ui.confirm-dialog
        id="delete-role"
        :title="tr('Delete Role')"
        :message="tr('Are you sure you want to delete this role? This action cannot be undone.')"
        :confirmText="tr('Delete Role')"
        cancelText="{{ tr('Keep Role') }}"
        confirmAction="wire:delete(__ID__)"
        type="danger"
    />

    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #d1d5db; }
    </style>
</div>
