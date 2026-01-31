@php
    $locale = app()->getLocale();
    $isRtl  = in_array(substr($locale, 0, 2), ['ar','fa','ur','he']);
@endphp

@section('topbar-left-content')
    <x-ui.page-header
        :title="tr('Attendance & Policy Settings')"
        :subtitle="tr('Manage attendance tracking, preparation methods, and company policies')"
        class="!flex-col {{ $isRtl ? '!items-end !text-right' : '!items-start !text-left' }} !justify-start !gap-1"
        titleSize="xl"
    />
@endsection

@section('topbar-actions')
    <x-ui.secondary-button
        href="{{ route('company-admin.settings.attendance') }}"
        :arrow="false"
        :fullWidth="false"
        class="!px-4 !py-2 !text-sm !rounded-xl !gap-2"
    >
        <i class="fas {{ $isRtl ? 'fa-arrow-right' : 'fa-arrow-left' }} text-xs"></i>
        <span>{{ tr('Back') }}</span>
    </x-ui.secondary-button>
@endsection

<div class="space-y-5 pb-6">
    {{-- Tabs Navigation --}}
    <x-ui.card class="!p-0 border-none shadow-sm overflow-hidden bg-white">
        <div class="flex border-b border-gray-100">
            <button 
                type="button"
                wire:click="setActiveTab('policies')"
                class="flex-1 py-4 px-6 font-semibold text-sm flex items-center justify-center gap-2 transition-all duration-200 {{ $activeTab === 'policies' ? 'text-[color:var(--brand-via)] border-b-2 border-[color:var(--brand-via)] bg-white' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50' }}"
            >
                <i class="fas fa-clock"></i>
                <span>{{ tr('Attendance Tracking') }}</span>
            </button>
            <button 
                type="button"
                wire:click="setActiveTab('regulations')"
                class="flex-1 py-4 px-6 font-semibold text-sm flex items-center justify-center gap-2 transition-all duration-200 {{ $activeTab === 'regulations' ? 'text-[color:var(--brand-via)] border-b-2 border-[color:var(--brand-via)] bg-white' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50' }}"
            >
                <i class="fas fa-gavel"></i>
                <span>{{ tr('Regulations & Penalties') }}</span>
            </button>
            <button 
                type="button"
                wire:click="setActiveTab('groups')"
                class="flex-1 py-4 px-6 font-semibold text-sm flex items-center justify-center gap-2 transition-all duration-200 {{ $activeTab === 'groups' ? 'text-[color:var(--brand-via)] border-b-2 border-[color:var(--brand-via)] bg-white' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50' }}"
            >
                <i class="fas fa-users-cog"></i>
                <span>{{ tr('Groups') }}</span>
            </button>
        </div>
    </x-ui.card>

    {{-- Content Area --}}
    <div>
        @if($activeTab === 'policies')
            @include('systemsettings::livewire.attendance.partials.tabs.policies')
        @elseif($activeTab === 'regulations')
            @include('systemsettings::livewire.attendance.partials.tabs.regulations')
        @elseif($activeTab === 'groups')
            @include('systemsettings::livewire.attendance.partials.tabs.groups')
        @endif
    </div>

    {{-- MODALS --}}
    @include('systemsettings::livewire.attendance.partials.modals.group-modal')
    @include('systemsettings::livewire.attendance.partials.modals.penalty-modal')
    @include('systemsettings::livewire.attendance.partials.modals.absence-modal')
    @include('systemsettings::livewire.attendance.partials.modals.gps-modals')
    @include('systemsettings::livewire.attendance.partials.modals.device-modals')

    {{-- Confirmation Dialogs & Assets --}}
    @include('systemsettings::livewire.attendance.partials.modals.confirmations')
</div>





