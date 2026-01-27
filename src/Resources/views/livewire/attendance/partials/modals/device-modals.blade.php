{{-- Fingerprint Device Modal --}}
<x-ui.modal wire:model="showFingerprintModal" maxWidth="xl">
    <x-slot:title>
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center text-lg border border-indigo-100 shadow-sm"><i class="fas fa-fingerprint"></i></div>
            <h3 class="font-bold text-gray-900 text-lg leading-tight">{{ tr('Add Fingerprint Device') }}</h3>
        </div>
    </x-slot:title>
    <x-slot:content>
        <div class="space-y-5 py-2">
            <x-ui.input label="{{ tr('Device Name') }}" wire:model.defer="deviceForm.name" placeholder="{{ tr('e.g. Front Office ZK') }}" required />
            <x-ui.select label="{{ tr('Device Location (Branch)') }}" wire:model.defer="deviceForm.branch" required>
                <option value="main">{{ tr('Main Branch HQ') }}</option>
            </x-ui.select>
            <x-ui.input label="{{ tr('Location Inside Branch') }}" wire:model.defer="deviceForm.location_inside" placeholder="{{ tr('e.g. 1st Floor Reception') }}" required />
            <x-ui.input label="{{ tr('Serial Number (SN)') }}" wire:model.defer="deviceForm.serial_number" placeholder="{{ tr('SN-XXXX-XXXX') }}" />
        </div>
    </x-slot:content>
    <x-slot:footer>
        <x-ui.secondary-button wire:click="$set('showFingerprintModal', false)">{{ tr('Cancel') }}</x-ui.secondary-button>
        <x-ui.brand-button wire:click="saveDevice" class="!px-10 shadow-lg">{{ tr('Register Device') }}</x-ui.brand-button>
    </x-slot:footer>
</x-ui.modal>

{{-- NFC Card Modal --}}
<x-ui.modal wire:model="showNfcModal" maxWidth="xl">
    <x-slot:title>
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center text-lg border border-blue-100 shadow-sm"><i class="fas fa-wifi"></i></div>
            <h3 class="font-bold text-gray-900 text-lg leading-tight">{{ tr('Add NFC Card/Device') }}</h3>
        </div>
    </x-slot:title>
    <x-slot:content>
        <div class="space-y-5 py-2">
            <x-ui.input label="{{ tr('Device/Card Name') }}" wire:model.defer="deviceForm.name" placeholder="{{ tr('e.g. Security Chip A1') }}" required />
            <x-ui.select label="{{ tr('Location (Branch)') }}" wire:model.defer="deviceForm.branch" required>
                <option value="main">{{ tr('Main Branch HQ') }}</option>
            </x-ui.select>
            <x-ui.input label="{{ tr('Installation Point') }}" wire:model.defer="deviceForm.location_inside" placeholder="{{ tr('e.g. Warehouse Entrance') }}" required />
            <x-ui.input label="{{ tr('Serial Number') }}" wire:model.defer="deviceForm.serial_number" placeholder="{{ tr('HEX-XXXX-XXXX') }}" />
        </div>
    </x-slot:content>
    <x-slot:footer>
        <x-ui.secondary-button wire:click="$set('showNfcModal', false)">{{ tr('Cancel') }}</x-ui.secondary-button>
        <x-ui.brand-button wire:click="saveDevice" class="!px-10 shadow-lg">{{ tr('Register NFC') }}</x-ui.brand-button>
    </x-slot:footer>
</x-ui.modal>

{{-- Saved Fingerprint Devices Modal --}}
<x-ui.modal wire:model="showSavedFingerprintModal" maxWidth="4xl">
    <x-slot:title>
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-gray-50 text-indigo-600 rounded-xl flex items-center justify-center text-lg border border-gray-100"><i class="fas fa-fingerprint"></i></div>
            <div>
                <h3 class="font-bold text-gray-900 text-lg leading-tight">{{ tr('Registered Fingerprint Devices') }}</h3>
                <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">{{ count($fingerprintDevices) }} {{ tr('Active Terminals') }}</p>
            </div>
        </div>
    </x-slot:title>
    <x-slot:content>
        <div class="space-y-4 max-h-[60vh] overflow-y-auto pr-2 custom-scrollbar py-2">
            @forelse($fingerprintDevices as $dev)
                <x-ui.card class="!p-0 border-none shadow-sm overflow-hidden bg-white hover:border-indigo-100 border-2 border-transparent transition-all group">
                    <div class="flex items-stretch divide-x divide-gray-50 rtl:divide-x-reverse">
                        <div class="p-4 bg-gray-50/50 flex items-center justify-center">
                            <div class="w-10 h-10 rounded-full bg-white shadow-sm flex items-center justify-center text-indigo-500 border border-indigo-50">
                                <i class="fas fa-microchip"></i>
                            </div>
                        </div>
                        <div class="flex-1 p-4 grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="col-span-2 md:col-span-1">
                                <span class="text-[8px] font-black text-gray-400 uppercase tracking-widest block">{{ tr('Name') }}</span>
                                <span class="text-sm font-bold text-gray-800">{{ $dev['name'] }}</span>
                            </div>
                            <div class="hidden md:block">
                                <span class="text-[8px] font-black text-gray-400 uppercase tracking-widest block">{{ tr('Branch') }}</span>
                                <span class="text-[10px] font-bold text-gray-600">{{ $dev['branch_id'] === 1 ? tr('Main Branch') : $dev['branch_id'] }}</span>
                            </div>
                            <div class="hidden md:block">
                                <span class="text-[8px] font-black text-gray-400 uppercase tracking-widest block">{{ tr('Inside Location') }}</span>
                                <span class="text-[10px] font-bold text-gray-600">{{ $dev['location_in_branch'] }}</span>
                            </div>
                            <div class="col-span-2 md:col-span-1 flex flex-col justify-center">
                                <span class="text-[8px] font-black text-gray-400 uppercase tracking-widest block">{{ tr('Serial') }}</span>
                                <span class="text-[10px] font-mono text-indigo-400">{{ $dev['serial_no'] }}</span>
                            </div>
                        </div>
                        <div class="p-4 flex items-center justify-center bg-gray-50/30">
                            <x-ui.actions-menu>
                                <x-ui.dropdown-item danger>
                                    <i class="fas fa-trash-alt me-2 text-red-500"></i>
                                    <span>{{ tr('Remove') }}</span>
                                </x-ui.dropdown-item>
                            </x-ui.actions-menu>
                        </div>
                    </div>
                </x-ui.card>
            @empty
                <div class="text-center py-10 bg-gray-50 rounded-2xl border-2 border-dashed border-gray-200">
                    <i class="fas fa-fingerprint text-3xl text-gray-300 mb-3"></i>
                    <p class="text-sm font-bold text-gray-400">{{ tr('No devices registered.') }}</p>
                </div>
            @endforelse
        </div>
    </x-slot:content>
    <x-slot:footer>
        <x-ui.secondary-button wire:click="$set('showSavedFingerprintModal', false)">{{ tr('Close') }}</x-ui.secondary-button>
    </x-slot:footer>
</x-ui.modal>

{{-- Saved NFC Devices Modal --}}
<x-ui.modal wire:model="showSavedNfcModal" maxWidth="4xl">
    <x-slot:title>
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-gray-50 text-blue-600 rounded-xl flex items-center justify-center text-lg border border-gray-100"><i class="fas fa-wifi"></i></div>
            <div>
                <h3 class="font-bold text-gray-900 text-lg leading-tight">{{ tr('Registered NFC Points/Chips') }}</h3>
                <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">{{ count($nfcDevices) }} {{ tr('Linked Components') }}</p>
            </div>
        </div>
    </x-slot:title>
    <x-slot:content>
        <div class="space-y-4 max-h-[60vh] overflow-y-auto pr-2 custom-scrollbar py-2">
            @forelse($nfcDevices as $dev)
                <x-ui.card class="!p-0 border-none shadow-sm overflow-hidden bg-white hover:border-blue-100 border-2 border-transparent transition-all group">
                    <div class="flex items-stretch divide-x divide-gray-50 rtl:divide-x-reverse">
                        <div class="p-4 bg-gray-50/50 flex items-center justify-center">
                            <div class="w-10 h-10 rounded-full bg-white shadow-sm flex items-center justify-center text-blue-500 border border-blue-50">
                                <i class="fas fa-wifi"></i>
                            </div>
                        </div>
                        <div class="flex-1 p-4 grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="col-span-2 md:col-span-1">
                                <span class="text-[8px] font-black text-gray-400 uppercase tracking-widest block">{{ tr('Name') }}</span>
                                <span class="text-sm font-bold text-gray-800">{{ $dev['name'] }}</span>
                            </div>
                            <div class="hidden md:block">
                                <span class="text-[8px] font-black text-gray-400 uppercase tracking-widest block">{{ tr('Branch') }}</span>
                                <span class="text-[10px] font-bold text-gray-600">{{ $dev['branch_id'] === 1 ? tr('Main Branch') : $dev['branch_id'] }}</span>
                            </div>
                            <div class="hidden md:block">
                                <span class="text-[8px] font-black text-gray-400 uppercase tracking-widest block">{{ tr('Inside Location') }}</span>
                                <span class="text-[10px] font-bold text-gray-600">{{ $dev['location_in_branch'] }}</span>
                            </div>
                            <div class="col-span-2 md:col-span-1 flex flex-col justify-center">
                                <span class="text-[8px] font-black text-gray-400 uppercase tracking-widest block">{{ tr('Serial') }}</span>
                                <span class="text-[10px] font-mono text-blue-400">{{ $dev['serial_no'] }}</span>
                            </div>
                        </div>
                        <div class="p-4 flex items-center justify-center bg-gray-50/30">
                            <x-ui.actions-menu>
                                <x-ui.dropdown-item danger>
                                    <i class="fas fa-trash-alt me-2 text-red-500"></i>
                                    <span>{{ tr('Remove') }}</span>
                                </x-ui.dropdown-item>
                            </x-ui.actions-menu>
                        </div>
                    </div>
                </x-ui.card>
            @empty
                <div class="text-center py-10 bg-gray-50 rounded-2xl border-2 border-dashed border-gray-200">
                    <i class="fas fa-wifi text-3xl text-gray-300 mb-3"></i>
                    <p class="text-sm font-bold text-gray-400">{{ tr('No chips registered.') }}</p>
                </div>
            @endforelse
        </div>
    </x-slot:content>
    <x-slot:footer>
        <x-ui.secondary-button wire:click="$set('showSavedNfcModal', false)">{{ tr('Close') }}</x-ui.secondary-button>
    </x-slot:footer>
</x-ui.modal>





