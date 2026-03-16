@php
    $locale = app()->getLocale();
    $isRtl  = in_array(substr($locale, 0, 2), ['ar','fa','ur','he']);
@endphp

@section('topbar-left-content')
    <x-ui.page-header
        :title="tr('User Access Control')"
        :subtitle="tr('Manage user accounts, roles, and system permissions')"
        class="!flex-col {{ $isRtl ? '!items-end !text-right' : '!items-start !text-left' }} !justify-start !gap-1"
        titleSize="xl"
    />
@endsection

@section('topbar-actions')
    <x-ui.secondary-button
        href="{{ route('company-admin.settings.general') }}"
        :arrow="false"
        :fullWidth="false"
        class="!px-4 !py-2 !text-sm !rounded-xl !gap-2 cursor-pointer"
    >
        <i class="fas {{ $isRtl ? 'fa-arrow-right' : 'fa-arrow-left' }} text-xs"></i>
        <span>{{ tr('Back') }}</span>
    </x-ui.secondary-button>
@endsection

<div class="space-y-6">
    {{-- Tabs Container --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        {{-- Tabs Header --}}
        <div class="border-b border-gray-200 bg-gray-50">
            <div class="flex items-center justify-between">
                <div class="flex justify-center flex-1">
                    {{-- Users Tab --}}
                    @can('uac.users.view')
                    <button
                        wire:click="setActiveTab('users')"
                        class="px-6 py-4 font-semibold text-sm transition-all duration-200 flex items-center gap-2 cursor-pointer {{ $activeTab === 'users' 
                            ? 'border-b-2 border-[color:var(--brand-via)] text-[color:var(--brand-via)] bg-white' 
                            : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' }}"
                    >
                        <i class="fas fa-users text-lg"></i>
                        <span>{{ tr('Users') }}</span>
                    </button>
                    @endcan

                    {{-- Roles Tab --}}
                    @can('uac.roles.view')
                    <button
                        wire:click="setActiveTab('roles')"
                        class="px-6 py-4 font-semibold text-sm transition-all duration-200 flex items-center gap-2 cursor-pointer {{ $activeTab === 'roles' 
                            ? 'border-b-2 border-[color:var(--brand-via)] text-[color:var(--brand-via)] bg-white' 
                            : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' }}"
                    >
                        <i class="fas fa-user-tag text-lg"></i>
                        <span>{{ tr('Roles & Permissions') }}</span>
                    </button>
                    @endcan
                </div>
                {{-- Add Button --}}
                <div class="px-4">
                    @can('uac.users.manage')
                    @if($activeTab === 'users')
                    <button
                        type="button"
                        @click="$dispatch('open-add-user-modal')"
                        class="group relative overflow-hidden rounded-xl px-4 py-2 text-sm font-semibold text-white shadow-md bg-gradient-to-r from-[color:var(--brand-from)] via-[color:var(--brand-via)] to-[color:var(--brand-to)] hover:shadow-lg active:scale-[0.98] transition-all duration-300 focus:outline-none focus:ring-2 focus:ring-[color:var(--brand-from)]/30 cursor-pointer"
                    >
                        <span class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-1000"></span>
                        <span class="relative flex items-center gap-2">
                            <i class="fas fa-user-plus text-xs"></i>
                            <span>{{ tr('Add User') }}</span>
                        </span>
                    </button>
                    @endif
                    @endcan

                    {{-- Add Role Button --}}
                    @can('uac.roles.manage')
                    @if($activeTab === 'roles')
                    <button
                        type="button"
                        @click="$dispatch('open-add-role-modal')"
                        class="group relative overflow-hidden rounded-xl px-4 py-2 text-sm font-semibold text-white shadow-md bg-gradient-to-r from-[color:var(--brand-from)] via-[color:var(--brand-via)] to-[color:var(--brand-to)] hover:shadow-lg active:scale-[0.98] transition-all duration-300 focus:outline-none focus:ring-2 focus:ring-[color:var(--brand-from)]/30 cursor-pointer"
                    >
                        <span class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-1000"></span>
                        <span class="relative flex items-center gap-2">
                            <i class="fas fa-plus-circle text-xs"></i>
                            <span>{{ tr('Add Role') }}</span>
                        </span>
                    </button>
                    @endif
                    @endcan
                </div>
            </div>
        </div>

        {{-- Tabs Content --}}
        <div class="p-6">
            @if($activeTab === 'users')
                @livewire('systemsettings.user-access-control.users')
            @elseif($activeTab === 'roles')
                @livewire('systemsettings.user-access-control.roles')
            @endif
        </div>
    </div>
</div>





