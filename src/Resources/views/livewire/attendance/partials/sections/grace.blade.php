<div class="flex items-center justify-between px-1">
    <h3 class="text-base font-bold text-gray-800 flex items-center gap-2">
        <span class="w-1 h-5 bg-amber-500 rounded-full"></span>
        {{ tr('Basic Settings') }}
    </h3>
</div>

<x-ui.card class="space-y-6 !p-6 border-none shadow-md bg-white">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        {{-- Monthly Grace Period --}}
        <div class="space-y-2">
            <x-ui.input 
                label="{{ tr('Monthly Grace Period (min)') }}" 
                type="number" 
                wire:model="gracePeriods.late_arrival" 
                required 
                hint="{{ tr('Monthly allowance for late arrival and early departure.') }}"
                class="!py-2.5"
                :disabled="!auth()->user()->can('settings.attendance.manage')"
            />
        </div>

        {{-- Auto Departure --}}
        <div class="space-y-2">
            <x-ui.input 
                label="{{ tr('Max Auto-Checkout (hours)') }}" 
                type="number" 
                wire:model="gracePeriods.auto_departure" 
                required 
                hint="{{ tr('System will auto-checkout employee after these hours.') }}"
                class="!py-2.5"
                :disabled="!auth()->user()->can('settings.attendance.manage')"
            />
        </div>
    </div>

    <div class="pt-4 border-t border-gray-50 flex items-center gap-4">
        <label class="flex items-center gap-2 cursor-pointer group">
            <input type="checkbox" 
                id="auto_departure_penalty_enabled"
                wire:model.live="gracePeriods.auto_departure_penalty_enabled"
                class="w-4 h-4 text-blue-600 rounded border-gray-300"
            >
            <span class="text-xs font-bold text-gray-700">{{ tr('Activate Auto-Checkout Penalties') }}</span>
        </label>
        
        @if($gracePeriods['auto_departure_penalty_enabled'])
        <div class="w-48">
            <x-ui.input 
                placeholder="{{ tr('Deduction Amount') }}"
                type="number"
                wire:model="gracePeriods.auto_departure_penalty_amount"
                class="!py-1.5"
            />
        </div>
        @endif
    </div>

    <div class="flex justify-end pt-2">
        <x-ui.primary-button 
            wire:click="saveGracePeriods"
            wire:loading.attr="disabled"
            :arrow="false"
            :fullWidth="false"
            class="!px-6 !py-2 !rounded-xl"
        >
            <i class="fas fa-save me-2"></i>
            {{ tr('Save Basic Settings') }}
        </x-ui.primary-button>
    </div>
</x-ui.card>





