@php
    $locale = app()->getLocale();
    $isRtl  = in_array(substr($locale, 0, 2), ['ar','fa','ur','he']);
@endphp

@section('topbar-left-content')
    <x-ui.page-header
        :title="tr('Currencies')"
        :subtitle="tr('Manage currencies and exchange rates')"
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

<div
    class="space-y-6"
    x-data="{
        view: (localStorage.getItem('settings_currencies_view') || 'table'),
        setView(v){
            this.view = v;
            localStorage.setItem('settings_currencies_view', v);
        }
    }"
    x-init="localStorage.setItem('settings_currencies_view', view)"
>
    <x-ui.flash-toast />

    {{-- Search & Filters --}}
    <x-ui.card :padding="false" class="border-gray-200 p-4">
        <div class="flex flex-col md:flex-row gap-4 items-start md:items-end justify-between">
            <div class="flex-1 w-full">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1.5">
                            {{ tr('Search') }}
                        </label>
                        <x-ui.search-box wire:model.live.debounce.300ms="search" :placeholder="tr('Search currency name, code or symbol...')" />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1.5">
                            {{ tr('Rows Per Page') }}
                        </label>
                        <x-ui.select wire:model.live="perPage">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </x-ui.select>
                    </div>
                </div>
            </div>
            
            <div class="flex items-center gap-4">
                {{-- Add Button next to toggle --}}
                @can('settings.currencies.manage')
                    <button
                        type="button"
                        wire:click="openCreate"
                        class="group inline-flex items-center gap-2.5 px-5 py-2.5 bg-gradient-to-r from-[color:var(--brand-from)] to-[color:var(--brand-to)] text-white rounded-xl shadow-lg shadow-[color:var(--brand-from)]/20 hover:shadow-xl hover:shadow-[color:var(--brand-from)]/30 hover:-translate-y-0.5 transition-all duration-300 cursor-pointer"
                    >
                        <div class="w-6 h-6 bg-white/20 rounded-lg flex items-center justify-center group-hover:rotate-90 transition-transform duration-500">
                            <i class="fas fa-plus text-sm"></i>
                        </div>
                        <span class="font-bold tracking-wide text-sm">{{ tr('Add New') }}</span>
                    </button>
                @endcan

                <div class="h-10 w-[1px] bg-gray-200 hidden md:block"></div>

                <x-ui.view-toggle />
            </div>
        </div>
    </x-ui.card>

    {{-- Cards Layout --}}
    <div x-show="view === 'cards'" x-cloak>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            @forelse($currencies as $c)
                <x-ui.card wire:key="currency-card-{{ $c->id }}" :hover="true" :padding="false" class="rounded-2xl border-gray-200 p-5 group flex flex-col h-full relative overflow-hidden">
                    @if($c->is_default)
                        <div class="absolute top-0 right-0 bg-emerald-500 text-white px-3 py-1 rounded-bl-xl text-[10px] font-black uppercase tracking-widest shadow-sm">
                            {{ tr('Default') }}
                        </div>
                    @endif

                    <div class="flex items-center gap-4 mb-6">
                        <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white shadow-lg shrink-0 group-hover:scale-110 transition-transform duration-500">
                            <span class="text-xl font-black">{{ $c->symbol }}</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h4 class="text-lg font-black text-gray-900 truncate leading-tight">{{ $c->name }}</h4>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="px-2 py-0.5 bg-gray-100 text-gray-600 rounded-md text-[10px] font-bold uppercase tracking-wider border border-gray-200">
                                    {{ $c->code }}
                                </span>
                            </div>
                        </div>

                        @can('settings.currencies.manage')
                            <x-ui.actions-menu>
                                <x-ui.dropdown-item wire:click="openEdit({{ $c->id }})">
                                    <i class="fas fa-edit mr-2 w-5 text-blue-500"></i>
                                    {{ tr('Edit') }}
                                </x-ui.dropdown-item>
                                @if(!$c->is_default)
                                    <x-ui.dropdown-item wire:click="setDefault({{ $c->id }})">
                                        <i class="fas fa-star mr-2 w-5 text-amber-500"></i>
                                        {{ tr('Set as default') }}
                                    </x-ui.dropdown-item>
                                    <x-ui.dropdown-item wire:click="confirmDelete({{ $c->id }})" :danger="true">
                                        <i class="fas fa-trash-alt mr-2 w-5 text-red-500"></i>
                                        {{ tr('Delete') }}
                                    </x-ui.dropdown-item>
                                @endif
                            </x-ui.actions-menu>
                        @endcan
                    </div>

                    <div class="mt-auto pt-4 border-t border-gray-50 flex items-center justify-between text-xs font-bold text-gray-400">
                        <span>{{ tr('Currency Code') }}</span>
                        <span class="text-gray-600">{{ $c->code }}</span>
                    </div>
                </x-ui.card>
            @empty
                <div class="col-span-full py-20 flex flex-col items-center justify-center text-center">
                    <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mb-4 border border-gray-100 italic">
                        <i class="fas fa-coins text-gray-300 text-2xl"></i>
                    </div>
                    <h4 class="text-sm font-black text-gray-400 uppercase tracking-widest">{{ tr('No currencies found') }}</h4>
                    <p class="text-xs text-gray-400 font-bold mt-1">{{ tr('Try adjusting your search to find what you are looking for.') }}</p>
                </div>
            @endforelse
        </div>
    </div>

    {{-- Table Layout --}}
    <div x-show="view === 'table'" x-cloak>
        <x-ui.card :padding="false" class="rounded-2xl border-gray-200 overflow-hidden shadow-sm">
            <x-ui.table>
                <x-slot name="head">
                    <tr class="bg-gray-50/50">
                        <th class="px-6 py-4 text-start text-xs font-black text-gray-500 uppercase tracking-widest">{{ tr('Currency') }}</th>
                        <th class="px-6 py-4 text-start text-xs font-black text-gray-500 uppercase tracking-widest">{{ tr('Code') }}</th>
                        <th class="px-6 py-4 text-start text-xs font-black text-gray-500 uppercase tracking-widest">{{ tr('Symbol') }}</th>
                        <th class="px-6 py-4 text-start text-xs font-black text-gray-500 uppercase tracking-widest">{{ tr('Status') }}</th>
                        @can('settings.currencies.manage')
                            <th class="px-6 py-4 text-end text-xs font-black text-gray-500 uppercase tracking-widest">{{ tr('Actions') }}</th>
                        @endcan
                    </tr>
                </x-slot>

                <x-slot name="body">
                    @forelse($currencies as $c)
                        <tr class="hover:bg-gray-50/80 transition-colors duration-200 border-b border-gray-100 last:border-0">
                            <td class="px-6 py-4">
                                <span class="text-sm font-bold text-gray-900">{{ $c->name }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-gray-100 text-gray-700 border border-gray-200">
                                    {{ $c->code }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-sm font-bold text-gray-600 bg-gray-50 w-8 h-8 rounded-lg flex items-center justify-center border border-gray-100">
                                    {{ $c->symbol }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                @if($c->is_default)
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-emerald-50 text-emerald-600 border border-emerald-100 shadow-sm">
                                        <i class="fas fa-check-circle mr-1.5"></i>
                                        {{ tr('Default') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest bg-gray-50 text-gray-400 border border-gray-100 italic">
                                        {{ tr('Secondary') }}
                                    </span>
                                @endif
                            </td>
                            @can('settings.currencies.manage')
                                <td class="px-6 py-4 text-end">
                                    <div class="flex items-center justify-end">
                                        <x-ui.actions-menu>
                                            <x-ui.dropdown-item wire:click="openEdit({{ $c->id }})">
                                                <i class="fas fa-edit mr-2 w-5 text-blue-500"></i>
                                                {{ tr('Edit') }}
                                            </x-ui.dropdown-item>
                                            @if(!$c->is_default)
                                                <x-ui.dropdown-item wire:click="setDefault({{ $c->id }})">
                                                    <i class="fas fa-star mr-2 w-5 text-amber-500"></i>
                                                    {{ tr('Set as default') }}
                                                </x-ui.dropdown-item>
                                                <x-ui.dropdown-item wire:click="confirmDelete({{ $c->id }})" :danger="true">
                                                    <i class="fas fa-trash-alt mr-2 w-5 text-red-500"></i>
                                                    {{ tr('Delete') }}
                                                </x-ui.dropdown-item>
                                            @endif
                                        </x-ui.actions-menu>
                                    </div>
                                </td>
                            @endcan
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12">
                                <div class="flex flex-col items-center justify-center text-center">
                                    <div class="w-12 h-12 bg-gray-50 rounded-full flex items-center justify-center mb-3">
                                        <i class="fas fa-search text-gray-200"></i>
                                    </div>
                                    <p class="text-xs font-black text-gray-400 uppercase tracking-widest">{{ tr('No currencies found') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </x-slot>
            </x-ui.table>
        </x-ui.card>
    </div>

    {{-- Pagination --}}
    <div class="mt-6">
        {{ $currencies->links() }}
    </div>

    {{-- Modal: Add/Edit Currency --}}
    <x-ui.modal id="currency-modal" wire:model="modalOpen" maxWidth="lg">
        <x-slot name="title">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white shadow-md">
                    <i class="fas fa-coins text-sm"></i>
                </div>
                <div>
                    <h3 class="text-lg font-black text-gray-900 leading-tight">
                        {{ $mode === 'create' ? tr('Add New Currency') : tr('Edit Currency Settings') }}
                    </h3>
                    <p class="text-xs text-gray-500 font-bold mt-0.5">
                        {{ $mode === 'create' ? tr('Setup a new currency for the system') : tr('Update existing currency details') }}
                    </p>
                </div>
            </div>
        </x-slot>

        <x-slot name="content">
            <div class="space-y-6">
                {{-- Currency Selection Component --}}
                <div>
                    <x-ui.select 
                        :label="tr('Currency Selection')" 
                        wire:model.live="code" 
                        class="w-full" 
                        :disabled="$mode === 'edit' && $codeLocked"
                    >
                        <option value="">{{ tr('Choose a currency from list...') }}</option>
                        @foreach($catalog as $ccode => $meta)
                            <option value="{{ $ccode }}">{{ $meta['name'] }} ({{ $ccode }})</option>
                        @endforeach
                    </x-ui.select>
                    
                    @if($mode === 'edit' && $codeLocked)
                        <div class="mt-2 flex items-center gap-2 text-[10px] text-amber-600 font-black uppercase tracking-tighter">
                            <i class="fas fa-lock"></i>
                            {{ tr('Locked: Linked to existing transactions') }}
                        </div>
                    @endif
                    @error('code') <p class="mt-1.5 text-[11px] font-bold text-red-500">{{ $message }}</p> @enderror
                </div>

                {{-- Auto-filled Details using Official Components --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-ui.input 
                        :label="tr('Display Name')" 
                        wire:model="name" 
                        disabled 
                        class="bg-gray-50 font-bold"
                    />
                    
                    <x-ui.input 
                        :label="tr('Symbol')" 
                        wire:model="symbol" 
                        disabled 
                        class="bg-gray-50 font-bold text-center"
                    />
                </div>

                {{-- Default toggle --}}
                @if(!$is_default)
                    <div class="p-4 bg-emerald-50/50 border border-emerald-100 rounded-2xl flex items-center justify-between">
                        <div>
                            <h5 class="text-sm font-black text-emerald-900">{{ tr('Primary Currency') }}</h5>
                            <p class="text-[10px] text-emerald-600 font-bold leading-tight mt-0.5">{{ tr('Set this as the main currency for all financial entries') }}</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" wire:model="is_default" class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
                        </label>
                    </div>
                @else
                    <div class="p-4 bg-amber-50/50 border border-amber-100 rounded-2xl flex items-center gap-3">
                        <i class="fas fa-info-circle text-amber-500"></i>
                        <p class="text-[10px] text-amber-700 font-bold leading-tight">{{ tr('This is the default currency and cannot be unset here. Set another currency as default to change.') }}</p>
                    </div>
                @endif
            </div>
        </x-slot>

        <x-slot name="footer">
            <div class="flex items-center justify-end gap-3 w-full">
                <x-ui.secondary-button wire:click="closeModal" class="!rounded-xl font-bold">
                    {{ tr('Cancel') }}
                </x-ui.secondary-button>
                <x-ui.primary-button wire:click="save" loading="save" class="!rounded-xl !px-10 font-black shadow-lg shadow-[color:var(--brand-from)]/20">
                    {{ tr('Save Changes') }}
                </x-ui.primary-button>
            </div>
        </x-slot>
    </x-ui.modal>

    {{-- Confirmation Dialog --}}
    <x-ui.confirm-dialog
        id="currency-delete-confirm"
        :title="tr('Delete Currency')"
        :message="tr('Are you sure you want to delete this currency? This action cannot be undone if not linked to records.')"
        :confirmText="tr('Delete Now')"
        confirmAction="delete"
        type="danger"
    />
</div>
