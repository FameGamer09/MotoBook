const panel = document.getElementById('tracking-panel');
const mapElement = document.getElementById('customer-live-map');
const statusElement = document.getElementById('tracking-status');

if (panel && mapElement && statusElement) {
    const L = window.L;

    if (!L) {
        throw new Error('Leaflet failed to load for customer order tracking.');
    }

    const coordinatesFrom = (latitude, longitude) => {
        const lat = Number(latitude);
        const lng = Number(longitude);

        return latitude !== '' && longitude !== '' && Number.isFinite(lat) && Number.isFinite(lng)
            ? [lat, lng]
            : null;
    };

    const destination = coordinatesFrom(panel.dataset.destinationLat, panel.dataset.destinationLng);
    const pickup = coordinatesFrom(panel.dataset.pickupLat, panel.dataset.pickupLng);
    const initialCenter = destination || pickup || [12.8797, 121.774];
    const map = L.map(mapElement, { scrollWheelZoom: false }).setView(initialCenter, destination || pickup ? 14 : 5);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);

    const routePoints = [pickup, destination].filter(Boolean);
    if (pickup) {
        L.circleMarker(pickup, {
            radius: 8,
            color: '#ffffff',
            weight: 3,
            fillColor: '#08a8d8',
            fillOpacity: 1,
        }).addTo(map).bindPopup('Pickup location');
    }
    if (destination) {
        L.circleMarker(destination, {
            radius: 8,
            color: '#ffffff',
            weight: 3,
            fillColor: '#e24f55',
            fillOpacity: 1,
        }).addTo(map).bindPopup('Drop-off location');
    }

    let riderMarker = null;
    let routeLine = null;
    let animationFrame = null;
    let lastGpsPoint = null;
    let lastEtaRequestAt = 0;
    const orderStatusElement = document.getElementById('order-live-status');
    const etaElement = document.getElementById('delivery-eta');
    const deliveryMessages = {
        assigned: 'Rider assigned to your order',
        accepted: 'Rider accepted your delivery',
        en_route_to_pickup: 'Rider is heading to the restaurant',
        arrived_at_pickup: 'Rider has arrived at the restaurant',
        picked_up: 'Your order has been picked up',
        en_route_to_customer: 'Your rider is on the way to you',
        arrived_at_customer: 'Your rider has arrived',
        delivered: 'Delivered!',
    };

    async function loadDeliveryRoute() {
        if (routePoints.length !== 2) return;

        const coordinates = routePoints.map(([latitude, longitude]) => `${longitude},${latitude}`).join(';');

        try {
            const response = await fetch(`https://router.project-osrm.org/route/v1/driving/${coordinates}?overview=full&geometries=geojson`, {
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) return;

            const result = await response.json();
            const routeCoordinates = result.routes?.[0]?.geometry?.coordinates;
            if (!routeCoordinates?.length) return;

            const routeLatLngs = routeCoordinates.map(([longitude, latitude]) => [latitude, longitude]);
            L.polyline(routeLatLngs, { color: '#ffffff', weight: 9, opacity: 0.9, lineCap: 'round', lineJoin: 'round' }).addTo(map);
            routeLine = L.polyline(routeLatLngs, { color: '#087eff', weight: 5, opacity: 0.95, lineCap: 'round', lineJoin: 'round' }).addTo(map);
            map.fitBounds(routeLine.getBounds().pad(0.12), { maxZoom: 16 });
        } catch {
            routeLine = null;
        }
    }

    async function updateDeliveryEta(riderPosition, deliveryStatus) {
        if (!etaElement) return;
        if (deliveryStatus !== 'en_route_to_customer' || !destination) {
            etaElement.textContent = deliveryStatus === 'en_route_to_pickup'
                ? 'Rider heading to pickup'
                : 'Updates as your order progresses';
            return;
        }

        if (Date.now() - lastEtaRequestAt < 30_000) return;
        lastEtaRequestAt = Date.now();
        const coordinates = [riderPosition, destination]
            .map(([latitude, longitude]) => `${longitude},${latitude}`)
            .join(';');

        try {
            const response = await fetch(`https://router.project-osrm.org/route/v1/driving/${coordinates}?overview=false`, {
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) return;

            const result = await response.json();
            const durationSeconds = result.routes?.[0]?.duration;
            if (!Number.isFinite(durationSeconds)) return;

            const minutes = Math.max(1, Math.ceil(durationSeconds / 60));
            const lowerBound = Math.max(1, Math.floor(minutes / 5) * 5);
            const upperBound = Math.max(lowerBound + 5, Math.ceil(minutes / 5) * 5);
            etaElement.textContent = `${lowerBound}–${upperBound} min`;
        } catch {
            etaElement.textContent = 'Calculating route…';
        }
    }

    function animateRiderTo(position) {
        const current = riderMarker?.getLatLng();
        if (animationFrame !== null) {
            window.cancelAnimationFrame(animationFrame);
            animationFrame = null;
        }

        if (!current) {
            riderMarker = L.circleMarker(position, {
                radius: 10,
                color: '#ffffff',
                weight: 3,
                fillColor: '#08a8d8',
                fillOpacity: 1,
            }).addTo(map);
            return;
        }

        const start = { lat: current.lat, lng: current.lng };
        const latitudeDelta = position[0] - start.lat;
        const longitudeDelta = position[1] - start.lng;
        const duration = 4200;
        let startedAt = null;

        function step(timestamp) {
            if (startedAt === null) startedAt = timestamp;
            const progress = Math.min((timestamp - startedAt) / duration, 1);
            const easedProgress = progress * (2 - progress);
            riderMarker.setLatLng([
                start.lat + latitudeDelta * easedProgress,
                start.lng + longitudeDelta * easedProgress,
            ]);

            if (progress < 1) {
                animationFrame = window.requestAnimationFrame(step);
            } else {
                animationFrame = null;
            }
        }

        animationFrame = window.requestAnimationFrame(step);
    }

    async function refreshTracking() {
        try {
            const response = await fetch(panel.dataset.trackingUrl, {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
            });
            if (!response.ok) return;

            const tracking = await response.json();
            if (orderStatusElement && deliveryMessages[tracking.delivery_status]) {
                orderStatusElement.textContent = deliveryMessages[tracking.delivery_status];
            }
            if (['delivered', 'completed', 'cancelled', 'rejected'].includes(tracking.status)) {
                if (etaElement) etaElement.textContent = tracking.status === 'delivered' || tracking.status === 'completed' ? 'Delivered' : 'Unavailable';
                statusElement.textContent = tracking.status === 'delivered' || tracking.status === 'completed'
                    ? 'Delivery complete.'
                    : 'Tracking has ended for this order.';
                window.clearInterval(trackingInterval);
                return;
            }

            const riderPosition = coordinatesFrom(tracking.rider?.latitude, tracking.rider?.longitude);
            if (!riderPosition) {
                statusElement.textContent = tracking.rider
                    ? 'Rider is assigned. Waiting for a fresh GPS update…'
                    : 'Waiting for a rider to be assigned…';
                return;
            }

            const recordedAt = tracking.rider.updated_at ? new Date(tracking.rider.updated_at) : null;
            if (!recordedAt || Number.isNaN(recordedAt.getTime()) || Date.now() - recordedAt.getTime() > 90_000) {
                statusElement.textContent = 'Rider GPS is temporarily unavailable. Waiting for a fresh update…';
                return;
            }

            animateRiderTo(riderPosition);
            updateDeliveryEta(riderPosition, tracking.delivery_status);
            const updatedAt = recordedAt.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
            statusElement.textContent = `${tracking.rider.name || 'Your rider'} · Updated ${updatedAt}`;

            const movedEnough = !lastGpsPoint
                || Math.abs(lastGpsPoint[0] - riderPosition[0]) + Math.abs(lastGpsPoint[1] - riderPosition[1]) > 0.0001;
            if (movedEnough && !routeLine && !lastGpsPoint) {
                const bounds = L.latLngBounds([riderPosition, ...routePoints]);
                map.fitBounds(bounds.pad(0.2), { maxZoom: 15 });
            }
            lastGpsPoint = riderPosition;
        } catch {
            statusElement.textContent = 'Unable to refresh rider location. Retrying…';
        }
    }

    loadDeliveryRoute();
    refreshTracking();
    const trackingInterval = window.setInterval(refreshTracking, 5000);
    window.addEventListener('pagehide', () => {
        window.clearInterval(trackingInterval);
        if (animationFrame !== null) window.cancelAnimationFrame(animationFrame);
    }, { once: true });
    window.setTimeout(() => map.invalidateSize(), 0);
}
