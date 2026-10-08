const button = document.getElementById('share-location');
const feedback = document.getElementById('location-feedback');

if (button && !button.disabled) {
    let sharingInterval = null;
    let requestInFlight = false;

    const sendLocation = async (position) => {
        const customUrl = button.dataset.customLocationUrl;
        const response = await fetch(customUrl || button.dataset.locationUrl || '/rider/location', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({
                ...(customUrl ? {} : { delivery_id: Number(button.dataset.deliveryId) }),
                latitude: position.coords.latitude,
                longitude: position.coords.longitude,
            }),
        });

        if (!response.ok) {
            throw new Error('Location update rejected.');
        }

        feedback.textContent = `Sharing location · updated ${new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })}`;
    };

    const requestLocation = () => {
        if (requestInFlight) return;
        requestInFlight = true;

        navigator.geolocation.getCurrentPosition(
            async (position) => {
                try {
                    await sendLocation(position);
                } catch {
                    feedback.textContent = 'Could not update location. Check your connection.';
                } finally {
                    requestInFlight = false;
                }
            },
            (error) => {
                requestInFlight = false;
                feedback.textContent = error.code === error.PERMISSION_DENIED
                    ? 'Location permission was denied.'
                    : 'Unable to get your location. Check GPS and try again.';
                if (error.code === error.PERMISSION_DENIED && sharingInterval !== null) {
                    window.clearInterval(sharingInterval);
                    sharingInterval = null;
                    button.textContent = 'Start live location sharing';
                }
            },
            { enableHighAccuracy: true, maximumAge: 0, timeout: 12000 },
        );
    };

    button.addEventListener('click', () => {
        if (sharingInterval !== null) {
            window.clearInterval(sharingInterval);
            sharingInterval = null;
            button.textContent = 'Start live location sharing';
            feedback.textContent = 'Location sharing stopped.';
            return;
        }

        if (!navigator.geolocation) {
            feedback.textContent = 'Location is not available in this browser.';
            return;
        }

        button.disabled = true;
        feedback.textContent = 'Waiting for GPS permission…';
        requestLocation();
        sharingInterval = window.setInterval(requestLocation, 15000);
        button.disabled = false;
        button.textContent = 'Stop live location sharing';
    });

    window.addEventListener('pagehide', () => {
        if (sharingInterval !== null) window.clearInterval(sharingInterval);
    });
}
