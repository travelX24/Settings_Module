@php
    $locale = app()->getLocale();
    $isRtl  = in_array(substr($locale, 0, 2), ['ar','fa','ur','he']);

    $savedLabel = $saved_calendar_type === 'hijri' ? tr('Hijri calendar') : tr('Gregorian calendar');
    $currentLabel = $calendar_type === 'hijri' ? tr('Hijri calendar') : tr('Gregorian calendar');

    $isDirty = ($calendar_type !== $saved_calendar_type);
@endphp

@section('topbar-left-content')
    <x-ui.page-header
        :title="tr('Calendar Settings')"
        :subtitle="tr('Choose the default calendar type for the company')"
        class="!flex-col {{ $isRtl ? '!items-end !text-right' : '!items-start !text-left' }} !justify-start !gap-1"
        titleSize="xl"
    />
@endsection

@section('topbar-actions')
    <x-ui.secondary-button
        href="{{ route('company-admin.settings.general') }}"
        :arrow="false"
        :fullWidth="false"
        class="!px-4 !py-2 !text-sm !rounded-xl !gap-2"
    >
        <i class="fas {{ $isRtl ? 'fa-arrow-right' : 'fa-arrow-left' }} text-xs"></i>
        <span>{{ tr('Back') }}</span>
    </x-ui.secondary-button>
@endsection

<div class="max-w-4xl mx-auto space-y-6">
    <x-ui.flash-toast />

    <x-ui.card :padding="false" class="rounded-3xl border-gray-200 overflow-hidden shadow-sm bg-white">
        {{-- Header with clear status --}}
        <div class="p-8 border-b border-gray-50 bg-gray-50/30">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white shadow-lg shadow-indigo-100">
                        <i class="fas fa-calendar-alt text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-black text-gray-900 leading-tight">
                            {{ tr('System Calendar') }}
                        </h3>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">{{ tr('Current Mode') }}:</span>
                            <span class="inline-flex items-center px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider
                                         {{ $saved_calendar_type === 'hijri' ? 'bg-amber-100 text-amber-700' : 'bg-indigo-100 text-indigo-700' }}">
                                <i class="fas {{ $saved_calendar_type === 'hijri' ? 'fa-moon' : 'fa-sun' }} mr-1.5"></i>
                                {{ $savedLabel }}
                            </span>
                        </div>
                    </div>
                </div>

                @if ($isDirty)
                    <div class="flex items-center gap-3 px-4 py-2 bg-red-50 border border-red-100 rounded-2xl animate-pulse">
                        <div class="w-2 h-2 rounded-full bg-red-500"></div>
                        <span class="text-[10px] font-black text-red-600 uppercase tracking-widest">{{ tr('Changes not saved') }}</span>
                    </div>
                @endif
            </div>
        </div>

        <div class="p-8 space-y-10">
            {{-- Simplified Selection Section --}}
            <div class="max-w-2xl mx-auto space-y-6">
                <div class="space-y-2 text-center">
                    <label class="block text-sm font-black text-gray-900">{{ tr('Switch Calendar Standard') }}</label>
                    <p class="text-xs text-gray-400 font-bold">{{ tr('Select the primary calendar used for company-wide dates and reports.') }}</p>
                </div>

                <div class="p-2 bg-gray-50 rounded-[2rem] border border-gray-100 shadow-inner">
                    <x-ui.select wire:model.live="calendar_type" class="!py-5 !px-8 !text-lg font-black !rounded-[1.8rem] !border-none !shadow-xl" :disabled="!auth()->user()->can('settings.calendar.manage')">
                        <option value="hijri">{{ tr('Hijri calendar') }}</option>
                        <option value="gregorian">{{ tr('Gregorian calendar') }}</option>
                    </x-ui.select>
                </div>

                @error('calendar_type')
                    <p class="text-[11px] font-bold text-red-500 text-center">{{ $message }}</p>
                @enderror
            </div>

            {{-- Action Footer - Very Clean --}}
            @can('settings.calendar.manage')
            <div class="pt-8 border-t border-gray-50 flex items-center justify-center gap-6">
                @if ($isDirty)
                    <button type="button" wire:click="resetToSaved" class="text-xs font-black text-gray-400 uppercase tracking-widest hover:text-red-500 transition-colors">
                         {{ tr('Discard') }}
                    </button>
                    <div class="w-[1px] h-4 bg-gray-200"></div>
                @endif
                
                <x-ui.primary-button 
                    type="button" 
                    wire:click="save" 
                    loading="save"
                    class="!px-16 !rounded-2xl font-black shadow-xl shadow-indigo-200"
                >
                    {{ tr('Confirm & Save') }}
                </x-ui.primary-button>
            </div>
            @endcan
        </div>
    </x-ui.card>
</div>
