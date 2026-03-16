<div class="space-y-4">
    <h3 class="text-base font-bold text-gray-800 flex items-center gap-2 px-1">
        <span class="w-1 h-5 bg-[color:var(--brand-via)] rounded-full"></span>
        {{ tr('Preparation Methods') }}
    </h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        @foreach(['gps' => ['title' => tr('GPS Location'), 'icon' => 'fa-map-marker-alt'], 'fingerprint' => ['title' => tr('Fingerprint'), 'icon' => 'fa-fingerprint'], 'nfc' => ['title' => tr('NFC Tech'), 'icon' => 'fa-wifi']] as $key => $meta)
        <x-ui.card class="!p-4">
            <div class="flex items-center justify-between mb-4">
                <div class="w-10 h-10 bg-gray-50 border border-gray-100 text-gray-500 rounded-xl flex items-center justify-center text-lg"><i class="fas {{ $meta['icon'] }}"></i></div>
                @can('settings.attendance.manage')
                <button wire:click="togglePrepMethod('{{ $key }}')" class="w-11 h-6 rounded-full transition-all relative border cursor-pointer {{ $prepMethods[$key]['enabled'] ? 'bg-[color:var(--brand-via)] border-[color:var(--brand-via)]' : 'bg-gray-200 border-gray-200' }}">
                    <div class="absolute top-0.5 w-5 h-5 bg-white rounded-full shadow-sm transition-all {{ $prepMethods[$key]['enabled'] ? ($isRtl ? 'right-5.5' : 'left-5.5') : ($isRtl ? 'right-0.5' : 'left-0.5') }}"></div>
                </button>
                @else
                <button disabled class="w-11 h-6 rounded-full transition-all relative border cursor-not-allowed opacity-60 {{ $prepMethods[$key]['enabled'] ? 'bg-[color:var(--brand-via)] border-[color:var(--brand-via)]' : 'bg-gray-200 border-gray-200' }}">
                    <div class="absolute top-0.5 w-5 h-5 bg-white rounded-full shadow-sm {{ $prepMethods[$key]['enabled'] ? ($isRtl ? 'right-5.5' : 'left-5.5') : ($isRtl ? 'right-0.5' : 'left-0.5') }}"></div>
                </button>
                @endcan
            </div>
            <h4 class="font-bold text-gray-800">{{ $meta['title'] }}</h4>
            <div class="flex items-center justify-between mt-6 pt-4 border-t border-gray-50 text-[10px]">
                <span class="font-semibold text-gray-400 uppercase tracking-widest">{{ $prepMethods[$key]['device_count'] }} {{ tr('Devices') }}</span>
                <div class="flex gap-3">
                    @if($key === 'gps')
                        <button wire:click="openSavedLocationsModal" class="font-bold text-gray-500 hover:text-gray-700 transition-colors cursor-pointer">{{ tr('View Saved') }}</button>
                        @can('settings.attendance.manage')
                        <button wire:click="openGpsModal" class="font-bold text-[color:var(--brand-via)] hover:underline cursor-pointer">{{ tr('Add') }}</button>
                        @endcan
                    @else
                        <button wire:click="$set('showSaved{{ ucfirst($key) }}Modal', true)" class="font-bold text-gray-500 hover:text-gray-700 transition-colors cursor-pointer">{{ tr('View Saved') }}</button>
                        @can('settings.attendance.manage')
                        <button wire:click="openDeviceModal('{{ $key }}')" class="font-bold text-[color:var(--brand-via)] hover:underline cursor-pointer">{{ tr('Add') }}</button>
                        @endcan
                    @endif
                </div>
            </div>
        </x-ui.card>
        @endforeach
    </div>
</div>





