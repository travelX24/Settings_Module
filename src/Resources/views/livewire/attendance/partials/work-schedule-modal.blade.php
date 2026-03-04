{{-- systemsettings::livewire.attendance.partials.work-schedule-modal --}}

@php
    $totalMinutes = 0;
    $hasDay = false;
    $hasNight = false;
    $validPeriods = array_filter($scheduleData['periods'], fn($p) => $p['start_time'] && $p['end_time']);

    if (count($validPeriods) > 0) {
        foreach($validPeriods as $p) {
            $start = \Carbon\Carbon::parse($p['start_time']);
            $end = \Carbon\Carbon::parse($p['end_time']);

            if($p['is_night_shift'] || $end->lessThan($start)) {
                $end->addDay();
            }
            $totalMinutes += $end->diffInMinutes($start);

            $hour = (int)substr($p['start_time'], 0, 2);
            if ($p['is_night_shift'] || $hour >= 18 || $hour < 6) $hasNight = true;
            else $hasDay = true;
        }
    }

    $hours = floor($totalMinutes / 60);
    $mins = $totalMinutes % 60;
    $weeklyHours = ($totalMinutes * count($scheduleData['work_days'])) / 60;

    // Standard Professional Themes
    $themeTitle = tr('Standard');
    $themeIcon = 'fa-clock';
    $themeCls = 'bg-gray-800 text-white'; // Fallback

    if ($hasDay && $hasNight) {
        $themeCls = 'bg-gradient-to-br from-[color:var(--brand-via)] to-gray-900 text-white';
        $themeIcon = 'fa-adjust';
        $themeTitle = tr('Mixed Shift');
    } elseif ($hasDay) {
        $themeCls = 'bg-gradient-to-br from-blue-500 to-indigo-600 text-white';
        $themeIcon = 'fa-sun';
        $themeTitle = tr('Day Shift');
    } elseif ($hasNight) {
        $themeCls = 'bg-gradient-to-br from-indigo-900 to-black text-white';
        $themeIcon = 'fa-moon';
        $themeTitle = tr('Night Shift');
    }
@endphp

<x-ui.modal wire:model="showScheduleModal" maxWidth="5xl">
    <x-slot:title>
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-[color:var(--brand-via)]/5 text-[color:var(--brand-via)] rounded-xl flex items-center justify-center border border-[color:var(--brand-via)]/10 shadow-sm">
                <i class="fas fa-calendar-plus text-sm"></i>
            </div>
            <div>
                <h3 class="font-bold text-gray-900 text-lg leading-tight">{{ $isEditing ? tr('Edit Work Schedule') : tr('Enrich Schedule') }}</h3>
                <p class="text-[9px] text-gray-400 font-black uppercase tracking-widest">{{ tr('Temporal Mapping & Templates') }}</p>
            </div>
        </div>
    </x-slot:title>

    <x-slot:content>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 py-1">
            {{-- Form Side --}}
            <div class="lg:col-span-2 space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="col-span-1">
                        <x-ui.input
                            label="{{ tr('Schedule Name') }}"
                            wire:model.defer="scheduleData.name"
                            placeholder="{{ tr('e.g. Morning Shift') }}"
                            class="!py-2 !text-xs"
                            required
                            :disabled="!auth()->user()->can('settings.attendance.manage')"
                        />
                    </div>
                    <div class="col-span-1">
                        <x-ui.input
                            label="{{ tr('Description') }}"
                            wire:model.defer="scheduleData.description"
                            placeholder="{{ tr('Optional...') }}"
                            class="!py-2 !text-xs"
                            :disabled="!auth()->user()->can('settings.attendance.manage')"
                        />
                    </div>
                </div>

                {{-- Week Config --}}
                <div class="p-3 bg-gray-50/50 rounded-2xl border border-gray-100 flex flex-col gap-3">
                    <div class="grid grid-cols-2 gap-3">
                        <x-ui.select
                            label="{{ tr('Week Start') }}"
                            wire:model="scheduleData.week_start_day"
                            class="!bg-white shadow-sm !py-1.5 !text-xs"
                            :disabled="!auth()->user()->can('settings.attendance.manage')"
                        >
                            @foreach($daysOfWeek as $val => $lbl)
                                <option value="{{ $val }}">{{ $lbl }}</option>
                            @endforeach
                        </x-ui.select>

                        <x-ui.select
                            label="{{ tr('Week End') }}"
                            wire:model="scheduleData.week_end_day"
                            class="!bg-white shadow-sm !py-1.5 !text-xs"
                            :disabled="!auth()->user()->can('settings.attendance.manage')"
                        >
                            @foreach($daysOfWeek as $val => $lbl)
                                <option value="{{ $val }}">{{ $lbl }}</option>
                            @endforeach
                        </x-ui.select>
                    </div>

                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">{{ tr('Work Days Selection') }}</label>
                            <button wire:click="toggleAllDays" class="text-[10px] font-black text-[color:var(--brand-via)] uppercase hover:underline">
                                {{ count($scheduleData['work_days']) === 7 ? tr('Deselect All') : tr('Select All') }}
                            </button>
                        </div>

                        <div class="flex flex-wrap gap-1.5">
                            @foreach($daysOfWeek as $val => $lbl)
                                <label class="cursor-pointer group">
                                    <input type="checkbox" wire:model.live="scheduleData.work_days" value="{{ $val }}" class="hidden peer">
                                    <div class="px-3 py-2 rounded-xl border border-gray-100 bg-white text-[11px] font-bold text-gray-500 transition-all peer-checked:bg-[color:var(--brand-via)] peer-checked:text-white peer-checked:border-[color:var(--brand-via)] shadow-sm group-hover:bg-gray-50">
                                        {{ $lbl }}
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Periods --}}
                <div class="space-y-3">
                    <div class="flex items-center justify-between border-b border-gray-100 pb-2">
                        <div class="flex items-center gap-2">
                            <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">{{ tr('Shift Periods') }}</label>
                            <span class="text-[9px] font-black px-1.5 py-0.5 rounded bg-gray-100 text-gray-500 uppercase">{{ count($scheduleData['periods']) }}/4</span>
                        </div>

                        @if(count($scheduleData['periods']) < 4)
                            <button wire:click="addPeriod" class="text-[10px] font-black text-[color:var(--brand-via)] uppercase hover:underline transition-all">
                                <i class="fas fa-plus me-1"></i>{{ tr('Add Period') }}
                            </button>
                        @endif
                    </div>

                    <div class="grid grid-cols-1 gap-3">
                        @foreach($scheduleData['periods'] as $idx => $p)
                            <div class="space-y-1">
                                <div class="flex items-end gap-3 p-2.5 bg-white rounded-xl border border-gray-100 shadow-sm animate-in fade-in slide-in-from-top-1 {{ $errors->has('scheduleData.periods.'.$idx.'.end_time') ? 'border-red-200 bg-red-50/10' : '' }}">
                                    <div class="flex-1 grid grid-cols-2 gap-3">
                                        {{-- ✅ changed live -> lazy --}}
                                        <x-ui.input
                                            label="{{ tr('Starts') }}"
                                            type="time"
                                            wire:model.lazy="scheduleData.periods.{{ $idx }}.start_time"
                                            class="!py-1 !text-xs"
                                        />
                                        {{-- ✅ changed live -> lazy --}}
                                        <x-ui.input
                                            label="{{ tr('Ends') }}"
                                            type="time"
                                            wire:model.lazy="scheduleData.periods.{{ $idx }}.end_time"
                                            class="!py-1 !text-xs"
                                        />
                                    </div>

                                    <div class="mb-1.5">
                                        <label class="flex items-center gap-1.5 cursor-pointer p-1.5 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                                            {{-- ✅ changed live -> lazy --}}
                                            <input
                                                type="checkbox"
                                                wire:model.lazy="scheduleData.periods.{{ $idx }}.is_night_shift"
                                                class="w-3.5 h-3.5 text-[color:var(--brand-via)] rounded border-gray-300"
                                            >
                                            <i class="fas fa-moon text-[9px] {{ $p['is_night_shift'] ? 'text-indigo-500' : 'text-gray-400' }}"></i>
                                        </label>
                                    </div>

                                    @if(count($scheduleData['periods']) > 1)
                                        <button wire:click="removePeriod({{ $idx }})" class="mb-1.5 w-7 h-7 rounded-lg text-red-400 hover:bg-red-50 flex items-center justify-center transition-colors">
                                            <i class="fas fa-trash-alt text-[10px]"></i>
                                        </button>
                                    @endif
                                </div>

                                @error('scheduleData.periods.'.$idx.'.end_time')
                                    <p class="text-[9px] font-bold text-red-500 px-3 flex items-center gap-1">
                                        <i class="fas fa-exclamation-circle text-[8px]"></i> {{ $message }}
                                    </p>
                                @enderror

                                @error('scheduleData.periods.'.$idx.'.start_time')
                                    <p class="text-[9px] font-bold text-red-500 px-3 flex items-center gap-1">
                                        <i class="fas fa-exclamation-circle text-[8px]"></i> {{ $message }}
                                    </p>
                                @enderror
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Exceptions Section --}}
                <div class="space-y-3 pt-3 border-t border-gray-100">
                    <div class="flex items-center justify-between">
                        <div>
                            <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest">{{ tr('Daily Exceptions') }}</label>
                        </div>
                        <button wire:click="addException" class="text-[9px] font-black text-indigo-500 uppercase hover:underline">
                            <i class="fas fa-plus me-1"></i> {{ tr('Add Exception') }}
                        </button>
                    </div>

                    @foreach($scheduleData['exceptions'] as $idx => $e)
                        <div class="p-4 bg-gray-50/50 rounded-2xl border border-gray-100 relative space-y-3 animate-in fade-in slide-in-from-top-2">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] font-black text-gray-700 uppercase">{{ tr('Exception') }} #{{ $idx + 1 }}</span>
                                    <label class="flex items-center gap-1.5 cursor-pointer ml-2">
                                        <input type="checkbox" wire:model.defer="scheduleData.exceptions.{{ $idx }}.is_active" class="w-3.5 h-3.5 text-emerald-500 rounded border-gray-300">
                                        <span class="text-[9px] font-bold text-gray-500">{{ tr('Active') }}</span>
                                    </label>
                                </div>
                                <button wire:click="removeException({{ $idx }})" class="text-red-400 hover:text-red-600 transition-colors">
                                    <i class="fas fa-trash-alt text-[10px]"></i>
                                </button>
                            </div>

                            <div class="grid grid-cols-4 gap-2 items-end">
                                <div class="col-span-1">
                                    <x-ui.select label="{{ tr('Day') }}" wire:model.defer="scheduleData.exceptions.{{ $idx }}.day_of_week" class="!py-1 !text-[10px] !bg-white">
                                        @foreach($daysOfWeek as $v => $l)
                                            <option value="{{ $v }}">{{ $l }}</option>
                                        @endforeach
                                    </x-ui.select>
                                </div>

                                <div class="col-span-1">
                                    {{-- ✅ changed live -> lazy --}}
                                    <x-ui.input
                                        label="{{ tr('Start') }}"
                                        type="time"
                                        wire:model.lazy="scheduleData.exceptions.{{ $idx }}.start_time"
                                        class="!py-1 !text-[10px] !bg-white"
                                    />
                                </div>

                                <div class="col-span-1">
                                    {{-- ✅ changed live -> lazy --}}
                                    <x-ui.input
                                        label="{{ tr('End') }}"
                                        type="time"
                                        wire:model.lazy="scheduleData.exceptions.{{ $idx }}.end_time"
                                        class="!py-1 !text-[10px] !bg-white"
                                    />
                                </div>

                                <div class="col-span-1 flex items-center gap-1.5 h-[34px] px-2 bg-white rounded-lg border border-gray-100">
                                    {{-- ✅ changed live -> lazy --}}
                                    <input type="checkbox" wire:model.lazy="scheduleData.exceptions.{{ $idx }}.is_night_shift" class="w-3 h-3 text-indigo-500 rounded border-gray-300">
                                    <span class="text-[8px] font-bold text-gray-500">{{ tr('Night') }}</span>
                                    <i class="fas fa-moon text-[8px] text-gray-300 ms-auto"></i>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Preview Side --}}
            <div class="lg:col-span-1 space-y-6">
            {{-- Preview Side --}}
            <div class="lg:col-span-1 space-y-6">
                <div class="{{ $themeCls }} rounded-3xl p-6 shadow-xl relative overflow-hidden transition-all duration-500 border border-white/10">
                    <div class="absolute top-0 right-0 p-4 opacity-10 text-6xl transition-all duration-500">
                        <i class="fas {{ $themeIcon }}"></i>
                    </div>

                    <div class="relative z-10 space-y-6">
                        <div>
                            <span class="text-[10px] font-black uppercase tracking-widest opacity-60">{{ tr('Template Preview') }}</span>
                            <h4 class="text-xl font-black mt-0.5 truncate">{{ $scheduleData['name'] ?: $themeTitle }}</h4>
                        </div>

                        <div class="py-5 border-y border-white/10 text-center">
                            <div class="text-4xl font-black">
                                {{ $hours }}h <span class="text-xl opacity-60">{{ $mins > 0 ? $mins.'m' : '' }}</span>
                            </div>
                            <p class="text-[10px] font-black opacity-50 uppercase tracking-widest mt-1">{{ tr('Average Daily Hours') }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div class="bg-white/10 p-2.5 rounded-xl border border-white/5 flex items-center gap-2">
                                <i class="fas fa-calendar-check text-[10px] opacity-40"></i>
                                <div class="flex flex-col">
                                    <span class="text-[10px] font-black leading-none">{{ count($scheduleData['work_days']) }}</span>
                                    <span class="text-[7px] font-black opacity-40 uppercase mt-0.5">{{ tr('Work Days') }}</span>
                                </div>
                            </div>

                            <div class="bg-white/10 p-2.5 rounded-xl border border-white/5 flex items-center gap-2">
                                <i class="fas fa-coffee text-[10px] opacity-40"></i>
                                <div class="flex flex-col">
                                    <span class="text-[10px] font-black leading-none">{{ 7 - count($scheduleData['work_days']) }}</span>
                                    <span class="text-[7px] font-black opacity-40 uppercase mt-0.5">{{ tr('Rest Days') }}</span>
                                </div>
                            </div>

                            <div class="bg-white/10 p-2.5 rounded-xl border border-white/5 flex items-center gap-2">
                                <i class="fas fa-history text-[10px] opacity-40"></i>
                                <div class="flex flex-col">
                                    <span class="text-[10px] font-black leading-none">{{ $weeklyHours }}h</span>
                                    <span class="text-[7px] font-black opacity-40 uppercase mt-0.5">{{ tr('Weekly') }}</span>
                                </div>
                            </div>

                            <div class="bg-white/10 p-2.5 rounded-xl border border-white/5 flex items-center gap-2">
                                <i class="fas fa-exclamation-triangle text-[10px] opacity-40"></i>
                                <div class="flex flex-col">
                                    <span class="text-[10px] font-black leading-none">{{ count($scheduleData['exceptions']) }}</span>
                                    <span class="text-[7px] font-black opacity-40 uppercase mt-0.5">{{ tr('Excp.') }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <p class="text-[9px] font-black opacity-60 uppercase tracking-widest text-center">{{ tr('Weekly Matrix & Weekends') }}</p>
                            <div class="grid grid-cols-2 gap-1.5">
                                @foreach(['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday'] as $d)
                                    @php
                                        $isWork = in_array($d, $scheduleData['work_days']);
                                        $isWeekend = ($d === $scheduleData['week_end_day']);
                                    @endphp
                                    <div class="flex items-center gap-2 px-2.5 py-1.5 rounded-lg border transition-all
                                        {{ $isWork ? 'bg-white text-gray-900 border-white' : 'bg-white/5 border-white/10 text-white/30' }}">
                                        <div class="w-1.5 h-1.5 rounded-full {{ $isWork ? 'bg-[color:var(--brand-via)]' : 'bg-transparent border border-white/20' }}"></div>
                                        <span class="text-[10px] font-bold truncate">{{ $daysOfWeek[$d] }}</span>
                                        @if($isWeekend)
                                            <i class="fas fa-star text-[8px] text-amber-400 ms-auto" title="{{ tr('Week End') }}"></i>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-4 bg-white rounded-[1.5rem] border border-gray-100 space-y-3">
                    <label class="flex items-center justify-between cursor-pointer group">
                        <div class="flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded-lg bg-amber-50 text-amber-500 flex items-center justify-center transition-transform group-hover:scale-110 shadow-inner">
                                <i class="fas fa-star text-[10px]"></i>
                            </div>
                            <div>
                                <h4 class="text-[11px] font-bold text-gray-800 leading-none">{{ tr('Default Schedule') }}</h4>
                                <p class="text-[8px] text-gray-400 font-medium mt-1 leading-none">{{ tr('Apply to new staff') }}</p>
                            </div>
                        </div>
                        <input type="checkbox" wire:model.defer="scheduleData.is_default" class="w-4 h-4 text-amber-500 rounded border-gray-300">
                    </label>

                    <label class="flex items-center justify-between cursor-pointer group">
                        <div class="flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded-lg bg-green-50 text-green-500 flex items-center justify-center transition-transform group-hover:scale-110 shadow-inner">
                                <i class="fas fa-power-off text-[10px]"></i>
                            </div>
                            <div>
                                <h4 class="text-[11px] font-bold text-gray-800 leading-none">{{ tr('Active State') }}</h4>
                                <p class="text-[8px] text-gray-400 font-medium mt-1 leading-none">{{ tr('Enable Template') }}</p>
                            </div>
                        </div>
                        <input type="checkbox" wire:model.defer="scheduleData.is_active" class="w-4 h-4 text-green-500 rounded border-gray-300">
                    </label>
                </div>
            </div>
        </div>
    </x-slot:content>

    <x-slot:footer>
        <x-ui.secondary-button wire:click="$set('showScheduleModal', false)" class="!rounded-xl shadow-sm hover:!bg-gray-50">
            {{ tr('Discard') }}
        </x-ui.secondary-button>

        @can('settings.attendance.manage')
        <x-ui.brand-button wire:click="saveSchedule" class="!px-12 !rounded-xl shadow-lg shadow-[color:var(--brand-via)]/20">
            <i class="fas fa-save me-2"></i> {{ tr('Save Schedule') }}
        </x-ui.brand-button>
        @endcan
    </x-slot:footer>
</x-ui.modal>