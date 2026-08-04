{{-- Leaflet and Leaflet-Geoman are bundled locally by HrWithModules/Vite. --}}
@pushOnce('styles', 'attendance-map-picker-styles')
<style>
    .leaflet-container {
        font-family: inherit;
        background: #e8edf2;
    }

    .athka-map-picker-shell .leaflet-tile-pane {
        filter: saturate(1.18) contrast(1.08) brightness(0.99);
        transition: filter 220ms ease, opacity 220ms ease;
    }

    .athka-map-picker-shell[data-map-style="light"] .leaflet-tile-pane {
        filter: saturate(1.08) contrast(1.1) brightness(0.98);
    }

    .athka-map-picker-shell[data-map-style="satellite"] .leaflet-tile-pane {
        filter: saturate(1.12) contrast(1.06) brightness(0.96);
    }

    .leaflet-bar {
        border: 1px solid rgb(226 232 240 / 0.9) !important;
        border-radius: 14px !important;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.14) !important;
    }

    .leaflet-bar a {
        width: 34px !important;
        height: 34px !important;
        line-height: 34px !important;
        background: rgb(255 255 255 / 0.96) !important;
        border: none !important;
        color: #475569 !important;
    }

    .leaflet-bar a:hover {
        color: var(--accent-orange) !important;
        background: white !important;
    }

    .leaflet-pm-toolbar .leaflet-buttons-control-button {
        background-color: rgb(255 255 255 / 0.96) !important;
    }

    .leaflet-control-attribution {
        margin: 0 8px 8px 0 !important;
        border-radius: 9px !important;
        background: rgb(255 255 255 / 0.78) !important;
        backdrop-filter: blur(8px);
        color: #64748b !important;
        font-size: 8px !important;
        padding: 2px 6px !important;
    }

    .athka-map-pin-wrapper {
        background: transparent;
        border: 0;
    }

    .athka-map-pin {
        position: relative;
        display: grid;
        width: 42px;
        height: 42px;
        place-items: center;
        transform: translateY(-8px);
    }

    .athka-map-pin::before {
        position: absolute;
        inset: 3px;
        content: '';
        border-radius: 50% 50% 50% 8px;
        background: linear-gradient(145deg, #b83d5d, #7f233b);
        box-shadow: 0 10px 22px rgba(127, 35, 59, 0.34), inset 0 1px 0 rgba(255,255,255,0.35);
        transform: rotate(-45deg);
        border: 2px solid rgba(255,255,255,0.95);
    }

    .athka-map-pin::after {
        position: absolute;
        width: 12px;
        height: 12px;
        content: '';
        border-radius: 999px;
        background: white;
        box-shadow: 0 0 0 5px rgba(255,255,255,0.18);
    }

    .athka-map-pin-pulse {
        position: absolute;
        bottom: -5px;
        width: 34px;
        height: 12px;
        border-radius: 999px;
        background: rgba(144, 55, 73, 0.2);
        filter: blur(1px);
        animation: athka-map-pin-pulse 1.8s ease-out infinite;
    }

    @keyframes athka-map-pin-pulse {
        0% { transform: scale(0.65); opacity: 0.9; }
        80%, 100% { transform: scale(1.35); opacity: 0; }
    }

    .athka-geofence-shape {
        filter: drop-shadow(0 6px 8px rgba(144, 55, 73, 0.22));
    }

    .athka-map-result-type {
        max-width: 110px;
    }
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
                boundaryType: config.boundaryType || 'circle',
                boundaryGeoJson: config.boundaryGeoJson || '',
                show: config.show,

                country: '...',
                city: '...',
                region: '...',
                address: '...',
                geocodingError: '',
                isFetching: false,

                searchQuery: '',
                searchResults: [],
                searchHasRun: false,
                isSearching: false,
                isLocating: false,
                currentAccuracy: null,
                mapStyleMenuOpen: false,
                mapStyle: (() => {
                    try {
                        return localStorage.getItem('athka-map-style') || 'streets';
                    } catch (_) {
                        return 'streets';
                    }
                })(),

                map: null,
                baseLayers: {},
                activeBaseLayer: null,
                activeLabelsLayer: null,
                marker: null,
                circle: null,
                polygonLayer: null,
                polygonPointCount: 0,
                _addressFetchKey: null,
                _loadedBoundary: null,
                _syncingBoundary: false,

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

                        if (this.boundaryType === 'polygon' && this.polygonLayer) {
                            this.fitPolygonBounds();
                        } else {
                            this.map.setView([currentLat, currentLng], 15);
                        }

                        if (this.marker) {
                            this.marker.setLatLng([currentLat, currentLng]);
                        }

                        if (this.circle) {
                            this.circle.setLatLng([currentLat, currentLng]);
                            this.circle.setRadius(this.normalizeRadius(this.radius));
                        }

                        this.loadPolygonFromGeoJson();
                        this.syncBoundaryMode();
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
                    this.$watch('boundaryType', () => this.syncBoundaryMode());
                    this.$watch('boundaryGeoJson', (value) => {
                        if (!this._syncingBoundary && value !== this._loadedBoundary) {
                            this.loadPolygonFromGeoJson(true);
                            this.syncBoundaryMode();
                        }
                    });

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
                        this._mapAssetRetryCount = (this._mapAssetRetryCount || 0) + 1;

                        if (this._mapAssetRetryCount === 20) {
                            console.error(
                                'Athka map assets did not load from the local Vite bundle.',
                                window.__athkaMapAssets || null
                            );
                        }

                        setTimeout(() => this.createMap(), 250);
                        return;
                    }

                    this._mapAssetRetryCount = 0;

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
                        attributionControl: false,
                        preferCanvas: true,
                        zoomSnap: 0.5,
                        zoomDelta: 0.5
                    }).setView([defaultLat, defaultLng], 15);

                    this.map.createPane('athkaBasePane');
                    this.map.getPane('athkaBasePane').style.zIndex = 200;
                    this.map.createPane('athkaGeofencePane');
                    this.map.getPane('athkaGeofencePane').style.zIndex = 430;
                    this.map.createPane('athkaMarkerPane');
                    this.map.getPane('athkaMarkerPane').style.zIndex = 610;
                    this.map.createPane('athkaLabelsPane');
                    this.map.getPane('athkaLabelsPane').style.zIndex = 650;
                    this.map.getPane('athkaLabelsPane').style.pointerEvents = 'none';

                    L.control.attribution({
                        position: 'bottomright',
                        prefix: false
                    }).addTo(this.map);

                    this.initializeBaseLayers();
                    this.setMapStyle(this.mapStyle, false);

                    this.marker = L.marker([defaultLat, defaultLng], {
                        draggable: true,
                        icon: this.createMarkerIcon(),
                        pane: 'athkaMarkerPane',
                        riseOnHover: true
                    }).addTo(this.map);
                    this.circle = L.circle([defaultLat, defaultLng], {
                        radius: this.normalizeRadius(this.radius),
                        color: '#8f2945',
                        fillColor: '#b83d5d',
                        fillOpacity: 0.19,
                        weight: 3,
                        opacity: 0.96,
                        className: 'athka-geofence-shape',
                        pane: 'athkaGeofencePane'
                    }).addTo(this.map);

                    this.map.on('click', (event) => {
                        if (this.boundaryType !== 'circle') {
                            return;
                        }

                        this.searchResults = [];
                        this.isSearching = false;
                        this.updatePosition(event.latlng.lat, event.latlng.lng);
                    });

                    this.marker.on('dragend', () => {
                        if (this.boundaryType !== 'circle') {
                            return;
                        }

                        const position = this.marker.getLatLng();
                        this.updatePosition(position.lat, position.lng);
                    });

                    this.registerPolygonEvents();
                    this.loadPolygonFromGeoJson(true);
                    this.syncBoundaryMode();
                    this.fetchAddress(defaultLat, defaultLng);
                },

                createMarkerIcon() {
                    return L.divIcon({
                        className: 'athka-map-pin-wrapper',
                        html: '<span class="athka-map-pin"><span class="athka-map-pin-pulse"></span></span>',
                        iconSize: [42, 48],
                        iconAnchor: [21, 42]
                    });
                },

                initializeBaseLayers() {
                    const cartoAttribution = '&copy; OpenStreetMap contributors &copy; CARTO';
                    const esriAttribution = 'Tiles &copy; Esri';
                    const cartoOptions = {
                        maxZoom: 20,
                        minZoom: 2,
                        subdomains: 'abcd',
                        detectRetina: true,
                        pane: 'athkaBasePane',
                        attribution: cartoAttribution,
                        crossOrigin: true
                    };
                    const labelOptions = {
                        ...cartoOptions,
                        pane: 'athkaLabelsPane'
                    };

                    this.baseLayers = {
                        streets: {
                            base: L.tileLayer(
                                'https://{s}.basemaps.cartocdn.com/rastertiles/voyager_nolabels/{z}/{x}/{y}{r}.png',
                                cartoOptions
                            ),
                            labels: L.tileLayer(
                                'https://{s}.basemaps.cartocdn.com/rastertiles/voyager_only_labels/{z}/{x}/{y}{r}.png',
                                labelOptions
                            )
                        },
                        light: {
                            base: L.tileLayer(
                                'https://{s}.basemaps.cartocdn.com/light_nolabels/{z}/{x}/{y}{r}.png',
                                cartoOptions
                            ),
                            labels: L.tileLayer(
                                'https://{s}.basemaps.cartocdn.com/light_only_labels/{z}/{x}/{y}{r}.png',
                                labelOptions
                            )
                        },
                        satellite: {
                            base: L.tileLayer(
                                'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
                                {
                                    maxZoom: 20,
                                    minZoom: 2,
                                    detectRetina: false,
                                    pane: 'athkaBasePane',
                                    attribution: esriAttribution,
                                    crossOrigin: true
                                }
                            ),
                            labels: L.tileLayer(
                                'https://{s}.basemaps.cartocdn.com/rastertiles/voyager_only_labels/{z}/{x}/{y}{r}.png',
                                labelOptions
                            )
                        }
                    };
                },

                setMapStyle(style, announce = true) {
                    if (!this.map || !this.baseLayers?.[style]) {
                        style = 'streets';
                    }

                    if (!this.map || !this.baseLayers?.[style]) {
                        return;
                    }

                    if (this.activeBaseLayer && this.map.hasLayer(this.activeBaseLayer)) {
                        this.map.removeLayer(this.activeBaseLayer);
                    }

                    if (this.activeLabelsLayer && this.map.hasLayer(this.activeLabelsLayer)) {
                        this.map.removeLayer(this.activeLabelsLayer);
                    }

                    const selected = this.baseLayers[style];
                    this.activeBaseLayer = selected.base;
                    this.activeLabelsLayer = selected.labels;
                    this.activeBaseLayer.addTo(this.map);
                    this.activeLabelsLayer.addTo(this.map);
                    this.mapStyle = style;

                    const container = this.getMapContainer();
                    const shell = container?.closest('.athka-map-picker-shell');

                    if (shell) {
                        shell.dataset.mapStyle = style;
                    }

                    try {
                        localStorage.setItem('athka-map-style', style);
                    } catch (_) {
                    }

                    this.mapStyleMenuOpen = false;

                    if (announce) {
                        this.map.invalidateSize();
                    }
                },

                getSearchCenter() {
                    const center = this.map?.getCenter?.();

                    if (center && Number.isFinite(center.lat) && Number.isFinite(center.lng)) {
                        return { lat: center.lat, lng: center.lng };
                    }

                    return {
                        lat: this.normalizeCoordinate(this.lat, 15.37946),
                        lng: this.normalizeCoordinate(this.lng, 44.17241)
                    };
                },

                getSearchBounds() {
                    const bounds = this.map?.getBounds?.();

                    if (!bounds?.isValid?.()) {
                        return null;
                    }

                    return {
                        south: bounds.getSouth(),
                        west: bounds.getWest(),
                        north: bounds.getNorth(),
                        east: bounds.getEast()
                    };
                },

                clearPlaceSearch() {
                    this.searchQuery = '';
                    this.searchResults = [];
                    this.searchHasRun = false;
                    this.isSearching = false;
                    this._searchRequestId = (this._searchRequestId || 0) + 1;
                },

                closePlaceSearchResults() {
                    this.searchResults = [];
                    this.searchHasRun = false;
                },

                formatResultDistance(result) {
                    const distance = Number(result?.distance_km);

                    if (!Number.isFinite(distance)) {
                        return '';
                    }

                    if (distance < 1) {
                        return `${Math.max(1, Math.round(distance * 1000))} m`;
                    }

                    return `${distance.toFixed(distance < 10 ? 1 : 0)} km`;
                },

                resultTypeLabel(result) {
                    const value = `${result?.type || ''} ${result?.category || ''}`.toLowerCase();
                    const labels = {
                        coordinates: "{{ tr('Coordinates') }}",
                        country: "{{ tr('Country') }}",
                        state: "{{ tr('Region') }}",
                        province: "{{ tr('Region') }}",
                        region: "{{ tr('Region') }}",
                        governorate: "{{ tr('Governorate') }}",
                        administrative: "{{ tr('Administrative Area') }}",
                        district: "{{ tr('District') }}",
                        municipality: "{{ tr('Municipality') }}",
                        locality: "{{ tr('Area') }}",
                        residential: "{{ tr('Residential Area') }}",
                        neighbourhood: "{{ tr('Neighborhood') }}",
                        suburb: "{{ tr('Neighborhood') }}",
                        quarter: "{{ tr('Neighborhood') }}",
                        road: "{{ tr('Street') }}",
                        street: "{{ tr('Street') }}",
                        city: "{{ tr('City') }}",
                        town: "{{ tr('City') }}",
                        village: "{{ tr('Village') }}",
                        hospital: "{{ tr('Hospital') }}",
                        clinic: "{{ tr('Clinic') }}",
                        pharmacy: "{{ tr('Pharmacy') }}",
                        school: "{{ tr('School') }}",
                        university: "{{ tr('University') }}",
                        restaurant: "{{ tr('Restaurant') }}",
                        cafe: "{{ tr('Cafe') }}",
                        hotel: "{{ tr('Hotel') }}",
                        supermarket: "{{ tr('Store') }}",
                        mall: "{{ tr('Shopping Center') }}",
                        marketplace: "{{ tr('Market') }}",
                        company: "{{ tr('Company') }}",
                        commercial: "{{ tr('Business') }}",
                        shop: "{{ tr('Business') }}",
                        office: "{{ tr('Company') }}",
                        mosque: "{{ tr('Mosque') }}",
                        bank: "{{ tr('Bank') }}",
                        fuel: "{{ tr('Fuel Station') }}",
                        amenity: "{{ tr('Point of Interest') }}"
                    };

                    for (const [needle, label] of Object.entries(labels)) {
                        if (value.includes(needle)) {
                            return label;
                        }
                    }

                    return "{{ tr('Place') }}";
                },

                resultIconClass(result) {
                    const value = `${result?.type || ''} ${result?.category || ''}`.toLowerCase();

                    if (value.includes('coordinates')) return 'fa-crosshairs';
                    if (value.includes('country')) return 'fa-earth-asia';
                    if (value.includes('state') || value.includes('province') || value.includes('region') || value.includes('governorate') || value.includes('administrative')) return 'fa-map-location-dot';
                    if (value.includes('district') || value.includes('municipality') || value.includes('locality')) return 'fa-map';
                    if (value.includes('hospital') || value.includes('clinic')) return 'fa-hospital';
                    if (value.includes('pharmacy')) return 'fa-prescription-bottle-medical';
                    if (value.includes('school') || value.includes('university')) return 'fa-graduation-cap';
                    if (value.includes('restaurant') || value.includes('cafe')) return 'fa-utensils';
                    if (value.includes('hotel') || value.includes('tourism')) return 'fa-hotel';
                    if (value.includes('road') || value.includes('street')) return 'fa-road';
                    if (value.includes('neighbourhood') || value.includes('suburb') || value.includes('quarter')) return 'fa-map';
                    if (value.includes('city') || value.includes('town') || value.includes('village')) return 'fa-city';
                    if (value.includes('mosque')) return 'fa-mosque';
                    if (value.includes('bank')) return 'fa-building-columns';
                    if (value.includes('fuel')) return 'fa-gas-pump';
                    if (value.includes('shop') || value.includes('office') || value.includes('company') || value.includes('commercial')) return 'fa-store';
                    if (value.includes('house') || value.includes('building')) return 'fa-building';

                    return 'fa-map-marker-alt';
                },

                registerPolygonEvents() {
                    if (!this.map) {
                        return;
                    }

                    if (!this.map.pm) {
                        setTimeout(() => this.registerPolygonEvents(), 250);
                        return;
                    }

                    this.map.off('pm:create');
                    this.map.on('pm:create', (event) => {
                        if (event.shape !== 'Polygon') {
                            this.map.removeLayer(event.layer);
                            return;
                        }

                        this.setPolygonLayer(event.layer);
                        this.capturePolygonBoundary();
                        this.fitPolygonBounds();
                    });

                    this.map.off('pm:edit');
                    this.map.on('pm:edit', () => this.capturePolygonBoundary());

                    this.map.off('pm:remove');
                    this.map.on('pm:remove', (event) => {
                        if (event.layer === this.polygonLayer) {
                            this.polygonLayer = null;
                            this.polygonPointCount = 0;
                            this.setBoundaryGeoJson('');
                        }
                    });
                },

                syncBoundaryMode() {
                    if (!this.map) {
                        return;
                    }

                    if (this.boundaryType === 'polygon') {
                        if (this.circle && this.map.hasLayer(this.circle)) {
                            this.map.removeLayer(this.circle);
                        }

                        if (this.marker && this.map.hasLayer(this.marker)) {
                            this.map.removeLayer(this.marker);
                        }

                        if (this.polygonLayer && !this.map.hasLayer(this.polygonLayer)) {
                            this.polygonLayer.addTo(this.map);
                        }

                        this.enablePolygonControls();
                        this.fitPolygonBounds();
                        return;
                    }

                    this.disablePolygonControls();

                    if (this.polygonLayer && this.map.hasLayer(this.polygonLayer)) {
                        this.map.removeLayer(this.polygonLayer);
                    }

                    if (this.marker && !this.map.hasLayer(this.marker)) {
                        this.marker.addTo(this.map);
                    }

                    if (this.circle && !this.map.hasLayer(this.circle)) {
                        this.circle.addTo(this.map);
                    }

                    this.updateCircle();
                },

                enablePolygonControls() {
                    if (!this.map?.pm) {
                        setTimeout(() => this.enablePolygonControls(), 250);
                        return;
                    }

                    this.map.pm.removeControls();
                    this.map.pm.addControls({
                        position: 'topleft',
                        drawMarker: false,
                        drawCircleMarker: false,
                        drawPolyline: false,
                        drawRectangle: false,
                        drawCircle: false,
                        drawText: false,
                        drawPolygon: true,
                        editMode: true,
                        dragMode: false,
                        cutPolygon: false,
                        removalMode: true,
                        rotateMode: false
                    });

                    this.map.pm.setGlobalOptions({
                        snappable: true,
                        snapDistance: 20,
                        allowSelfIntersection: false,
                        templineStyle: { color: '#903749' },
                        hintlineStyle: { color: '#903749', dashArray: [5, 5] },
                        pathOptions: {
                            color: '#8f2945',
                            fillColor: '#b83d5d',
                            fillOpacity: 0.2,
                            weight: 3,
                            opacity: 0.98,
                            className: 'athka-geofence-shape',
                            pane: 'athkaGeofencePane'
                        }
                    });
                },

                disablePolygonControls() {
                    if (this.map?.pm) {
                        this.map.pm.disableDraw();
                        this.map.pm.disableGlobalEditMode();
                        this.map.pm.disableGlobalRemovalMode();
                        this.map.pm.removeControls();
                    }
                },

                setPolygonLayer(layer) {
                    if (this.polygonLayer && this.map?.hasLayer(this.polygonLayer)) {
                        this.map.removeLayer(this.polygonLayer);
                    }

                    this.polygonLayer = layer;

                    if (this.map && !this.map.hasLayer(layer)) {
                        layer.addTo(this.map);
                    }

                    if (layer.setStyle) {
                        layer.setStyle({
                            color: '#8f2945',
                            fillColor: '#b83d5d',
                            fillOpacity: 0.2,
                            weight: 3,
                            opacity: 0.98,
                            className: 'athka-geofence-shape'
                        });
                    }

                    this.updatePolygonPointCount();
                },

                loadPolygonFromGeoJson(force = false) {
                    if (!this.map) {
                        return;
                    }

                    const rawValue = typeof this.boundaryGeoJson === 'string'
                        ? this.boundaryGeoJson
                        : JSON.stringify(this.boundaryGeoJson || '');

                    if (!force && rawValue === this._loadedBoundary) {
                        return;
                    }

                    this._loadedBoundary = rawValue;

                    if (this.polygonLayer && this.map.hasLayer(this.polygonLayer)) {
                        this.map.removeLayer(this.polygonLayer);
                    }

                    this.polygonLayer = null;
                    this.polygonPointCount = 0;

                    if (!rawValue) {
                        return;
                    }

                    try {
                        const parsed = JSON.parse(rawValue);
                        const geometry = parsed?.type === 'Feature' ? parsed.geometry : parsed;

                        if (!geometry || geometry.type !== 'Polygon') {
                            return;
                        }

                        const geoJsonLayer = L.geoJSON(geometry, {
                            style: {
                                color: '#8f2945',
                                fillColor: '#b83d5d',
                                fillOpacity: 0.2,
                                weight: 3,
                                opacity: 0.98,
                                className: 'athka-geofence-shape',
                                pane: 'athkaGeofencePane'
                            }
                        });

                        const layers = geoJsonLayer.getLayers();

                        if (layers.length > 0) {
                            this.setPolygonLayer(layers[0]);
                        }
                    } catch (error) {
                        console.error('Invalid boundary GeoJSON:', error);
                    }
                },

                capturePolygonBoundary() {
                    if (!this.polygonLayer) {
                        this.setBoundaryGeoJson('');
                        this.polygonPointCount = 0;
                        return;
                    }

                    const feature = this.polygonLayer.toGeoJSON();
                    const geometry = feature?.geometry;

                    if (!geometry || geometry.type !== 'Polygon') {
                        this.setBoundaryGeoJson('');
                        return;
                    }

                    this.setBoundaryGeoJson(JSON.stringify(geometry));
                    this.updatePolygonPointCount();

                    const center = this.polygonLayer.getBounds().getCenter();
                    this.lat = center.lat;
                    this.lng = center.lng;

                    this.fetchAddress(center.lat, center.lng);
                },

                setBoundaryGeoJson(value) {
                    this._syncingBoundary = true;
                    this.boundaryGeoJson = value;
                    this._loadedBoundary = value;
                    this.$wire.set('gpsData.boundary_geojson', value);

                    setTimeout(() => {
                        this._syncingBoundary = false;
                    }, 0);
                },

                updatePolygonPointCount() {
                    if (!this.polygonLayer) {
                        this.polygonPointCount = 0;
                        return;
                    }

                    const latLngs = this.polygonLayer.getLatLngs();
                    const outerRing = Array.isArray(latLngs?.[0]) ? latLngs[0] : [];
                    this.polygonPointCount = outerRing.length;
                },

                fitPolygonBounds() {
                    if (this.boundaryType !== 'polygon' || !this.polygonLayer || !this.map) {
                        return;
                    }

                    const bounds = this.polygonLayer.getBounds();

                    if (bounds?.isValid()) {
                        this.map.fitBounds(bounds, { padding: [30, 30], maxZoom: 18 });
                    }
                },

                clearPolygon() {
                    if (this.polygonLayer && this.map?.hasLayer(this.polygonLayer)) {
                        this.map.removeLayer(this.polygonLayer);
                    }

                    this.polygonLayer = null;
                    this.polygonPointCount = 0;
                    this.setBoundaryGeoJson('');
                },

                updateCircle() {
                    const radius = this.normalizeRadius(this.radius);
                    this.radius = radius;

                    if (this.circle) {
                        this.circle.setRadius(radius);
                    }
                },

                async searchLocation() {
                    const query = this.searchQuery.trim();

                    if (query.length < 2) {
                        this.searchResults = [];
                        this.searchHasRun = false;
                        this.isSearching = false;
                        return;
                    }

                    const requestId = (this._searchRequestId || 0) + 1;
                    this._searchRequestId = requestId;
                    this.isSearching = true;
                    this.searchHasRun = true;
                    const center = this.getSearchCenter();

                    try {
                        const results = await this.$wire.searchLocation(
                            query,
                            center.lat,
                            center.lng,
                            this.city,
                            this.country,
                            this.getSearchBounds()
                        );

                        if (requestId !== this._searchRequestId) {
                            return;
                        }

                        this.searchResults = Array.isArray(results) ? results : [];
                    } catch (error) {
                        if (requestId === this._searchRequestId) {
                            this.searchResults = [];
                        }

                        console.error('Search Error:', error);
                    } finally {
                        if (requestId === this._searchRequestId) {
                            this.isSearching = false;
                        }
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
                        const boundingBox = Array.isArray(location.boundingbox)
                            ? location.boundingbox.map(Number)
                            : null;

                        if (boundingBox?.length === 4 && boundingBox.every(Number.isFinite)) {
                            const bounds = L.latLngBounds(
                                [boundingBox[0], boundingBox[2]],
                                [boundingBox[1], boundingBox[3]]
                            );

                            if (bounds.isValid()) {
                                this.map.fitBounds(bounds, { padding: [36, 36], maxZoom: 18 });
                            } else {
                                this.map.flyTo([lat, lng], 17, { duration: 0.65 });
                            }
                        } else {
                            this.map.flyTo([lat, lng], 17, { duration: 0.65 });
                        }
                    }

                    this.searchResults = [];
                    this.searchHasRun = false;
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
                    this.geocodingError = '';

                    try {
                        const data = await this.$wire.reverseGeocode(lat, lng);

                        if (this._addressFetchKey !== requestKey) {
                            return;
                        }

                        if (!data || !data.address) {
                            this.country = '---';
                            this.city = '---';
                            this.region = '---';
                            this.address = '---';
                            this.geocodingError = "{{ tr('Location details are temporarily unavailable. You can still save the coordinates.') }}";
                            return;
                        }

                        this.country = data.address.country || '---';
                        this.city = data.address.major_city
                            || data.address.city
                            || data.address.town
                            || data.address.state
                            || '---';
                        this.region = data.address.suburb
                            || data.address.neighbourhood
                            || data.address.district
                            || data.address.quarter
                            || data.address.locality
                            || data.address.village
                            || data.address.road
                            || '---';
                        this.address = data.display_name
                            ? data.display_name.split(',').slice(0, 3).join(',')
                            : [this.region, this.city, this.country]
                                .filter((value) => value && value !== '---')
                                .join(', ') || '---';

                        this.$wire.set('gpsData.address', this.address);
                        this.$wire.set('gpsData.country', this.country);
                        this.$wire.set('gpsData.city', this.city);
                        this.$wire.set('gpsData.region', this.region);
                    } catch (error) {
                        if (this._addressFetchKey === requestKey) {
                            this.country = '---';
                            this.city = '---';
                            this.region = '---';
                            this.address = '---';
                            this.geocodingError = "{{ tr('Location details are temporarily unavailable. You can still save the coordinates.') }}";
                        }

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

                    if (this.isLocating) {
                        return;
                    }

                    this.isLocating = true;
                    this.currentAccuracy = null;

                    let bestPosition = null;
                    let watchId = null;
                    let finished = false;

                    const clearWatch = () => {
                        if (watchId !== null) {
                            navigator.geolocation.clearWatch(watchId);
                            watchId = null;
                        }
                    };

                    const applyPosition = (position) => {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        const accuracy = Math.round(position.coords.accuracy || 0);

                        this.currentAccuracy = accuracy;
                        this.updatePosition(lat, lng);

                        if (this.map) {
                            this.map.setView([lat, lng], accuracy > 0 && accuracy <= 50 ? 18 : 16);
                        }

                        window.dispatchEvent(new CustomEvent('toast', {
                            detail: {
                                type: 'success',
                                message: "{{ tr('Location synchronized successfully') }}"
                            }
                        }));
                    };

                    const finish = (position) => {
                        if (finished) {
                            return;
                        }

                        finished = true;
                        clearWatch();
                        this.isLocating = false;

                        if (position) {
                            applyPosition(position);
                            return;
                        }

                        alert("{{ tr('Unable to retrieve location') }}");
                    };

                    const handlePosition = (position) => {
                        const accuracy = position.coords.accuracy || Number.MAX_SAFE_INTEGER;
                        const bestAccuracy = bestPosition?.coords?.accuracy || Number.MAX_SAFE_INTEGER;

                        if (!bestPosition || accuracy < bestAccuracy) {
                            bestPosition = position;
                        }

                        if (accuracy <= 30) {
                            finish(position);
                        }
                    };

                    const handleError = (error) => {
                        if (bestPosition) {
                            finish(bestPosition);
                            return;
                        }

                        clearWatch();
                        this.isLocating = false;

                        let message = "{{ tr('Unable to retrieve location') }}";

                        if (error.code === error.PERMISSION_DENIED) {
                            message = "{{ tr('Location access denied. Please enable location permissions in your browser.') }}";
                        } else if (error.code === error.POSITION_UNAVAILABLE) {
                            message = "{{ tr('Location information is unavailable.') }}";
                        } else if (error.code === error.TIMEOUT) {
                            message = "{{ tr('The request to get user location timed out.') }}";
                        }

                        alert(message);
                    };

                    watchId = navigator.geolocation.watchPosition(
                        handlePosition,
                        handleError,
                        {
                            enableHighAccuracy: true,
                            timeout: 15000,
                            maximumAge: 0
                        }
                    );

                    setTimeout(() => {
                        if (bestPosition) {
                            finish(bestPosition);
                        }
                    }, 10000);

                    setTimeout(() => finish(null), 16000);
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
