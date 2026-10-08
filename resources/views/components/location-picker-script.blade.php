<script>
document.querySelectorAll('[data-location-picker]').forEach((picker) => {
    const latitude = document.getElementById(picker.dataset.latitudeTarget);
    const longitude = document.getElementById(picker.dataset.longitudeTarget);
    const status = picker.querySelector('[data-location-status]');
    const button = picker.querySelector('button');

    button.addEventListener('click', () => {
        if (!navigator.geolocation) {
            status.textContent = 'Location access is unavailable. Enter the coordinates manually.';
            return;
        }

        status.textContent = 'Finding your location...';
        navigator.geolocation.getCurrentPosition(({ coords }) => {
            latitude.value = coords.latitude.toFixed(7);
            longitude.value = coords.longitude.toFixed(7);
            status.textContent = 'Location added. Check that the pin matches the delivery address.';
        }, () => {
            status.textContent = 'Could not access your location. Allow location access or enter the coordinates manually.';
        }, { enableHighAccuracy: true, timeout: 10000 });
    });
});
</script>
