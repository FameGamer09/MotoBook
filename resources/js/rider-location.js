const button = document.getElementById('share-location');
const feedback = document.getElementById('location-feedback');
const maximumAccuracyMeters = 500;

if (button && !button.disabled) {
    let sharingInterval = null;
    let requestInFlight = false;

    const sendLocation = async (position) => {
        const accuracy = position.coords.accuracy;
        if (!Number.isFinite(accuracy) || accuracy > maximumAccuracyMeters) {
            feedback.textContent = `GPS accuracy is about ${Math.round(accuracy || 0)} m. Enable Precise Location and try outdoors.`;
            return;
        }

        const customUrl = button.dataset.customLocationUrl;
        const response = await fetch(customUrl || button.dataset.locationUrl || '/rider/location', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({
                ...(!customUrl && button.dataset.deliveryId ? { delivery_id: Number(button.dataset.deliveryId) } : {}),
                latitude: position.coords.latitude,
                longitude: position.coords.longitude,
                accuracy_m: accuracy,
            }),
        });

        if (!response.ok) {
            const result = await response.json().catch(() => ({}));
            const validationMessage = Object.values(result.errors || {}).flat()[0];
            throw new Error(result.message || validationMessage || 'Location update rejected.');
        }

        feedback.textContent = `Precise GPS confirmed (±${Math.round(accuracy)} m). Updated ${new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })}.`;
    };

    const requestLocation = () => {
        if (requestInFlight) return;
        requestInFlight = true;

        navigator.geolocation.getCurrentPosition(
            async (position) => {
                try {
                    await sendLocation(position);
                } catch (error) {
                    feedback.textContent = error instanceof Error ? error.message : 'Could not update location. Check your connection.';
                } finally {
                    requestInFlight = false;
                }
            },
            (error) => {
                requestInFlight = false;
                feedback.textContent = error.code === error.PERMISSION_DENIED
                    ? 'Location permission was denied. Allow precise location for MotoBook in your browser settings.'
                    : 'Unable to get a GPS fix. Turn on device location and try again outdoors.';
                if (error.code === error.PERMISSION_DENIED && sharingInterval !== null) {
                    window.clearInterval(sharingInterval);
                    sharingInterval = null;
                    button.textContent = 'Share live GPS location';
                }
            },
            { enableHighAccuracy: true, maximumAge: 0, timeout: 30000 },
        );
    };

    button.addEventListener('click', () => {
        if (sharingInterval !== null) {
            window.clearInterval(sharingInterval);
            sharingInterval = null;
            button.textContent = 'Share live GPS location';
            feedback.textContent = 'Location sharing stopped.';
            return;
        }

        if (!navigator.geolocation) {
            feedback.textContent = 'Location is not available in this browser.';
            return;
        }

        button.disabled = true;
        feedback.textContent = 'Waiting for a precise GPS fix. Keep this page open and allow Precise Location.';
        sharingInterval = window.setInterval(requestLocation, 15000);
        requestLocation();
        button.disabled = false;
        button.textContent = 'Stop live location sharing';
    });

    window.addEventListener('pagehide', () => {
        if (sharingInterval !== null) window.clearInterval(sharingInterval);
    });
}
