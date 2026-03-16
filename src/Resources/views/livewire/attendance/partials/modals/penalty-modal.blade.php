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
                <div class="space-y-1">
                    <x-ui.select 
                        label="{{ tr('Violation Type') }}"
                        wire:model="newPenalty.violation_type"
                        :disabled="!auth()->user()->can('settings.attendance.manage')"
                    >
                        <option value="late_arrival">{{ tr('Late Arrival') }}</option>
                        <option value="early_departure">{{ tr('Early Departure') }}</option>
                        <option value="unexcused_absence">{{ tr('Unexcused Absence') }}</option>
                    </x-ui.select>
                    @error('newPenalty.violation_type') <span class="text-[10px] text-red-500 font-bold px-2">{{ tr($message) }}</span> @enderror
                </div>

                {{-- Recurrence --}}
                <div class="space-y-1">
                    <x-ui.select 
                        label="{{ tr('Recurrence Count') }}"
                        wire:model="newPenalty.recurrence_count"
                        :disabled="!auth()->user()->can('settings.attendance.manage')"
                    >
                        <option value="2">2 {{ tr('Times') }}</option>
                        <option value="3">3 {{ tr('Times') }}</option>
                        <option value="4">4 {{ tr('Times') }}</option>
                    </x-ui.select>
                    @error('newPenalty.recurrence_count') <span class="text-[10px] text-red-500 font-bold px-2">{{ tr($message) }}</span> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                {{-- Threshold Time --}}
                <div class="space-y-1">
                    <x-ui.input 
                        label="{{ tr('Penalty Time (minutes)') }}" 
                        type="number" 
                        wire:model.defer="newPenalty.threshold_minutes"
                        hint="{{ tr('Should be more than the basic grace period.') }}"
                        :disabled="!auth()->user()->can('settings.attendance.manage')"
                    />
                    @error('newPenalty.threshold_minutes') <span class="text-[10px] text-red-500 font-bold px-2">{{ tr($message) }}</span> @enderror
                </div>

                {{-- Penalty Action --}}
                <div class="space-y-1">
                    <x-ui.select 
                        label="{{ tr('Penalty Action') }}"
                        wire:model="newPenalty.penalty_action"
                        :disabled="!auth()->user()->can('settings.attendance.manage')"
                    >
                        <option value="deduction">{{ tr('Financial Deduction') }}</option>
                        <option value="notification">{{ tr('System Notification') }}</option>

                        <option value="warning_verbal">{{ app()->isLocale('ar') ? 'انذار لفظي' : tr('Verbal Warning') }}</option>
                        <option value="warning_written">{{ app()->isLocale('ar') ? 'كتابي اول' : tr('Written Warning') }}</option>
                        <option value="warning_final">{{ app()->isLocale('ar') ? 'كتابي ثاني' : tr('Second Written Warning') }}</option>

                        <option value="disciplinary_committee">{{ app()->isLocale('ar') ? 'لجنة تأديبية' : tr('Disciplinary Committee') }}</option>
                        <option value="suspension">{{ app()->isLocale('ar') ? 'ايقاف عن العمل' : tr('Suspension') }}</option>
                        <option value="termination">{{ app()->isLocale('ar') ? 'فصل' : tr('Termination') }}</option>
                    </x-ui.select>
                    @error('newPenalty.penalty_action') <span class="text-[10px] text-red-500 font-bold px-2">{{ tr($message) }}</span> @enderror
                </div>
            
            @if(in_array(($newPenalty['penalty_action'] ?? ''), ['warning_verbal','warning_written','warning_final'], true))
                <div class="p-4 bg-amber-50/50 rounded-2xl border border-amber-100">
                    <x-ui.input
                        label="{{ app()->isLocale('ar') ? 'الملاحظة' : tr('Note') }}"
                        wire:model.defer="newPenalty.notification_message"
                        placeholder="{{ app()->isLocale('ar') ? 'اكتب الملاحظة الخاصة بالانذار...' : tr('Write the warning note...') }}"
                        required
                        :disabled="!auth()->user()->can('settings.attendance.manage')"
                    />
                </div>
            @endif

            </div>

            @if($newPenalty['penalty_action'] === 'deduction')
            <div class="p-6 bg-purple-50/50 rounded-3xl border border-purple-100 animate-in fade-in zoom-in duration-300">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-end">
                        <div class="space-y-1">
                            <x-ui.select 
                                label="{{ tr('Deduction Type') }}"
                                wire:model="newPenalty.deduction_type"
                                :disabled="!auth()->user()->can('settings.attendance.manage')"
                            >
                                <option value="percentage">{{ tr('Percentage (%)') }}</option>
                                <option value="fixed">{{ tr('Fixed Amount') }}</option>
                            </x-ui.select>
                            @error('newPenalty.deduction_type') <span class="text-[10px] text-red-500 font-bold px-2">{{ tr($message) }}</span> @enderror
                        </div>
                        
                        <div class="space-y-1">
                            <x-ui.input 
                                label="{{ tr('Deduction Value') }}"
                                type="number" 
                                wire:model.defer="newPenalty.deduction_value"
                                :disabled="!auth()->user()->can('settings.attendance.manage')"
                            />
                            @error('newPenalty.deduction_value') <span class="text-[10px] text-red-500 font-bold px-2">{{ tr($message) }}</span> @enderror
                        </div>
                </div>
            </div>
            @endif

            {{-- Removed: include_basic_penalty checkbox as per user request (one violation = one penalty) --}}
        </div>
    </x-slot:content>

    <x-slot:footer>
        <div class="flex items-center justify-end gap-3 w-full">
            <x-ui.secondary-button @click="$wire.set('showPenaltyModal', false)" class="!text-xs !py-3 !px-6 !rounded-2xl cursor-pointer">
                {{ tr('Cancel') }}
            </x-ui.secondary-button>
            @can('settings.attendance.manage')
            <x-ui.primary-button 
                wire:click="savePenalty" 
                wire:loading.attr="disabled"
                class="!bg-purple-600 hover:!bg-purple-700 !text-xs !py-3 !px-10 !rounded-2xl shadow-xl flex items-center gap-2 cursor-pointer"
            >
                <div wire:loading wire:target="savePenalty">
                    <i class="fas fa-spinner fa-spin text-xs"></i>
                </div>
                <i wire:loading.remove wire:target="savePenalty" class="fas fa-save"></i>
                <span>{{ tr('Save Policy') }}</span>
            </x-ui.primary-button>
            @endcan
        </div>
    </x-slot:footer>
</x-ui.modal>
