{{-- Saved Locations Modal --}}
<x-ui.modal wire:model="showSavedLocationsModal" maxWidth="4xl">
    <x-slot:title>
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-[rgb(var(--accent-orange-rgb)/0.08)] text-[color:var(--accent-orange)] rounded-xl flex items-center justify-center text-lg border border-[rgb(var(--accent-orange-rgb)/0.16)]"><i class="fas fa-list-ul"></i></div>
            <div>
                <h3 class="font-bold text-gray-900 text-lg leading-tight">{{ tr('Saved Geographic Locations') }}</h3>
                <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">{{ count($geographicLocations) }} {{ tr('Locations Recorded') }}</p>
            </div>
        </div>
    </x-slot:title>
    <x-slot:content>
        <div class="space-y-4 max-h-[60vh] overflow-y-auto pr-2 custom-scrollbar py-2">
            @forelse($geographicLocations as $loc)
                <x-ui.card class="!p-0 border-none shadow-sm overflow-hidden bg-white hover:border-[rgb(var(--accent-orange-rgb)/0.16)] border-2 border-transparent transition-all group">
                    <div class="flex items-stretch divide-x divide-gray-50 rtl:divide-x-reverse">
                        <div class="p-4 bg-gray-50/50 flex items-center justify-center">
                            <div class="w-10 h-10 rounded-full bg-white shadow-sm flex items-center justify-center text-[color:var(--accent-orange)] border border-[rgb(var(--accent-orange-rgb)/0.12)]">
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
                                <span class="text-[8px] font-black text-gray-400 uppercase tracking-widest block">{{ tr('Geofence Type') }}</span>
                                @if(($loc['geofence_type'] ?? 'circle') === 'polygon')
                                    <span class="text-xs font-bold text-gray-600">{{ tr('Custom Boundary') }}</span>
                                @else
                                    <span class="text-xs font-bold text-gray-600">{{ $loc['radius_meters'] ?? $loc['radius'] ?? 0 }}m</span>
                                @endif
                            </div>
                            <div class="col-span-2 md:col-span-1 flex flex-col justify-center">
                                <span class="text-[8px] font-black text-gray-400 uppercase tracking-widest block">{{ tr('Coordinates') }}</span>
                                <span class="text-[10px] font-mono text-gray-400">{{ number_format($loc['lat'], 4) }}, {{ number_format($loc['lng'], 4) }}</span>
                            </div>
                        </div>
                        <div class="p-4 flex items-center justify-center bg-gray-50/30">
                            @if($canManageAttendance)
                            <x-ui.actions-menu>
                                <x-ui.dropdown-item wire:click="editGpsLocation({{ $loc['id'] }})">
                                    <i class="fas fa-edit me-2 text-[color:var(--accent-orange)]"></i>
                                    <span>{{ tr('Edit') }}</span>
                                </x-ui.dropdown-item>
                                <x-ui.dropdown-item
                                    danger
                                    @click="$dispatch('open-confirm-delete-location', { id: {{ $loc['id'] }} })"
                                >
                                    <i class="fas fa-trash-alt me-2 text-[color:var(--error)]"></i>
                                    <span>{{ tr('Remove') }}</span>
                                </x-ui.dropdown-item>
                            </x-ui.actions-menu>
                            @else
                            <span class="text-[10px] font-bold text-gray-400 italic">{{ tr('View Only') }}</span>
                            @endif
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
            <div class="w-10 h-10 bg-[rgb(var(--accent-orange-rgb)/0.08)] text-[color:var(--accent-orange)] rounded-xl flex items-center justify-center text-lg border border-[rgb(var(--accent-orange-rgb)/0.16)] shadow-sm"><i class="fas fa-map-marked-alt"></i></div>
            <div>
                <h3 class="font-bold text-gray-900 text-lg leading-tight">{{ tr('Select Geographic Location') }}</h3>
                <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">{{ tr('Interactive Map Picker') }}</p>
            </div>
        </div>
    </x-slot:title>
    <x-slot:content>
        @if($errors->any())
            <div class="mb-4 bg-[rgb(239_68_68/0.10)] border-s-4 border-[color:var(--error)] p-3 rounded-e-xl">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-circle text-[color:var(--error)] mt-0.5"></i>
                    </div>
                    <div class="ms-3">
                        <p class="text-xs text-[color:var(--error)] font-bold uppercase tracking-wider mb-1">
                            {{ tr('The following errors occurred:') }}
                        </p>
                        <ul class="list-disc list-inside text-[11px] text-[color:var(--error)] space-y-0.5">
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
                boundaryType: @entangle('gpsData.geofence_type'),
                boundaryGeoJson: @entangle('gpsData.boundary_geojson'),
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
                                    <input type="radio" wire:model.live="gpsTarget" value="branch" class="w-3.5 h-3.5 text-[color:var(--accent-orange)] border-gray-300" @if(!$canManageAttendance) disabled @endif>
                                    <span class="text-xs font-bold text-gray-600">{{ tr('Branch') }}</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" wire:model.live="gpsTarget" value="groups" class="w-3.5 h-3.5 text-[color:var(--accent-orange)] border-gray-300" @if(!$canManageAttendance) disabled @endif>
                                    <span class="text-xs font-bold text-gray-600">{{ tr('Employee Group') }}</span>
                                </label>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div wire:key="gps-target-block-{{ $gpsTarget }}">
                                @if($gpsTarget === 'branch')
                                    @if(count($branches) > 0)
                                        <x-ui.select wire:key="select-branch-target-{{ $gpsTarget }}" id="gps_target_branch" label="{{ tr('Select Branch') }}" wire:model.defer="selectedBranch" name="selectedBranch" class="!py-2 shadow-sm" :disabled="!$canManageAttendance">
                                            <option value="">{{ tr('Select a Branch') }}</option>
                                            @foreach($branches as $branch)
                                                @if(isset($branch['id']))
                                                    <option value="{{ $branch['id'] }}">{{ $branch['name'] }}</option>
                                                @endif
                                            @endforeach
                                        </x-ui.select>
                                        @error('selectedBranch') <span class="text-[10px] text-[color:var(--error)] font-bold px-1">{{ tr($message) }}</span> @enderror
                                    @else
                                        <div class="text-[11px] text-gray-500 bg-gray-50 p-2 rounded-lg border border-gray-100 flex items-center gap-2">
                                            <i class="fas fa-info-circle text-[color:var(--accent-orange)]"></i>
                                            {{ tr('No branches available.') }}
                                        </div>
                                    @endif
                                @else
                                    @if(count($groups) > 0)
                                        <x-ui.select wire:key="select-groups-target-{{ $gpsTarget }}" id="gps_target_groups" label="{{ tr('Select Employee Groups') }}" wire:model.defer="selectedGroups" name="selectedGroups" multiple class="!py-2 shadow-sm" :disabled="!$canManageAttendance">
                                           @foreach($groups as $g)
                                                <option value="{{ $g['id'] }}">{{ $g['name'] }}</option>
                                           @endforeach
                                        </x-ui.select>
                                        @error('selectedGroups') <span class="text-[10px] text-[color:var(--error)] font-bold px-1">{{ tr($message) }}</span> @enderror
                                    @else
                                        <div class="text-[11px] text-gray-500 bg-gray-50 p-2 rounded-lg border border-gray-100 flex items-center gap-2">
                                            <i class="fas fa-info-circle text-[color:var(--accent-orange)]"></i>
                                            {{ tr('No employee groups available.') }}
                                        </div>
                                    @endif
                                @endif
                            </div>

                            <x-ui.input label="{{ tr('Location Name') }}" wire:model.defer="gpsData.name" name="gpsData.name" placeholder="{{ tr('e.g. Sales Dept Area') }}" required class="!py-2" :disabled="!$canManageAttendance" />
                            @error('gpsData.name') <span class="text-[10px] text-[color:var(--error)] font-bold px-1">{{ tr($message) }}</span> @enderror

                            <div class="space-y-2">
                                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block">{{ tr('Geofence Type') }}</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <label class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 cursor-pointer">
                                        <input type="radio" x-model="boundaryType" value="circle" class="w-3.5 h-3.5 text-[color:var(--accent-orange)] border-gray-300" @if(!$canManageAttendance) disabled @endif>
                                        <span class="text-[11px] font-bold text-gray-700">{{ tr('Circular Radius') }}</span>
                                    </label>
                                    <label class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 cursor-pointer">
                                        <input type="radio" x-model="boundaryType" value="polygon" class="w-3.5 h-3.5 text-[color:var(--accent-orange)] border-gray-300" @if(!$canManageAttendance) disabled @endif>
                                        <span class="text-[11px] font-bold text-gray-700">{{ tr('Custom Boundary') }}</span>
                                    </label>
                                </div>
                                @error('gpsData.geofence_type') <span class="text-[10px] text-[color:var(--error)] font-bold px-1">{{ tr($message) }}</span> @enderror
                                @error('gpsData.boundary_geojson') <span class="text-[10px] text-[color:var(--error)] font-bold px-1">{{ tr($message) }}</span> @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Metadata --}}
                    <div class="p-4 bg-white rounded-2xl border border-gray-100 shadow-sm space-y-3 relative overflow-hidden">
                        {{-- Loading Overlay --}}
                        <div x-show="isFetching" x-transition class="absolute inset-0 bg-white/60 backdrop-blur-[1px] z-10 flex items-center justify-center">
                            <i class="fas fa-circle-notch fa-spin text-[color:var(--accent-orange)]"></i>
                        </div>

                        <h5 class="text-[9px] font-black text-[color:var(--accent-orange)] uppercase tracking-widest border-b border-gray-50 pb-2 mb-2">{{ tr('Location Metadata') }}</h5>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-[10px] text-gray-400 block mb-1">{{ tr('Latitude') }}</label>
                                <span x-text="parseFloat(lat || 0).toFixed(6)" class="text-xs font-mono font-bold text-gray-700 bg-gray-50 px-2 py-1 rounded w-full block"></span>
                                @error('gpsData.lat') <span class="text-[9px] text-[color:var(--error)] font-bold">{{ tr($message) }}</span> @enderror
                            </div>
                            <div>
                                <label class="text-[10px] text-gray-400 block mb-1">{{ tr('Longitude') }}</label>
                                <span x-text="parseFloat(lng || 0).toFixed(6)" class="text-xs font-mono font-bold text-gray-700 bg-gray-50 px-2 py-1 rounded w-full block"></span>
                                @error('gpsData.lng') <span class="text-[9px] text-[color:var(--error)] font-bold">{{ tr($message) }}</span> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="text-[10px] text-gray-400 block mb-1">{{ tr('Address / Landmark') }}</label>
                            <div class="flex items-start gap-2">
                                <i class="fas fa-map-signs text-gray-300 mt-0.5 text-xs"></i>
                                <span x-text="address || '{{ tr('Drag marker to fetch address...') }}'" class="text-[11px] font-medium text-gray-600 leading-snug"></span>
                            </div>
                            <p
                                x-show="geocodingError"
                                x-text="geocodingError"
                                class="mt-2 text-[9px] font-bold text-amber-600 leading-relaxed"
                            ></p>
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
                            <div x-show="boundaryType === 'circle'">
                                <span class="text-[8px] font-bold text-gray-400 block mb-0.5">{{ tr('Radius Accuracy') }}</span>
                                <span class="text-[11px] font-black text-[color:var(--success)] block truncate" x-text="radius + 'm'"></span>
                                @error('gpsData.radius') <span class="text-[9px] text-[color:var(--error)] font-bold">{{ tr($message) }}</span> @enderror
                            </div>
                            <div x-show="boundaryType === 'polygon'">
                                <span class="text-[8px] font-bold text-gray-400 block mb-0.5">{{ tr('Boundary Points') }}</span>
                                <span class="text-[11px] font-black text-[color:var(--success)] block truncate" x-text="polygonPointCount"></span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Right: Map Area --}}
                <div class="lg:col-span-8 space-y-4">
                    <div
                        class="athka-map-picker-shell relative w-full h-[470px] bg-slate-100 rounded-2xl overflow-hidden border border-slate-200 shadow-[inset_0_0_0_1px_rgba(255,255,255,0.65),0_12px_28px_rgba(15,23,42,0.07)]"
                        :data-map-style="mapStyle"
                    >
                        <div id="map-picker-container" data-map-picker-container class="absolute inset-0 z-10" wire:ignore></div>

                        {{-- Compact map controls: one place search, current location, and layers --}}
                        <div class="absolute top-4 left-14 right-4 z-20 pointer-events-none">
                            <div class="mx-auto flex max-w-2xl items-start gap-2">
                                {{-- Single specialized place search --}}
                                <div class="min-w-0 flex-1 pointer-events-auto">
                                    <div class="relative group/search">
                                        <div class="absolute inset-y-0 start-0 ps-3.5 flex items-center pointer-events-none">
                                            <i
                                                class="fas text-sm"
                                                :class="isSearching
                                                    ? 'fa-circle-notch fa-spin text-[color:var(--accent-orange)]'
                                                    : 'fa-search-location text-slate-400 group-focus-within/search:text-[color:var(--accent-orange)]'"
                                            ></i>
                                        </div>
                                        <input
                                            type="text"
                                            x-model="searchQuery"
                                            @input.debounce.450ms="searchLocation()"
                                            @keydown.enter.prevent="searchLocation()"
                                            placeholder="{{ app()->getLocale() === 'ar' ? 'ابحث عن دولة، مدينة، منطقة، حي أو شارع...' : 'Search for a country, city, area, neighborhood, or street...' }}"
                                            dir="auto"
                                            autocomplete="off"
                                            class="block w-full ps-11 pe-10 py-3 bg-white/[0.97] backdrop-blur-xl border border-slate-200/90 rounded-xl text-xs font-bold shadow-[0_10px_26px_rgba(15,23,42,0.14)] focus:ring-4 focus:ring-[rgb(var(--accent-orange-rgb)/0.14)] focus:border-[color:var(--accent-orange)] transition-all placeholder:text-slate-400 placeholder:font-medium"
                                        >
                                        <button
                                            x-show="searchQuery.length > 0"
                                            x-cloak
                                            @click="clearPlaceSearch()"
                                            class="absolute inset-y-0 end-0 pe-3.5 flex items-center text-slate-300 hover:text-[color:var(--error)] transition-colors"
                                            type="button"
                                            title="{{ tr('Clear') }}"
                                        >
                                            <i class="fas fa-times-circle text-base"></i>
                                        </button>
                                    </div>

                                    {{-- Compact search results dropdown --}}
                                    <div
                                        x-show="searchResults.length > 0 || isSearching || searchHasRun"
                                        x-cloak
                                        x-transition:enter="transition ease-out duration-180"
                                        x-transition:enter-start="opacity-0 -translate-y-1"
                                        x-transition:enter-end="opacity-100 translate-y-0"
                                        class="mt-2 bg-white/[0.98] backdrop-blur-xl rounded-xl shadow-[0_18px_42px_rgba(15,23,42,0.18)] border border-slate-100 overflow-hidden max-h-64 overflow-y-auto custom-scrollbar z-30"
                                        @click.away="closePlaceSearchResults()"
                                    >
                                        <template x-if="isSearching && searchResults.length === 0">
                                            <div class="flex items-center gap-3 px-4 py-3 text-slate-500">
                                                <i class="fas fa-circle-notch fa-spin text-[color:var(--accent-orange)]"></i>
                                                <span class="text-[11px] font-bold">{{ app()->getLocale() === 'ar' ? 'جارٍ البحث عن الأماكن...' : 'Searching places...' }}</span>
                                            </div>
                                        </template>

                                        <template x-if="searchHasRun && !isSearching && searchResults.length === 0">
                                            <div class="px-4 py-5 text-center">
                                                <i class="fas fa-map-marker-alt mb-2 text-lg text-slate-300"></i>
                                                <p class="text-[11px] font-black text-slate-600">
                                                    {{ app()->getLocale() === 'ar' ? 'لم نجد مكانًا مطابقًا' : 'No matching place found' }}
                                                </p>
                                                <p class="mt-1 text-[9px] font-medium text-slate-400">
                                                    {{ app()->getLocale() === 'ar' ? 'اكتب اسم الدولة أو المدينة أو المنطقة أو الحي أو الشارع، أو أدخل الإحداثيات.' : 'Enter a country, city, area, neighborhood, street, or coordinates.' }}
                                                </p>
                                            </div>
                                        </template>

                                        <div class="divide-y divide-slate-100">
                                            <template x-for="result in searchResults" :key="result.place_id">
                                                <button
                                                    type="button"
                                                    @click="selectLocation(result)"
                                                    class="w-full text-start px-3 py-2.5 hover:bg-[rgb(var(--accent-orange-rgb)/0.07)] transition-colors flex items-center gap-3 group"
                                                >
                                                    <div class="w-8 h-8 rounded-lg bg-[rgb(var(--accent-orange-rgb)/0.08)] flex items-center justify-center text-[color:var(--accent-orange)] group-hover:bg-[color:var(--accent-orange)] group-hover:text-white transition-colors shrink-0">
                                                        <i class="fas text-xs" :class="resultIconClass(result)"></i>
                                                    </div>
                                                    <div class="flex-1 min-w-0">
                                                        <div class="flex items-center gap-2 min-w-0">
                                                            <p
                                                                class="text-[11px] font-black text-slate-800 truncate"
                                                                x-text="result.name || result.display_name.split(',')[0]"
                                                            ></p>
                                                            <span
                                                                class="athka-map-result-type shrink-0 truncate rounded-full bg-slate-100 px-1.5 py-0.5 text-[7px] font-black text-slate-500"
                                                                x-text="resultTypeLabel(result)"
                                                            ></span>
                                                        </div>
                                                        <div class="mt-0.5 flex items-center gap-2 min-w-0">
                                                            <p class="flex-1 text-[9px] text-slate-500 truncate font-medium" x-text="result.display_name"></p>
                                                            <span
                                                                x-show="formatResultDistance(result)"
                                                                class="shrink-0 text-[8px] font-bold text-slate-400"
                                                                x-text="formatResultDistance(result)"
                                                            ></span>
                                                        </div>
                                                    </div>
                                                    <i class="fas {{ app()->getLocale() === 'ar' ? 'fa-chevron-left' : 'fa-chevron-right' }} text-[9px] text-slate-300 group-hover:text-[color:var(--accent-orange)]"></i>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </div>

                                {{-- Current location --}}
                                <button
                                    type="button"
                                    @click="getCurrentLocation()"
                                    class="pointer-events-auto grid h-11 w-11 shrink-0 place-items-center rounded-xl border border-white/90 bg-white/[0.97] text-[color:var(--accent-orange)] shadow-[0_10px_26px_rgba(15,23,42,0.14)] backdrop-blur-xl transition hover:bg-[color:var(--accent-orange)] hover:text-white active:scale-95 disabled:opacity-50"
                                    title="{{ tr('Sync My Location') }}"
                                    aria-label="{{ tr('Sync My Location') }}"
                                    @if(!$canManageAttendance) disabled @endif
                                >
                                    <i class="fas fa-location-crosshairs" :class="isLocating ? 'fa-spin' : ''"></i>
                                </button>

                                {{-- Compact basemap menu --}}
                                <div
                                    class="relative pointer-events-auto"
                                    @click.outside="mapStyleMenuOpen = false"
                                >
                                    <button
                                        type="button"
                                        @click="mapStyleMenuOpen = !mapStyleMenuOpen"
                                        class="grid h-11 w-11 shrink-0 place-items-center rounded-xl border border-white/90 bg-white/[0.97] text-slate-600 shadow-[0_10px_26px_rgba(15,23,42,0.14)] backdrop-blur-xl transition hover:text-[color:var(--accent-orange)] active:scale-95"
                                        title="{{ app()->getLocale() === 'ar' ? 'نوع الخريطة' : 'Map style' }}"
                                        aria-label="{{ app()->getLocale() === 'ar' ? 'نوع الخريطة' : 'Map style' }}"
                                        :aria-expanded="mapStyleMenuOpen"
                                    >
                                        <i class="fas fa-layer-group"></i>
                                    </button>

                                    <div
                                        x-show="mapStyleMenuOpen"
                                        x-cloak
                                        x-transition:enter="transition ease-out duration-150"
                                        x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                                        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                                        class="absolute top-[52px] end-0 w-36 overflow-hidden rounded-xl border border-slate-100 bg-white/[0.98] p-1.5 shadow-[0_16px_36px_rgba(15,23,42,0.18)] backdrop-blur-xl"
                                    >
                                        <button
                                            type="button"
                                            @click="setMapStyle('streets')"
                                            class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-[9px] font-black transition"
                                            :class="mapStyle === 'streets' ? 'bg-[rgb(var(--accent-orange-rgb)/0.10)] text-[color:var(--accent-orange)]' : 'text-slate-500 hover:bg-slate-50'"
                                        >
                                            <i class="fas fa-road w-3"></i>
                                            {{ app()->getLocale() === 'ar' ? 'الشوارع' : 'Streets' }}
                                        </button>
                                        <button
                                            type="button"
                                            @click="setMapStyle('satellite')"
                                            class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-[9px] font-black transition"
                                            :class="mapStyle === 'satellite' ? 'bg-[rgb(var(--accent-orange-rgb)/0.10)] text-[color:var(--accent-orange)]' : 'text-slate-500 hover:bg-slate-50'"
                                        >
                                            <i class="fas fa-satellite w-3"></i>
                                            {{ app()->getLocale() === 'ar' ? 'القمر الصناعي' : 'Satellite' }}
                                        </button>
                                        <button
                                            type="button"
                                            @click="setMapStyle('light')"
                                            class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-[9px] font-black transition"
                                            :class="mapStyle === 'light' ? 'bg-[rgb(var(--accent-orange-rgb)/0.10)] text-[color:var(--accent-orange)]' : 'text-slate-500 hover:bg-slate-50'"
                                        >
                                            <i class="fas fa-pen-ruler w-3"></i>
                                            {{ app()->getLocale() === 'ar' ? 'هادئة للرسم' : 'Light drawing' }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Circle radius control --}}
                        <div x-show="boundaryType === 'circle'" x-transition class="absolute bottom-5 left-1/2 -translate-x-1/2 bg-white/95 backdrop-blur-md px-4 py-2.5 rounded-xl shadow-lg border border-gray-200 flex items-center gap-3 z-20 w-[72%] max-w-sm pointer-events-auto">
                            <span class="text-xs font-bold text-gray-600 whitespace-nowrap">{{ tr('Geofence Radius') }}</span>
                            <div class="flex-1 relative flex items-center">
                                <input type="range" class="w-full h-1.5 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-[color:var(--accent-orange)] ml-5"
                                    min="10" max="1000" step="10" x-model="radius" @input="updateCircle()"
                                    @if(!$canManageAttendance) disabled @endif
                                >
                            </div>
                            <span class="text-xs font-black text-[color:var(--accent-orange)] w-12 text-end" x-text="radius + 'm'"></span>
                        </div>

                        {{-- Polygon helper --}}
                        <div x-show="boundaryType === 'polygon'" x-transition class="absolute bottom-5 left-1/2 -translate-x-1/2 z-20 pointer-events-auto">
                            <div class="flex items-center gap-3 rounded-2xl border border-gray-200 bg-white/95 backdrop-blur-md px-4 py-2.5 shadow-xl">
                                <span class="text-[10px] font-bold text-gray-600">{{ tr('Use the polygon tool, then edit or remove the boundary from the map toolbar.') }}</span>
                                <button type="button" @click="clearPolygon()" class="text-[10px] font-black text-[color:var(--error)] hover:underline" @if(!$canManageAttendance) disabled @endif>
                                    {{ tr('Clear Boundary') }}
                                </button>
                            </div>
                        </div>

                    </div>

                    <div class="flex items-center justify-between text-[11px] text-gray-400 px-2">
                        <span x-show="boundaryType === 'circle'"><i class="fas fa-mouse-pointer me-1"></i> {{ tr('Click map to move pin') }}</span>
                        <span x-show="boundaryType === 'circle'"><i class="fas fa-layer-group me-1"></i> {{ tr('Adjust the radius from the slider') }}</span>
                        <span x-show="boundaryType === 'polygon'"><i class="fas fa-draw-polygon me-1"></i> {{ tr('Draw at least three boundary points') }}</span>
                        <span x-show="boundaryType === 'polygon'"><i class="fas fa-edit me-1"></i> {{ tr('Use edit mode to move boundary points') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </x-slot:content>
    <x-slot:footer>
        <div class="flex items-center justify-between w-full">
            <span class="text-xs text-gray-400">
                <i class="fas fa-satellite-dish me-1 animate-pulse text-[color:var(--success)]"></i> {{ tr('GPS Signal Active') }}
            </span>
            <div class="flex gap-3">
                <x-ui.secondary-button wire:click="$set('showGpsModal', false)">{{ tr('Cancel') }}</x-ui.secondary-button>
                @if($canManageAttendance)
                <x-ui.primary-button
                    wire:click="saveGpsLocation"
                    loading="saveGpsLocation"
                    class="!px-8 !rounded-xl shadow-[0_10px_20px_rgb(var(--accent-orange-rgb)/0.20)]"
                    :fullWidth="false"
                >
                    {{ $isEditing ? tr('Update Location') : tr('Save Location') }}
                </x-ui.primary-button>
                @endif
            </div>
        </div>
    </x-slot:footer>
</x-ui.modal>
