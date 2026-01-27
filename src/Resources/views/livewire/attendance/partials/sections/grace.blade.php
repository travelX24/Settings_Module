<div class="flex items-center justify-between px-1">
    <h3 class="text-base font-bold text-gray-800 flex items-center gap-2">
        <span class="w-1 h-5 bg-amber-500 rounded-full"></span>
        {{ tr('Grace Periods') }}
    </h3>
    <i class="fas fa-circle-info text-gray-300 text-xs" title="{{ tr('Time limits before violations are recorded') }}"></i>
</div>

<x-ui.card class="space-y-6 !p-6 border-none shadow-md bg-white">
    {{-- Late Arrival --}}
    <div class="space-y-2">
        <x-ui.input 
            label="{{ tr('Late Arrival Grace (min)') }}" 
            type="number" 
            wire:model.defer="gracePeriods.late_arrival" 
            wire:blur="saveGracePeriods"
            required 
            hint="{{ tr('Max time allowed after start time without penalty (e.g. 15 mins).') }}"
            class="!py-2.5"
            :disabled="!auth()->user()->can('settings.attendance.manage')"
        />
        <p class="text-[10px] text-gray-400 bg-gray-50 p-2 rounded-lg border border-gray-100">
            <strong>{{ tr('Example') }}:</strong> {{ tr('If work starts at 8:00 and grace is 15m, arriving at 8:16 counts as a violation.') }}
        </p>
    </div>

    {{-- Early Departure --}}
    <div class="space-y-2">
        <x-ui.input 
            label="{{ tr('Early Departure Grace (min)') }}" 
            type="number" 
            wire:model.defer="gracePeriods.early_departure" 
            wire:blur="saveGracePeriods"
            required 
            hint="{{ tr('Max time allowed to leave before end time without penalty.') }}"
            class="!py-2.5"
            :disabled="!auth()->user()->can('settings.attendance.manage')"
        />
        <p class="text-[10px] text-gray-400 bg-gray-50 p-2 rounded-lg border border-gray-100">
            <strong>{{ tr('Example') }}:</strong> {{ tr('If work ends at 5:00 and grace is 10m, leaving at 4:49 counts as a violation.') }}
        </p>
    </div>

    {{-- Auto Departure --}}
    <div class="space-y-2">
        <x-ui.input 
            label="{{ tr('Auto Departure Threshold (min)') }}" 
            type="number" 
            wire:model.defer="gracePeriods.auto_departure" 
            wire:blur="saveGracePeriods"
            required 
            hint="{{ tr('Time after which the system auto-signs out the employee if they forget.') }}"
            class="!py-2.5"
            :disabled="!auth()->user()->can('settings.attendance.manage')"
        />
        <p class="text-[10px] text-gray-400 bg-gray-50 p-2 rounded-lg border border-gray-100">
            {{ tr('Helps maintain attendance accuracy even when manual sign-out is missed.') }}
        </p>
    </div>
</x-ui.card>





