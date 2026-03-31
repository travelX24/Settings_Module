<div class="space-y-4">
    <div class="flex items-center justify-between px-1">
        <h3 class="text-base font-bold text-gray-800 flex items-center gap-2">
            <span class="w-1 h-5 bg-purple-500 rounded-full"></span>
            {{ tr('Recurring Violations (2, 3, 4)') }}
        </h3>
        @can('settings.attendance.manage')
        <x-ui.secondary-button wire:click="openPenaltyModal" class="!px-4 !py-2 !text-xs !rounded-xl shadow-sm cursor-pointer">
            <i class="fas fa-plus me-1 text-purple-500"></i>
            {{ tr('Add Recurring Violation') }}
        </x-ui.secondary-button>
        @endcan
    </div>

    @php
        $lateRecurring = collect($penalties)->where('violation_type', 'late_arrival')->where('recurrence_count', '>', 1)->sortBy('recurrence_count');
        $earlyRecurring = collect($penalties)->where('violation_type', 'early_departure')->where('recurrence_count', '>', 1)->sortBy('recurrence_count');
        $absenceRecurring = collect($penalties)->where('violation_type', 'unexcused_absence')->where('recurrence_count', '>', 1)
            ->merge(collect($absencePolicies)->where('recurrence_count', '>', 1))
            ->sortBy('recurrence_count');
    @endphp

    {{-- Late Arrival Recurring --}}
    <div class="space-y-2">
        <h4 class="text-xs font-bold text-gray-600 px-2 flex items-center gap-2">
            <i class="fas fa-clock text-orange-400"></i>
            {{ tr('Late Arrival Recurring Violations') }}
        </h4>
        @include('systemsettings::livewire.attendance.partials.sections.penalty-table', ['items' => $lateRecurring, 'type' => 'late'])
    </div>

    {{-- Early Departure Recurring --}}
    <div class="space-y-2">
        <h4 class="text-xs font-bold text-gray-600 px-2 flex items-center gap-2">
            <i class="fas fa-sign-out-alt text-blue-400"></i>
            {{ tr('Early Departure Recurring Violations') }}
        </h4>
        @include('systemsettings::livewire.attendance.partials.sections.penalty-table', ['items' => $earlyRecurring, 'type' => 'early'])
    </div>

    {{-- Absence Recurring --}}
    <div class="space-y-2">
        <h4 class="text-xs font-bold text-gray-600 px-2 flex items-center gap-2">
            <i class="fas fa-calendar-times text-red-400"></i>
            {{ tr('Unexcused Absence Recurring Violations') }}
        </h4>
        @include('systemsettings::livewire.attendance.partials.sections.penalty-table', ['items' => $absenceRecurring, 'type' => 'absence'])
    </div>
</div>
