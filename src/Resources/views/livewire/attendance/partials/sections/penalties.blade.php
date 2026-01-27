<div class="flex items-center justify-between px-1">
    <h3 class="text-base font-bold text-gray-800 flex items-center gap-2">
        <span class="w-1 h-5 bg-red-500 rounded-full"></span>
        {{ tr('Penalty Policies') }}
    </h3>
    @can('settings.attendance.manage')
    <x-ui.secondary-button wire:click="openPenaltyModal" class="!px-4 !py-2 !text-xs !rounded-xl shadow-sm">
        <i class="fas fa-plus me-1 text-[color:var(--brand-via)]"></i>
        {{ tr('Add New Policy') }}
    </x-ui.secondary-button>
    @endcan
</div>

<div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
    <table class="w-full text-start border-collapse">
        <thead>
            <tr class="bg-gray-50/50 border-b border-gray-100">
                <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-start">{{ tr('Violation Type') }}</th>
                <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">{{ tr('Time Range') }}</th>
                <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">{{ tr('Repeat') }}</th>
                <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">{{ tr('Penalty') }}</th>
                <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-end">{{ tr('Actions') }}</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse($penalties as $penalty)
            <tr class="hover:bg-gray-50/30 transition-colors group">
                <td class="px-6 py-4">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-gray-50 border border-gray-100 flex items-center justify-center text-gray-500">
                            <i class="fas {{ ($penalty['violation_type'] ?? '') === 'late_arrival' ? 'fa-clock' : 'fa-door-open' }} text-xs"></i>
                        </div>
                        <span class="text-sm font-bold text-gray-700">{{ tr($penalty['violation_type'] ?? 'Unknown') }}</span>
                    </div>
                </td>
                <td class="px-6 py-4 text-center">
                    <span class="px-3 py-1 bg-gray-100 rounded-full text-[11px] font-bold text-gray-500">{{ $penalty['minutes_from'] ?? 0 }}-{{ $penalty['minutes_to'] ?? 0 }} {{ tr('min') }}</span>
                </td>
                <td class="px-6 py-4 text-center">
                    <div class="flex items-center justify-center gap-1">
                        @for($i=1; $i<=4; $i++)
                            <span class="w-1.5 h-1.5 rounded-full {{ $i <= ($penalty['recurrence_from'] ?? 0) ? 'bg-amber-400' : 'bg-gray-200' }}"></span>
                        @endfor
                        <span class="ms-1 text-[11px] font-bold text-gray-600">{{ $penalty['recurrence_from'] ?? 0 }}x</span>
                    </div>
                </td>
                <td class="px-6 py-4 text-center">
                    @php
                        $pType = $penalty['penalty_action'] ?? 'notification';
                        $tagCls = match($pType) {
                            'notification', 'notice' => 'bg-blue-50 text-blue-600 border-blue-100',
                            'warning_verbal', 'warning_written' => 'bg-amber-50 text-amber-600 border-amber-100',
                            'deduction', 'suspension' => 'bg-red-50 text-red-600 border-red-100',
                            default => 'bg-gray-50 text-gray-600 border-gray-100'
                        };
                    @endphp
                    <x-ui.badge type="custom" size="sm" class="{{ $tagCls }} !text-[10px] !font-black uppercase">
                        {{ tr($pType) }}
                        @if($pType === 'deduction') ({{ $penalty['deduction_value'] ?? 0 }}) @endif
                    </x-ui.badge>
                </td>
                <td class="px-6 py-4 text-end">
                    @can('settings.attendance.manage')
                    <x-ui.actions-menu>
                        <x-ui.dropdown-item wire:click="editPenalty('{{ $penalty['id'] }}')">
                            <i class="fas fa-edit me-2 text-blue-500"></i> {{ tr('Edit') }}
                        </x-ui.dropdown-item>
                        <x-ui.dropdown-item 
                            danger
                            @click="$dispatch('open-confirm-delete-penalty', { id: '{{ $penalty['id'] }}' })"
                        >
                            <i class="fas fa-trash-alt me-2 text-red-500"></i> {{ tr('Delete') }}
                        </x-ui.dropdown-item>
                    </x-ui.actions-menu>
                    @else
                    <span class="text-xs text-gray-400 font-bold italic">{{ tr('View Only') }}</span>
                    @endcan
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="px-6 py-10 text-center">
                    <div class="flex flex-col items-center opacity-30">
                        <i class="fas fa-shield-alt text-4xl mb-3"></i>
                        <p class="text-sm font-bold">{{ tr('No penalty policies defined yet.') }}</p>
                    </div>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>





