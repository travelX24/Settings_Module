<div>
    {{-- System Confirmation Dialog for Deletion --}}
    <x-ui.confirm-dialog 
        id="delete-location"
        title="{{ tr('Remove Location?') }}"
        message="{{ tr('Are you sure you want to remove this geographic location? This action cannot be undone.') }}"
        confirmText="{{ tr('Yes, Remove') }}"
        cancelText="{{ tr('Cancel') }}"
        confirmAction="wire:deleteGpsLocation(__ID__)"
        type="danger"
    />

    <x-ui.confirm-dialog 
        id="delete-penalty"
        title="{{ tr('Delete Policy?') }}"
        message="{{ tr('Are you sure you want to delete this penalty policy? This will stop applying its rules to violations.') }}"
        confirmText="{{ tr('Yes, Delete') }}"
        cancelText="{{ tr('Cancel') }}"
        confirmAction="wire:deletePenalty(__ID__)"
        type="danger"
    />

    <x-ui.confirm-dialog 
        id="delete-absence"
        title="{{ tr('Delete Absence Rule?') }}"
        message="{{ tr('Are you sure you want to delete this absence policy? Unapproved absences will no longer trigger this penalty.') }}"
        confirmText="{{ tr('Yes, Delete') }}"
        cancelText="{{ tr('Cancel') }}"
        confirmAction="wire:deleteAbsencePolicy(__ID__)"
        type="danger"
    />

    <x-ui.confirm-dialog 
        id="delete-group"
        title="{{ tr('Delete Group?') }}"
        message="{{ tr('Are you sure you want to delete this group? All employee assignments and custom policies will be lost.') }}"
        confirmText="{{ tr('Yes, Delete') }}"
        cancelText="{{ tr('Cancel') }}"
        confirmAction="wire:deleteGroup(__ID__)"
        type="danger"
    />

    <x-ui.confirm-dialog 
        id="delete-device"
        title="{{ tr('Remove Device?') }}"
        message="{{ tr('Are you sure you want to remove this device? It will no longer be able to record attendance.') }}"
        confirmText="{{ tr('Yes, Remove') }}"
        cancelText="{{ tr('Cancel') }}"
        confirmAction="wire:deleteDevice(__ID__)"
        type="danger"
    />

    <style>
        [x-cloak] { display: none !important; }
        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-track { background: #f9fafb; }
        ::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #d1d5db; }
    </style>

    {{-- Leaflet Assets (Only loaded if GPS is used, but safe to keep here or in GPS modal) --}}
    @push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <style>
        .leaflet-container { font-family: inherit; }
        .leaflet-bar { border: none !important; border-radius: 12px !important; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1) !important; }
        .leaflet-bar a { background: white !important; border: none !important; color: #64748b !important; }
        .leaflet-bar a:hover { color: #3b82f6 !important; }
    </style>
    @endpush

    @push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('mapPicker', (config) => ({
                lat: config.lat,
                lng: config.lng,
                radius: config.radius,
                show: config.show,
                
                // Metadata properties for auto-fill
                country: '...',
                city: '...',
                region: '...',
                address: '...',
                isFetching: false,

                map: null,
                marker: null,
                circle: null,
                
                initMap() {
                    this.$watch('show', (val) => {
                        if (val) {
                            setTimeout(() => {
                                if (!this.map) {
                                    this.createMap();
                                } else {
                                    this.map.invalidateSize();
                                }
                            }, 500);
                        }
                    });

                    this.$watch('radius', (val) => {
                        if (this.circle) this.circle.setRadius(val);
                    });
                },

                createMap() {
                    const defaultLat = this.lat || 15.37946;
                    const defaultLng = this.lng || 44.17241;
                    
                    this.map = L.map('map-picker-container', {
                        zoomControl: true,
                        attributionControl: false
                    }).setView([defaultLat, defaultLng], 13);

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(this.map);

                    this.marker = L.marker([defaultLat, defaultLng], { draggable: true }).addTo(this.map);
                    this.circle = L.circle([defaultLat, defaultLng], {
                        radius: this.radius || 100,
                        color: '#3b82f6',
                        fillColor: '#3b82f6',
                        fillOpacity: 0.15,
                        weight: 2
                    }).addTo(this.map);

                    this.map.on('click', (e) => {
                        this.updatePosition(e.latlng.lat, e.latlng.lng);
                    });

                    this.marker.on('dragend', () => {
                        const pos = this.marker.getLatLng();
                        this.updatePosition(pos.lat, pos.lng);
                    });

                    // Initial fetch
                    this.fetchAddress(defaultLat, defaultLng);
                },

                async updatePosition(lat, lng) {
                    this.lat = lat;
                    this.lng = lng;
                    this.marker.setLatLng([lat, lng]);
                    this.circle.setLatLng([lat, lng]);
                    await this.fetchAddress(lat, lng);
                },

                async fetchAddress(lat, lng) {
                    this.isFetching = true;
                    try {
                        // Call backend proxy via Livewire to avoid CORS/403 issues
                        const data = await this.$wire.reverseGeocode(lat, lng);
                        
                        if (data && data.address) {
                            this.country = data.address.country || '---';
                            this.city = data.address.city || data.address.town || data.address.state || '---';
                            this.region = data.address.suburb || data.address.neighbourhood || data.address.district || '---';
                            this.address = data.display_name.split(',').slice(0, 3).join(',') || '---';
                            
                            // Also update Livewire to store these if needed
                            this.$wire.set('gpsData.address', this.address);
                            this.$wire.set('gpsData.country', this.country);
                            this.$wire.set('gpsData.city', this.city);
                            this.$wire.set('gpsData.region', this.region);
                        }
                    } catch (e) {
                        console.error('Reverse Geocoding Error:', e);
                    } finally {
                        this.isFetching = false;
                    }
                },

                getCurrentLocation() {
                    if (!navigator.geolocation) {
                        alert("{{ tr('Geolocation is not supported by your browser.') }}");
                        return;
                    }

                    const options = {
                        enableHighAccuracy: true,
                        timeout: 5000,
                        maximumAge: 0
                    };

                    navigator.geolocation.getCurrentPosition(
                        (position) => {
                            const lat = position.coords.latitude;
                            const lng = position.coords.longitude;
                            
                            // تحريك الماركر والخريطة
                            this.updatePosition(lat, lng);
                            this.map.setView([lat, lng], 16);
                            
                            // إشعار نجاح
                            window.dispatchEvent(new CustomEvent('toast', { 
                                detail: { type: 'success', message: "{{ tr('Location synchronized successfully') }}" } 
                            }));
                        },
                        (error) => {
                            let msg = "{{ tr('Unable to retrieve location') }}";
                            switch(error.code) {
                                case error.PERMISSION_DENIED:
                                    msg = "{{ tr('Location access denied. Please enable location permissions in your browser.') }}";
                                    break;
                                case error.POSITION_UNAVAILABLE:
                                    msg = "{{ tr('Location information is unavailable.') }}";
                                    break;
                                case error.TIMEOUT:
                                    msg = "{{ tr('The request to get user location timed out.') }}";
                                    break;
                            }
                            alert(msg);
                        },
                        options
                    );
                }
            }));
        });
    </script>
    @endpush
</div>





