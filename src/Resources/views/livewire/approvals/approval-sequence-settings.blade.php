{{-- Settings_Module/src/Resources/views/livewire/approvals/approval-sequence-settings.blade.php --}}

@php
    $locale = app()->getLocale();
    $isRtl  = in_array(substr($locale, 0, 2), ['ar','fa','ur','he']);
    $dir    = $isRtl ? 'rtl' : 'ltr';

    $headers = [
        tr('Policy Name'),
        tr('Applies To'),
        tr('Approvers Count'),
        tr('Status'),
        tr('Actions'),
    ];

    $headerAlign = ['start', 'start', 'start', 'start', 'end'];

    $list = $employees ?? [];
    if (($scope_type ?? 'all') === 'department') {
        $list = $departments ?? [];
    } elseif (($scope_type ?? 'all') === 'job_title') {
        $list = $jobTitles ?? [];
    } elseif (($scope_type ?? 'all') === 'branch') {
        $list = $branches ?? [];
    }

    $modalTitle = ($editingId ?? null) ? tr('Edit Policy') : tr('Add Policy');
@endphp

{{-- ✅ عنوان الصفحة في التوب بار فقط --}}
@section('topbar-left-content')
    <x-ui.page-header
        :title="tr('Approval Sequence Settings')"
        :subtitle="tr('Set up approval workflows and sequences')"
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

<div class="space-y-6 p-4 sm:p-6" dir="{{ $dir }}">

    <x-ui.flash-toast />

    {{-- ✅ فقط زر الإضافة (بدون تكرار عنوان/وصف داخل الصفحة) --}}
    <div class="flex justify-end">
        <x-ui.primary-button
            type="button"
            wire:click="openCreate"
            class="!w-auto"
        >
            <span class="inline-flex items-center gap-2">
                <i class="fa fa-plus"></i>
                {{ tr('Add Policy') }}
            </span>
        </x-ui.primary-button>
    </div>

    {{-- Tabs --}}
    <x-ui.card class="!p-3 sm:!p-4">
        <div class="flex flex-wrap gap-2">
            @foreach($this->tabs as $key => $label)
                @php($c = (int)($counts[$key] ?? 0))

                @if($tab === $key)
                    <x-ui.brand-button type="button" wire:click="$set('tab', '{{ $key }}')">
                        <span class="inline-flex items-center gap-2">
                            <span>{{ $label }}</span>
                            <x-ui.badge type="info">({{ $c }})</x-ui.badge>
                        </span>
                    </x-ui.brand-button>
                @else
                    <x-ui.secondary-button type="button" wire:click="$set('tab', '{{ $key }}')">
                        <span class="inline-flex items-center gap-2">
                            <span>{{ $label }}</span>
                            <x-ui.badge type="default">({{ $c }})</x-ui.badge>
                        </span>
                    </x-ui.secondary-button>
                @endif
            @endforeach
        </div>
    </x-ui.card>

    {{-- ✅ Filters: صف واحد + بدون عنوان "Status" --}}
    <x-ui.card class="!p-4 sm:!p-5">
        <x-ui.filters-bar :showApply="false" :showClear="false">
            <div class="flex flex-col md:flex-row md:items-end gap-3 w-full">
                <div class="flex-1">
                    <x-ui.search-box
                        wire:model.live="search"
                        :placeholder="tr('Search by policy name')"
                    />
                </div>

               <div class="w-full md:w-64">
                    <x-ui.select
                        wire:model.live="filterBranchId"
                    >
                        <option value="">{{ tr('All Branches') }}</option>
                        @foreach(($branches ?? []) as $br)
                            <option value="{{ $br['id'] }}">
                                {{ $br['name'] ?? ('#'.$br['id']) }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </div>

                <div class="w-full md:w-64">
                    <x-ui.select
                        id="filter_status"
                        name="filterStatus"
                        wire:model.live="filterStatus"
                    >
                        <option value="all">{{ tr('Status') }}</option>
                        <option value="active">{{ tr('Active') }}</option>
                        <option value="inactive">{{ tr('Inactive') }}</option>
                    </x-ui.select>
                </div>
            </div>
        </x-ui.filters-bar>
    </x-ui.card>

    {{-- Table --}}
    <x-ui.card class="!p-4 sm:!p-5">
        <x-ui.table
            :headers="$headers"
            :headerAlign="$headerAlign"
            :enablePagination="false"
        >
            @forelse($policies as $p)
                <tr class="border-b border-gray-100 hover:bg-gray-50/50">
                    <td class="py-3 px-6 text-sm font-semibold text-gray-900">
                        {{ $p->name }}
                    </td>

                    <td class="py-3 px-6 text-sm text-gray-700">
                        @if(($p->scope_type ?? 'all') === 'all')
                            <x-ui.badge type="info">{{ tr('All Employees') }}</x-ui.badge>
                        @else
                            <div class="flex flex-wrap items-center gap-2">
                                <x-ui.badge type="default">
                                    {{ tr('Type') }}: {{ $p->scope_type }}
                                </x-ui.badge>
                                <span class="text-gray-500 text-xs">
                                    ({{ $p->scopes()->count() }})
                                </span>
                            </div>
                        @endif
                    </td>

                    <td class="py-3 px-6 text-sm text-gray-700">
                        <x-ui.badge type="default">{{ (int) $p->steps_count }}</x-ui.badge>
                    </td>

                    <td class="py-3 px-6 text-sm">
                        @if($p->is_active)
                            <x-ui.badge type="success">{{ tr('Active') }}</x-ui.badge>
                        @else
                            <x-ui.badge type="default">{{ tr('Inactive') }}</x-ui.badge>
                        @endif
                    </td>

                    <td class="py-3 px-6 text-sm text-end">
                        <div class="inline-flex items-center gap-2">
                            <x-ui.secondary-button type="button" wire:click="openEdit({{ $p->id }})">
                                {{ tr('Edit') }}
                            </x-ui.secondary-button>

                            <x-ui.secondary-button
                                type="button"
                                wire:click="deletePolicy({{ $p->id }})"
                                class="!border-red-200 !text-red-700 hover:!bg-red-50"
                            >
                                {{ tr('Delete') }}
                            </x-ui.secondary-button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="py-10 px-6 text-center text-gray-500">
                        {{ tr('No policies found') }}
                    </td>
                </tr>
            @endforelse
        </x-ui.table>

        <div class="pt-4">
            {{ $policies->links() }}
        </div>
    </x-ui.card>

    {{-- ✅ Modal --}}
    <x-ui.modal wire:model="showModal" maxWidth="3xl">
        <x-slot:title>
            <div class="space-y-1">
                <div>{{ $modalTitle }}</div>
                <div class="text-sm font-normal text-gray-600">
                    {{ tr('Operation') }}:
                    <span class="font-semibold">{{ $this->tabs[$tab] ?? $tab }}</span>
                </div>
            </div>
        </x-slot:title>

        <x-slot:content>
            <div class="space-y-4">

                {{-- Basic --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <x-ui.input
                            name="name"
                            :label="tr('Policy Name')"
                            wire:model.defer="name"
                            :required="true"
                            :disabled="!auth()->user()->can('settings.approval.manage')"
                        />
                    </div>

                    <div class="flex items-center gap-2 md:pt-8">
                        <label class="inline-flex items-center gap-2 text-sm font-semibold text-gray-700">
                            <input type="checkbox" wire:model.defer="is_active" class="rounded border-gray-300" @cannot('settings.approval.manage') disabled @endcannot>
                            {{ tr('Active') }}
                        </label>
                    </div>
                </div>

                {{-- Scope --}}
                <x-ui.card class="!p-4">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-3">
                        <div class="font-semibold text-gray-900">{{ tr('Application Scope') }}</div>
                        <div class="text-xs text-gray-500">
                            {{ tr('You can select multiple values inside the chosen scope type') }}
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <x-ui.select
                                name="scope_type"
                                :label="tr('Scope Type')"
                                wire:model.live="scope_type"
                                :required="true"
                                :disabled="!auth()->user()->can('settings.approval.manage')"
                            >
                                <option value="all">{{ tr('All Employees') }}</option>
                                <option value="department">{{ tr('Department') }}</option>
                                <option value="job_title">{{ tr('Job Title') }}</option>
                                <option value="branch">{{ tr('Branch') }}</option>
                                <option value="employee">{{ tr('Specific Employees') }}</option>
                            </x-ui.select>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                {{ tr('Values') }}
                            </label>

                            @if($scope_type === 'all')
                                <div class="text-sm text-gray-600 py-2">
                                    {{ tr('Applies to all employees') }}
                                </div>
                            @else
                                <select
                                    wire:model.defer="scope_ids"
                                    multiple
                                    class="w-full rounded-xl border bg-white px-4 py-2.5 text-sm shadow-sm
                                           border-gray-200 text-gray-900
                                           focus:outline-none focus:ring-2 focus:ring-[color:var(--brand-via)]/20
                                           focus:border-[color:var(--brand-via)] transition
                                           min-h-[110px]"
                                    @cannot('settings.approval.manage') disabled @endcannot
                                >
                                    @foreach($list as $item)
                                        <option value="{{ $item['id'] }}">{{ $item['name'] }}</option>
                                    @endforeach
                                </select>

                                @error('scope_ids')
                                    <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                                @enderror
                            @endif
                        </div>
                    </div>
                </x-ui.card>

                {{-- ✅ Steps: فقط زر إضافة موافق --}}
                <x-ui.card class="!p-4">
                    @can('settings.approval.manage')
                    <div class="flex justify-end mb-3">
                        <x-ui.secondary-button type="button" wire:click="addStep" class="!w-auto">
                            + {{ tr('Add Approver') }}
                        </x-ui.secondary-button>
                    </div>
                    @endcan

                    <div class="space-y-2">
                        @foreach($steps as $i => $s)
                            @php($t = $steps[$i]['approver_type'] ?? 'direct_manager')

                            <div class="border border-gray-200 rounded-xl p-3" wire:key="step-{{ $steps[$i]['_key'] ?? $i }}">

                                {{-- ✅ صف واحد مرتب + الأسهم فوق/تحت --}}
                                <div class="flex flex-col md:flex-row md:items-center gap-2">

                                    <div class="shrink-0 md:w-10 text-sm font-bold text-gray-700">
                                        {{ $i + 1 }}
                                    </div>

                                    <div class="shrink-0 md:w-64">
                                        <x-ui.select wire:model.defer="steps.{{ $i }}.approver_type" :disabled="!auth()->user()->can('settings.approval.manage')">
                                            <option value="direct_manager">{{ tr('Direct Manager') }}</option>
                                            <option value="user">{{ tr('Specific User') }}</option>
                                        </x-ui.select>
                                    </div>

                                    <div class="flex-1 min-w-0">
                                        @if($t === 'user')
                                            <x-ui.select wire:model.defer="steps.{{ $i }}.approver_id" :disabled="!auth()->user()->can('settings.approval.manage')">
                                                <option value="0">—</option>
                                                @foreach($employees as $e)
                                                    <option value="{{ $e['id'] }}">{{ $e['name'] }}</option>
                                                @endforeach
                                            </x-ui.select>
                                        @else
                                            <div class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-700">
                                                {{ tr('Direct Manager') }}
                                            </div>
                                        @endif
                                    </div>

                                    <div class="shrink-0 md:w-[170px] flex items-center justify-end gap-2 flex-nowrap">
                                            <div class="inline-flex items-center gap-2 flex-nowrap">
                                                <x-ui.secondary-button
                                                    type="button"
                                                    wire:click="moveStepUp({{ $i }})"
                                                    class="!w-10 !h-10 !p-0 !rounded-full"
                                                    title="{{ tr('Move Up') }}"
                                                >↑</x-ui.secondary-button>

                                                <x-ui.secondary-button
                                                    type="button"
                                                    wire:click="moveStepDown({{ $i }})"
                                                    class="!w-10 !h-10 !p-0 !rounded-full"
                                                    title="{{ tr('Move Down') }}"
                                                >↓</x-ui.secondary-button>
                                            </div>

                                            <x-ui.secondary-button
                                                type="button"
                                                wire:click="removeStep({{ $i }})"
                                                class="!w-10 !h-10 !p-0 !rounded-full !border-red-200 !text-red-700 hover:!bg-red-50"
                                                title="{{ tr('Remove') }}"
                                            >✕</x-ui.secondary-button>
                                        </div>


                                </div>

                                @error("steps.$i.approver_type")
                                    <div class="text-xs text-red-600 mt-2">{{ $message }}</div>
                                @enderror

                                @error("steps.$i.approver_id")
                                    <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        @endforeach
                    </div>
                </x-ui.card>

            </div>
        </x-slot:content>

        <x-slot:footer>
            <x-ui.secondary-button type="button" wire:click="$set('showModal', false)">
                {{ tr('Cancel') }}
            </x-ui.secondary-button>

            @can('settings.approval.manage')
            <x-ui.primary-button type="button" wire:click="save">
                {{ tr('Save Policy') }}
            </x-ui.primary-button>
            @endcan
        </x-slot:footer>
    </x-ui.modal>

    {{-- ✅ إخفاء سهم الـ select الخاص بفلتر الحالة فقط --}}
    @once
        <style>
            #filter_status + div.relative > div.absolute {
                display: none !important;
            }
        </style>
    @endonce

</div>
