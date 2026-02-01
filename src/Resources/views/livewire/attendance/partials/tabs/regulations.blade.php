<div class="space-y-6">
    {{-- Section 1: Basic Settings --}}
    @include('systemsettings::livewire.attendance.partials.sections.grace')

    {{-- Section 2: Basic Penalties --}}
    <div class="flex items-center px-1">
        <h3 class="text-base font-bold text-gray-800 flex items-center gap-2">
            <span class="w-1 h-5 bg-red-500 rounded-full"></span>
            {{ tr('Basic Penalties') }}
        </h3>
    </div>
    <p class="text-xs text-gray-500 px-1">{{ tr('Applied after grace period for the first violation only.') }}</p>

    {{-- A. Late Arrival --}}
    @include('systemsettings::livewire.attendance.partials.sections.basic-late')

    {{-- B. Early Departure --}}
    @include('systemsettings::livewire.attendance.partials.sections.basic-early')

    {{-- C. Unexcused Absence --}}
    @include('systemsettings::livewire.attendance.partials.sections.basic-absence')

    {{-- Section 3: Recurring Violations --}}
    @include('systemsettings::livewire.attendance.partials.sections.recurring-penalties')
</div>
