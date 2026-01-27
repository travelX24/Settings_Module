<div class="space-y-4">
        <h3 class="text-base font-bold text-gray-800 flex items-center gap-2">
            <span class="w-1 h-5 bg-indigo-500 rounded-full"></span>
            {{ tr('Absence Without Permission') }}
        </h3>
        @can('settings.attendance.manage')
        <x-ui.secondary-button wire:click="openAbsenceModal" class="!px-4 !py-2 !text-xs !rounded-xl shadow-sm border-indigo-100">
            <i class="fas fa-calendar-times me-1 text-indigo-500"></i>
            {{ tr('Add Absence Policy') }}
        </x-ui.secondary-button>
        @endcan


    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="w-full text-start border-collapse">
            <thead>
                <tr class="bg-gray-50/50 border-b border-gray-100">
                    <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-start">{{ tr('Absence Type') }}</th>
                    <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">{{ tr('Duration') }}</th>
                    <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">{{ tr('Penalty') }}</th>
                    <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">{{ tr('Deduction') }}</th>
                    <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-end">{{ tr('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($absencePolicies as $ap)
                <tr class="hover:bg-gray-50/30 transition-colors group">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-gray-50 border border-gray-100 flex items-center justify-center text-indigo-500">
                                <i class="fas fa-user-slash text-xs"></i>
                            </div>
                            <div class="flex flex-col">
                                <span class="text-sm font-bold text-gray-700">{{ $absenceTypes[$ap['absence_reason_type'] ?? ''] ?? ($ap['absence_reason_type'] ?? '') }}</span>
                                @if(($ap['absence_reason_type'] ?? '') === 'late_early')
                                    <span class="text-[9px] text-gray-400 font-bold uppercase">{{ $ap['late_minutes'] ?? 0 }} {{ tr('min') }} | {{ $ap['recurrence_count'] ?? 0 }} {{ tr('times') }}</span>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-center">
                        @if(($ap['day_selector_type'] ?? 'single') === 'single')
                            <span class="px-3 py-1 bg-indigo-50 text-indigo-600 rounded-full text-[10px] font-black border border-indigo-100">{{ tr('Day') }} {{ $ap['day_from'] ?? 1 }}</span>
                        @else
                            <span class="px-3 py-1 bg-purple-50 text-purple-600 rounded-full text-[10px] font-black border border-purple-100">{{ tr('Range') }}: {{ $ap['day_from'] ?? 1 }}-{{ $ap['day_to'] ?? 1 }} {{ tr('Days') }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-center">
                        @php
                            $pType = $ap['penalty_action'] ?? 'notification';
                            $tagCls = match($pType) {
                                'notification', 'notice' => 'bg-blue-50 text-blue-600 border-blue-100',
                                'warning_verbal', 'warning_written' => 'bg-amber-50 text-amber-600 border-amber-100',
                                'deduction', 'suspension', 'termination' => 'bg-red-50 text-red-600 border-red-100',
                                default => 'bg-gray-50 text-gray-600 border-gray-100'
                            };
                        @endphp
                        <span class="px-3 py-1 rounded-full text-[10px] font-black border {{ $tagCls }}">
                            {{ tr($pType) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-center text-[11px] font-bold text-gray-600">
                        @if(($ap['penalty_action'] ?? '') === 'deduction')
                            {{ $ap['deduction_value'] ?? 0 }} {{ ($ap['deduction_type'] ?? '') === 'percentage' ? '%' : 'SAR' }}
                        @else
                            -
                        @endif
                    </td>
                    <td class="px-6 py-4 text-end">
                        @can('settings.attendance.manage')
                        <x-ui.actions-menu>
                            <x-ui.dropdown-item wire:click="editAbsencePolicy('{{ $ap['id'] }}')">
                                <i class="fas fa-edit me-2 text-blue-500"></i> {{ tr('Edit') }}
                            </x-ui.dropdown-item>
                            <x-ui.dropdown-item 
                                danger
                                @click="$dispatch('open-confirm-delete-absence', { id: '{{ $ap['id'] }}' })"
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
                            <i class="fas fa-calendar-times text-4xl mb-3"></i>
                            <p class="text-sm font-bold">{{ tr('No absence policies defined.') }}</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>





