<x-ui.modal wire:model="showGpsModal" maxWidth="5xl">
    <x-slot:title>
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center text-lg border border-blue-100 shadow-sm"><i class="fas fa-map-marked-alt"></i></div>
            <div>
                <h3 class="font-bold text-gray-900 text-lg leading-tight">{{ tr('Select Geographic Location') }}</h3>
                <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">{{ tr('Interactive Map Picker') }}</p>
            </div>
        </div>
    </x-slot:title>
    <x-slot:content>
        <div class="space-y-4 py-1" 
            x-data="mapPicker({ 
                lat: @entangle('gpsData.lat'), 
                lng: @entangle('gpsData.lng'), 
                radius: @entangle('gpsData.radius'),
                show: @entangle('showGpsModal')
            })"
            x-init="initMap()"
        >
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
                {{-- Left Sidebar: Settings (Strictly following requirements) --}}
                <div class="lg:col-span-4 space-y-4">
                    {{-- Target Selection --}}
                    <div class="bg-gray-50/50 p-4 rounded-2xl border border-gray-100 space-y-4">
                        <div class="flex items-center justify-between">
                            <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">{{ tr('Location By') }}</label>
                            <div class="flex gap-4">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" wire:model.live="gpsTarget" value="branch" class="w-3.5 h-3.5 text-[color:var(--brand-via)] border-gray-300">
                                    <span class="text-xs font-bold text-gray-600">{{ tr('Branch') }}</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" wire:model.live="gpsTarget" value="groups" class="w-3.5 h-3.5 text-[color:var(--brand-via)] border-gray-300">
                                    <span class="text-xs font-bold text-gray-600">{{ tr('Employee Group') }}</span>
                                </label>
                            </div>
                        </div>

                        <div class="space-y-3">
                            @if($gpsTarget === 'branch')
                                <x-ui.select wire:key="select-branch-target" id="gps_target_branch" label="{{ tr('Select Branch') }}" wire:model.defer="selectedBranch" required class="!py-2 shadow-sm">
                                    <option value="main">{{ tr('Main Branch HQ') }}</option>
                                    <option value="" disabled>{{ tr('Additional branches coming soon...') }}</option>
                                </x-ui.select>
                            @else
                                <x-ui.select wire:key="select-groups-target" id="gps_target_groups" label="{{ tr('Select Employee Groups') }}" wire:model.defer="selectedGroups" multiple required class="!py-2 shadow-sm">
                                   @foreach($groups as $g)
                                        <option value="{{ $g['id'] }}">{{ $g['name'] }}</option>
                                   @endforeach
                                </x-ui.select>
                            @endif

                            <x-ui.input label="{{ tr('Location Name') }}" wire:model.defer="locationName" placeholder="{{ tr('e.g. Sales Dept Area') }}" required class="!py-2" />
                        </div>
                    </div>

                    {{-- Metadata (Auto-filled fields from doc) --}}
                    <div class="p-4 bg-white rounded-2xl border border-gray-100 shadow-sm space-y-3 relative overflow-hidden">
                        {{-- Loading Overlay --}}
                        <div x-show="isFetching" x-transition class="absolute inset-0 bg-white/60 backdrop-blur-[1px] z-10 flex items-center justify-center">
                            <i class="fas fa-circle-notch fa-spin text-blue-500"></i>
                        </div>

                        <h5 class="text-[9px] font-black text-blue-500 uppercase tracking-widest border-b border-gray-50 pb-2 mb-2">{{ tr('Location Metadata') }}</h5>
                        
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-[10px] text-gray-400 block mb-1">{{ tr('Latitude') }}</label>
                                <span x-text="lat.toFixed(6)" class="text-xs font-mono font-bold text-gray-700 bg-gray-50 px-2 py-1 rounded w-full block"></span>
                            </div>
                            <div>
                                <label class="text-[10px] text-gray-400 block mb-1">{{ tr('Longitude') }}</label>
                                <span x-text="lng.toFixed(6)" class="text-xs font-mono font-bold text-gray-700 bg-gray-50 px-2 py-1 rounded w-full block"></span>
                            </div>
                        </div>

                        <div>
                            <label class="text-[10px] text-gray-400 block mb-1">{{ tr('Address / Landmark') }}</label>
                            <div class="flex items-start gap-2">
                                <i class="fas fa-map-signs text-gray-300 mt-0.5 text-xs"></i>
                                <span x-text="address || '{{ tr('Drag marker to fetch address...') }}'" class="text-[11px] font-medium text-gray-600 leading-snug"></span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Right: Map Area --}}
                <div class="lg:col-span-8 space-y-4">
                    <div class="relative w-full h-[450px] bg-gray-100 rounded-2xl overflow-hidden border border-gray-200 shadow-inner group">
                        <div id="map" class="w-full h-full z-0" wire:ignore></div>
                        
                        {{-- Search Box Overlay --}}
                        <div class="absolute top-4 left-4 right-4 z-10 max-w-sm">
                            <div class="relative shadow-lg rounded-xl">
                                <input id="pac-input" type="text" placeholder="{{ tr('Search for a location') }}" class="w-full pl-10 pr-4 py-3 rounded-xl border-0 focus:ring-2 focus:ring-blue-500 shadow-sm text-sm" />
                                <i class="fas fa-search absolute left-3.5 top-3.5 text-gray-400"></i>
                            </div>
                        </div>

                        {{-- Radius Control Overlay --}}
                        <div class="absolute bottom-6 left-1/2 -translate-x-1/2 bg-white/90 backdrop-blur-md px-6 py-3 rounded-full shadow-lg border border-gray-200 flex items-center gap-4 z-10 w-3/4 max-w-md transition-all hover:scale-105">
                            <span class="text-xs font-bold text-gray-600 whitespace-nowrap">{{ tr('Geofence Radius') }}</span>
                            <div class="flex-1 relative flex items-center">
                                <i class="fas fa-bullseye text-[color:var(--brand-via)] absolute left-0 text-xs"></i>
                                <input type="range" class="w-full h-1.5 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-[color:var(--brand-via)] ml-5" 
                                    min="10" max="1000" step="10" x-model="radius" @input="updateCircle()"
                                >
                            </div>
                            <span class="text-xs font-black text-[color:var(--brand-via)] w-12 text-end" x-text="radius + 'm'"></span>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between text-[11px] text-gray-400 px-2">
                        <span><i class="fas fa-mouse-pointer me-1"></i> {{ tr('Click map to move pin') }}</span>
                        <span><i class="fas fa-layer-group me-1"></i> {{ tr('Drag circle edge to resize') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </x-slot:content>
    <x-slot:footer>
        <div class="flex items-center justify-between w-full">
            <span class="text-xs text-gray-400">
                <i class="fas fa-satellite-dish me-1 animate-pulse text-green-500"></i> {{ tr('GPS Signal Active') }}
            </span>
            <div class="flex gap-3">
                <x-ui.secondary-button wire:click="$set('showGpsModal', false)">{{ tr('Cancel') }}</x-ui.secondary-button>
                <x-ui.brand-button wire:click="saveGpsLocation" class="!px-8 !rounded-xl shadow-lg shadow-blue-100">{{ $isEditing ? tr('Update Location') : tr('Save Location') }}</x-ui.brand-button>
            </div>
        </div>
    </x-slot:footer>
</x-ui.modal>





