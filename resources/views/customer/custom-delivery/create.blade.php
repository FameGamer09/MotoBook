<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <title>Custom Delivery · MotoBook</title>
    @vite(['resources/css/app.css','resources/css/pages/customer-experience.css'])
</head>
<body class="cx-page cx-custom-delivery" data-theme="{{ (auth()->user()->customer_settings['dark_mode'] ?? false) ? 'dark' : 'light' }}">
<main class="cx-wrap cx-custom-delivery-wrap">
    <header class="cx-topbar">
        <a class="cx-back" href="{{ route('customer.restaurants') }}" aria-label="Back to restaurants">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
        </a>
        <h1>Custom Delivery</h1>
    </header>

    @if($errors->any())
        <div class="cx-error" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('customer.custom-delivery.store') }}">
        @csrf
        <section class="cx-card">
            <h2 class="delivery-section-title">Pickup details</h2>
            <div class="cx-field">
                <label for="pickup_address">Pickup Address *</label>
                <input id="pickup_address" name="pickup_address" value="{{ old('pickup_address', $address?->address_line) }}" required maxlength="500" autocomplete="street-address">
            </div>
            <div class="cx-field">
                <label for="pickup_landmark">Landmark / Pickup Instructions</label>
                <input id="pickup_landmark" name="pickup_landmark" value="{{ old('pickup_landmark', $address?->landmark) }}" maxlength="255">
            </div>
            <div class="cx-two">
                <div class="cx-field">
                    <label for="sender_name">Sender Name *</label>
                    <input id="sender_name" name="sender_name" value="{{ old('sender_name', auth()->user()->name) }}" required maxlength="120" autocomplete="name">
                </div>
                <div class="cx-field">
                    <label for="sender_phone">Sender Phone No. *</label>
                    <input id="sender_phone" name="sender_phone" value="{{ old('sender_phone', auth()->user()->phone) }}" required maxlength="30" autocomplete="tel">
                </div>
            </div>
            <details class="delivery-location-tools">
                <summary>Optional pickup map pin</summary>
                <div class="cx-two">
                    <div class="cx-field">
                        <label for="pickup_latitude">Latitude</label>
                        <input id="pickup_latitude" name="pickup_latitude" type="number" step="any" value="{{ old('pickup_latitude', $address?->latitude) }}">
                    </div>
                    <div class="cx-field">
                        <label for="pickup_longitude">Longitude</label>
                        <input id="pickup_longitude" name="pickup_longitude" type="number" step="any" value="{{ old('pickup_longitude', $address?->longitude) }}">
                    </div>
                </div>
                <button class="cx-button secondary" type="button" data-geolocate="pickup">Use my current location</button>
            </details>
        </section>

        <section class="cx-card">
            <h2 class="delivery-section-title">Drop-off details</h2>
            <div class="cx-field">
                <label for="dropoff_address">Drop-off Address *</label>
                <input id="dropoff_address" name="dropoff_address" value="{{ old('dropoff_address') }}" required maxlength="500" autocomplete="street-address">
            </div>
            <div class="cx-field">
                <label for="dropoff_landmark">Landmark / Drop-off Instructions</label>
                <input id="dropoff_landmark" name="dropoff_landmark" value="{{ old('dropoff_landmark') }}" maxlength="255">
            </div>
            <div class="cx-two">
                <div class="cx-field">
                    <label for="recipient_name">Recipient Name *</label>
                    <input id="recipient_name" name="recipient_name" value="{{ old('recipient_name') }}" required maxlength="120" autocomplete="name">
                </div>
                <div class="cx-field">
                    <label for="recipient_phone">Recipient Phone No. *</label>
                    <input id="recipient_phone" name="recipient_phone" value="{{ old('recipient_phone') }}" required maxlength="30" autocomplete="tel">
                </div>
            </div>
            <details class="delivery-location-tools">
                <summary>Optional drop-off map pin</summary>
                <div class="cx-two">
                    <div class="cx-field">
                        <label for="dropoff_latitude">Latitude</label>
                        <input id="dropoff_latitude" name="dropoff_latitude" type="number" step="any" value="{{ old('dropoff_latitude') }}">
                    </div>
                    <div class="cx-field">
                        <label for="dropoff_longitude">Longitude</label>
                        <input id="dropoff_longitude" name="dropoff_longitude" type="number" step="any" value="{{ old('dropoff_longitude') }}">
                    </div>
                </div>
                <button class="cx-button secondary" type="button" data-geolocate="dropoff">Use my current location</button>
            </details>
        </section>

        <section class="cx-card">
            <h2 class="delivery-section-title package-section-title">Package details</h2>
            <fieldset class="delivery-package-fieldset">
                <legend>Item Category *</legend>
                <div class="package-options">
                    <label class="package-option">
                        <input type="radio" name="package_category" value="documents" required @checked(old('package_category', 'documents') === 'documents')>
                        <span class="package-option-content">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h8l5 5v13H6zM14 3v6h5M9 14h7m-7 3h7"/></svg>
                            <span>Documents</span>
                        </span>
                    </label>
                    <label class="package-option">
                        <input type="radio" name="package_category" value="small_parcel" @checked(old('package_category') === 'small_parcel')>
                        <span class="package-option-content">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 9 5-9 5-9-5 9-5Zm-9 5v9l9 5 9-5V8M12 13v9"/></svg>
                            <span>Small Parcel</span>
                        </span>
                    </label>
                    <label class="package-option">
                        <input type="radio" name="package_category" value="other" @checked(old('package_category') === 'other')>
                        <span class="package-option-content">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m20 13-7 7-10-10V3h7l10 10Z"/><circle cx="7.5" cy="7.5" r="1"/></svg>
                            <span>Other</span>
                        </span>
                    </label>
                </div>
            </fieldset>
            <div class="cx-field">
                <label for="description">Description / Special Handling *</label>
                <textarea id="description" name="description" required maxlength="1000" placeholder="e.g. Signed contract papers, fragile blueprints, legal documents">{{ old('description') }}</textarea>
            </div>
            <details class="delivery-payment-details">
                <summary>
                    <span>Payment and delivery fee</span>
                    <strong>₱{{ number_format($deliveryFee + $serviceFee, 2) }}</strong>
                </summary>
                <label class="cx-choice">
                    <input type="radio" name="payment_method" value="cod" @checked(old('payment_method', 'cod') === 'cod')>
                    <span><strong>Cash on delivery</strong><small class="cx-copy">Recipient pays the rider</small></span>
                </label>
                <label class="cx-choice">
                    <input type="radio" name="payment_method" value="gcash" @checked(old('payment_method') === 'gcash')>
                    <span><strong>GCash</strong><small class="cx-copy">Request stays unpaid until confirmed</small></span>
                </label>
                <div class="cx-summary-row"><span>Delivery fee</span><strong>₱{{ number_format($deliveryFee, 2) }}</strong></div>
                <div class="cx-summary-row"><span>Service fee</span><strong>₱{{ number_format($serviceFee, 2) }}</strong></div>
                <div class="cx-summary-row cx-summary-total"><span>Total</span><strong>₱{{ number_format($deliveryFee + $serviceFee, 2) }}</strong></div>
            </details>
        </section>

        <div class="delivery-submit-bar">
            <button class="cx-button" type="submit">
                Verify Order Details
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
            </button>
        </div>
    </form>
</main>
<script>
document.querySelectorAll('[data-geolocate]').forEach(button => button.addEventListener('click', () => {
    if (!navigator.geolocation) {
        alert('Location is not available in this browser.');
        return;
    }

    button.disabled = true;
    button.textContent = 'Finding location…';
    navigator.geolocation.getCurrentPosition(position => {
        const prefix = button.dataset.geolocate;
        document.getElementById(prefix + '_latitude').value = position.coords.latitude.toFixed(7);
        document.getElementById(prefix + '_longitude').value = position.coords.longitude.toFixed(7);
        button.disabled = false;
        button.textContent = 'Location pinned';
    }, () => {
        button.disabled = false;
        button.textContent = 'Could not get location. Check browser permission.';
    }, { enableHighAccuracy: true, timeout: 12000 });
}));
</script>
</body>
</html>
