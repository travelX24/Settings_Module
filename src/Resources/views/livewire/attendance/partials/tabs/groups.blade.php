<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-1">
            <h3 class="text-base font-bold text-gray-800 flex items-center gap-2">
                <span class="w-1 h-5 bg-[color:var(--brand-via)] rounded-full"></span>
                {{ tr('Employee Groups Management') }}
            </h3>

            <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                @if(!empty($branchesOptions))
                    <div class="min-w-[220px]">
                        <x-ui.select
                            label="{{ tr('Branch') }}"
                            wire:model.live="filterBranchId"
                            :disabled="$lockBranchFilter"
                        >
                            <option value="">{{ tr('All Branches') }}</option>
                            @foreach($branchesOptions as $b)
                                <option value="{{ $b['id'] }}">{{ $b['name'] }}</option>
                            @endforeach
                        </x-ui.select>

                        @if($lockBranchFilter)
                            <p class="mt-1 text-[10px] text-gray-400 font-bold">
                                {{ tr('Access is limited to your branch.') }}
                            </p>
                        @endif
                    </div>
                @endif

                @can('settings.attendance.manage')
                    <x-ui.primary-button wire:click="openGroupModal" class="!rounded-xl shadow-md cursor-pointer">
                        <i class="fas fa-plus-circle me-1"></i> {{ tr('New Group') }}
                    </x-ui.primary-button>
                @endcan
            </div>
        </div>

    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 mt-2 w-full overflow-x-auto">
        <table class="w-full text-start border-collapse">
            <thead>
                <tr class="bg-gray-50/50 border-b border-gray-100">
                    <th class="px-6 py-4 text-[10px] font-black text-gray-400 border-b border-gray-100 uppercase tracking-widest text-center w-12 whitespace-nowrap">#</th>
                    <th class="px-6 py-4 text-[10px] font-black text-gray-400 border-b border-gray-100 uppercase tracking-widest text-start whitespace-nowrap">{{ tr('Group Name') }}</th>
                    <th class="px-6 py-4 text-[10px] font-black text-gray-400 border-b border-gray-100 uppercase tracking-widest text-start min-w-[200px]">
                        {{ tr('Description') }}
                    </th>

                    <th class="px-6 py-4 text-[10px] font-black text-gray-400 border-b border-gray-100 uppercase tracking-widest text-center whitespace-nowrap">
                        {{ tr('Policy') }}
                    </th>
                    <th class="px-6 py-4 text-[10px] font-black text-gray-400 border-b border-gray-100 uppercase tracking-widest text-center whitespace-nowrap">{{ tr('Employees') }}</th>
                    <th class="px-6 py-4 text-[10px] font-black text-gray-400 border-b border-gray-100 uppercase tracking-widest text-center whitespace-nowrap">{{ tr('Methods') }}</th>
                    <th class="px-6 py-4 text-[10px] font-black text-gray-400 border-b border-gray-100 uppercase tracking-widest text-end whitespace-nowrap">{{ tr('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($groups as $index => $group)
                <tr class="hover:bg-gray-50/30 transition-colors group/row">
                    <td class="px-6 py-4 text-center whitespace-nowrap">
                        <span class="text-xs font-bold text-gray-400">{{ $index + 1 }}</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-orange-50 to-pink-50 border border-purple-100 flex items-center justify-center text-[color:var(--brand-from)]">
                                <i class="fas fa-users text-sm"></i>
                            </div>
                            <span class="text-sm font-bold text-gray-800">{{ $group['name'] }}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 min-w-[200px]">
                        <p class="text-[11px] text-gray-500 line-clamp-2 leading-relaxed">{{ $group['description'] }}</p>
                    </td>

                    <td class="px-6 py-4 text-center whitespace-nowrap">
                        @php
                            $policyLabel  = $policyTypes[$group['policy']] ?? $group['policy'];
                            $policyCls = match($group['policy']) {
                                'general' => 'bg-green-50 text-green-600 border-green-100',
                                'special' => 'bg-orange-50 text-[color:var(--brand-from)] border-orange-100',
                                'custom' => 'bg-purple-50 text-[color:var(--brand-from)] border-purple-100',
                                default => 'bg-gray-50 text-gray-600 border-gray-100'
                            };
                        @endphp
                        <span class="px-3 py-1 rounded-full text-[10px] font-black border {{ $policyCls }}">{{ $policyLabel }}</span>
                    </td>
                    <td class="px-6 py-4 text-center whitespace-nowrap">
                        <div class="flex items-center justify-center relative">
                            <div class="relative group/tooltip">
                                {{-- Subtle Badge --}}
                                <div class="px-2.5 py-1 rounded-lg bg-gray-50 border border-gray-100 flex items-center gap-2 cursor-help hover:bg-white hover:border-gray-200 transition-all">
                                    <i class="fas fa-users text-[10px] text-gray-400"></i>
                                    <span class="text-[11px] font-bold text-gray-700 leading-none">{{ $group['employee_count'] }}</span>
                                </div>
                                
                                {{-- Official Minimal Tooltip --}}
                                @if(!empty($group['employee_names']))
                                <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-48 bg-white rounded-xl shadow-lg border border-gray-200 p-3 z-[100] opacity-0 invisible group-hover/tooltip:opacity-100 group-hover/tooltip:visible transition-all duration-150 pointer-events-none">
                                    <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2 pb-1 border-b border-gray-50">{{ tr('Group Members') }}</h4>
                                    <div class="max-h-40 overflow-y-auto space-y-1.5 custom-scrollbar text-start">
                                        @foreach($group['employee_names'] as $empName)
                                            <div class="text-[11px] text-gray-600 font-medium flex items-center gap-2">
                                                <div class="w-1.5 h-1.5 rounded-full bg-gray-200"></div>
                                                {{ $empName }}
                                            </div>
                                        @endforeach
                                    </div>
                                    {{-- Arrow --}}
                                    <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-200"></div>
                                    <div class="absolute top-[calc(full-1px)] left-1/2 -translate-x-1/2 border-4 border-transparent border-t-white"></div>
                                </div>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center justify-center gap-1">
                            @if(($group['tracking_mode'] ?? '') === 'automatic')
                                <div class="px-2 py-0.5 rounded-lg border border-amber-100 bg-amber-50 text-amber-600 flex items-center gap-1 text-[9px] font-black whitespace-nowrap shadow-sm">
                                    <i class="fas fa-magic scale-75"></i>
                                    {{ tr('Auto Prep') }}
                                </div>
                            @else
                                @foreach($group['methods'] as $m)
                                    @php
                                        $methodMeta = match($m) {
                                            'gps' => ['icon' => 'fa-map-pin', 'cls' => 'bg-orange-50 text-[color:var(--brand-from)] border-orange-100', 'label' => 'GPS'],
                                            'nfc' => ['icon' => 'fa-wifi', 'cls' => 'bg-purple-50 text-[color:var(--brand-from)] border-purple-100', 'label' => 'NFC'],
                                            'fingerprint' => ['icon' => 'fa-fingerprint', 'cls' => 'bg-orange-50 text-[color:var(--brand-from)] border-orange-100', 'label' => tr('Finger')],
                                            default => ['icon' => 'fa-check', 'cls' => 'bg-gray-50 text-gray-600 border-gray-100', 'label' => $m]
                                        };
                                    @endphp
                                    <div class="px-2 py-0.5 rounded-lg border {{ $methodMeta['cls'] }} flex items-center gap-1 text-[9px] font-black whitespace-nowrap">
                                        <i class="fas {{ $methodMeta['icon'] }} scale-75"></i>
                                        {{ $methodMeta['label'] }}
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </td>
                    <td class="px-6 py-4 text-end whitespace-nowrap">
                        @can('settings.attendance.manage')
                        <div class="flex items-center justify-end gap-2">
                            <button wire:click="editGroup('{{ $group['id'] }}')" class="p-2 text-[color:var(--brand-from)] hover:bg-orange-50 rounded-xl transition-colors cursor-pointer">
                                <i class="fas fa-edit text-xs"></i>
                            </button>
                            <button @click="$dispatch('open-confirm-delete-group', { id: '{{ $group['id'] }}' })" class="p-2 text-red-500 hover:bg-red-50 rounded-xl transition-colors cursor-pointer">
                                <i class="fas fa-trash-alt text-xs"></i>
                            </button>
                        </div>
                        @else
                        <span class="text-[10px] font-bold text-gray-400 italic">{{ tr('View Only') }}</span>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-6 py-10 text-center">
                        <div class="flex flex-col items-center opacity-30">
                            <i class="fas fa-users-slash text-4xl mb-3"></i>
                            <p class="text-sm font-bold">{{ tr('No groups created yet.') }}</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>





