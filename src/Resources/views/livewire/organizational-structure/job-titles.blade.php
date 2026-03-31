<div
    x-data="{
        view: (localStorage.getItem('org_job_view') || 'table'),
        setView(v){
            this.view = v;
            localStorage.setItem('org_job_view', v);
        }
    }"
    x-init="localStorage.setItem('org_job_view', view)"
    @open-add-job-title-modal.window="$wire.openAddModal()"
>
    {{-- Statistics Bar --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 gap-4 mb-8">
        {{-- Total Job Titles --}}
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 flex items-center gap-4 group hover:border-[color:var(--brand-via)]/30 transition-all duration-300">
            <div class="w-12 h-12 bg-purple-50 rounded-xl flex items-center justify-center text-purple-600 group-hover:bg-purple-600 group-hover:text-white transition-all duration-500 shadow-sm">
                <i class="fas fa-briefcase text-xl"></i>
            </div>
            <div>
                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">{{ tr('Total Job Titles') }}</p>
                <h3 class="text-xl font-black text-gray-900 leading-none mt-1">{{ $stats['total'] ?? 0 }}</h3>
            </div>
        </div>

        {{-- Status Summary --}}
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 flex items-center justify-around group hover:border-[color:var(--brand-via)]/30 transition-all duration-300">
            <div class="text-center">
                <p class="text-[9px] font-bold text-green-500 uppercase">{{ tr('Active') }}</p>
                <p class="text-lg font-black text-gray-900">{{ $stats['active'] ?? 0 }}</p>
            </div>
            <div class="w-px h-8 bg-gray-100"></div>
            <div class="text-center">
                <p class="text-[9px] font-bold text-gray-400 uppercase">{{ tr('Inactive') }}</p>
                <p class="text-lg font-black text-gray-400">{{ $stats['inactive'] ?? 0 }}</p>
            </div>
        </div>
    </div>

    {{-- Search and Actions --}}
    <x-ui.card :padding="false" class="border-gray-200 p-4 mb-6">
        <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between">
            <div class="flex-1 w-full sm:w-auto relative">
                <div class="absolute inset-y-0 start-0 flex items-center ps-4 pointer-events-none">
                    <i class="fas fa-search w-5 h-5 text-gray-400"></i>
                </div>

                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ tr('Search job titles...') }}"
                    class="w-full px-4 py-2.5 ps-10 text-sm rounded-xl border border-gray-200 bg-white shadow-sm placeholder-gray-400 text-gray-900
                           focus:outline-none focus:ring-2 focus:ring-[color:var(--brand-via)]/20 focus:border-[color:var(--brand-via)]
                           transition"
                />
            </div>

            <div class="flex items-center gap-2">
                {{-- Clear Filters Button --}}
                <div
                    x-data="{
                        hasFilters() {
                            return ($wire.search && $wire.search.trim() !== '');
                        }
                    }"
                    x-show="hasFilters()"
                    x-transition
                    class="flex items-center"
                >
                    <button
                        type="button"
                        wire:click="clearAllFilters"
                        wire:loading.attr="disabled"
                        wire:target="clearAllFilters"
                        class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-semibold text-gray-600 hover:text-gray-900 transition-colors disabled:opacity-50 border-e border-gray-100 pe-4 me-2"
                    >
                        <i class="fas fa-times" wire:loading.remove wire:target="clearAllFilters"></i>
                        <i class="fas fa-spinner fa-spin" wire:loading wire:target="clearAllFilters"></i>
                        <span wire:loading.remove wire:target="clearAllFilters">{{ tr('Clear filters') }}</span>
                    </button>
                </div>

                {{-- View Toggle (Cards / List) --}}
                <x-ui.view-toggle />

                {{-- Export --}}
                <button
                    wire:click="export"
                    class="cursor-pointer px-4 py-2.5 text-sm font-semibold text-gray-700 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 transition-colors flex items-center gap-2"
                >
                    <i class="fas fa-download"></i>
                    <span>{{ tr('Export') }}</span>
                </button>
            </div>
        </div>
    </x-ui.card>

    {{-- Content Container --}}
    <x-ui.card :padding="false" class="border-gray-200 overflow-hidden p-6">
        @if($jobTitles->count() > 0)

            {{-- ✅ CARDS VIEW --}}
            <div x-show="view === 'cards'" x-cloak>
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    @foreach($jobTitles as $jobTitle)
                        <x-ui.card
                            :hover="true"
                            :padding="false"
                            wire:key="job-title-card-{{ $jobTitle->id }}"
                            class="rounded-2xl border-gray-200 p-4"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="text-sm font-bold text-gray-900 truncate">
                                        {{ $jobTitle->name }}
                                    </div>

                                    @if($jobTitle->description && mb_strlen($jobTitle->description) > 60)
                                        <div x-data="{ expanded: false }" class="mt-0.5">
                                            <div :class="expanded ? 'whitespace-normal break-words' : 'line-clamp-2'" class="text-xs text-gray-500 transition-all duration-300">
                                                {{ $jobTitle->description }}
                                            </div>
                                            <button @click="expanded = !expanded" class="text-[10px] font-semibold text-[color:var(--brand-via)] hover:underline mt-1 focus:outline-none">
                                                <span x-show="!expanded">{{ tr('Read more') }}</span>
                                                <span x-show="expanded" x-cloak>{{ tr('Show less') }}</span>
                                            </button>
                                        </div>
                                    @else
                                        <div class="text-xs text-gray-500 mt-0.5 whitespace-normal break-words">
                                            {{ $jobTitle->description ?? '-' }}
                                        </div>
                                    @endif
                                    @if($jobTitle->code)
                                        <div class="mt-1">
                                            <span class="px-1.5 py-0.5 text-[10px] font-bold bg-gray-100 text-gray-600 rounded"># {{ $jobTitle->code }}</span>
                                        </div>
                                    @endif
                                </div>

                                <div class="flex items-center gap-2 shrink-0">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $jobTitle->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                        {{ $jobTitle->is_active ? tr('Active') : tr('Inactive') }}
                                    </span>

                                    @can('settings.organizational.manage')
                                        <x-ui.actions-menu>
                                            <x-ui.dropdown-item
                                                wire:click="openEditModal({{ $jobTitle->id }})"
                                                @click="$dispatch('close-actions-menu')"
                                            >
                                                <i class="fas fa-edit me-2"></i>
                                                <span>{{ tr('Edit') }}</span>
                                            </x-ui.dropdown-item>

                                            <x-ui.dropdown-item
                                                wire:click="toggleActive({{ $jobTitle->id }})"
                                                @click="$dispatch('close-actions-menu')"
                                            >
                                                <i class="fas fa-{{ $jobTitle->is_active ? 'eye-slash' : 'eye' }} me-2"></i>
                                                <span>{{ $jobTitle->is_active ? tr('Deactivate') : tr('Activate') }}</span>
                                            </x-ui.dropdown-item>

                                            <x-ui.dropdown-item
                                                @click="$dispatch('open-confirm-delete-job-title', { id: {{ $jobTitle->id }} }); $dispatch('close-actions-menu')"
                                                danger
                                            >
                                                <i class="fas fa-trash me-2"></i>
                                                <span>{{ tr('Delete') }}</span>
                                            </x-ui.dropdown-item>
                                        </x-ui.actions-menu>
                                    @endcan
                                </div>
                            </div>

                            <div class="mt-4 flex items-center justify-between text-sm">
                                <div class="text-gray-500">{{ tr('Employees') }}</div>

                                <button
                                    wire:click="$dispatch('open-employees-modal', { type: 'job-title', id: {{ $jobTitle->id }} })"
                                    class="font-bold text-[color:var(--brand-via)] hover:text-[color:var(--brand-via)]/80 hover:underline"
                                >
                                    {{ (int) ($jobTitle->employees_count ?? 0) }}
                                </button>
                            </div>
                        </x-ui.card>
                    @endforeach
                </div>
            </div>

            {{-- ✅ TABLE/LIST VIEW (Default) --}}
            <div x-show="view === 'table'" x-cloak>
                <x-ui.table
                    :headers="[
                        tr('Job Title Name'),
                        tr('Code'),
                        tr('Employees'),
                        tr('Description'),
                        tr('Status'),
                        tr('Actions')
                    ]"
                    :perPage="10"
                    :enablePagination="true"
                >
                    @foreach($jobTitles as $jobTitle)
                        <tr
                            wire:key="job-title-row-{{ $jobTitle->id }}"
                            class="hover:bg-gray-50 transition-colors border-b border-gray-200"
                        >
                            <td class="py-4 px-4 align-top whitespace-nowrap">
                                <div class="text-sm font-bold text-gray-900">
                                    {{ $jobTitle->name }}
                                </div>
                            </td>

                            <td class="py-4 px-6 align-top whitespace-nowrap">
                                <div>
                                    <span class="text-xs font-bold font-mono text-gray-500 px-2 py-0.5 bg-gray-50 border border-gray-100 rounded-lg">
                                        {{ $jobTitle->code ?? '-' }}
                                    </span>
                                </div>
                            </td>

                            <td class="py-4 px-6 align-top whitespace-nowrap">
                                <button
                                    wire:click="$dispatch('open-employees-modal', { type: 'job-title', id: {{ $jobTitle->id }} })"
                                    class="text-sm font-semibold text-[color:var(--brand-via)] hover:text-[color:var(--brand-via)]/80 hover:underline cursor-pointer"
                                >
                                    {{ (int) ($jobTitle->employees_count ?? 0) }}
                                </button>
                            </td>

                            <td class="py-4 px-6 align-top">
                                @if($jobTitle->description && mb_strlen($jobTitle->description) > 60)
                                    <div x-data="{ expanded: false }" class="text-sm text-gray-500 max-w-xs">
                                        <div :class="expanded ? 'whitespace-normal break-words' : 'line-clamp-2'" class="transition-all duration-300">
                                            {{ $jobTitle->description }}
                                        </div>
                                        <button @click="expanded = !expanded" class="text-xs font-semibold text-[color:var(--brand-via)] hover:underline mt-1 focus:outline-none">
                                            <span x-show="!expanded">{{ tr('Read more') }}</span>
                                            <span x-show="expanded" x-cloak>{{ tr('Show less') }}</span>
                                        </button>
                                    </div>
                                @else
                                    <div class="text-sm text-gray-500 max-w-xs whitespace-normal break-words">
                                        {{ $jobTitle->description ?? '-' }}
                                    </div>
                                @endif
                            </td>

                            <td class="py-4 px-6 align-top whitespace-nowrap">
                                <div>
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $jobTitle->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                        {{ $jobTitle->is_active ? tr('Active') : tr('Inactive') }}
                                    </span>
                                </div>
                            </td>

                            <td class="py-4 px-6 align-top whitespace-nowrap text-sm font-medium">
                                @can('settings.organizational.manage')
                                    <x-ui.actions-menu>
                                        <x-ui.dropdown-item
                                            wire:click="openEditModal({{ $jobTitle->id }})"
                                            @click="$dispatch('close-actions-menu')"
                                        >
                                            <i class="fas fa-edit me-2"></i>
                                            <span>{{ tr('Edit') }}</span>
                                        </x-ui.dropdown-item>

                                        <x-ui.dropdown-item
                                            wire:click="toggleActive({{ $jobTitle->id }})"
                                            @click="$dispatch('close-actions-menu')"
                                        >
                                            <i class="fas fa-{{ $jobTitle->is_active ? 'eye-slash' : 'eye' }} me-2"></i>
                                            <span>{{ $jobTitle->is_active ? tr('Deactivate') : tr('Activate') }}</span>
                                        </x-ui.dropdown-item>

                                        <x-ui.dropdown-item
                                            @click="$dispatch('open-confirm-delete-job-title', { id: {{ $jobTitle->id }} }); $dispatch('close-actions-menu')"
                                            danger
                                        >
                                            <i class="fas fa-trash me-2"></i>
                                            <span>{{ tr('Delete') }}</span>
                                        </x-ui.dropdown-item>
                                    </x-ui.actions-menu>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </x-ui.table>
            </div>

        @else
            <div class="p-12 text-center">
                <i class="fas fa-briefcase text-gray-300 text-5xl mb-4"></i>
                <p class="text-gray-500 text-lg mb-2">{{ tr('No job titles found') }}</p>
                <p class="text-gray-400 text-sm">{{ tr('Click "Add Job Title" to create your first job title') }}</p>
            </div>
        @endif
    </x-ui.card>

    {{-- Add/Edit Modal --}}
    <x-ui.modal wire:model="showModal" maxWidth="4xl">
        <x-slot:title>
            <div class="space-y-1">
                <div>{{ $editingId ? tr('Edit Job Title') : tr('Add Job Title') }}</div>
                <div class="text-xs text-gray-500 mt-0.5 font-normal">
                    {{ $editingId ? tr('Update job title information') : tr('Create a new job title') }}
                </div>
            </div>
        </x-slot:title>

        <x-slot:icon>
            <i class="fas fa-briefcase text-white text-lg"></i>
        </x-slot:icon>

        <x-slot:content>
            <form wire:submit.prevent="save" id="job-title-form">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- Left Column --}}
                    <div class="space-y-5">
                        {{-- Name --}}
                        <x-ui.input
                            label="{{ tr('Job Title Name') }}"
                            name="name"
                            wire:model="name"
                            placeholder="{{ tr('Enter job title name') }}"
                            required
                            :disabled="!auth()->user()->can('settings.organizational.manage')"
                        />

                        {{-- Is Active --}}
                        <div class="flex items-center gap-3 p-4 bg-gray-50 rounded-xl border border-gray-200">
                            <input
                                type="checkbox"
                                wire:model="is_active"
                                id="is_active_job"
                                class="w-5 h-5 text-[color:var(--brand-via)] border-gray-300 rounded focus:ring-[color:var(--brand-via)] focus:ring-2 disabled:opacity-50"
                                @cannot('settings.organizational.manage') disabled @endcannot
                            />
                            <label for="is_active_job" class="text-sm font-semibold text-gray-700 cursor-pointer">
                                {{ tr('Active') }}
                                <span class="text-xs text-gray-500 font-normal ms-1">({{ tr('Job title will be available for selection') }})</span>
                            </label>
                        </div>

                        {{-- Code --}}
                        <x-ui.input
                            label="{{ tr('Job Code') }}"
                            name="code"
                            wire:model="code"
                            placeholder="{{ tr('Enter unique job code') }}"
                            hint="{{ tr('Will be used for matching in imports') }}"
                            :disabled="!auth()->user()->can('settings.organizational.manage')"
                        />
                    </div>

                    {{-- Right Column --}}
                    <div class="space-y-5">
                        {{-- Description --}}
                        <x-ui.textarea
                            label="{{ tr('Description') }}"
                            name="description"
                            wire:model="description"
                            placeholder="{{ tr('Enter job title description') }}"
                            hint="{{ tr('Optional') }}"
                            :rows="8"
                            :disabled="!auth()->user()->can('settings.organizational.manage')"
                        />
                    </div>
                </div>
            </form>
        </x-slot:content>

        <x-slot:footer>
            <x-ui.secondary-button type="button" wire:click="closeModal" class="cursor-pointer">
                {{ tr('Cancel') }}
            </x-ui.secondary-button>

            @can('settings.organizational.manage')
            <x-ui.primary-button type="submit" form="job-title-form" :fullWidth="false" class="cursor-pointer" loading="save">
                <i class="fas fa-save me-2"></i>
                {{ tr('Save') }}
            </x-ui.primary-button>
            @endcan
        </x-slot:footer>
    </x-ui.modal>

    {{-- Deletion Confirmation Dialog --}}
    <x-ui.confirm-dialog
        id="delete-job-title"
        type="danger"
        :title="tr('Delete Job Title')"
        :message="tr('Are you sure you want to delete this job title? This action cannot be undone.')"
        :confirmText="tr('Yes, Delete')"
        confirmAction="wire:delete(__ID__)"
    />
</div>
