<x-ui.card class="space-y-4 !p-6 border-none shadow-sm bg-white !rounded-3xl">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-orange-50 flex items-center justify-center text-orange-500 border border-orange-100 shadow-sm">
                <i class="fas fa-clock text-lg"></i>
            </div>
            <div>
                <h4 class="text-sm font-bold text-gray-800">{{ tr('A. Late Arrival Penalties') }}</h4>
                <p class="text-[11px] text-gray-400 font-medium">{{ tr('Settings for the first time late arrival.') }}</p>
            </div>
        </div>
        <label class="flex items-center gap-3 cursor-pointer group bg-gray-50/50 px-4 py-2 rounded-xl border border-gray-100 hover:bg-white transition-all">
            <input type="checkbox" 
                wire:model.live="basicLatePenalty.enabled"
                class="w-4 h-4 text-orange-600 rounded border-gray-300 focus:ring-orange-500"
                @cannot('settings.attendance.manage') disabled @endcannot
            >
            <span class="text-xs font-bold text-gray-700 group-hover:text-orange-600 transition-colors">{{ tr('Activate Penalties') }}</span>
        </label>
    </div>

    @if($basicLatePenalty['enabled'])
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 pt-6 border-t border-gray-50 mt-2 animate-in fade-in slide-in-from-top-2 duration-300">
        {{-- Grace Minutes --}}
        <x-ui.input 
            label="{{ tr('Grace Period (min)') }}" 
            type="number" 
            wire:model="basicLatePenalty.grace_minutes" 
            class="!py-3 !rounded-2xl"
            :disabled="!auth()->user()->can('settings.attendance.manage')"
        />

        {{-- Interval Minutes --}}
        <x-ui.input 
            label="{{ tr('After grace, for every (min)') }}" 
            type="number" 
            wire:model="basicLatePenalty.interval_minutes" 
            hint="{{ tr('Deduction will trigger every X minutes.') }}"
            class="!py-3 !rounded-2xl"
            :disabled="!auth()->user()->can('settings.attendance.manage')"
        />

        {{-- Deduction --}}
        <div class="space-y-3">
            <div class="flex items-end gap-3">
                <div class="flex-1">
                    <x-ui.select 
                        wire:key="late-deduction-{{ $basicLatePenalty['deduction_type'] }}"
                        label="{{ tr('Deduction Type') }}"
                        wire:model="basicLatePenalty.deduction_type" 
                        model="basicLatePenalty.deduction_type"
                        :disabled="!auth()->user()->can('settings.attendance.manage')"
                    >
                        <option value="percentage" {{ $basicLatePenalty['deduction_type'] === 'percentage' ? 'selected' : '' }}>{{ tr('Percentage (%)') }}</option>
                        <option value="fixed" {{ $basicLatePenalty['deduction_type'] === 'fixed' ? 'selected' : '' }}>{{ tr('Fixed Amount') }}</option>
                    </x-ui.select>
                </div>
                <div class="w-24">
                    <x-ui.input 
                        type="number" 
                        wire:model="basicLatePenalty.deduction_value" 
                        class="!py-3 !rounded-2xl"
                        :disabled="!auth()->user()->can('settings.attendance.manage')"
                    />
                </div>
            </div>
            <p class="text-[10px] font-bold text-orange-500/70 italic px-1">
                @if($basicLatePenalty['deduction_type'] === 'percentage')
                    <i class="fas fa-info-circle me-1"></i> {{ tr('Percentage of minute wage.') }}
                @else
                    <i class="fas fa-info-circle me-1"></i> {{ tr('Fixed amount per interval.') }}
                @endif
            </p>
        </div>
    </div>
    
    @can('settings.attendance.manage')
    <div class="flex justify-end pt-4 border-t border-gray-50">
        <x-ui.primary-button 
            wire:click="saveBasicLatePenalty"
            wire:loading.attr="disabled"
            :arrow="false"
            :fullWidth="false"
            class="!px-6 !py-2 !rounded-xl !bg-orange-600 hover:!bg-orange-700"
        >
            <i class="fas fa-save me-2"></i>
            {{ tr('Save Late Arrival Fines') }}
        </x-ui.primary-button>
    </div>
    @endcan
    @endif
</x-ui.card>
