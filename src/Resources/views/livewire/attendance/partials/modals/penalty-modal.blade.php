<x-ui.modal 
    wire:model="showPenaltyModal" 
    max-width="2xl"
>
    <x-slot:title>
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-purple-50 text-purple-600 rounded-xl flex items-center justify-center text-lg border border-purple-100 shadow-sm">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div>
                <h3 class="font-bold text-gray-900 text-lg leading-tight">
                    {{ $isEditingPenalty ? tr('Edit Recurring Violation') : tr('Add Recurring Violation') }}
                </h3>
                <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">
                    {{ tr('Penalty and Violation Governance') }}
                </p>
            </div>
        </div>
    </x-slot:title>

    <x-slot:content>
        <div class="space-y-8 py-2">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                {{-- Violation Type --}}
                <x-ui.select 
                    label="{{ tr('Violation Type') }}"
                    wire:model="newPenalty.violation_type"
                >
                    <option value="late_arrival">{{ tr('Late Arrival') }}</option>
                    <option value="early_departure">{{ tr('Early Departure') }}</option>
                    <option value="unexcused_absence">{{ tr('Unexcused Absence') }}</option>
                </x-ui.select>

                {{-- Recurrence --}}
                <x-ui.select 
                    label="{{ tr('Recurrence Count') }}"
                    wire:model="newPenalty.recurrence_count"
                >
                    <option value="2">2 {{ tr('Times') }}</option>
                    <option value="3">3 {{ tr('Times') }}</option>
                    <option value="4">4 {{ tr('Times') }}</option>
                </x-ui.select>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                {{-- Threshold Time --}}
                <x-ui.input 
                    label="{{ tr('Penalty Time (minutes)') }}" 
                    type="number" 
                    wire:model.defer="newPenalty.threshold_minutes"
                    hint="{{ tr('Should be more than the basic grace period.') }}"
                />

                {{-- Penalty Action --}}
                <x-ui.select 
                    label="{{ tr('Penalty Action') }}"
                    wire:model="newPenalty.penalty_action"
                >
                    <option value="deduction">{{ tr('Financial Deduction') }}</option>
                    <option value="notification">{{ tr('System Notification') }}</option>
                    <option value="warning_verbal">{{ tr('Verbal Warning') }}</option>
                    <option value="warning_written">{{ tr('Written Warning') }}</option>
                    <option value="warning_final">{{ tr('Final Warning') }}</option>
                    <option value="termination">{{ tr('Termination') }}</option>
                </x-ui.select>
            </div>

            @if($newPenalty['penalty_action'] === 'deduction')
            <div class="p-6 bg-purple-50/50 rounded-3xl border border-purple-100 animate-in fade-in zoom-in duration-300">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-end">
                    <x-ui.select 
                        label="{{ tr('Deduction Type') }}"
                        wire:model="newPenalty.deduction_type"
                    >
                        <option value="percentage">{{ tr('Percentage (%)') }}</option>
                        <option value="fixed">{{ tr('Fixed Amount') }}</option>
                    </x-ui.select>
                    
                    <x-ui.input 
                        label="{{ tr('Deduction Value') }}"
                        type="number" 
                        wire:model.defer="newPenalty.deduction_value"
                    />
                </div>
            </div>
            @endif

            <div class="p-4 bg-gray-50/50 rounded-2xl border border-gray-100">
                <label class="flex items-center gap-3 cursor-pointer group">
                    <input type="checkbox" 
                        wire:model="newPenalty.include_basic_penalty"
                        class="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500"
                    >
                    <span class="text-xs font-bold text-gray-600 group-hover:text-purple-700 transition-colors">
                        {{ tr('Apply basic penalty deduction in addition to this penalty') }}
                    </span>
                </label>
            </div>
        </div>
    </x-slot:content>

    <x-slot:footer>
        <div class="flex items-center justify-end gap-3 w-full">
            <x-ui.secondary-button @click="$wire.set('showPenaltyModal', false)" class="!text-xs !py-3 !px-6 !rounded-2xl">
                {{ tr('Cancel') }}
            </x-ui.secondary-button>
            <x-ui.primary-button wire:click="savePenalty" class="!bg-purple-600 hover:!bg-purple-700 !text-xs !py-3 !px-10 !rounded-2xl shadow-xl">
                <i class="fas fa-save me-2"></i>
                {{ tr('Save Policy') }}
            </x-ui.primary-button>
        </div>
    </x-slot:footer>
</x-ui.modal>
