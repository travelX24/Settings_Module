<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <table class="w-full text-start border-collapse">
        <thead>
            <tr class="bg-gray-50/50 border-b border-gray-100">
                <th class="px-6 py-3 text-[10px] font-black text-gray-400 uppercase tracking-widest text-start">{{ tr('Violation') }}</th>
                <th class="px-6 py-3 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">{{ tr('Time') }}</th>
                <th class="px-6 py-3 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">{{ tr('Recurrence') }}</th>
                <th class="px-6 py-3 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">{{ tr('Penalty') }}</th>
                @can('settings.attendance.manage')
                <th class="px-6 py-3 text-[10px] font-black text-gray-400 uppercase tracking-widest text-end">{{ tr('Actions') }}</th>
                @endcan
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse($items as $item)
            <tr class="hover:bg-gray-50/30 transition-colors group cursor-default">
                <td class="px-6 py-3">
                    <span class="text-xs font-bold text-gray-700">
                        @if($type === 'absence')
                            {{ tr('Unexcused Absence') }}
                        @else
                            @php
                                $vType = $item['violation_type'] ?? '';
                            @endphp
                            @if($vType === 'late_arrival')
                                {{ tr('Late Arrival') }}
                            @elseif($vType === 'early_departure')
                                {{ tr('Early Departure') }}
                            @elseif($vType === 'unexcused_absence')
                                {{ tr('Unexcused Absence') }}
                            @elseif($vType === 'auto_checkout')
                                {{ tr('Auto Checkout') }}
                            @else
                                {{ $vType ?: tr('Unknown') }}
                            @endif
                        @endif
                    </span>
                <td class="px-6 py-3 text-center">
                    <span class="px-3 py-1 bg-gray-100 rounded-full text-[10px] font-bold text-gray-500">
                        {{ $item['threshold_minutes'] ?? ($item['late_minutes'] ?? 0) }} {{ tr('min') }}
                    </span>
                </td>
                <td class="px-6 py-3 text-center">
                    <div class="flex items-center justify-center gap-1">
                        @for($i=1; $i<=4; $i++)
                            <span class="w-1.5 h-1.5 rounded-full {{ $i <= ($item['recurrence_count'] ?? 0) ? 'bg-purple-400' : 'bg-gray-200' }}"></span>
                        @endfor
                        <span class="ms-1 text-[10px] font-bold text-gray-600">{{ $item['recurrence_count'] ?? 0 }}x</span>
                    </div>
                </td>
                <td class="px-6 py-3 text-center">
                    <x-ui.badge type="custom" size="sm" class="bg-red-50 text-red-600 border-red-100 !text-[9px] !font-black uppercase">
                        {{ tr($item['penalty_action'] ?? 'deduction') }}
                        @if(($item['penalty_action'] ?? 'deduction') === 'deduction') 
                            ({{ $item['deduction_value'] ?? 0 }}{{ ($item['deduction_type'] ?? '') === 'percentage' ? '%' : '' }})
                        @endif
                    </x-ui.badge>
                </td>
                <td class="px-6 py-3 text-end">
                    @can('settings.attendance.manage')
                    <div class="flex items-center justify-end gap-2">
                        <button wire:click="{{ $type === 'absence' ? 'editAbsencePolicy' : 'editPenalty' }}('{{ $item['id'] }}')" 
                            class="p-1.5 text-blue-500 hover:bg-blue-50 rounded-lg transition-colors cursor-pointer"
                            title="{{ tr('Edit') }}">
                            <i class="fas fa-edit text-xs"></i>
                        </button>
                        <button 
                            @if($type === 'absence')
                                @click="$dispatch('open-confirm-delete-absence', { id: '{{ $item['id'] }}' })"
                            @else
                                @click="$dispatch('open-confirm-delete-penalty', { id: '{{ $item['id'] }}' })"
                            @endif
                            class="p-1.5 text-red-500 hover:bg-red-50 rounded-lg transition-colors cursor-pointer"
                            title="{{ tr('Delete') }}">
                            <i class="fas fa-trash-alt text-xs"></i>
                        </button>
                    </div>
                    @endcan
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="px-6 py-6 text-center">
                    <p class="text-[11px] text-gray-400 italic">{{ tr('No recurring violations defined.') }}</p>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
