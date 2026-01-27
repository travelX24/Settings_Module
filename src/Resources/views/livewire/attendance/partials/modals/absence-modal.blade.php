<x-ui.modal wire:model="showAbsenceModal" maxWidth="3xl">
    <x-slot:title>
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center text-lg border border-indigo-100 shadow-sm"><i class="fas fa-calendar-times"></i></div>
            <div>
                <h3 class="font-bold text-gray-900 text-lg leading-tight">{{ $isEditingAbsence ? tr('Edit Absence Policy') : tr('Add Absence Policy') }}</h3>
                <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">{{ tr('Unapproved Absence Governance') }}</p>
            </div>
        </div>
    </x-slot:title>
    <x-slot:content>
        <div class="space-y-6 py-2">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Left Column --}}
                <div class="space-y-5">
                    <x-ui.select label="{{ tr('Absence Type') }}" wire:model.live="newAbsencePolicy.absence_reason_type" required>
                        @foreach($absenceTypes as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </x-ui.select>

                    <div class="space-y-3 p-4 bg-gray-50/50 rounded-2xl border border-gray-100 shadow-inner">
                        <label class="block text-xs font-black text-gray-400 uppercase tracking-widest">{{ tr('Duration Determination') }}</label>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2 cursor-pointer group">
                                <input type="radio" wire:model.live="newAbsencePolicy.day_selector_type" value="single" class="w-3.5 h-3.5 text-indigo-600 border-gray-300">
                                <span class="text-sm font-bold text-gray-600 group-hover:text-indigo-600 transition-colors">{{ tr('Single Day (Custom)') }}</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer group">
                                <input type="radio" wire:model.live="newAbsencePolicy.day_selector_type" value="range" class="w-3.5 h-3.5 text-indigo-600 border-gray-300">
                                <span class="text-sm font-bold text-gray-600 group-hover:text-indigo-600 transition-colors">{{ tr('Days Range') }}</span>
                            </label>
                        </div>

                        <div class="grid grid-cols-2 gap-4 mt-4">
                            @if(($newAbsencePolicy['day_selector_type'] ?? 'single') === 'single')
                                <div class="col-span-2">
                                    <x-ui.input label="{{ tr('Day Number') }}" type="number" wire:model.defer="newAbsencePolicy.day_from" placeholder="1" required />
                                </div>
                            @else
                                <x-ui.input label="{{ tr('From Day') }}" type="number" wire:model.defer="newAbsencePolicy.day_from" placeholder="1" required />
                                <x-ui.input label="{{ tr('To Day') }}" type="number" wire:model.defer="newAbsencePolicy.day_to" placeholder="10" required />
                            @endif
                        </div>
                    </div>

                    @if(($newAbsencePolicy['absence_reason_type'] ?? '') === 'late_early')
                        <div class="grid grid-cols-2 gap-4 p-4 bg-amber-50/50 rounded-2xl border border-amber-100">
                            <x-ui.input label="{{ tr('Minutes') }}" type="number" wire:model.defer="newAbsencePolicy.late_minutes" required />
                            <x-ui.input label="{{ tr('Repetitions') }}" type="number" wire:model.defer="newAbsencePolicy.recurrence_count" required />
                        </div>
                    @endif
                </div>

                {{-- Right Column --}}
                <div class="space-y-5">
                    <x-ui.select label="{{ tr('Penalty Action') }}" wire:model.live="newAbsencePolicy.penalty_action" required>
                        <option value="notification">{{ tr('Notice/Notification') }}</option>
                        <option value="warning_verbal">{{ tr('Verbal Warning') }}</option>
                        <option value="warning_written">{{ tr('Written Warning') }}</option>
                        <option value="deduction">{{ tr('Financial Deduction') }}</option>
                        <option value="suspension">{{ tr('Temporary Suspension') }}</option>
                        <option value="termination">{{ tr('Termination/Fire') }}</option>
                        <option value="overlook">{{ tr('Overlook/Tajawoz') }}</option>
                    </x-ui.select>

                    @if(($newAbsencePolicy['penalty_action'] ?? '') === 'deduction')
                        <div class="space-y-4 p-4 bg-red-50/50 rounded-2xl border border-red-100 shadow-inner">
                            <label class="block text-xs font-black text-red-400 uppercase tracking-widest">{{ tr('Deduction Details') }}</label>
                            <x-ui.select label="{{ tr('Deduction Type') }}" wire:model.defer="newAbsencePolicy.deduction_type" required>
                                <option value="fixed">{{ tr('Fixed Amount (SAR)') }}</option>
                                <option value="percentage">{{ tr('Percentage of Salary (%)') }}</option>
                            </x-ui.select>
                            <x-ui.input label="{{ tr('Deduction Value') }}" type="number" wire:model.defer="newAbsencePolicy.deduction_value" placeholder="0.00" required />
                        </div>
                    @endif

                    <div class="p-4 bg-gray-50 rounded-2xl border border-gray-100 flex items-start gap-3">
                        <i class="fas fa-info-circle text-indigo-400 mt-1"></i>
                        <div class="space-y-1">
                            <h5 class="text-[11px] font-bold text-gray-700 uppercase tracking-tight">{{ tr('System Intelligence') }}</h5>
                            <p class="text-[10px] text-gray-400 leading-relaxed">{{ tr('This policy will be automatically triggered twice a day during the reconciliation process to ensure real-time accuracy.') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-slot:content>
    <x-slot:footer>
        <x-ui.secondary-button wire:click="$set('showAbsenceModal', false)">{{ tr('Cancel') }}</x-ui.secondary-button>
        <x-ui.brand-button wire:click="saveAbsencePolicy" class="!px-10 !rounded-xl shadow-xl shadow-indigo-100">
            {{ $isEditingAbsence ? tr('Update Policy') : tr('Save Policy') }}
        </x-ui.brand-button>
    </x-slot:footer>
</x-ui.modal>





