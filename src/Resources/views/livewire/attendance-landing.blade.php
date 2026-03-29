@php
    $locale = app()->getLocale();
    $isRtl = in_array(substr($locale, 0, 2), ['ar', 'fa', 'ur', 'he']);
@endphp

@section('topbar-left-content')
    <x-ui.page-header :title="tr('Attendance Settings')" :subtitle="tr('Configure attendance policies, schedules, and related settings')"
        class="!flex-col {{ $isRtl ? '!items-end !justify-end' : '!items-start !justify-start' }} !gap-1" titleSize="xl" />
@endsection

@section('topbar-actions')
    <div class="flex w-full {{ $isRtl ? 'justify-start' : 'justify-end' }}">
        <x-ui.secondary-button href="{{ route('company-admin.settings.general') }}" :arrow="false" :fullWidth="false"
            class="!px-4 !py-2 !text-sm !rounded-xl !gap-2">
            <i class="fas {{ $isRtl ? 'fa-arrow-right' : 'fa-arrow-left' }} text-xs"></i>
            <span>{{ tr('Back') }}</span>
        </x-ui.secondary-button>
    </div>
@endsection

<div class="space-y-6">
    {{-- Settings Buttons Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {{-- Attendance Configuration --}}
        @can('settings.attendance.view')
            <x-ui.settings-button href="{{ route('company-admin.settings.attendance.settings') }}" icon="fa-clock"
                :title="tr('Attendance Configuration')" :description="tr('Set up attendance rules, check-in/check-out times, and schedules')" />
        @endcan

        {{-- Work Schedules --}}
        @can('settings.attendance.view')
            <x-ui.settings-button href="{{ route('company-admin.settings.attendance.schedules') }}" icon="fa-calendar-alt"
                :title="tr('Work Schedules')" :description="tr('Define and manage working hour templates and shifts')" />
        @endcan

        {{-- Leave Settings --}}
        @can('settings.attendance.view')
            <x-ui.settings-button href="{{ route('company-admin.settings.attendance.leaves') }}" icon="fa-calendar-check"
                :title="tr('Leave Settings')" :description="tr('Configure leave types, balances, and approval processes')" />
        @endcan


        {{-- Official Holidays --}}
        @can('settings.attendance.view')
            <x-ui.settings-button href="{{ route('company-admin.settings.attendance.holidays') }}" icon="fa-calendar-times"
                :title="tr('Official Holidays')" :description="tr('Manage official holidays and non-working days')" />
        @endcan

        {{-- Exceptional Days --}}
        @can('settings.attendance.view')
            <x-ui.settings-button href="{{ route('company-admin.settings.attendance.exceptional-days') }}" icon="fa-star"
                :title="tr('Exceptional Days')" :description="tr('Set special working days and exceptions')" />
        @endcan

    </div>
</div>
