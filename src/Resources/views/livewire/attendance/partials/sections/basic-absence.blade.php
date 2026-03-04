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
                wire:model.live="basicAbsencePenalty.enabled"
                class="w-4 h-4 text-red-600 rounded border-gray-300 focus:ring-red-500"
                @cannot('settings.attendance.manage') disabled @endcannot
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
            wire:model="basicAbsencePenalty.threshold_minutes" 
            hint="{{ tr('Cannot be less than late/early grace periods.') }}"
            class="!py-3 !rounded-2xl"
            :disabled="!auth()->user()->can('settings.attendance.manage')"
        />

        {{-- Penalty Action --}}
        <div class="space-y-6">
            {{-- Notification --}}
            <x-ui.input 
                label="{{ tr('Notification Text (First Time)') }}" 
                type="text" 
                wire:model="basicAbsencePenalty.notification_message" 
                placeholder="{{ tr('Enter message to show to employee...') }}"
                class="!py-3 !rounded-2xl"
                :disabled="!auth()->user()->can('settings.attendance.manage')"
            />

            {{-- Deduction --}}
            <div class="space-y-3">
                <div class="flex items-end gap-3">
                    <div class="flex-1">
                        <x-ui.select 
                            wire:key="absence-deduction-{{ $basicAbsencePenalty['deduction_type'] }}"
                            label="{{ tr('Additional Deduction') }}"
                            wire:model="basicAbsencePenalty.deduction_type" 
                            model="basicAbsencePenalty.deduction_type"
                            :disabled="!auth()->user()->can('settings.attendance.manage')"
                        >
                            <option value="percentage" {{ $basicAbsencePenalty['deduction_type'] === 'percentage' ? 'selected' : '' }}>{{ tr('Percentage (%)') }}</option>
                            <option value="fixed" {{ $basicAbsencePenalty['deduction_type'] === 'fixed' ? 'selected' : '' }}>{{ tr('Fixed Amount') }}</option>
                        </x-ui.select>
                    </div>
                    <div class="w-24">
                        <x-ui.input 
                            type="number" 
                            wire:model="basicAbsencePenalty.deduction_value" 
                            class="!py-3 !rounded-2xl"
                            :disabled="!auth()->user()->can('settings.attendance.manage')"
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

    @can('settings.attendance.manage')
    <div class="flex justify-end pt-4 border-t border-gray-50">
        <x-ui.primary-button 
            wire:click="saveBasicAbsencePenalty"
            wire:loading.attr="disabled"
            :arrow="false"
            :fullWidth="false"
            class="!px-6 !py-2 !rounded-xl !bg-red-600 hover:!bg-red-700"
        >
            <i class="fas fa-save me-2"></i>
            {{ tr('Save Absence Penalties') }}
        </x-ui.primary-button>
    </div>
    @endcan
    @endif
</x-ui.card>
