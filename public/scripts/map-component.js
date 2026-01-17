class MapComponent {
    constructor(elementId, options = {}) {
        this.elementId = elementId;
        this.mode = options.mode || 'view';
        this.initialLocation = options.initialLocation || [52.2297, 21.0122]; // Warsaw default
        this.zoom = options.zoom || 12;
        this.marker = null;
        this.map = null;
        this.onLocationChange = options.onLocationChange || null;

        this.init();
    }

    init() {
        const container = document.getElementById(this.elementId);
        if (!container) return;

        const mapOptions = {
            zoomControl: this.mode !== 'preview',
            attributionControl: this.mode !== 'preview',
            scrollWheelZoom: this.mode === 'picker' || this.mode === 'view',
            dragging: this.mode === 'picker' || this.mode === 'view',
            doubleClickZoom: this.mode === 'picker' || this.mode === 'view',
            boxZoom: this.mode === 'picker' || this.mode === 'view',
            keyboard: this.mode === 'picker' || this.mode === 'view'
        };

        // Force some options for specific modes
        if (this.mode === 'preview') {
            mapOptions.scrollWheelZoom = false;
            mapOptions.dragging = false;
            mapOptions.doubleClickZoom = false;
            mapOptions.boxZoom = false;
            mapOptions.keyboard = false;
        }

        try {
            this.map = L.map(this.elementId, mapOptions).setView(this.initialLocation, this.zoom);
        } catch (e) {
            console.error('Leaflet initialization failed:', e);
            return;
        }

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap'
        }).addTo(this.map);

        if (this.initialLocation) {
            this.setMarker(this.initialLocation);
        }

        // Handle potential container size shifts
        setTimeout(() => this.updateSize(), 200);

        if (this.mode === 'picker') {
            this.map.on('click', (e) => {
                this.setMarker(e.latlng);
                if (this.onLocationChange) {
                    this.onLocationChange(e.latlng);
                }
            });
        }
    }

    setMarker(latlng) {
        if (!latlng) return;

        let coords = latlng;
        if (Array.isArray(latlng)) {
            coords = { lat: latlng[0], lng: latlng[1] };
        }

        if (!this.marker) {
            this.marker = L.marker(coords).addTo(this.map);
        } else {
            this.marker.setLatLng(coords);
        }
    }

    setView(latlng, zoom) {
        if (!latlng) return;
        this.map.setView(latlng, zoom || this.map.getZoom());
        this.setMarker(latlng);
    }

    updateSize() {
        if (this.map) {
            this.map.invalidateSize();
        }
    }

    static async geocode(address) {
        try {
            const url = `https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(address)}&format=json&limit=1`;
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) return null;
            const data = await res.json();
            if (Array.isArray(data) && data.length > 0) {
                return { lat: parseFloat(data[0].lat), lon: parseFloat(data[0].lon) };
            }
        } catch (e) {
            console.error('Geocoding error:', e);
        }
        return null;
    }

    static parseLocation(locationStr) {
        if (!locationStr) return null;
        const m = locationStr.match(/^\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*$/);
        if (m) {
            return { lat: parseFloat(m[1]), lng: parseFloat(m[2]) };
        }
        return null;
    }
}
