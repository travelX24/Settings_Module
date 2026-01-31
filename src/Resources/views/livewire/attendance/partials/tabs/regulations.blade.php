<div class="space-y-6">
    {{-- Section 3: Grace Periods --}}
    @include('systemsettings::livewire.attendance.partials.sections.grace')

    {{-- Section 4: Penalty Policies --}}
    @include('systemsettings::livewire.attendance.partials.sections.penalties')
    
    {{-- Section 5: Absence Without Permission --}}
    @include('systemsettings::livewire.attendance.partials.sections.absence')
</div>
