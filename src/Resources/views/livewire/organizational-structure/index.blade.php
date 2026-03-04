@php
    $locale = app()->getLocale();
    $isRtl  = in_array(substr($locale, 0, 2), ['ar','fa','ur','he']);
@endphp

@section('topbar-left-content')
    <x-ui.page-header
        :title="tr('Organizational Structure Settings')"
        :subtitle="tr('Configure departments, positions, and organizational hierarchy')"
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

<div class="space-y-6">
    {{-- Tabs Container --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        {{-- Tabs Header --}}
        <div class="border-b border-gray-200 bg-gray-50">
            <div class="flex items-center justify-between">
                <div class="flex justify-center flex-1">
                    {{-- Departments Tab --}}
                    <button
                        wire:click="setActiveTab('departments')"
                        class="cursor-pointer px-6 py-4 font-semibold text-sm transition-all duration-200 flex items-center gap-2 {{ $activeTab === 'departments' 
                            ? 'border-b-2 border-[color:var(--brand-via)] text-[color:var(--brand-via)] bg-white' 
                            : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' }}"
                    >
                        <i class="fas fa-building text-lg"></i>
                        <span>{{ tr('Departments') }}</span>
                    </button>

                    {{-- Job Titles Tab --}}
                    <button
                        wire:click="setActiveTab('job-titles')"
                        class="cursor-pointer px-6 py-4 font-semibold text-sm transition-all duration-200 flex items-center gap-2 {{ $activeTab === 'job-titles' 
                            ? 'border-b-2 border-[color:var(--brand-via)] text-[color:var(--brand-via)] bg-white' 
                            : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' }}"
                    >
                        <i class="fas fa-briefcase text-lg"></i>
                        <span>{{ tr('Job Titles') }}</span>
                    </button>
                </div>
                {{-- Add Button --}}
                <div class="px-4">
                    @can('settings.organizational.manage')
                        @if($activeTab === 'departments')
                            <button
                                type="button"
                                @click="$dispatch('open-add-department-modal')"
                                class="cursor-pointer group relative overflow-hidden rounded-xl px-4 py-2 text-sm font-semibold text-white shadow-md bg-gradient-to-r from-[color:var(--brand-from)] via-[color:var(--brand-via)] to-[color:var(--brand-to)] hover:shadow-lg active:scale-[0.98] transition-all duration-300 focus:outline-none focus:ring-2 focus:ring-[color:var(--brand-from)]/30"
                            >
                                <span class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-1000"></span>
                                <span class="relative flex items-center gap-2">
                                    <i class="fas fa-plus text-xs"></i>
                                    <span>{{ tr('Add Department') }}</span>
                                </span>
                            </button>
                        @elseif($activeTab === 'job-titles')
                            <button
                                type="button"
                                @click="$dispatch('open-add-job-title-modal')"
                                class="cursor-pointer group relative overflow-hidden rounded-xl px-4 py-2 text-sm font-semibold text-white shadow-md bg-gradient-to-r from-[color:var(--brand-from)] via-[color:var(--brand-via)] to-[color:var(--brand-to)] hover:shadow-lg active:scale-[0.98] transition-all duration-300 focus:outline-none focus:ring-2 focus:ring-[color:var(--brand-from)]/30"
                            >
                                <span class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-1000"></span>
                                <span class="relative flex items-center gap-2">
                                    <i class="fas fa-plus text-xs"></i>
                                    <span>{{ tr('Add Job Title') }}</span>
                                </span>
                            </button>
                        @endif
                    @endcan
                </div>
            </div>
        </div>

        {{-- Tabs Content --}}
        <div class="p-6">
            @if($activeTab === 'departments')
                @livewire('systemsettings.organizational-structure.departments')
            @elseif($activeTab === 'job-titles')
                @livewire('systemsettings.organizational-structure.job-titles')
            @endif
        </div>
    </div>

    {{-- Global Employee Detail Modal (Summoned by ID) --}}
    @livewire('employees.detail-modal')

    {{-- Global Employees List Modal (Fetched via AJAX) --}}
    @include('systemsettings::livewire.organizational-structure.partials.employees-list-modal')
</div>





