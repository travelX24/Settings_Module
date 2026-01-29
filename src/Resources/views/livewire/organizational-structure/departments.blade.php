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
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 flex items-center gap-4 group hover:border-[color:var(--brand-via)]/30 transition-all duration-300">
            <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center text-blue-600 group-hover:bg-blue-600 group-hover:text-white transition-all duration-500 shadow-sm">
                <i class="fas fa-building text-xl"></i>
            </div>
            <div>
                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">{{ tr('Total Departments') }}</p>
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

        {{-- Hierarchy --}}
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 flex items-center justify-around group hover:border-[color:var(--brand-via)]/30 transition-all duration-300">
            <div class="text-center">
                <p class="text-[9px] font-bold text-amber-600 uppercase">{{ tr('Root') }}</p>
                <p class="text-lg font-black text-gray-900">{{ $stats['root'] ?? 0 }}</p>
            </div>
            <div class="w-px h-8 bg-gray-100"></div>
            <div class="text-center">
                <p class="text-[9px] font-bold text-blue-600 uppercase">{{ tr('Sub') }}</p>
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
                                   focus:outline-none focus:ring-2 focus:ring-[color:var(--brand-via)]/20 focus:border-[color:var(--brand-via)]
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
                {{-- View Toggle (Cards / List) --}}
                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        @click="setView('cards')"
                        :class="view === 'cards'
                            ? 'bg-amber-500 border-amber-500 text-white shadow-sm'
                            : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'"
                        class="cursor-pointer w-10 h-10 inline-flex items-center justify-center rounded-xl border transition"
                        title="{{ tr('Cards View') }}"
                    >
                    <i class="fas fa-table-cells"></i>

                    </button>

                    <button
                        type="button"
                        @click="setView('table')"
                        :class="view === 'table'
                            ? 'bg-amber-500 border-amber-500 text-white shadow-sm'
                            : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'"
                        class="cursor-pointer w-10 h-10 inline-flex items-center justify-center rounded-xl border transition"
                        title="{{ tr('Table View') }}"
                    >
                    <i class="fas fa-list"></i>
                </button>
                </div>

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
                                </div>
                            </div>

                            <div class="mt-4 space-y-2">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-500">{{ tr('Manager') }}</span>
                                    <span class="font-semibold text-gray-900">
                                        {{ $department->manager ? ($department->manager->name_ar ?? $department->manager->name_en) : '-' }}
                                    </span>
                                </div>

                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-500">{{ tr('Employees') }}</span>
                                    <button
                                        wire:click="$dispatch('open-employees-modal', { type: 'department', id: {{ $department->id }} })"
                                        class="font-bold text-[color:var(--brand-via)] hover:text-[color:var(--brand-via)]/80 hover:underline"
                                    >
                                        {{ $department->employees_count_display ?? 0 }}
                                    </button>
                                </div>

                                <div class="text-sm">
                                    <div class="text-gray-500 mb-1">{{ tr('Description') }}</div>
                                    <div class="text-gray-700 line-clamp-2">
                                        {{ $department->description ?? '-' }}
                                    </div>
                                </div>

                                <div class="pt-2">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $department->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                        {{ $department->is_active ? tr('Active') : tr('Inactive') }}
                                    </span>
                                </div>
                            </div>
                        </x-ui.card>
                    @endforeach
                </div>

                {{-- Pagination for Cards View --}}
                <div class="mt-6 border-t border-gray-200 pt-4">
                    {{ $departments->onEachSide(1)->links() }}
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
                    :enablePagination="true"
                >
                    @foreach($departments as $department)
                        <tr
                            wire:key="department-{{ $department->id }}"
                            class="hover:bg-gray-50 transition-colors border-b border-gray-200"
                        >
                            <td class="py-3 px-4">
                                <div class="text-sm font-medium text-gray-900">{{ $department->name }}</div>
                                @if($department->parent)
                                    <div class="text-xs text-gray-500">{{ tr('Sub of') }}: {{ $department->parent->name }}</div>
                                @endif
                            </td>

                            <td class="py-3 px-4 whitespace-nowrap">
                                <span class="text-sm text-gray-900">{{ $department->code ?? '-' }}</span>
                            </td>

                            <td class="py-3 px-4 whitespace-nowrap">
                                <span class="text-sm text-gray-900">{{ $department->manager ? ($department->manager->name_ar ?? $department->manager->name_en) : '-' }}</span>
                            </td>

                            <td class="py-3 px-4 whitespace-nowrap">
                                <button
                                    wire:click="$dispatch('open-employees-modal', { type: 'department', id: {{ $department->id }} })"
                                    class="text-sm font-semibold text-[color:var(--brand-via)] hover:text-[color:var(--brand-via)]/80 hover:underline cursor-pointer"
                                >
                                    {{ $department->employees_count_display ?? 0 }}
                                </button>
                            </td>

                            <td class="py-3 px-4">
                                <div class="text-sm text-gray-500 max-w-xs truncate">{{ $department->description ?? '-' }}</div>
                            </td>

                            <td class="py-3 px-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $department->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                    {{ $department->is_active ? tr('Active') : tr('Inactive') }}
                                </span>
                            </td>

                            <td class="py-3 px-4 whitespace-nowrap text-sm font-medium">
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
                            </td>
                        </tr>
                    @endforeach
                </x-ui.table>
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
        <div
            x-data="{ open: @entangle('showModal') }"
            x-show="open"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
            style="z-index: 9999;"
            @click.self="$wire.closeModal()"
            @keydown.escape.window="$wire.closeModal()"
            wire:key="modal-overlay-{{ $showModal ? 'open' : 'closed' }}"
        >
            <div
                @click.stop
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                x-transition:leave-end="opacity-0 scale-95 translate-y-4"
                class="bg-white rounded-2xl shadow-2xl max-w-5xl w-full overflow-hidden flex flex-col"
            >
                {{-- Header --}}
                <div class="p-6 border-b border-gray-200 bg-gradient-to-r from-[color:var(--brand-from)]/5 via-[color:var(--brand-via)]/5 to-[color:var(--brand-to)]/5">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-r from-[color:var(--brand-from)] via-[color:var(--brand-via)] to-[color:var(--brand-to)] flex items-center justify-center">
                                <i class="fas fa-building text-white text-lg"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-gray-900">
                                    {{ $editingId ? tr('Edit Department') : tr('Add Department') }}
                                </h3>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    {{ $editingId ? tr('Update department information') : tr('Create a new department') }}
                                </p>
                            </div>
                        </div>
                        <button
                            wire:click="closeModal"
                            class="cursor-pointer w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors"
                        >
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>

                {{-- Form Content --}}
                <form wire:submit.prevent="save" class="flex flex-col">
                    <div class="p-6">
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
                                />

                                {{-- Code --}}
                                <x-ui.input
                                    label="{{ tr('Code') }}"
                                    name="code"
                                    wire:model="code"
                                    placeholder="{{ tr('e.g., MKT') }}"
                                    hint="{{ tr('Optional') }}"
                                    maxlength="10"
                                />

                                {{-- Manager (Searchable Dropdown) --}}
                                <div
                                    x-data="searchableSelect({
                                        value: @entangle('manager_id').live,
                                        options: @js($managers),
                                        placeholder: @js(tr('Select Manager')),
                                        searchPlaceholder: @js(tr('Search...')),
                                        getLabel: (o) => (o.name ?? '') + (o.email ? ' (' + o.email + ')' : ''),
                                    })"
                                    class="space-y-2"
                                >
                                    <div class="flex items-center justify-between">
                                        <label class="block text-sm font-semibold text-gray-700">{{ tr('Manager') }}</label>
                                        <span class="text-xs text-gray-400">{{ tr('Optional') }}</span>
                                    </div>

                                    <div class="relative">
                                        <button
                                            type="button"
                                            @click="toggle()"
                                            class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm shadow-sm
                                                   focus:outline-none focus:ring-2 focus:ring-[color:var(--brand-via)]/20 focus:border-[color:var(--brand-via)]
                                                   transition flex items-center justify-between"
                                        >
                                            <span
                                                class="truncate"
                                                :class="selectedLabel() ? 'text-gray-900' : 'text-gray-400'"
                                                x-text="selectedLabel() || placeholder"
                                            ></span>

                                            <i class="fas fa-chevron-down text-xs text-gray-400 transition"
                                               :class="open ? 'rotate-180' : ''"></i>
                                        </button>

                                        {{-- Dropdown Panel: OPEN UP --}}
                                        <div
                                            x-show="open"
                                            x-transition
                                            @click.away="close()"
                                            @keydown.escape.window="close()"
                                            class="absolute z-[10000] w-full rounded-xl border border-gray-200 bg-white shadow-xl overflow-hidden"
                                            :class="direction === 'up' ? 'bottom-full mb-2' : 'top-full mt-2'"
                                            style="display:none;"
                                        >
                                            {{-- Search Row --}}
                                            <div class="p-3 border-b border-gray-100 bg-white">
                                                <div class="relative">
                                                    <span class="absolute inset-y-0 start-3 flex items-center pointer-events-none">
                                                        <i class="fas fa-search text-gray-400 text-sm"></i>
                                                    </span>
                                                    <input
                                                        x-ref="search"
                                                        type="search"
                                                        x-model="search"
                                                        :placeholder="searchPlaceholder"
                                                        class="w-full rounded-lg border border-gray-200 bg-white px-4 py-2 ps-10 text-sm
                                                               focus:outline-none focus:ring-2 focus:ring-[color:var(--brand-via)]/20 focus:border-[color:var(--brand-via)]"
                                                    />
                                                </div>
                                            </div>

                                            {{-- Items --}}
                                            <div class="max-h-56 overflow-y-auto">
                                                <template x-if="filtered().length === 0">
                                                    <div class="p-4 text-sm text-gray-500">{{ tr('No results found') }}</div>
                                                </template>

                                                <template x-for="opt in filtered()" :key="opt.id">
                                                    <button
                                                        type="button"
                                                        @click="choose(opt)"
                                                        class="w-full text-start px-4 py-2.5 hover:bg-gray-50 transition flex items-center justify-between"
                                                    >
                                                        <span class="text-sm text-gray-900" x-text="getLabel(opt)"></span>
                                                        <i class="fas fa-check text-xs text-[color:var(--brand-via)]"
                                                           x-show="String(value) === String(opt.id)"></i>
                                                    </button>
                                                </template>
                                            </div>

                                            {{-- Footer --}}
                                            <div class="p-2 border-t border-gray-100 flex items-center justify-between bg-white">
                                                <button type="button" @click="clear()" class="text-xs text-gray-500 hover:text-gray-700">
                                                    {{ tr('Clear') }}
                                                </button>
                                                <button type="button" @click="close()" class="text-xs text-gray-500 hover:text-gray-700">
                                                    {{ tr('Close') }}
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    @error('manager_id')
                                        <div class="text-xs text-red-600 font-semibold">{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Parent Department (Searchable Dropdown) --}}
                                <div
                                    x-data="searchableSelect({
                                        value: @entangle('parent_id').live,
                                        options: @js($parentDepartments),
                                        placeholder: @js(tr('Select Parent Department')),
                                        searchPlaceholder: @js(tr('Search...')),
                                        getLabel: (o) => (o.name ?? ''),
                                    })"
                                    class="space-y-2"
                                >
                                    <div class="flex items-center justify-between">
                                        <label class="block text-sm font-semibold text-gray-700">{{ tr('Parent Department') }}</label>
                                        <span class="text-xs text-gray-400">{{ tr('Optional') }}</span>
                                    </div>

                                    <div class="relative">
                                        <button
                                            type="button"
                                            @click="toggle()"
                                            class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm shadow-sm
                                                   focus:outline-none focus:ring-2 focus:ring-[color:var(--brand-via)]/20 focus:border-[color:var(--brand-via)]
                                                   transition flex items-center justify-between"
                                        >
                                            <span
                                                class="truncate"
                                                :class="selectedLabel() ? 'text-gray-900' : 'text-gray-400'"
                                                x-text="selectedLabel() || placeholder"
                                            ></span>

                                            <i class="fas fa-chevron-down text-xs text-gray-400 transition"
                                               :class="open ? 'rotate-180' : ''"></i>
                                        </button>

                                        {{-- Dropdown Panel: OPEN UP --}}
                                        <div
                                            x-show="open"
                                            x-transition
                                            @click.away="close()"
                                            @keydown.escape.window="close()"
                                            class="absolute z-[10000] w-full rounded-xl border border-gray-200 bg-white shadow-xl overflow-hidden"
                                            :class="direction === 'up' ? 'bottom-full mb-2' : 'top-full mt-2'"
                                            style="display:none;"
                                        >
                                            <div class="p-3 border-b border-gray-100 bg-white">
                                                <div class="relative">
                                                    <span class="absolute inset-y-0 start-3 flex items-center pointer-events-none">
                                                        <i class="fas fa-search text-gray-400 text-sm"></i>
                                                    </span>
                                                    <input
                                                        x-ref="search"
                                                        type="search"
                                                        x-model="search"
                                                        :placeholder="searchPlaceholder"
                                                        class="w-full rounded-lg border border-gray-200 bg-white px-4 py-2 ps-10 text-sm
                                                               focus:outline-none focus:ring-2 focus:ring-[color:var(--brand-via)]/20 focus:border-[color:var(--brand-via)]"
                                                    />
                                                </div>
                                            </div>

                                            <div class="max-h-56 overflow-y-auto">
                                                <template x-if="filtered().length === 0">
                                                    <div class="p-4 text-sm text-gray-500">{{ tr('No results found') }}</div>
                                                </template>

                                                <template x-for="opt in filtered()" :key="opt.id">
                                                    <button
                                                        type="button"
                                                        @click="choose(opt)"
                                                        class="w-full text-start px-4 py-2.5 hover:bg-gray-50 transition flex items-center justify-between"
                                                    >
                                                        <span class="text-sm text-gray-900" x-text="getLabel(opt)"></span>
                                                        <i class="fas fa-check text-xs text-[color:var(--brand-via)]"
                                                           x-show="String(value) === String(opt.id)"></i>
                                                    </button>
                                                </template>
                                            </div>

                                            <div class="p-2 border-t border-gray-100 flex items-center justify-between bg-white">
                                                <button type="button" @click="clear()" class="text-xs text-gray-500 hover:text-gray-700">
                                                    {{ tr('Clear') }}
                                                </button>
                                                <button type="button" @click="close()" class="text-xs text-gray-500 hover:text-gray-700">
                                                    {{ tr('Close') }}
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    @error('parent_id')
                                        <div class="text-xs text-red-600 font-semibold">{{ $message }}</div>
                                    @enderror
                                </div>
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
                                />

                                {{-- Is Active --}}
                                <div class="flex items-center gap-3 p-4 bg-gray-50 rounded-xl border border-gray-200">
                                    <input
                                        type="checkbox"
                                        wire:model="is_active"
                                        id="is_active"
                                        class="w-5 h-5 text-[color:var(--brand-via)] border-gray-300 rounded focus:ring-[color:var(--brand-via)] focus:ring-2"
                                    />
                                    <label for="is_active" class="text-sm font-semibold text-gray-700 cursor-pointer">
                                        {{ tr('Active') }}
                                        <span class="text-xs text-gray-500 font-normal ms-1">({{ tr('Department will be available for selection') }})</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Footer Actions --}}
                    <div class="p-6 border-t border-gray-200 bg-gray-50">
                        <div class="flex items-center justify-end gap-3">
                            <x-ui.secondary-button type="button" wire:click="closeModal" class="cursor-pointer">
                                {{ tr('Cancel') }}
                            </x-ui.secondary-button>

                            <x-ui.primary-button type="submit" :fullWidth="false" class="cursor-pointer">
                                <i class="fas fa-save me-2"></i>
                                {{ tr('Save') }}
                            </x-ui.primary-button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
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

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('searchableSelect', (cfg) => ({
        open: false,
        search: '',
        value: cfg.value,
        options: Array.isArray(cfg.options) ? cfg.options : [],
        placeholder: cfg.placeholder || 'Select',
        searchPlaceholder: cfg.searchPlaceholder || 'Search...',
        getLabel: cfg.getLabel || ((o) => (o?.name ?? '')),

        // ✅ always open UP
        direction: 'up',

        toggle() {
            this.open = !this.open;
            if (this.open) {
                this.direction = 'up';
                this.$nextTick(() => this.$refs.search?.focus());
            }
        },
        close() {
            this.open = false;
            this.search = '';
        },
        clear() {
            this.value = null;
            this.close();
        },
        selected() {
            return this.options.find(o => String(o.id) === String(this.value));
        },
        selectedLabel() {
            const s = this.selected();
            return s ? this.getLabel(s) : '';
        },
        filtered() {
            const q = (this.search || '').trim().toLowerCase();
            if (!q) return this.options;

            return this.options.filter(o => {
                const label = (this.getLabel(o) || '').toLowerCase();
                return label.includes(q);
            });
        },
        choose(opt) {
            this.value = opt?.id ?? null;
            this.close();
        },
    }));
});
</script>
@endpush
