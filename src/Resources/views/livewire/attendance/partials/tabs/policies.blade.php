<div class="space-y-5">
    {{-- Section 1: Attendance Tracking Policy --}}
    @include('systemsettings::livewire.attendance.partials.sections.tracking')

    {{-- Section 2: Preparation Methods --}}
    @include('systemsettings::livewire.attendance.partials.sections.methods')

    {{-- Section 3: Grace Periods & Penalties --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1 space-y-5">
            @include('systemsettings::livewire.attendance.partials.sections.grace')
        </div>
        <div class="lg:col-span-2 space-y-5">
            @include('systemsettings::livewire.attendance.partials.sections.penalties')
            
            {{-- Section 5: Absence Without Permission --}}
            @include('systemsettings::livewire.attendance.partials.sections.absence')
        </div>
    </div>
</div>





