<div class="space-y-6">
    {{-- Top Controls --}}
    <div class="flex flex-col sm:flex-row justify-between gap-4 mb-6">
        <div class="w-full sm:w-1/3">
            <x-ui.input
                wire:model.live.debounce.300ms="search"
                type="search"
                icon="fa-search"
                placeholder="{{ tr('Search by name or email...') }}"
            />
        </div>
    </div>

    {{-- Users Table --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 shadow-sm bg-white">
        <x-ui.table
         :headers="[
                tr('User'),
                tr('Employee Name'),
                tr('License'),
                tr('Role'),
                tr('Permissions'),
                tr('Access Scope'),
                tr('Status'),
                tr('Actions')
            ]"

        >
            @forelse($users as $user)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex flex-col">
                            <span class="text-sm font-semibold text-gray-900">{{ $user->name }}</span>
                            <span class="text-xs text-gray-500">{{ $user->email }}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="text-sm text-gray-900">
                            {{ $user->employee ? ($user->employee->name_ar ?? $user->employee->name_en) : '-' }}
                        </span>
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

                    <td class="px-6 py-4 whitespace-nowrap align-middle">
                        <div class="flex items-center" x-data="{ 
                            showTooltip: false,
                            pos: { top: 0, left: 0 }
                        }">
                            @php 
                                $perms = $user->getAllPermissions();
                                $permsCount = $perms->count();
                            @endphp
                            <div class="relative inline-block">
                                <button 
                                    type="button" 
                                    x-ref="trigger"
                                    @click="
                                        showTooltip = !showTooltip;
                                        if(showTooltip) {
                                            const rect = $refs.trigger.getBoundingClientRect();
                                            pos = { top: rect.top, left: rect.left + rect.width / 2 };
                                        }
                                    "
                                    class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-100/50 hover:bg-indigo-100 transition-all group"
                                >
                                    <i class="fas fa-shield-alt text-indigo-400 group-hover:scale-110 transition-transform"></i>
                                    <span class="text-xs font-bold">{{ $permsCount }}</span>
                                </button>

                                {{-- Tooltip --}}
                                <template x-teleport="body">
                                    <div 
                                        x-show="showTooltip" 
                                        x-cloak
                                        @click.away="showTooltip = false"
                                        x-transition:enter="transition ease-out duration-200"
                                        x-transition:enter-start="opacity-0 translate-y-1"
                                        x-transition:enter-end="opacity-100 translate-y-0"
                                        class="fixed z-[9999]"
                                        :style="`top: ${pos.top}px; left: ${pos.left}px; transform: translate(-50%, -110%);`"
                                    >
                                        <div class="bg-white border border-gray-100 rounded-xl shadow-2xl p-3 w-64">
                                            <h5 class="font-bold text-gray-900 text-[10px] uppercase tracking-wider mb-2 border-b border-gray-50 pb-1 flex items-center gap-2">
                                                <i class="fas fa-shield-alt text-indigo-500"></i>
                                                {{ tr('User Permissions') }}
                                            </h5>
                                            <div class="flex flex-wrap gap-1 max-h-48 overflow-y-auto custom-scrollbar">
                                                @forelse($perms as $perm)
                                                    <span class="text-[9px] bg-gray-50 text-gray-600 px-1.5 py-0.5 rounded border border-gray-100">{{ tr($permissionsMap[$perm->name] ?? $perm->name) }}</span>
                                                @empty
                                                    <span class="text-[10px] text-gray-400 italic">{{ tr('No specific permissions') }}</span>
                                                @endforelse
                                            </div>
                                            <div class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-2 h-2 bg-white border-b border-r border-gray-100 transform rotate-45"></div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="text-sm text-gray-700">
                            {{ $user->access_scope === 'all_branches' ? tr('All Branches') : tr('My Branch') }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $user->is_active ?? true ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                            {{ ($user->is_active ?? true) ? tr('Active') : tr('Inactive') }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <x-ui.actions-menu>
                            @can('uac.users.manage')
                            {{-- Send Password Reset --}}
                            <x-ui.dropdown-item wire:click="sendPasswordReset({{ $user->id }})">
                                <i class="fas fa-envelope mr-2 w-5 text-indigo-500"></i>
                                {{ tr('Send Password Reset') }}
                            </x-ui.dropdown-item>

                            {{-- Edit --}}
                            <x-ui.dropdown-item wire:click="openEditModal({{ $user->id }})">
                                <i class="fas fa-edit mr-2 w-5 text-blue-500"></i>
                                {{ tr('Edit') }}
                            </x-ui.dropdown-item>

                            {{-- Toggle Status --}}
                            <x-ui.dropdown-item 
                                wire:click="toggleStatus({{ $user->id }})" 
                                :danger="$user->is_active ?? true"
                                onclick="confirm('{{ tr('Are you sure you want to change this user status?') }}') || event.stopImmediatePropagation()"
                            >
                                <i class="fas {{ ($user->is_active ?? true) ? 'fa-user-slash' : 'fa-user-check mr-2 w-5' }}"></i>
                                {{ ($user->is_active ?? true) ? tr('Deactivate') : tr('Activate') }}
                            </x-ui.dropdown-item>
                            @endcan
                        </x-ui.actions-menu>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                        <div class="flex flex-col items-center justify-center">
                            <i class="fas fa-users-slash text-4xl mb-3 text-gray-300"></i>
                            <p>{{ tr('No users found.') }}</p>
                        </div>
                    </td>
                </tr>
            @endforelse
        </x-ui.table>
    </div>

    {{-- Pagination --}}
    <div class="mt-4">
        {{ $users->links() }}
    </div>

    {{-- Add/Edit User Modal --}}
    <x-ui.modal wire:model="showModal" maxWidth="2xl">
        <x-slot name="title">
            {{ $editingId ? tr('Edit User') : tr('Add New User') }}
        </x-slot>

        <x-slot name="content">
            <form wire:submit.prevent="save">
                @if($errors->any())
                    <div class="mb-4 bg-red-50 border-l-4 border-red-500 p-3">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-circle text-red-500"></i>
                            </div>
                            <div class="ms-3">
                                <p class="text-sm text-red-700 font-medium">
                                    {{ tr('Please fix the following errors:') }}
                                </p>
                                <ul class="mt-1 list-disc list-inside text-xs text-red-600">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif

                @if(!$editingId || $needs_employee_link)
                    {{-- Part 1: Select Employee --}}
                    <div class="mb-3 p-3 bg-gray-50 rounded-lg border border-gray-100">
                        <h3 class="text-xs font-semibold text-gray-900 mb-2">{{ tr('Select Employee') }}</h3>
                        
                        <div class="relative">
                            <x-ui.input 
                                wire:model.live.debounce.300ms="employeeSearch" 
                                type="text" 
                                icon="fa-search" 
                                placeholder="{{ tr('Search by name or number...') }}" 
                                class="!py-2 !text-sm"
                            />
                            
                            @if(!empty($foundEmployees))
                                <div class="absolute z-10 mt-1 w-full bg-white shadow-xl rounded-xl border border-gray-100 py-2 max-h-60 overflow-auto custom-scrollbar">
                                    @foreach($foundEmployees as $emp)
                                        <button 
                                            type="button" 
                                            wire:click="selectEmployee({{ $emp->id }})"
                                            class="w-full text-start px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-[color:var(--brand-via)] transition-colors flex justify-between items-center group border-b border-gray-50 last:border-0"
                                        >
                                            <div class="flex flex-col">
                                                <span class="font-bold text-gray-800 group-hover:text-[color:var(--brand-via)] transition-colors">{{ $emp->name_ar ?? $emp->name_en }}</span>
                                                <span class="text-xs text-gray-500">{{ $emp->jobTitle->name ?? '-' }}</span>
                                            </div>
                                            <span class="px-2 py-1 bg-gray-100 text-xs font-medium text-gray-500 rounded-md group-hover:bg-[color:var(--brand-via)]/10 group-hover:text-[color:var(--brand-via)] transition-colors">
                                                {{ $emp->employee_no }}
                                            </span>
                                        </button>
                                    @endforeach
                                </div>
                            @elseif(strlen($employeeSearch) >= 2 && $selectedEmployeeId === null)
                                <div class="absolute z-10 mt-1 w-full bg-white shadow-lg rounded-md border border-gray-200 py-2 px-4 text-sm text-gray-500">
                                    {{ tr('No available employees found without accounts.') }}
                                </div>
                            @endif
                        </div>

                        @if($selectedEmployeeId)
                            <div class="mt-2 grid grid-cols-2 gap-2 text-xs">
                                <div>
                                    <span class="block text-xs text-gray-500">{{ tr('Name') }}</span>
                                    <span class="font-medium">{{ $display_name }}</span>
                                </div>
                                <div>
                                    <span class="block text-xs text-gray-500">{{ tr('Phone') }}</span>
                                    <span class="font-medium">{{ $display_phone ?: '-' }}</span>
                                </div>
                                <div>
                                    <span class="block text-xs text-gray-500">{{ tr('Department') }}</span>
                                    <span class="font-medium">{{ $display_department }}</span>
                                </div>
                                <div>
                                    <span class="block text-xs text-gray-500">{{ tr('Job Title') }}</span>
                                    <span class="font-medium">{{ $display_job_title }}</span>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Part 2: Account Data --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <x-ui.input 
                        label="{{ tr('Username / Name') }}" 
                        wire:model="name" 
                        required 
                        :disabled="$editingId && true"
                        readonly="{{ $editingId ? 'readonly' : '' }}"
                    />
                    
                    <x-ui.input 
                        label="{{ tr('Email') }}" 
                        wire:model="email" 
                        type="email" 
                        required 
                    />
                    
           <div>
                <x-ui.select
                    label="{{ tr('User License') }}"
                    wire:model="access_type"
                    required
                    :disabled="$is_locked_role"
                >
                    <option value="system_and_app">{{ tr('System & App User License') }}</option>
                    <option value="hr_app_only">{{ tr('HR App Only') }}</option>
                </x-ui.select>
            </div>

            @if($access_type === 'system_and_app')
                <div>
                    <x-ui.select 
                        label="{{ tr('Role') }}" 
                        wire:model="role" 
                        required
                        :disabled="$is_locked_role"
                    >
                        <option value="">{{ tr('Select Role') }}</option>
                        @foreach($roles as $r)
                            <option value="{{ $r->name }}">{{ $r->name }}</option>
                        @endforeach
                    </x-ui.select>
                </div>
            @else
                <div class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2 text-xs text-gray-600">
                    {{ tr('This user can access the HR mobile app only. No system role is required.') }}
                </div>
            @endif


                    <div>
                        <x-ui.select 
                            label="{{ tr('Access Scope') }}" 
                            wire:model="access_scope" 
                            required
                        >
                            <option value="my_branch">{{ tr('My Branch Only') }}</option>
                            <option value="all_branches">{{ tr('All Branches') }}</option>
                        </x-ui.select>
                    </div>
                </div>

                @if(!$editingId)
                    <div class="mt-4 bg-blue-50 p-3 rounded-lg flex items-start gap-2">
                        <i class="fas fa-info-circle text-blue-500 mt-0.5 text-sm"></i>
                        <p class="text-xs text-blue-700">
                            {{ tr('A password reset message will be sent to your email.') }}
                        </p>
                    </div>
                @endif

                <div class="mt-4 flex justify-end gap-3">
                    <x-ui.secondary-button wire:click="$set('showModal', false)">
                        {{ tr('Cancel') }}
                    </x-ui.secondary-button>
                    
                    <x-ui.primary-button type="submit" wire:loading.attr="disabled">
                        <span wire:loading.remove>{{ tr('Save') }}</span>
                        <span wire:loading>
                            <i class="fas fa-spinner fa-spin mr-1"></i>
                            {{ tr('Saving...') }}
                        </span>
                    </x-ui.primary-button>
                </div>
            </form>
        </x-slot>
    </x-ui.modal>
</div>





