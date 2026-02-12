@section('topbar-left-content')
    <x-ui.page-header
        :title="tr('Currencies')"
        :subtitle="tr('Manage currencies and exchange rates')"
        class="!flex-col !items-start !justify-start !gap-1"
        titleSize="xl"
    />
@endsection

@php
    $catalog = $catalog ?? [];
    $codeDisabled = ($mode === 'edit' && $codeLocked);
@endphp

<div class="space-y-4">

    <x-ui.flash-toast />

    {{-- Toolbar --}}
    <x-ui.card>
        <div class="flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between">
            <div class="flex-1">
                <x-ui.search-box
                    wire:model.live="search"
                    :placeholder="tr('Search...')"
                    class="w-full sm:max-w-md"
                />
            </div>

            <div class="flex items-center gap-2">
                <x-ui.select wire:model.live="perPage" class="w-28">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </x-ui.select>

                <x-ui.primary-button type="button" wire:click="openCreate">
                    {{ tr('Add Currency') }}
                </x-ui.primary-button>
            </div>
        </div>
    </x-ui.card>

    {{-- Table --}}
    <x-ui.card class="!p-0">
        <div class="overflow-x-auto">
            <x-ui.table :enablePagination="false">

                <x-slot name="head">
                    <tr>
                        <th class="px-4 py-3 text-start">{{ tr('Currency Name') }}</th>
                        <th class="px-4 py-3 text-start">{{ tr('Symbol') }}</th>
                        <th class="px-4 py-3 text-start">{{ tr('Code') }}</th>
                        <th class="px-4 py-3 text-start">{{ tr('Default') }}</th>
                        <th class="px-4 py-3 text-end">{{ tr('Actions') }}</th>
                    </tr>
                </x-slot>

                <x-slot name="body">
                    @forelse ($currencies as $c)
                        <tr class="border-t border-gray-100">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $c->name }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $c->symbol }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $c->code }}</td>

                            <td class="px-4 py-3">
                                @if ($c->is_default)
                                    <x-ui.badge class="bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        {{ tr('Default') }}
                                    </x-ui.badge>
                                @else
                                    <x-ui.secondary-button
                                        type="button"
                                        wire:click="setDefault({{ $c->id }})"
                                        class="!text-xs !px-2.5 !py-1"
                                    >
                                        {{ tr('Set as default') }}
                                    </x-ui.secondary-button>
                                @endif
                            </td>

                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <x-ui.secondary-button type="button" wire:click="openEdit({{ $c->id }})">
                                        {{ tr('Edit') }}
                                    </x-ui.secondary-button>

                                 @if($c->is_default)
                                    <x-ui.secondary-button
                                            type="button"
                                            disabled
                                            class="!border-red-200 !text-red-700 opacity-50 cursor-not-allowed"
                                            title="{{ tr('Cannot delete default currency') }}"
                                        >
                                            {{ tr('Delete') }}
                                        </x-ui.secondary-button>
                                    @else
                                        <x-ui.secondary-button
                                            type="button"
                                            wire:click="confirmDelete({{ $c->id }})"
                                            class="!border-red-200 !text-red-700 hover:!bg-red-50"
                                        >
                                            {{ tr('Delete') }}
                                        </x-ui.secondary-button>
                                    @endif

                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-gray-500">
                                {{ tr('No currencies found') }}
                            </td>
                        </tr>
                    @endforelse
                </x-slot>
            </x-ui.table>
        </div>
    </x-ui.card>

    <div class="pt-2">
        {{ $currencies->links() }}
    </div>

    {{-- Modal --}}
    <x-ui.modal id="currency-modal" wire:model="modalOpen" maxWidth="lg">
        <x-slot name="title">
            {{ $mode === 'create' ? tr('Add Currency') : tr('Edit Currency') }}
        </x-slot>

        <x-slot name="content">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm text-gray-700 mb-1">{{ tr('Currency') }}</label>

                    {{-- ✅ لا تستخدم @disabled هنا لتفادي مشاكل compilation عندك --}}
                    <x-ui.select wire:model.live="code" class="w-full" :disabled="$codeDisabled">
                        <option value="">{{ tr('Choose currency...') }}</option>

                        @foreach($catalog as $ccode => $meta)
                            <option value="{{ $ccode }}">
                                {{ $meta['name'] }} ({{ $ccode }})
                            </option>
                        @endforeach
                    </x-ui.select>

                    @if($codeDisabled)
                        <div class="text-xs text-gray-500 mt-1">
                            {{ tr('Currency cannot be changed because it is linked to transactions') }}
                        </div>
                    @endif

                    @error('code')
                        <div class="text-xs text-red-600 mt-1">{{ __($message) }}</div>
                    @enderror
                </div>

                <x-ui.input :label="tr('Currency Name')" wire:model="name" disabled />
                <x-ui.input :label="tr('Symbol')" wire:model="symbol" disabled />
                <x-ui.input :label="tr('Code')" wire:model="code" disabled />

                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" wire:model.defer="is_default" class="rounded border-gray-300" />
                    <span>{{ tr('Set as default') }}</span>
                </label>
            </div>
        </x-slot>

        <x-slot name="footer">
            <div class="flex items-center justify-end gap-2">
                <x-ui.secondary-button type="button" wire:click="closeModal">
                    {{ tr('Cancel') }}
                </x-ui.secondary-button>

                <x-ui.primary-button type="button" wire:click="save">
                    {{ tr('Save') }}
                </x-ui.primary-button>
            </div>
        </x-slot>
    </x-ui.modal>

    {{-- Delete confirm --}}
    <x-ui.confirm-dialog
        id="currency-delete-confirm"
        :title="tr('Confirm Delete')"
        :message="tr('Are you sure you want to delete this currency?')"
        :confirmText="tr('Delete')"
        :cancelText="tr('Cancel')"
        confirmAction="wire:delete()"
        type="danger"
    />


</div>
