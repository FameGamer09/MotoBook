<script>
(() => {
    const orientalMindoroCenter = [13.1, 121.2];
    let leafletPromise;

    const loadLeaflet = () => {
        if (window.L) {
            return Promise.resolve(window.L);
        }

        if (!leafletPromise) {
            leafletPromise = new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                script.integrity = 'sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=';
                script.crossOrigin = '';
                script.onload = () => resolve(window.L);
                script.onerror = () => reject(new Error('Leaflet could not be loaded.'));
                document.head.append(script);
            });
        }

        return leafletPromise;
    };

    document.querySelectorAll('[data-location-picker]').forEach((picker) => {
        const latitude = document.getElementById(picker.dataset.latitudeTarget);
        const longitude = document.getElementById(picker.dataset.longitudeTarget);
        const status = picker.querySelector('[data-location-status]');
        const button = picker.querySelector('button');
        const mapElement = picker.querySelector('[data-location-map]');
        let map = null;
        let marker = null;

        const readCoordinates = () => {
            const lat = Number.parseFloat(latitude.value);
            const lng = Number.parseFloat(longitude.value);

            return Number.isFinite(lat) && Number.isFinite(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180
                ? [lat, lng]
                : null;
        };

        const updateMarker = (coordinates, center = false) => {
            if (!map || !coordinates) {
                return;
            }

            if (!marker) {
                marker = window.L.marker(coordinates, { draggable: true }).addTo(map);
                marker.on('dragend', () => setCoordinates(marker.getLatLng().lat, marker.getLatLng().lng));
            } else {
                marker.setLatLng(coordinates);
            }

            if (center) {
                map.setView(coordinates, Math.max(map.getZoom(), 16));
            }
        };

        const setCoordinates = (lat, lng) => {
            latitude.value = lat.toFixed(7);
            longitude.value = lng.toFixed(7);
            updateMarker([lat, lng]);
            status.textContent = 'Delivery pin updated. Check that it matches the address and landmark.';
        };

        if (mapElement && picker.hasAttribute('data-map-picker')) {
            loadLeaflet().then(() => {
                const initialCoordinates = readCoordinates();
                const center = initialCoordinates || orientalMindoroCenter;
                map = window.L.map(mapElement, { scrollWheelZoom: false }).setView(center, initialCoordinates ? 16 : 10);

                window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap contributors</a>',
                }).addTo(map);

                if (initialCoordinates) {
                    updateMarker(initialCoordinates);
                }

                map.on('click', ({ latlng }) => setCoordinates(latlng.lat, latlng.lng));
                window.setTimeout(() => map.invalidateSize(), 0);
            }).catch(() => {
                status.textContent = 'The map could not load. You can still enter coordinates or use your current location.';
            });

            [latitude, longitude].forEach((input) => input.addEventListener('change', () => {
                const coordinates = readCoordinates();
                if (coordinates) {
                    updateMarker(coordinates, true);
                }
            }));
        }

        button.addEventListener('click', () => {
            if (!navigator.geolocation) {
                status.textContent = 'Location access is unavailable. Enter coordinates or choose a point on the map.';
                return;
            }

            status.textContent = 'Finding your location...';
            navigator.geolocation.getCurrentPosition(({ coords }) => {
                setCoordinates(coords.latitude, coords.longitude);
                updateMarker([coords.latitude, coords.longitude], true);
                status.textContent = 'Current location added. Check that the pin matches the delivery address.';
            }, () => {
                status.textContent = 'Could not access your location. Allow location access, choose a point on the map, or enter coordinates.';
            }, { enableHighAccuracy: true, timeout: 10000 });
        });
    });
})();
</script>
