{{-- Saved Locations Modal --}}
<x-ui.modal wire:model="showSavedLocationsModal" maxWidth="4xl">
    <x-slot:title>
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-gray-50 text-[color:var(--brand-via)] rounded-xl flex items-center justify-center text-lg border border-gray-100"><i class="fas fa-list-ul"></i></div>
            <div>
                <h3 class="font-bold text-gray-900 text-lg leading-tight">{{ tr('Saved Geographic Locations') }}</h3>
                <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">{{ count($geographicLocations) }} {{ tr('Locations Recorded') }}</p>
            </div>
        </div>
    </x-slot:title>
    <x-slot:content>
        <div class="space-y-4 max-h-[60vh] overflow-y-auto pr-2 custom-scrollbar py-2">
            @forelse($geographicLocations as $loc)
                <x-ui.card class="!p-0 border-none shadow-sm overflow-hidden bg-white hover:border-blue-100 border-2 border-transparent transition-all group">
                    <div class="flex items-stretch divide-x divide-gray-50 rtl:divide-x-reverse">
                        <div class="p-4 bg-gray-50/50 flex items-center justify-center">
                            <div class="w-10 h-10 rounded-full bg-white shadow-sm flex items-center justify-center text-blue-500 border border-blue-50">
                                <i class="fas fa-map-marked-alt"></i>
                            </div>
                        </div>
                        <div class="flex-1 p-4 grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="col-span-2 md:col-span-1">
                                <span class="text-[8px] font-black text-gray-400 uppercase tracking-widest block">{{ tr('Name') }}</span>
                                <span class="text-sm font-bold text-gray-800">{{ $loc['name'] }}</span>
                            </div>
                            <div class="hidden md:block">
                                <span class="text-[8px] font-black text-gray-400 uppercase tracking-widest block">{{ tr('Target') }}</span>
                                <x-ui.badge type="info" size="xs" class="!text-[8px] !font-black !px-2">{{ $loc['target_name'] ?? ($loc['employee_group_id'] ? 'Group' : 'Branch') }}</x-ui.badge>
                            </div>
                            <div class="hidden md:block">
                                <span class="text-[8px] font-black text-gray-400 uppercase tracking-widest block">{{ tr('Radius') }}</span>
                                <span class="text-xs font-bold text-gray-600">{{ $loc['radius_meters'] ?? $loc['radius'] ?? 0 }}m</span>
                            </div>
                            <div class="col-span-2 md:col-span-1 flex flex-col justify-center">
                                <span class="text-[8px] font-black text-gray-400 uppercase tracking-widest block">{{ tr('Coordinates') }}</span>
                                <span class="text-[10px] font-mono text-gray-400">{{ number_format($loc['lat'], 4) }}, {{ number_format($loc['lng'], 4) }}</span>
                            </div>
                        </div>
                        <div class="p-4 flex items-center justify-center bg-gray-50/30">
                            @can('settings.attendance.manage')
                            <x-ui.actions-menu>
                                <x-ui.dropdown-item wire:click="editGpsLocation({{ $loc['id'] }})">
                                    <i class="fas fa-edit me-2 text-blue-500"></i>
                                    <span>{{ tr('Edit') }}</span>
                                </x-ui.dropdown-item>
                                <x-ui.dropdown-item 
                                    danger
                                    @click="$dispatch('open-confirm-delete-location', { id: {{ $loc['id'] }} })"
                                >
                                    <i class="fas fa-trash-alt me-2 text-red-500"></i>
                                    <span>{{ tr('Remove') }}</span>
                                </x-ui.dropdown-item>
                            </x-ui.actions-menu>
                            @else
                            <span class="text-[10px] font-bold text-gray-400 italic">{{ tr('View Only') }}</span>
                            @endcan
                        </div>
                    </div>
                </x-ui.card>
            @empty
                <div class="text-center py-10 bg-gray-50 rounded-2xl border-2 border-dashed border-gray-200">
                    <i class="fas fa-map-marker-alt text-3xl text-gray-300 mb-3"></i>
                    <p class="text-sm font-bold text-gray-400">{{ tr('No saved locations found.') }}</p>
                </div>
            @endforelse
        </div>
    </x-slot:content>
    <x-slot:footer>
        <x-ui.secondary-button wire:click="$set('showSavedLocationsModal', false)">{{ tr('Close') }}</x-ui.secondary-button>
    </x-slot:footer>
</x-ui.modal>

{{-- GPS Location Modal --}}
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
        @if($errors->any())
            <div class="mb-4 bg-red-50 border-s-4 border-red-500 p-3 rounded-e-xl">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-circle text-red-500 mt-0.5"></i>
                    </div>
                    <div class="ms-3">
                        <p class="text-xs text-red-700 font-bold uppercase tracking-wider mb-1">
                            {{ tr('The following errors occurred:') }}
                        </p>
                        <ul class="list-disc list-inside text-[11px] text-red-600 space-y-0.5">
                            @foreach ($errors->all() as $error)
                                <li>{{ tr($error) }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

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
                {{-- Left Sidebar: Settings --}}
                <div class="lg:col-span-4 space-y-4">
                    {{-- Target Selection --}}
                    <div class="bg-gray-50/50 p-4 rounded-2xl border border-gray-100 space-y-4">
                        <div class="flex items-center justify-between">
                            <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">{{ tr('Location By') }}</label>
                            <div class="flex gap-4">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" wire:model.live="gpsTarget" value="branch" class="w-3.5 h-3.5 text-[color:var(--brand-via)] border-gray-300" @cannot('settings.attendance.manage') disabled @endcannot>
                                    <span class="text-xs font-bold text-gray-600">{{ tr('Branch') }}</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" wire:model.live="gpsTarget" value="groups" class="w-3.5 h-3.5 text-[color:var(--brand-via)] border-gray-300" @cannot('settings.attendance.manage') disabled @endcannot>
                                    <span class="text-xs font-bold text-gray-600">{{ tr('Employee Group') }}</span>
                                </label>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div wire:key="gps-target-block-{{ $gpsTarget }}">
                                @if($gpsTarget === 'branch')
                                    @if(count($branches) > 0)
                                        <x-ui.select wire:key="select-branch-target-{{ $gpsTarget }}" id="gps_target_branch" label="{{ tr('Select Branch') }}" wire:model.defer="selectedBranch" name="selectedBranch" class="!py-2 shadow-sm" :disabled="!auth()->user()->can('settings.attendance.manage')">
                                            <option value="">{{ tr('Select a Branch') }}</option>
                                            @foreach($branches as $branch)
                                                @if(isset($branch['id']))
                                                    <option value="{{ $branch['id'] }}">{{ $branch['name'] }}</option>
                                                @endif
                                            @endforeach
                                        </x-ui.select>
                                        @error('selectedBranch') <span class="text-[10px] text-red-500 font-bold px-1">{{ tr($message) }}</span> @enderror
                                    @else
                                        <div class="text-[11px] text-gray-500 bg-gray-50 p-2 rounded-lg border border-gray-100 flex items-center gap-2">
                                            <i class="fas fa-info-circle text-blue-500"></i>
                                            {{ tr('No branches available.') }}
                                        </div>
                                    @endif
                                @else
                                    @if(count($groups) > 0)
                                        <x-ui.select wire:key="select-groups-target-{{ $gpsTarget }}" id="gps_target_groups" label="{{ tr('Select Employee Groups') }}" wire:model.defer="selectedGroups" name="selectedGroups" multiple class="!py-2 shadow-sm" :disabled="!auth()->user()->can('settings.attendance.manage')">
                                           @foreach($groups as $g)
                                                <option value="{{ $g['id'] }}">{{ $g['name'] }}</option>
                                           @endforeach
                                        </x-ui.select>
                                        @error('selectedGroups') <span class="text-[10px] text-red-500 font-bold px-1">{{ tr($message) }}</span> @enderror
                                    @else
                                        <div class="text-[11px] text-gray-500 bg-gray-50 p-2 rounded-lg border border-gray-100 flex items-center gap-2">
                                            <i class="fas fa-info-circle text-blue-500"></i>
                                            {{ tr('No employee groups available.') }}
                                        </div>
                                    @endif
                                @endif
                            </div>

                            <x-ui.input label="{{ tr('Location Name') }}" wire:model.defer="gpsData.name" name="gpsData.name" placeholder="{{ tr('e.g. Sales Dept Area') }}" required class="!py-2" :disabled="!auth()->user()->can('settings.attendance.manage')" />
                            @error('gpsData.name') <span class="text-[10px] text-red-500 font-bold px-1">{{ tr($message) }}</span> @enderror
                        </div>
                    </div>

                    {{-- Metadata --}}
                    <div class="p-4 bg-white rounded-2xl border border-gray-100 shadow-sm space-y-3 relative overflow-hidden">
                        {{-- Loading Overlay --}}
                        <div x-show="isFetching" x-transition class="absolute inset-0 bg-white/60 backdrop-blur-[1px] z-10 flex items-center justify-center">
                            <i class="fas fa-circle-notch fa-spin text-blue-500"></i>
                        </div>

                        <h5 class="text-[9px] font-black text-blue-500 uppercase tracking-widest border-b border-gray-50 pb-2 mb-2">{{ tr('Location Metadata') }}</h5>
                        
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-[10px] text-gray-400 block mb-1">{{ tr('Latitude') }}</label>
                                <span x-text="parseFloat(lat || 0).toFixed(6)" class="text-xs font-mono font-bold text-gray-700 bg-gray-50 px-2 py-1 rounded w-full block"></span>
                                @error('gpsData.lat') <span class="text-[9px] text-red-500 font-bold">{{ tr($message) }}</span> @enderror
                            </div>
                            <div>
                                <label class="text-[10px] text-gray-400 block mb-1">{{ tr('Longitude') }}</label>
                                <span x-text="parseFloat(lng || 0).toFixed(6)" class="text-xs font-mono font-bold text-gray-700 bg-gray-50 px-2 py-1 rounded w-full block"></span>
                                @error('gpsData.lng') <span class="text-[9px] text-red-500 font-bold">{{ tr($message) }}</span> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="text-[10px] text-gray-400 block mb-1">{{ tr('Address / Landmark') }}</label>
                            <div class="flex items-start gap-2">
                                <i class="fas fa-map-signs text-gray-300 mt-0.5 text-xs"></i>
                                <span x-text="address || '{{ tr('Drag marker to fetch address...') }}'" class="text-[11px] font-medium text-gray-600 leading-snug"></span>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-3 border-t border-gray-50 pt-2" :class="isFetching ? 'opacity-40' : ''">
                             <div>
                                <span class="text-[8px] font-bold text-gray-400 block mb-0.5">{{ tr('Country') }}</span>
                                <span class="text-[11px] font-black text-gray-700 block truncate" x-text="country"></span>
                            </div>
                            <div>
                                <span class="text-[8px] font-bold text-gray-400 block mb-0.5">{{ tr('City') }}</span>
                                <span class="text-[11px] font-black text-gray-700 block truncate" x-text="city"></span>
                            </div>
                            <div>
                                <span class="text-[8px] font-bold text-gray-400 block mb-0.5">{{ tr('Radius Accuracy') }}</span>
                                <span class="text-[11px] font-black text-emerald-600 block truncate" x-text="radius + 'm'"></span>
                                @error('gpsData.radius') <span class="text-[9px] text-red-500 font-bold">{{ tr($message) }}</span> @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Right: Map Area --}}
                <div class="lg:col-span-8 space-y-4">
                    <div class="relative w-full h-[450px] bg-gray-100 rounded-2xl overflow-hidden border border-gray-200 shadow-inner group">
                        <div id="map-picker-container" class="absolute inset-0 z-10" wire:ignore></div>
                        
                        {{-- Top Controls Container (Search + Sync) --}}
                        <div class="absolute top-4 left-16 right-4 z-20 flex items-start gap-3 pointer-events-none">
                            {{-- Search Bar --}}
                            <div class="flex-1 pointer-events-auto max-w-2xl">
                                <div class="relative group/search">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <i class="fas" :class="isSearching ? 'fa-circle-notch fa-spin text-blue-500' : 'fa-search text-gray-400 group-focus-within/search:text-blue-500'"></i>
                                    </div>
                                    <input 
                                        type="text" 
                                        x-model="searchQuery" 
                                        @input.debounce.500ms="searchLocation()"
                                        placeholder="{{ tr('Search for a place or address...') }}"
                                        class="block w-full pl-11 pr-11 py-2.5 bg-white/95 backdrop-blur-xl border border-gray-200/50 rounded-2xl text-xs font-bold shadow-xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-400 transition-all placeholder:text-gray-400 placeholder:font-medium"
                                    >
                                    <button 
                                        x-show="searchQuery.length > 0" 
                                        @click="searchQuery = ''; searchResults = []; isSearching = false" 
                                        class="absolute inset-y-0 right-0 pr-4 flex items-center text-gray-300 hover:text-red-500 transition-colors"
                                        type="button"
                                    >
                                        <i class="fas fa-times-circle text-lg"></i>
                                    </button>
                                </div>

                                {{-- Search Results Dropdown --}}
                                <div 
                                    x-show="searchResults.length > 0 || isSearching" 
                                    x-transition:enter="transition ease-out duration-300"
                                    x-transition:enter-start="opacity-0 translate-y-4"
                                    x-transition:enter-end="opacity-100 translate-y-0"
                                    class="mt-3 bg-white/98 backdrop-blur-xl rounded-2xl shadow-[0_20px_50px_rgba(0,0,0,0.15)] border border-gray-100 overflow-hidden max-h-80 overflow-y-auto custom-scrollbar z-30"
                                    @click.away="searchResults = []"
                                >
                                    <div class="p-3 border-b border-gray-50 bg-gray-50/50 flex items-center justify-between">
                                        <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest px-1">
                                            <span x-show="!isSearching">{{ tr('Found Locations') }}</span>
                                            <span x-show="isSearching">{{ tr('Searching...') }}</span>
                                        </span>
                                        <span x-show="!isSearching" class="text-[10px] font-bold text-blue-500 bg-blue-50 px-2 py-0.5 rounded-full" x-text="searchResults.length"></span>
                                        <i x-show="isSearching" class="fas fa-circle-notch fa-spin text-blue-500 text-[10px]"></i>
                                    </div>

                                    <div class="divide-y divide-gray-50">
                                        <template x-if="isSearching && searchResults.length === 0">
                                            <div class="p-8 text-center">
                                                <div class="w-12 h-12 bg-blue-50 rounded-full flex items-center justify-center mx-auto mb-3">
                                                    <i class="fas fa-search-location text-blue-400 animate-bounce"></i>
                                                </div>
                                                <p class="text-xs font-bold text-gray-400">{{ tr('Looking for places...') }}</p>
                                            </div>
                                        </template>

                                        <template x-for="result in searchResults" :key="result.place_id">
                                            <button 
                                                type="button"
                                                @click="selectLocation(result)"
                                                class="w-full text-start px-4 py-3.5 hover:bg-blue-50/50 transition-colors flex items-start gap-4 group"
                                            >
                                                <div class="w-10 h-10 rounded-xl bg-blue-50/50 flex items-center justify-center text-blue-500 group-hover:bg-blue-500 group-hover:text-white transition-all shrink-0 shadow-sm">
                                                    <i class="fas fa-map-marker-alt text-sm"></i>
                                                </div>
                                                <div class="flex-1 min-w-0 pt-0.5">
                                                    <p class="text-[12px] font-bold text-gray-800 truncate mb-0.5" x-text="result.display_name.split(',')[0]"></p>
                                                    <p class="text-[10px] text-gray-500 truncate font-medium leading-relaxed" x-text="result.display_name"></p>
                                                </div>
                                                <div class="self-center opacity-0 group-hover:opacity-100 transition-opacity pr-1">
                                                    <i class="fas fa-chevron-right text-blue-300 text-[10px]"></i>
                                                </div>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            {{-- Sync Button --}}
                            <div class="pointer-events-auto shrink-0">
                                <button 
                                    type="button" 
                                    @click="getCurrentLocation()" 
                                    class="flex items-center gap-2 text-[10px] font-black text-blue-600 hover:text-white hover:bg-blue-600 bg-white/95 backdrop-blur-md px-4 py-2.5 rounded-2xl border border-blue-100 transition-all shadow-xl active:scale-95 disabled:opacity-50" 
                                    @cannot('settings.attendance.manage') disabled @endcannot
                                >
                                    <i class="fas fa-location-arrow animate-pulse"></i> 
                                    <span class="hidden sm:inline">{{ tr('Sync My Location') }}</span>
                                </button>
                            </div>
                        </div>

                        {{-- Radius Control Overlay --}}
                        <div class="absolute bottom-6 left-1/2 -translate-x-1/2 bg-white/90 backdrop-blur-md px-6 py-3 rounded-full shadow-lg border border-gray-200 flex items-center gap-4 z-20 w-3/4 max-w-md transition-all hover:scale-105 pointer-events-auto">
                            <span class="text-xs font-bold text-gray-600 whitespace-nowrap">{{ tr('Geofence Radius') }}</span>
                            <div class="flex-1 relative flex items-center">
                                <input type="range" class="w-full h-1.5 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-[color:var(--brand-via)] ml-5" 
                                    min="10" max="1000" step="10" x-model="radius" @input="updateCircle()"
                                    @cannot('settings.attendance.manage') disabled @endcannot
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
                @can('settings.attendance.manage')
                <x-ui.primary-button 
                    wire:click="saveGpsLocation" 
                    loading="saveGpsLocation"
                    class="!px-8 !rounded-xl shadow-lg shadow-blue-100"
                    :fullWidth="false"
                >
                    {{ $isEditing ? tr('Update Location') : tr('Save Location') }}
                </x-ui.primary-button>
                @endcan
            </div>
        </div>
    </x-slot:footer>
</x-ui.modal>
