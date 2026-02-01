<x-ui.card class="space-y-4 !p-6 border-none shadow-sm bg-white !rounded-3xl">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-red-50 flex items-center justify-center text-red-500 border border-red-100 shadow-sm">
                <i class="fas fa-calendar-times text-lg"></i>
            </div>
            <div>
                <h4 class="text-sm font-bold text-gray-800">{{ tr('C. Unexcused Absence Penalties') }}</h4>
                <p class="text-[11px] text-gray-400 font-medium">{{ tr('Settings for the first time unexcused absence.') }}</p>
            </div>
        </div>
        <label class="flex items-center gap-3 cursor-pointer group bg-gray-50/50 px-4 py-2 rounded-xl border border-gray-100 hover:bg-white transition-all">
            <input type="checkbox" 
                wire:model="basicAbsencePenalty.enabled"
                wire:change="saveBasicAbsencePenalty"
                class="w-4 h-4 text-red-600 rounded border-gray-300 focus:ring-red-500"
            >
            <span class="text-xs font-bold text-gray-700 group-hover:text-red-600 transition-colors">{{ tr('Activate Penalties') }}</span>
        </label>
    </div>

    @if($basicAbsencePenalty['enabled'])
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 pt-6 border-t border-gray-50 mt-2 animate-in fade-in slide-in-from-top-2 duration-300">
        {{-- Threshold Minutes --}}
        <x-ui.input 
            label="{{ tr('Max Minutes to Count as Absent (min)') }}" 
            type="number" 
            wire:model.defer="basicAbsencePenalty.threshold_minutes" 
            wire:blur="saveBasicAbsencePenalty"
            hint="{{ tr('Cannot be less than late/early grace periods.') }}"
            class="!py-3 !rounded-2xl"
        />

        {{-- Penalty Action --}}
        <div class="space-y-6">
            {{-- Notification --}}
            <x-ui.input 
                label="{{ tr('Notification Text (First Time)') }}" 
                type="text" 
                wire:model.defer="basicAbsencePenalty.notification_message" 
                wire:blur="saveBasicAbsencePenalty"
                placeholder="{{ tr('Enter message to show to employee...') }}"
                class="!py-3 !rounded-2xl"
            />

            {{-- Deduction --}}
            <div class="space-y-3">
                <div class="flex items-end gap-3">
                    <div class="flex-1">
                        <x-ui.select 
                            label="{{ tr('Additional Deduction') }}"
                            wire:model="basicAbsencePenalty.deduction_type" 
                            wire:change="saveBasicAbsencePenalty"
                        >
                            <option value="percentage">{{ tr('Percentage (%)') }}</option>
                            <option value="fixed">{{ tr('Fixed Amount') }}</option>
                        </x-ui.select>
                    </div>
                    <div class="w-24">
                        <x-ui.input 
                            type="number" 
                            wire:model.defer="basicAbsencePenalty.deduction_value" 
                            wire:blur="saveBasicAbsencePenalty"
                            class="!py-3 !rounded-2xl"
                        />
                    </div>
                </div>
                <p class="text-[10px] font-bold text-red-500/70 italic px-1">
                    @if($basicAbsencePenalty['deduction_type'] === 'percentage')
                        <i class="fas fa-info-circle me-1"></i> {{ tr('Percentage of daily wage (100% = 1 day, 200% = 2 days).') }}
                    @else
                        <i class="fas fa-info-circle me-1"></i> {{ tr('Fixed amount per absence.') }}
                    @endif
                </p>
            </div>
        </div>
    </div>
    @endif
</x-ui.card>
