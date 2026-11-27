const dialog = document.querySelector('[data-location-dialog]');

if (dialog) {
    const mapElement = dialog.querySelector('[data-map]');
    const searchInput = dialog.querySelector('[data-map-search]');
    const addressElement = dialog.querySelector('[data-map-address]');
    const kindElement = dialog.querySelector('[data-map-kind]');
    const instructionInput = dialog.querySelector('[data-map-instructions]');
    const mapCss = document.createElement('link');
    mapCss.rel = 'stylesheet';
    mapCss.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
    mapCss.integrity = 'sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=';
    mapCss.crossOrigin = '';
    document.head.append(mapCss);

    let map;
    let marker;
    let selectedKind = 'pickup';
    let selectedPoint = null;
    let lookupTimer;

    const loadLeaflet = () => new Promise((resolve, reject) => {
        if (window.L) {
            resolve();
            return;
        }

        const script = document.createElement('script');
        script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
        script.integrity = 'sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=';
        script.crossOrigin = '';
        script.onload = resolve;
        script.onerror = reject;
        document.head.append(script);
    });

    const reverseGeocode = async (latitude, longitude) => {
        addressElement.textContent = 'Finding address…';

        try {
            const query = new URLSearchParams({ format: 'jsonv2', lat: latitude, lon: longitude, zoom: 18 });
            const response = await fetch(`https://nominatim.openstreetmap.org/reverse?${query}`);
            const result = await response.json();
            addressElement.textContent = result.display_name || `${latitude.toFixed(5)}, ${longitude.toFixed(5)}`;
        } catch {
            addressElement.textContent = `${latitude.toFixed(5)}, ${longitude.toFixed(5)}`;
        }
    };

    const updatePoint = (point, shouldLookup = true) => {
        selectedPoint = point;
        const latLng = [point.lat, point.lng];
        if (!marker) {
            marker = window.L.marker(latLng).addTo(map);
        } else {
            marker.setLatLng(latLng);
        }

        if (shouldLookup) {
            window.clearTimeout(lookupTimer);
            lookupTimer = window.setTimeout(() => reverseGeocode(point.lat, point.lng), 350);
        } else {
            addressElement.textContent = point.label || `${point.lat.toFixed(5)}, ${point.lng.toFixed(5)}`;
        }
    };

    const setMapView = async (latitude, longitude, zoom = 16) => {
        await loadLeaflet();
        if (!map) {
            map = window.L.map(mapElement, { zoomControl: false }).setView([latitude, longitude], zoom);
            window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            }).addTo(map);
            window.L.control.zoom({ position: 'bottomright' }).addTo(map);
            map.on('click', (event) => updatePoint(event.latlng));
        }

        map.setView([latitude, longitude], zoom);
        window.setTimeout(() => map.invalidateSize(), 80);
    };

    document.querySelectorAll('[data-location-open]').forEach((button) => button.addEventListener('click', async () => {
        selectedKind = button.dataset.locationOpen;
        const address = document.getElementById(`${selectedKind}_address`).value;
        const latitude = Number(document.getElementById(`${selectedKind}_latitude`).value);
        const longitude = Number(document.getElementById(`${selectedKind}_longitude`).value);
        const hasCoordinates = Number.isFinite(latitude) && Number.isFinite(longitude) && latitude !== 0 && longitude !== 0;
        const start = hasCoordinates ? { lat: latitude, lng: longitude, label: address } : { lat: 11.5853, lng: 122.7511 };
        selectedPoint = null;
        if (marker && !hasCoordinates) {
            map.removeLayer(marker);
            marker = null;
        }

        dialog.querySelector('[data-map-heading]').textContent = `Select ${selectedKind === 'pickup' ? 'Pickup' : 'Drop-off'} Location`;
        dialog.querySelector('[data-map-label]').textContent = `${selectedKind === 'pickup' ? 'Pickup' : 'Drop-off'} Location`;
        dialog.querySelector('[data-map-pin]').className = `delivery-location-pin ${selectedKind}`;
        dialog.querySelector('[data-map-confirm]').textContent = `Confirm ${selectedKind === 'pickup' ? 'Pickup' : 'Drop-off'} Location`;
        kindElement.textContent = `${selectedKind} location`;
        instructionInput.value = document.getElementById(`${selectedKind}_landmark`).value;
        addressElement.textContent = address || 'Move the map to choose a location';
        dialog.showModal();
        try {
            await setMapView(start.lat, start.lng, hasCoordinates ? 17 : 14);
            if (hasCoordinates) updatePoint(start, false);
        } catch {
            addressElement.textContent = 'Map could not load. Check your internet connection.';
        }
    }));

    dialog.querySelector('[data-map-back]').addEventListener('click', () => dialog.close());
    dialog.querySelector('[data-map-clear]').addEventListener('click', () => {
        if (marker) map.removeLayer(marker);
        marker = null;
        selectedPoint = null;
        addressElement.textContent = 'Tap the map to select a location';
    });

    dialog.querySelector('[data-map-current]').addEventListener('click', () => {
        if (!navigator.geolocation) return;
        navigator.geolocation.getCurrentPosition((position) => {
            const point = { lat: position.coords.latitude, lng: position.coords.longitude };
            map.setView([point.lat, point.lng], 17);
            updatePoint(point);
        }, () => {
            addressElement.textContent = 'Location permission was unavailable. Move the map to choose.';
        }, { enableHighAccuracy: true, timeout: 12000 });
    });

    dialog.querySelector('[data-map-search-form]').addEventListener('submit', async (event) => {
        event.preventDefault();
        if (searchInput.value.trim().length < 3) return;
        try {
            const query = new URLSearchParams({ format: 'jsonv2', limit: 1, q: `${searchInput.value}, Philippines` });
            const response = await fetch(`https://nominatim.openstreetmap.org/search?${query}`);
            const [result] = await response.json();
            if (!result) {
                addressElement.textContent = 'No matching place found. Try a nearby street or landmark.';
                return;
            }
            const point = { lat: Number(result.lat), lng: Number(result.lon), label: result.display_name };
            map.setView([point.lat, point.lng], 17);
            updatePoint(point, false);
        } catch {
            addressElement.textContent = 'Search is unavailable. You can still select a point on the map.';
        }
    });

    dialog.querySelector('[data-map-confirm]').addEventListener('click', () => {
        if (!selectedPoint) {
            addressElement.textContent = 'Tap the map to choose a location first.';
            return;
        }
        const prefix = selectedKind;
        const address = addressElement.textContent;
        document.getElementById(`${prefix}_address`).value = address;
        document.getElementById(`${prefix}_latitude`).value = selectedPoint.lat.toFixed(7);
        document.getElementById(`${prefix}_longitude`).value = selectedPoint.lng.toFixed(7);
        document.getElementById(`${prefix}_landmark`).value = instructionInput.value;
        document.querySelector(`[data-location-title="${prefix}"]`).textContent = address;
        document.querySelector(`[data-location-subtitle="${prefix}"]`).textContent = instructionInput.value || 'Pinned on map';
        dialog.close();
    });

    document.querySelector('form[action]')?.addEventListener('submit', (event) => {
        if (!document.getElementById('pickup_address').value || !document.getElementById('dropoff_address').value) {
            event.preventDefault();
            document.querySelector(`[data-location-open="${document.getElementById('pickup_address').value ? 'dropoff' : 'pickup'}"]`)?.focus();
        }
    });
}
