<x-ui.modal wire:model="showPenaltyModal" maxWidth="4xl">
    <x-slot:title>
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-red-50 text-red-600 rounded-xl flex items-center justify-center text-lg border border-red-100 shadow-sm"><i class="fas fa-gavel"></i></div>
            <div>
                <h3 class="font-bold text-gray-900 text-lg leading-tight">{{ $isEditingPenalty ? tr('Edit Penalty Policy') : tr('Add Penalty Policy') }}</h3>
                <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">{{ tr('Configure Progressive Violation Rules') }}</p>
            </div>
        </div>
    </x-slot:title>
    <x-slot:content>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 py-2" x-data="{
            insertVariable(v) {
                const el = document.getElementById('penalty_msg_template');
                const start = el.selectionStart;
                const end = el.selectionEnd;
                const text = el.value;
                const before = text.substring(0, start);
                const after  = text.substring(end, text.length);
                el.value = before + v + after;
                el.dispatchEvent(new Event('input'));
                el.focus();
            }
        }">
            {{-- Column 1: Policy Conditions --}}
            <div class="space-y-4">
                <x-ui.select label="{{ tr('Violation Type') }}" wire:model.live="newPenalty.violation_type" required>
                    <option value="late_arrival">{{ tr('Late Arrival') }}</option>
                    <option value="early_departure">{{ tr('Early Departure') }}</option>
                    <option value="auto_departure">{{ tr('Auto Departure') }}</option>
                </x-ui.select>

                <div class="grid grid-cols-2 gap-4">
                    <x-ui.input label="{{ tr('Delay From (min)') }}" type="number" wire:model.defer="newPenalty.minutes_from" required />
                    <x-ui.input label="{{ tr('Delay To (min)') }}" type="number" wire:model.defer="newPenalty.minutes_to" required />
                </div>

                <div class="space-y-2">
                    <label class="block text-sm font-semibold text-gray-700">{{ tr('Repetition Count') }}</label>
                    <div class="flex items-center gap-3">
                        @for($i=1; $i<=4; $i++)
                            <button type="button" 
                                wire:click="$set('newPenalty.recurrence_from', {{ $i }})"
                                class="flex-1 py-3 rounded-xl border-2 transition-all font-black text-xs
                                {{ ($newPenalty['recurrence_from'] ?? 1) == $i 
                                    ? 'bg-amber-500 border-amber-500 text-white shadow-lg shadow-amber-200 scale-105' 
                                    : 'bg-white border-gray-100 text-gray-400 hover:border-amber-200' }}">
                                {{ $i }}x
                            </button>
                        @endfor
                    </div>
                </div>

                <x-ui.select label="{{ tr('Penalty Action') }}" wire:model.live="newPenalty.penalty_action" required>
                    <option value="notification">{{ tr('Notice/Notification') }}</option>
                    <option value="warning_verbal">{{ tr('Verbal Warning') }}</option>
                    <option value="warning_written">{{ tr('Written Warning') }}</option>
                    <option value="deduction">{{ tr('Financial Deduction') }}</option>
                    <option value="suspension">{{ tr('Temporary Suspension') }}</option>
                    <option value="skip">{{ tr('Skip/No Action') }}</option>
                </x-ui.select>

                @if(($newPenalty['penalty_action'] ?? '') === 'deduction')
                    <x-ui.input label="{{ tr('Deduction Value') }}" type="number" wire:model.defer="newPenalty.deduction_value" placeholder="0.00" required />
                @elseif(($newPenalty['penalty_action'] ?? '') === 'suspension')
                    <x-ui.input label="{{ tr('Suspension Days') }}" type="number" wire:model.defer="newPenalty.suspension_days" placeholder="1" required />
                @endif
            </div>

            {{-- Column 2: Notification Template --}}
            <div class="space-y-4">
                <div class="p-5 bg-blue-50/50 rounded-2xl border border-blue-100 space-y-4 shadow-inner">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-bold text-blue-800 uppercase tracking-widest">{{ tr('Notification Template') }}</label>
                        <div class="flex gap-2">
                            <button @click="insertVariable('{اسم الموظف}')" class="text-[10px] font-bold bg-white px-2 py-1 rounded-md border border-blue-200 text-blue-600 hover:bg-blue-100 transition-colors shadow-sm">{إسم الموظف}</button>
                            <button @click="insertVariable('{تاريخ}')" class="text-[10px] font-bold bg-white px-2 py-1 rounded-md border border-blue-200 text-blue-600 hover:bg-blue-100 transition-colors shadow-sm">{تاريخ}</button>
                        </div>
                    </div>
                    <x-ui.textarea 
                        id="penalty_msg_template"
                        wire:model.defer="newPenalty.notification_message" 
                        rows="10" 
                        placeholder="{{ tr('e.g. Dear {employee}, you have been late on {date}.') }}"
                        class="!bg-white shadow-inner !text-sm leading-relaxed"
                    />
                    <div class="flex items-start gap-2 text-[10px] text-blue-400 font-medium leading-relaxed">
                        <i class="fas fa-lightbulb mt-0.5 opacity-70"></i>
                        <p>{{ tr('Variables will be replaced with actual employee name and violation date upon sending.') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </x-slot:content>
    <x-slot:footer>
        <x-ui.secondary-button wire:click="$set('showPenaltyModal', false)">{{ tr('Cancel') }}</x-ui.secondary-button>
        <x-ui.brand-button wire:click="savePenalty" class="!px-12 !rounded-xl shadow-xl shadow-red-100">{{ tr('Confirm & Publish') }}</x-ui.brand-button>
    </x-slot:footer>
</x-ui.modal>





