const panel = document.getElementById('tracking-panel');
const mapElement = document.getElementById('customer-live-map');
const statusElement = document.getElementById('tracking-status');

if (panel && mapElement && statusElement) {
    const L = window.L;

    if (!L) {
        throw new Error('Leaflet failed to load for customer order tracking.');
    }

    const destinationLatitude = panel.dataset.destinationLat;
    const destinationLongitude = panel.dataset.destinationLng;
    const destination = [Number(destinationLatitude), Number(destinationLongitude)];
    const hasDestination = destinationLatitude !== ''
        && destinationLongitude !== ''
        && destination.every(Number.isFinite);
    const initialCenter = hasDestination ? destination : [12.8797, 121.774];
    const map = L.map(mapElement, { scrollWheelZoom: false }).setView(initialCenter, hasDestination ? 14 : 5);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);

    if (hasDestination) {
        L.circleMarker(destination, {
            radius: 8,
            color: '#ffffff',
            weight: 3,
            fillColor: '#e24f55',
            fillOpacity: 1,
        }).addTo(map).bindPopup('Delivery address');
    }

    let riderMarker = null;
    let lastCoords = null;
    const orderStatusElement = document.getElementById('order-live-status');
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
                statusElement.textContent = tracking.status === 'delivered' || tracking.status === 'completed'
                    ? 'Delivery complete.'
                    : 'Tracking has ended for this order.';
                window.clearInterval(trackingInterval);
                return;
            }
            const rawLatitude = tracking.rider?.latitude;
            const rawLongitude = tracking.rider?.longitude;
            const latitude = Number(rawLatitude);
            const longitude = Number(rawLongitude);

            if (rawLatitude === null || rawLatitude === undefined || rawLongitude === null || rawLongitude === undefined || !Number.isFinite(latitude) || !Number.isFinite(longitude)) {
                statusElement.textContent = 'Waiting for rider to share GPS location…';
                return;
            }

            const riderPosition = [latitude, longitude];
            if (!riderMarker) {
                riderMarker = L.circleMarker(riderPosition, {
                    radius: 10,
                    color: '#ffffff',
                    weight: 3,
                    fillColor: '#08a8d8',
                    fillOpacity: 1,
                }).addTo(map).bindPopup(tracking.rider.name || 'Your rider');
            } else {
                riderMarker.setLatLng(riderPosition);
            }

            const updatedAt = tracking.rider.updated_at
                ? new Date(tracking.rider.updated_at).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })
                : 'just now';
            statusElement.textContent = `${tracking.rider.name || 'Your rider'} · Updated ${updatedAt}`;

            if (!lastCoords || Math.abs(lastCoords[0] - latitude) + Math.abs(lastCoords[1] - longitude) > 0.0001) {
                if (hasDestination) {
                    map.fitBounds(L.latLngBounds([riderPosition, destination]).pad(0.25), { maxZoom: 15 });
                } else {
                    map.setView(riderPosition, 15);
                }
                lastCoords = riderPosition;
            }
        } catch {
            statusElement.textContent = 'Unable to refresh rider location. Retrying…';
        }
    }

    refreshTracking();
    const trackingInterval = window.setInterval(refreshTracking, 5000);
    window.addEventListener('pagehide', () => window.clearInterval(trackingInterval), { once: true });
    window.setTimeout(() => map.invalidateSize(), 0);
}
