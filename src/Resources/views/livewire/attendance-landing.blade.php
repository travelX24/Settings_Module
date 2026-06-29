@php
    $locale = app()->getLocale();
    $isRtl = in_array(substr($locale, 0, 2), ['ar', 'fa', 'ur', 'he']);
@endphp

@section('topbar-left-content')
    <x-ui.page-header :title="tr('Attendance Settings')" :subtitle="tr('Configure attendance policies, schedules, and related settings')"
        class="!flex-col {{ $isRtl ? '!items-end !justify-end' : '!items-start !justify-start' }} !gap-1" titleSize="xl" />
@endsection

<div class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @canany(['settings.attendance.view', 'settings.attendance.manage'])
            <x-ui.settings-button href="{{ route('company-admin.settings.attendance.settings') }}" icon="fa-clock"
                :title="tr('Attendance Configuration')" :description="tr('Set up attendance rules, check-in/check-out times, and schedules')" />
        @endcanany

        @canany(['settings.attendance.schedules.view', 'settings.attendance.schedules.manage'])
            <x-ui.settings-button href="{{ route('company-admin.settings.attendance.schedules') }}" icon="fa-calendar-alt"
                :title="tr('Work Schedules')" :description="tr('Define and manage working hour templates and shifts')" />
        @endcanany

        @canany(['settings.attendance.leaves.view', 'settings.attendance.leaves.manage'])
            <x-ui.settings-button href="{{ route('company-admin.settings.attendance.leaves') }}" icon="fa-calendar-check"
                :title="tr('Leave Settings')" :description="tr('Configure leave types, balances, and approval processes')" />
        @endcanany

        @canany(['settings.attendance.holidays.view', 'settings.attendance.holidays.manage'])
            <x-ui.settings-button href="{{ route('company-admin.settings.attendance.holidays') }}" icon="fa-calendar-times"
                :title="tr('Official Holidays')" :description="tr('Manage official holidays and non-working days')" />
        @endcanany

        @canany(['settings.attendance.exceptional.view', 'settings.attendance.exceptional.manage'])
            <x-ui.settings-button href="{{ route('company-admin.settings.attendance.exceptional-days') }}" icon="fa-star"
                :title="tr('Exceptional Days')" :description="tr('Set special working days and exceptions')" />
        @endcanany
    </div>
</div>