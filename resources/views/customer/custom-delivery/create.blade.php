<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>Custom Delivery · MotoBook</title>@vite(['resources/css/app.css','resources/css/pages/customer-experience.css'])</head>
<body class="cx-page" data-theme="{{ (auth()->user()->customer_settings['dark_mode'] ?? false) ? 'dark' : 'light' }}"><main class="cx-wrap">
    <header class="cx-topbar"><a class="cx-back" href="{{ route('customer.restaurants') }}" aria-label="Back">‹</a><h1>Custom Delivery</h1></header>
    <div class="cx-card"><span class="cx-eyebrow">Door-to-door courier</span><h2 class="cx-title">Send a package across town</h2><p class="cx-copy">Enter pickup and drop-off details. A nearby approved rider can accept your request.</p></div>
    @if($errors->any())<div class="cx-error">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('customer.custom-delivery.store') }}">@csrf
        <section class="cx-card"><span class="cx-eyebrow">A · Pickup details</span>
            <div class="cx-field"><label for="pickup_address">Pickup address *</label><input id="pickup_address" name="pickup_address" value="{{ old('pickup_address', $address?->address_line) }}" required maxlength="500" autocomplete="street-address"></div>
            <div class="cx-field"><label for="pickup_landmark">Landmark / pickup instructions</label><input id="pickup_landmark" name="pickup_landmark" value="{{ old('pickup_landmark', $address?->landmark) }}" maxlength="255"></div>
            <div class="cx-two"><div class="cx-field"><label for="sender_name">Sender *</label><input id="sender_name" name="sender_name" value="{{ old('sender_name', auth()->user()->name) }}" required></div><div class="cx-field"><label for="sender_phone">Phone *</label><input id="sender_phone" name="sender_phone" value="{{ old('sender_phone', auth()->user()->phone) }}" required></div></div>
            <div class="cx-two"><div class="cx-field"><label for="pickup_latitude">Pickup latitude</label><input id="pickup_latitude" name="pickup_latitude" type="number" step="any" value="{{ old('pickup_latitude', $address?->latitude) }}"></div><div class="cx-field"><label for="pickup_longitude">Pickup longitude</label><input id="pickup_longitude" name="pickup_longitude" type="number" step="any" value="{{ old('pickup_longitude', $address?->longitude) }}"></div></div>
            <button class="cx-button secondary" type="button" data-geolocate="pickup">Use my current location</button>
        </section>
        <section class="cx-card"><span class="cx-eyebrow">B · Drop-off details</span>
            <div class="cx-field"><label for="dropoff_address">Drop-off address *</label><input id="dropoff_address" name="dropoff_address" value="{{ old('dropoff_address') }}" required maxlength="500" autocomplete="street-address"></div>
            <div class="cx-field"><label for="dropoff_landmark">Landmark / drop-off instructions</label><input id="dropoff_landmark" name="dropoff_landmark" value="{{ old('dropoff_landmark') }}" maxlength="255"></div>
            <div class="cx-two"><div class="cx-field"><label for="recipient_name">Recipient *</label><input id="recipient_name" name="recipient_name" value="{{ old('recipient_name') }}" required></div><div class="cx-field"><label for="recipient_phone">Phone *</label><input id="recipient_phone" name="recipient_phone" value="{{ old('recipient_phone') }}" required></div></div>
            <div class="cx-two"><div class="cx-field"><label for="dropoff_latitude">Drop-off latitude</label><input id="dropoff_latitude" name="dropoff_latitude" type="number" step="any" value="{{ old('dropoff_latitude') }}"></div><div class="cx-field"><label for="dropoff_longitude">Drop-off longitude</label><input id="dropoff_longitude" name="dropoff_longitude" type="number" step="any" value="{{ old('dropoff_longitude') }}"></div></div>
            <button class="cx-button secondary" type="button" data-geolocate="dropoff">Set drop-off pin with my location</button>
        </section>
        <section class="cx-card"><span class="cx-eyebrow">C · Package and service</span>
            <div class="cx-field"><label for="package_category">Package category *</label><select id="package_category" name="package_category" required><option value="documents">Documents</option><option value="small_parcel">Small parcel</option><option value="other">Other</option></select></div>
            <div class="cx-field"><label for="description">Description / special handling *</label><textarea id="description" name="description" required maxlength="1000" placeholder="Signed contract papers, fragile item, delivery instructions…">{{ old('description') }}</textarea></div>
        </section>
        <section class="cx-card"><span class="cx-eyebrow">Payment method</span>
            <label class="cx-choice"><input type="radio" name="payment_method" value="cod" checked><span><strong>Cash on delivery</strong><small class="cx-copy">Recipient pays ₱{{ number_format($deliveryFee + $serviceFee, 2) }} to the rider</small></span></label>
            <label class="cx-choice"><input type="radio" name="payment_method" value="gcash"><span><strong>GCash</strong><small class="cx-copy">Request is recorded as unpaid until payment is confirmed.</small></span></label>
            <div class="cx-summary-row"><span>Delivery fee</span><strong>₱{{ number_format($deliveryFee, 2) }}</strong></div><div class="cx-summary-row"><span>Service fee</span><strong>₱{{ number_format($serviceFee, 2) }}</strong></div><div class="cx-summary-row cx-summary-total"><span>Total</span><strong>₱{{ number_format($deliveryFee + $serviceFee, 2) }}</strong></div>
            <button class="cx-button" type="submit">Confirm delivery request</button>
        </section>
    </form>
</main>
<script>
document.querySelectorAll('[data-geolocate]').forEach(button=>button.addEventListener('click',()=>{if(!navigator.geolocation){alert('Location is not available in this browser.');return;}button.disabled=true;button.textContent='Finding location…';navigator.geolocation.getCurrentPosition(position=>{const prefix=button.dataset.geolocate;document.getElementById(prefix+'_latitude').value=position.coords.latitude.toFixed(7);document.getElementById(prefix+'_longitude').value=position.coords.longitude.toFixed(7);button.disabled=false;button.textContent='Location pinned';},()=>{button.disabled=false;button.textContent='Could not get location. Check browser permission.';},{enableHighAccuracy:true,timeout:12000});}));
</script>
@include('customer.partials.bottom-nav',['active'=>'customize'])</body></html>
