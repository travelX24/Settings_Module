<div
    x-data="{
        view: (localStorage.getItem('org_dept_view') || 'table'),
        setView(v){
            this.view = v;
            localStorage.setItem('org_dept_view', v);
        }
    }"
    x-init="localStorage.setItem('org_dept_view', view)"
    @open-add-department-modal.window="$wire.openAddModal()"
>
    {{-- Statistics Bar --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
        {{-- Total Departments --}}
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 flex items-center gap-4 group hover:border-[color:var(--accent-orange)]/30 transition-all duration-300">
            <div class="w-12 h-12 bg-[color:var(--accent-orange)]/10 rounded-xl flex items-center justify-center text-[color:var(--accent-orange)] group-hover:bg-[color:var(--accent-orange)] group-hover:text-white transition-all duration-500 shadow-sm">
                <i class="fas fa-building text-xl"></i>
            </div>
            <div>
                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">{{ tr('Total Departments') }}</p>
                <h3 class="text-xl font-black text-gray-900 leading-none mt-1">{{ $stats['total'] ?? 0 }}</h3>
            </div>
        </div>

        {{-- Status Summary --}}
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 flex items-center justify-around group hover:border-[color:var(--accent-orange)]/30 transition-all duration-300">
            <div class="text-center">
                <p class="text-[9px] font-bold text-[color:var(--success)] uppercase">{{ tr('Active') }}</p>
                <p class="text-lg font-black text-gray-900">{{ $stats['active'] ?? 0 }}</p>
            </div>
            <div class="w-px h-8 bg-gray-100"></div>
            <div class="text-center">
                <p class="text-[9px] font-bold text-gray-400 uppercase">{{ tr('Inactive') }}</p>
                <p class="text-lg font-black text-gray-400">{{ $stats['inactive'] ?? 0 }}</p>
            </div>
        </div>

        {{-- Hierarchy --}}
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 flex items-center justify-around group hover:border-[color:var(--accent-orange)]/30 transition-all duration-300">
            <div class="text-center">
                <p class="text-[9px] font-bold text-[color:var(--warning)] uppercase">{{ tr('Root') }}</p>
                <p class="text-lg font-black text-gray-900">{{ $stats['root'] ?? 0 }}</p>
            </div>
            <div class="w-px h-8 bg-gray-100"></div>
            <div class="text-center">
                <p class="text-[9px] font-bold text-[color:var(--accent-orange)] uppercase">{{ tr('Sub') }}</p>
                <p class="text-lg font-black text-gray-900">{{ $stats['sub'] ?? 0 }}</p>
            </div>
        </div>
    </div>

    {{-- Search and Actions --}}
    <x-ui.card :padding="false" class="border-gray-200 p-4 mb-6">
        <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between">
            <div class="flex-1 w-full sm:w-auto">
                <div class="flex flex-col sm:flex-row gap-3">
                    <div class="flex-1 relative">
                        <div class="absolute inset-y-0 start-0 flex items-center ps-4 pointer-events-none">
                            <i class="fas fa-search w-5 h-5 text-gray-400"></i>
                        </div>

                        <input
                            type="search"
                            wire:model.live.debounce.300ms="search"
                            placeholder="{{ tr('Search departments...') }}"
                            class="w-full px-4 py-2.5 ps-10 text-sm rounded-xl border border-gray-200 bg-white shadow-sm placeholder-gray-400 text-gray-900
                                   focus:outline-none focus:ring-2 focus:ring-[color:var(--accent-orange)]/20 focus:border-[color:var(--accent-orange)]
                                   transition"
                        />
                    </div>

                    <x-ui.filter-select
                        model="rootDepartmentId"
                        :placeholder="tr('Department')"
                        :options="$rootDepartments ?? []"
                        width="md"
                        :defer="false"
                        allValue="all"
                    />
                </div>
            </div>

            <div class="flex items-center gap-2">
                {{-- Clear Filters Button --}}
                <div
                    x-data="{
                        hasFilters() {
                            return ($wire.search && $wire.search.trim() !== '') ||
                                   ($wire.rootDepartmentId && $wire.rootDepartmentId !== 'all');
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

        @if($departments->count() > 0)

            {{-- ✅ Cards View --}}
            <div x-show="view === 'cards'" x-cloak>
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    @foreach($departments as $department)
                        <x-ui.card
                            wire:key="department-card-{{ $department->id }}"
                            :hover="true"
                            :padding="false"
                            class="rounded-2xl border-gray-200 p-4"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="text-sm font-bold text-gray-900 truncate">
                                        {{ $department->name }}
                                    </div>

                                    @if($department->parent)
                                        <div class="text-xs text-gray-500 mt-0.5 truncate">
                                            {{ tr('Sub of') }}: {{ $department->parent->name }}
                                        </div>
                                    @endif
                                </div>

                                <div class="flex items-center gap-2 shrink-0">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-lg border border-gray-200 bg-gray-50 text-gray-700">
                                        {{ $department->code ?? '-' }}
                                    </span>

                                    @can('settings.organizational.manage')
                                        <x-ui.actions-menu>
                                            <x-ui.dropdown-item
                                            class="cursor-pointer"
                                                wire:click="openEditModal({{ $department->id }})"
                                                @click="$dispatch('close-actions-menu')"
                                            >
                                                <i class="fas fa-edit me-2"></i>
                                                <span>{{ tr('Edit') }}</span>
                                            </x-ui.dropdown-item>

                                            <x-ui.dropdown-item
                                                class="cursor-pointer"
                                                wire:click="toggleActive({{ $department->id }})"
                                                @click="$dispatch('close-actions-menu')"
                                            >
                                                <i class="fas fa-{{ $department->is_active ? 'eye-slash' : 'eye' }} me-2"></i>
                                                <span>{{ $department->is_active ? tr('Deactivate') : tr('Activate') }}</span>
                                            </x-ui.dropdown-item>

                                            <x-ui.dropdown-item
                                                class="cursor-pointer"
                                                @click="$dispatch('open-confirm-delete-department', { id: {{ $department->id }} }); $dispatch('close-actions-menu')"
                                                danger
                                            >
                                                <i class="fas fa-trash me-2"></i>
                                                <span>{{ tr('Delete') }}</span>
                                            </x-ui.dropdown-item>
                                        </x-ui.actions-menu>
                                    @endcan
                                </div>
                            </div>

                            <div class="mt-4 space-y-2">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-500">{{ tr('Manager') }}</span>
                                    <span class="font-semibold text-gray-900">
                                        @if($department->manager)
                                            {{ app()->getLocale() === 'ar' 
                                                ? ($department->manager->name_ar ?? $department->manager->name_en) 
                                                : ($department->manager->name_en ?? $department->manager->name_ar) 
                                            }}
                                        @else
                                            -
                                        @endif
                                    </span>
                                </div>

                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-500">{{ tr('Employees') }}</span>
                                    <button
                                        wire:click="$dispatch('open-employees-modal', { type: 'department', id: {{ $department->id }} })"
                                        class="font-bold text-[color:var(--accent-orange)] hover:text-[color:var(--accent-orange-hover)] hover:underline"
                                    >
                                        {{ $department->employees_count_display ?? 0 }}
                                    </button>
                                </div>

                                <div class="text-sm">
                                    <div class="text-gray-500 mb-1">{{ tr('Description') }}</div>
                                    @if($department->description && mb_strlen($department->description) > 60)
                                        <div x-data="{ expanded: false }" class="text-gray-700 w-full">
                                            <div :class="expanded ? 'whitespace-normal break-words' : 'line-clamp-2'" class="transition-all duration-300">
                                                {{ $department->description }}
                                            </div>
                                            <button @click="expanded = !expanded" class="text-xs font-semibold text-[color:var(--accent-orange)] hover:underline mt-1 focus:outline-none">
                                                <span x-show="!expanded">{{ tr('Read more') }}</span>
                                                <span x-show="expanded" x-cloak>{{ tr('Show less') }}</span>
                                            </button>
                                        </div>
                                    @else
                                        <div class="text-gray-700 whitespace-normal break-words">
                                            {{ $department->description ?? '-' }}
                                        </div>
                                    @endif
                                </div>

                                <div class="pt-2">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $department->is_active ? 'bg-[color:var(--success)]/10 text-[color:var(--success)]' : 'bg-gray-100 text-gray-800' }}">
                                        {{ $department->is_active ? tr('Active') : tr('Inactive') }}
                                    </span>
                                </div>
                            </div>
                        </x-ui.card>
                    @endforeach
                </div>
                
            </div>

            {{-- ✅ Table/List View --}}
            <div x-show="view === 'table'" x-cloak>
                <x-ui.table
                    :headers="[
                        tr('Department Name'),
                        tr('Code'),
                        tr('Manager'),
                        tr('Employees'),
                        tr('Description'),
                        tr('Status'),
                        tr('Actions')
                    ]"
                    :perPage="10"
                    :enablePagination="false"
                >
                    @foreach($departments as $department)
                        <tr
                            wire:key="department-{{ $department->id }}"
                            class="hover:bg-gray-50 transition-colors border-b border-gray-200"
                        >
                            <td class="py-4 px-4 align-top">
                                <div class="space-y-1">
                                    <div class="text-sm font-bold text-gray-900">
                                        {{ $department->name }}
                                    </div>

                                    @if($department->parent)
                                        <div class="text-xs text-gray-500">
                                            {{ tr('Sub of') }}: {{ $department->parent->name }}
                                        </div>
                                    @endif
                                </div>
                            </td>

                            <td class="py-4 px-6 align-top whitespace-nowrap">
                                <div>
                                    <span class="text-xs font-bold font-mono text-gray-500 px-2 py-0.5 bg-gray-50 border border-gray-100 rounded-lg">
                                        {{ $department->code ?? '-' }}
                                    </span>
                                </div>
                            </td>

                            <td class="py-4 px-6 align-top whitespace-nowrap">
                                <div class="text-sm text-gray-900 border-b border-dotted border-gray-100 pb-1">
                                    @if($department->manager)
                                        {{ app()->getLocale() === 'ar' 
                                            ? ($department->manager->name_ar ?? $department->manager->name_en) 
                                            : ($department->manager->name_en ?? $department->manager->name_ar) 
                                        }}
                                    @else
                                        -
                                    @endif
                                </div>
                            </td>

                            <td class="py-4 px-6 align-top whitespace-nowrap">
                                <button
                                    wire:click="$dispatch('open-employees-modal', { type: 'department', id: {{ $department->id }} })"
                                    class="text-sm font-semibold text-[color:var(--accent-orange)] hover:text-[color:var(--accent-orange-hover)] hover:underline cursor-pointer"
                                >
                                    {{ $department->employees_count_display ?? 0 }}
                                </button>
                            </td>

                            <td class="py-4 px-6 align-top">
                                @if($department->description && mb_strlen($department->description) > 60)
                                    <div x-data="{ expanded: false }" class="text-sm text-gray-500 max-w-xs">
                                        <div :class="expanded ? 'whitespace-normal break-words' : 'line-clamp-2'" class="transition-all duration-300">
                                            {{ $department->description }}
                                        </div>
                                        <button @click="expanded = !expanded" class="text-xs font-semibold text-[color:var(--accent-orange)] hover:underline mt-1 focus:outline-none">
                                            <span x-show="!expanded">{{ tr('Read more') }}</span>
                                            <span x-show="expanded" x-cloak>{{ tr('Show less') }}</span>
                                        </button>
                                    </div>
                                @else
                                    <div class="text-sm text-gray-500 max-w-xs whitespace-normal break-words">
                                        {{ $department->description ?? '-' }}
                                    </div>
                                @endif
                            </td>

                            <td class="py-4 px-6 align-top whitespace-nowrap">
                                <div>
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $department->is_active ? 'bg-[color:var(--success)]/10 text-[color:var(--success)]' : 'bg-gray-100 text-gray-800' }}">
                                        {{ $department->is_active ? tr('Active') : tr('Inactive') }}
                                    </span>
                                </div>
                            </td>

                            <td class="py-4 px-6 align-top whitespace-nowrap text-sm font-medium">
                                @can('settings.organizational.manage')
                                    <x-ui.actions-menu>
                                        <x-ui.dropdown-item
                                            wire:click="openEditModal({{ $department->id }})"
                                            @click="$dispatch('close-actions-menu')"
                                        >
                                            <i class="fas fa-edit me-2"></i>
                                            <span>{{ tr('Edit') }}</span>
                                        </x-ui.dropdown-item>

                                        <x-ui.dropdown-item
                                            wire:click="toggleActive({{ $department->id }})"
                                            @click="$dispatch('close-actions-menu')"
                                        >
                                            <i class="fas fa-{{ $department->is_active ? 'eye-slash' : 'eye' }} me-2"></i>
                                            <span>{{ $department->is_active ? tr('Deactivate') : tr('Activate') }}</span>
                                        </x-ui.dropdown-item>

                                        <x-ui.dropdown-item
                                            @click="$dispatch('open-confirm-delete-department', { id: {{ $department->id }} }); $dispatch('close-actions-menu')"
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

            {{-- Server-Side Pagination (Applied to both views - Card 232 Design) --}}
            @php
                $currentPage = $departments->currentPage();
                $lastPage = $departments->lastPage();
                $total = $departments->total();
                $from = $departments->firstItem() ?? 0;
                $to = $departments->lastItem() ?? 0;

                $pages = [];

                if ($lastPage <= 7) {
                    $pages = range(1, $lastPage);
                } else {
                    $pages[] = 1;

                    $start = max(2, $currentPage - 1);
                    $end = min($lastPage - 1, $currentPage + 1);

                    if ($start > 2) {
                        $pages[] = '...';
                    }

                    for ($page = $start; $page <= $end; $page++) {
                        $pages[] = $page;
                    }

                    if ($end < $lastPage - 1) {
                        $pages[] = '...';
                    }

                    $pages[] = $lastPage;
                }

                $prevDisabled = $currentPage <= 1;
                $nextDisabled = $currentPage >= $lastPage;
                
                $locale = app()->getLocale();
                $isRtl = in_array(substr($locale, 0, 2), ['ar', 'fa', 'ur', 'he']);
            @endphp

            <div class="mt-6 border-t border-gray-200 pt-4">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="text-sm text-gray-600">
                        <span>
                            {{ tr('Showing') }}
                            <span class="font-semibold">{{ $from }}</span>
                            {{ tr('to') }}
                            <span class="font-semibold">{{ $to }}</span>
                            {{ tr('of') }}
                            <span class="font-semibold">{{ $total }}</span>
                            {{ tr('results') }}
                        </span>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            wire:click="previousPage('page')"
                            @disabled($prevDisabled)
                            class="px-4 py-2 text-sm font-medium bg-white border border-gray-300 rounded-md transition-colors duration-200 {{ $prevDisabled ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-100 cursor-pointer' }}"
                        >
                            <i class="fas fa-chevron-{{ $isRtl ? 'right' : 'left' }} me-1"></i>
                            <span>{{ tr('Previous') }}</span>
                        </button>

                        <div class="flex items-center gap-1">
                            @foreach($pages as $page)
                                @if($page === '...')
                                    <span class="px-2 text-sm text-gray-400">…</span>
                                @else
                                    <button
                                        type="button"
                                        wire:key="departments-page-{{ $page }}"
                                        wire:click="gotoPage({{ $page }}, 'page')"
                                        class="px-3 py-2 text-sm font-medium border rounded-md transition-colors duration-200 min-w-[40px] {{ $currentPage === $page ? 'bg-[color:var(--accent-orange)] text-white border-[color:var(--accent-orange)]' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50 cursor-pointer' }}"
                                    >
                                        {{ $page }}
                                    </button>
                                @endif
                            @endforeach
                        </div>

                        <button
                            type="button"
                            wire:click="nextPage('page')"
                            @disabled($nextDisabled)
                            class="px-4 py-2 text-sm font-medium bg-white border border-gray-300 rounded-md transition-colors duration-200 {{ $nextDisabled ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-100 cursor-pointer' }}"
                        >
                            <span>{{ tr('Next') }}</span>
                            <i class="fas fa-chevron-{{ $isRtl ? 'left' : 'right' }} ms-1"></i>
                        </button>
                    </div>
                </div>
            </div>

        @else
            <div class="p-12 text-center">
                <i class="fas fa-building text-gray-300 text-5xl mb-4"></i>
                <p class="text-gray-500 text-lg mb-2">{{ tr('No departments found') }}</p>
                <p class="text-gray-400 text-sm">{{ tr('Click "Add Department" to create your first department') }}</p>
            </div>
        @endif
    </x-ui.card>

    {{-- Add/Edit Modal --}}
    @if($showModal)
    <x-ui.modal wire:model="showModal" maxWidth="5xl">
        <x-slot:title>
            <div class="space-y-1">
                <div>{{ $editingId ? tr('Edit Department') : tr('Add Department') }}</div>
                <div class="text-xs text-gray-500 mt-0.5 font-normal">
                    {{ $editingId ? tr('Update department information') : tr('Create a new department') }}
                </div>
            </div>
        </x-slot:title>

        <x-slot:icon>
            <i class="fas fa-building text-white text-lg"></i>
        </x-slot:icon>

        <x-slot:content>
            <form wire:submit.prevent="save" id="department-form">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- Left Column --}}
                    <div class="space-y-5">
                        {{-- Name --}}
                        <x-ui.input
                            label="{{ tr('Department Name') }}"
                            name="name"
                            wire:model="name"
                            placeholder="{{ tr('Enter department name') }}"
                            required
                            :disabled="!auth()->user()->can('settings.organizational.manage')"
                        />

                        {{-- Code --}}
                        <x-ui.input
                            label="{{ tr('Code') }}"
                            name="code"
                            wire:model="code"
                            placeholder="{{ tr('e.g., MKT') }}"
                            maxlength="10"
                            required
                            :disabled="!auth()->user()->can('settings.organizational.manage')"
                        />

                        {{-- Manager --}}
                        <x-ui.select
                            label="{{ tr('Manager') }}"
                            name="manager_id"
                            wire:model="manager_id"
                            placeholder="{{ tr('Select Manager') }}"
                            hint="{{ tr('Optional') }}"
                            searchable="true"
                            :disabled="!auth()->user()->can('settings.organizational.manage')"
                        >
                            <option value="">{{ tr('Select Manager') }}</option>
                            @foreach($managers as $m)
                                <option value="{{ $m['id'] ?? $m->id }}">
                                    {{ $m['name'] ?? $m->name }} {{ isset($m['email']) || isset($m->email) ? '(' . ($m['email'] ?? $m->email) . ')' : '' }}
                                </option>
                            @endforeach
                        </x-ui.select>

                        {{-- Parent Department --}}
                        <x-ui.select
                            label="{{ tr('Parent Department') }}"
                            name="parent_id"
                            wire:model="parent_id"
                            placeholder="{{ tr('Select Parent Department') }}"
                            hint="{{ $hasChildren ? tr('This department cannot have a parent because it already contains sub-departments.') : tr('Select only main departments (Max 2 levels allowed)') }}"
                            searchable="true"
                            align="up"
                            :disabled="!auth()->user()->can('settings.organizational.manage') || $hasChildren"
                        >
                            <option value="">{{ tr('Select Parent Department') }}</option>
                            @foreach($parentDepartments as $pd)
                                <option value="{{ $pd->id }}">{{ $pd->name }}</option>
                            @endforeach
                        </x-ui.select>
                    </div>

                    {{-- Right Column --}}
                    <div class="space-y-5">
                        {{-- Description --}}
                        <x-ui.textarea
                            label="{{ tr('Description') }}"
                            name="description"
                            wire:model="description"
                            placeholder="{{ tr('Enter department description') }}"
                            hint="{{ tr('Optional') }}"
                            :rows="8"
                            :disabled="!auth()->user()->can('settings.organizational.manage')"
                        />

                        {{-- Is Active --}}
                        <div class="flex items-center gap-3 p-4 bg-gray-50 rounded-xl border border-gray-200">
                            <input
                                type="checkbox"
                                wire:model="is_active"
                                id="is_active"
                                class="w-5 h-5 text-[color:var(--accent-orange)] border-gray-300 rounded focus:ring-[color:var(--accent-orange)] focus:ring-2 disabled:opacity-50"
                                @cannot('settings.organizational.manage') disabled @endcannot
                            />
                            <label for="is_active" class="text-sm font-semibold text-gray-700 cursor-pointer">
                                {{ tr('Active') }}
                                <span class="text-xs text-gray-500 font-normal ms-1">({{ tr('Department will be available for selection') }})</span>
                            </label>
                        </div>
                    </div>
                </div>
            </form>
        </x-slot:content>

        <x-slot:footer>
            <x-ui.secondary-button type="button" wire:click="closeModal" class="cursor-pointer">
                {{ tr('Cancel') }}
            </x-ui.secondary-button>

            @can('settings.organizational.manage')
            <x-ui.primary-button type="submit" form="department-form" :fullWidth="false" class="cursor-pointer" loading="save">
                <i class="fas fa-save me-2"></i>
                {{ tr('Save') }}
            </x-ui.primary-button>
            @endcan
        </x-slot:footer>
    </x-ui.modal>
    @endif

    {{-- Deletion Confirmation Dialog --}}
    <x-ui.confirm-dialog
        id="delete-department"
        type="danger"
        :title="tr('Delete Department')"
        :message="tr('Are you sure you want to delete this department? This action cannot be undone.')"
        :confirmText="tr('Yes, Delete')"
        confirmAction="wire:delete(__ID__)"
    />
</div>

