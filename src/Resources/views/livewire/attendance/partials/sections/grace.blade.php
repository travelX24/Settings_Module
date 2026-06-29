<div class="flex items-center justify-between px-1">
    <h3 class="text-base font-bold text-gray-800 flex items-center gap-2">
        <span class="w-1 h-5 bg-[color:var(--warning)] rounded-full"></span>
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
                :disabled="!$canManageAttendance"
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
                :disabled="!$canManageAttendance"
            />
            {{-- Warning note --}}
            <div class="flex items-start gap-2 rounded-xl border border-[rgb(245_158_11/0.22)] bg-[rgb(245_158_11/0.10)] px-3 py-2">
                <i class="fas fa-exclamation-triangle text-[color:var(--warning)] text-xs mt-0.5 shrink-0"></i>
                <p class="text-[11px] text-[color:var(--warning)] font-medium leading-snug">
                    @if(app()->getLocale() === 'ar')
                        يُنصح بأن يكون الحد الأقصى لتسجيل الخروج التلقائي أقل من فترة الاستراحة بين فترات العمل في الجدول؛ وإلا سيُسجَّل الخروج عند بداية الفترة التالية بدلاً من الحد الأقصى المحدد. أنت مسؤول عن اختيار القيمة المناسبة.
                    @else
                        It is recommended that the max auto-checkout limit be less than the break duration between schedule periods. Otherwise, checkout will be recorded at the next period's check-in time instead of the configured limit. You are responsible for choosing the appropriate value.
                    @endif
                </p>
            </div>
        </div>
    </div>

    <div class="pt-4 border-t border-gray-50 space-y-4">
        <label class="flex items-center gap-2 cursor-pointer group w-fit">
            <input type="checkbox" 
                id="auto_departure_penalty_enabled"
                wire:model.live="gracePeriods.auto_departure_penalty_enabled"
                class="w-4 h-4 text-[color:var(--accent-orange)] rounded border-gray-300"
                @if(!$canManageAttendance) disabled @endif
            >
            <span class="text-xs font-bold text-gray-700">{{ tr('Activate Auto-Checkout Penalties') }}</span>
        </label>
        
        @if($gracePeriods['auto_departure_penalty_enabled'])
        <div class="space-y-3 max-w-md animate-in fade-in slide-in-from-top-2 duration-300">
            <div class="flex items-end gap-3">
                <div class="flex-1">
                    <x-ui.select 
                        label="{{ tr('Deduction Type') }}"
                        wire:model.live="gracePeriods.auto_checkout_deduction_type"
                        :disabled="!$canManageAttendance"
                    >
                        <option value="fixed">{{ tr('Fixed Amount') }}</option>
                        <option value="daily">{{ tr('% of Daily Wage') }}</option>
                    </x-ui.select>
                </div>
                <div class="w-32">
                    <x-ui.input 
                        type="number"
                        wire:model="gracePeriods.auto_departure_penalty_amount"
                        class="!py-3 !rounded-2xl"
                        :disabled="!$canManageAttendance"
                    />
                </div>
            </div>
             <p class="text-[10px] font-bold text-gray-400 italic px-1">
                @if(($gracePeriods['auto_checkout_deduction_type'] ?? '') === 'daily')
                    <i class="fas fa-info-circle me-1"></i> {{ tr('Percentage of daily wage.') }}
                @else
                    <i class="fas fa-info-circle me-1"></i> {{ tr('Fixed amount.') }}
                @endif
            </p>
        </div>
        @endif
    </div>

    @if($canManageAttendance)
    <div class="flex justify-end pt-2">
        <x-ui.primary-button 
            wire:click="saveGracePeriods"
            wire:loading.attr="disabled"
            :arrow="false"
            :fullWidth="false"
            class="!px-6 !py-2 !rounded-xl cursor-pointer"
        >
            <span wire:loading.remove wire:target="saveGracePeriods">
                <i class="fas fa-save me-2"></i>
                {{ tr('Save Basic Settings') }}
            </span>
            <span wire:loading wire:target="saveGracePeriods" class="flex items-center gap-2">
                <i class="fas fa-spinner fa-spin"></i>
                {{ tr('Saving...') }}
            </span>
        </x-ui.primary-button>
    </div>
    @endif
</x-ui.card>





