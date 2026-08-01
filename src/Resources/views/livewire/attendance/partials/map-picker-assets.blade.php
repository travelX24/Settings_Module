{{-- Leaflet Assets (shared by GPS modals) --}}
@pushOnce('styles', 'attendance-map-picker-styles')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" integrity="sha512-h9FcoyWjHcOcmEVkxOfTLnmZFWIH0iZhVI1ZXJyTtLirkiVNUucGVMxvacunWScRBZPGXLNV4CYAio4mYRLOFQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
<style>
    .leaflet-container { font-family: inherit; }
    .leaflet-bar { border: none !important; border-radius: 12px !important; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1) !important; }
    .leaflet-bar a { background: white !important; border: none !important; color: #64748b !important; }
    .leaflet-bar a:hover { color: var(--accent-orange) !important; }
</style>
@endpushOnce

<script>
    (function () {
        if (window.mapPicker) {
            return;
        }

        window.mapPicker = function (config) {
            return {
                lat: config.lat,
                lng: config.lng,
                radius: config.radius || 100,
                show: config.show,

                country: '...',
                city: '...',
                region: '...',
                address: '...',
                isFetching: false,

                searchQuery: '',
                searchResults: [],
                isSearching: false,

                map: null,
                marker: null,
                circle: null,
                _addressFetchKey: null,

                initMap() {
                    const setup = () => {
                        if (!this.show) {
                            return;
                        }

                        if (!this.map) {
                            this.createMap();
                        }

                        if (!this.map) {
                            return;
                        }

                        const currentLat = this.normalizeCoordinate(this.lat, 15.37946);
                        const currentLng = this.normalizeCoordinate(this.lng, 44.17241);

                        this.map.invalidateSize();
                        this.map.setView([currentLat, currentLng], 15);

                        if (this.marker) {
                            this.marker.setLatLng([currentLat, currentLng]);
                        }

                        if (this.circle) {
                            this.circle.setLatLng([currentLat, currentLng]);
                            this.circle.setRadius(this.normalizeRadius(this.radius));
                        }

                        this.fetchAddress(currentLat, currentLng);
                    };

                    this.$watch('show', (value) => {
                        if (value) {
                            setTimeout(setup, 100);
                            setTimeout(setup, 400);
                            setTimeout(setup, 800);
                        }
                    });

                    this.$watch('radius', () => this.updateCircle());

                    if (this.show) {
                        this.$nextTick(() => setTimeout(setup, 100));
                    }
                },

                normalizeCoordinate(value, fallback) {
                    const parsed = parseFloat(value);
                    return Number.isFinite(parsed) ? parsed : fallback;
                },

                normalizeRadius(value) {
                    const parsed = parseInt(value, 10);
                    return Number.isFinite(parsed) && parsed >= 10 ? parsed : 100;
                },

                getMapContainer() {
                    return this.$root.querySelector('[data-map-picker-container]')
                        || this.$root.querySelector('#map-picker-container')
                        || this.$root.querySelector('#map');
                },

                createMap() {
                    if (typeof L === 'undefined') {
                        setTimeout(() => this.createMap(), 250);
                        return;
                    }

                    const container = this.getMapContainer();
                    if (!container) {
                        return;
                    }

                    const defaultLat = this.normalizeCoordinate(this.lat, 15.37946);
                    const defaultLng = this.normalizeCoordinate(this.lng, 44.17241);

                    if (this.map) {
                        this.map.remove();
                    }

                    if (container._leaflet_id) {
                        container._leaflet_id = null;
                    }

                    this.map = L.map(container, {
                        zoomControl: true,
                        attributionControl: false
                    }).setView([defaultLat, defaultLng], 15);

                    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
                        maxZoom: 19,
                        subdomains: 'abcd'
                    }).addTo(this.map);

                    this.marker = L.marker([defaultLat, defaultLng], { draggable: true }).addTo(this.map);
                    this.circle = L.circle([defaultLat, defaultLng], {
                        radius: this.normalizeRadius(this.radius),
                        color: '#903749',
                        fillColor: '#903749',
                        fillOpacity: 0.15,
                        weight: 2
                    }).addTo(this.map);

                    this.map.on('click', (event) => {
                        this.searchResults = [];
                        this.isSearching = false;
                        this.updatePosition(event.latlng.lat, event.latlng.lng);
                    });

                    this.marker.on('dragend', () => {
                        const position = this.marker.getLatLng();
                        this.updatePosition(position.lat, position.lng);
                    });

                    this.fetchAddress(defaultLat, defaultLng);
                },

                updateCircle() {
                    const radius = this.normalizeRadius(this.radius);
                    this.radius = radius;

                    if (this.circle) {
                        this.circle.setRadius(radius);
                    }
                },

                async searchLocation() {
                    if (this.searchQuery.length < 2) {
                        this.searchResults = [];
                        this.isSearching = false;
                        return;
                    }

                    this.isSearching = true;

                    try {
                        this.searchResults = await this.$wire.searchLocation(this.searchQuery);
                    } catch (error) {
                        console.error('Search Error:', error);
                    } finally {
                        this.isSearching = false;
                    }
                },

                selectLocation(location) {
                    const lat = parseFloat(location.lat);
                    const lng = parseFloat(location.lon);

                    if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                        return;
                    }

                    this.updatePosition(lat, lng);

                    if (this.map) {
                        this.map.setView([lat, lng], 16);
                    }

                    this.searchResults = [];
                    this.searchQuery = location.display_name;
                },

                async updatePosition(lat, lng) {
                    this.lat = lat;
                    this.lng = lng;

                    if (this.marker) {
                        this.marker.setLatLng([lat, lng]);
                    }

                    if (this.circle) {
                        this.circle.setLatLng([lat, lng]);
                    }

                    await this.fetchAddress(lat, lng);
                },

                async fetchAddress(lat, lng) {
                    const requestKey = `${lat},${lng}`;
                    this._addressFetchKey = requestKey;
                    this.isFetching = true;

                    try {
                        const data = await this.$wire.reverseGeocode(lat, lng);

                        if (this._addressFetchKey !== requestKey || !data || !data.address) {
                            return;
                        }

                        this.country = data.address.country || '---';
                        this.city = data.address.city || data.address.town || data.address.state || '---';
                        this.region = data.address.suburb || data.address.neighbourhood || data.address.district || '---';
                        this.address = data.display_name ? data.display_name.split(',').slice(0, 3).join(',') : '---';

                        this.$wire.set('gpsData.address', this.address);
                        this.$wire.set('gpsData.country', this.country);
                        this.$wire.set('gpsData.city', this.city);
                        this.$wire.set('gpsData.region', this.region);
                    } catch (error) {
                        console.error('Reverse Geocoding Error:', error);
                    } finally {
                        if (this._addressFetchKey === requestKey) {
                            this.isFetching = false;
                        }
                    }
                },

                getCurrentLocation() {
                    if (!navigator.geolocation) {
                        alert("{{ tr('Geolocation is not supported by your browser.') }}");
                        return;
                    }

                    navigator.geolocation.getCurrentPosition(
                        (position) => {
                            const lat = position.coords.latitude;
                            const lng = position.coords.longitude;

                            this.updatePosition(lat, lng);

                            if (this.map) {
                                this.map.setView([lat, lng], 16);
                            }

                            window.dispatchEvent(new CustomEvent('toast', {
                                detail: { type: 'success', message: "{{ tr('Location synchronized successfully') }}" }
                            }));
                        },
                        (error) => {
                            let message = "{{ tr('Unable to retrieve location') }}";

                            if (error.code === error.PERMISSION_DENIED) {
                                message = "{{ tr('Location access denied. Please enable location permissions in your browser.') }}";
                            } else if (error.code === error.POSITION_UNAVAILABLE) {
                                message = "{{ tr('Location information is unavailable.') }}";
                            } else if (error.code === error.TIMEOUT) {
                                message = "{{ tr('The request to get user location timed out.') }}";
                            }

                            alert(message);
                        },
                        {
                            enableHighAccuracy: true,
                            timeout: 5000,
                            maximumAge: 0
                        }
                    );
                }
            };
        };

        const registerMapPicker = function () {
            if (!window.Alpine || window.__attendanceMapPickerRegistered) {
                return;
            }

            window.Alpine.data('mapPicker', window.mapPicker);
            window.__attendanceMapPickerRegistered = true;
        };

        document.addEventListener('alpine:init', registerMapPicker, { once: true });
        registerMapPicker();
    })();
</script>

@pushOnce('scripts', 'attendance-map-picker-scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js" integrity="sha512-BwHfrr4c9kmRkLh6RiK25PNL0YXqs1PWo48188d60uPpZE+dbwfTXhnTly5fd4sQqOmauTI77EHYqUXjkXVO9A==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
@endpushOnce
