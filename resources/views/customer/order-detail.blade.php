<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Track Order · {{ $order->order_number }} · MotoBook</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    @vite(['resources/css/app.css', 'resources/css/pages/order-detail.css'])
    @vite('resources/css/pages/customer-experience.css')
    @vite('resources/js/customer-order-tracking.js')
</head>
<body class="order-detail-shell track-order-shell" data-theme="{{ (auth()->user()->customer_settings['dark_mode'] ?? false) ? 'dark' : 'light' }}">
    @php
        $isCancelled = in_array($order->status, ['rejected', 'cancelled'], true);
        $isDone = in_array($order->status, ['delivered', 'completed'], true);
        $managementRider = $managementTracking['rider'] ?? null;
        $delivery = $order->delivery;
        $deliveryStatus = $managementTracking['delivery_status'] ?? $delivery?->status;
        $rider = $delivery?->rider;
        $riderName = $managementRider['name'] ?? $rider?->user?->name;
        $riderPhone = $managementRider['phone'] ?? $rider?->user?->phone;
        $riderVehicle = $rider?->vehicle_type ? ucfirst(str_replace('_', ' ', $rider->vehicle_type)) : 'MotoBook delivery rider';
        $riderAvatar = $rider?->user?->avatar;
        $deliveryStatusMessage = match ($deliveryStatus) {
            'assigned' => 'Rider assigned to your order',
            'accepted' => 'Rider accepted your delivery',
            'en_route_to_pickup' => 'Rider is heading to the restaurant',
            'arrived_at_pickup' => 'Rider has arrived at the restaurant',
            'picked_up' => 'Your order has been picked up',
            'en_route_to_customer' => 'Your rider is on the way to you',
            'arrived_at_customer' => 'Your rider has arrived',
            'delivered' => 'Delivered!',
            default => match ($order->status) {
                'pending' => 'Waiting for the restaurant to confirm',
                'accepted', 'preparing' => 'The restaurant is preparing your order',
                'ready_for_pickup' => 'Your order is ready for a rider',
                default => 'We’ll keep you updated on your order',
            },
        };
        $currentStep = match (true) {
            $isDone => 4,
            in_array($deliveryStatus, ['en_route_to_customer', 'arrived_at_customer'], true) => 3,
            in_array($deliveryStatus, ['picked_up'], true), $order->status === 'out_for_delivery' => 2,
            in_array($deliveryStatus, ['assigned', 'accepted', 'en_route_to_pickup', 'arrived_at_pickup'], true),
            in_array($order->status, ['accepted', 'preparing', 'ready_for_pickup', 'assigned'], true) => 1,
            default => 0,
        };
        $steps = [
            ['label' => 'Pending', 'time' => $order->created_at],
            ['label' => 'Confirmed', 'time' => $order->accepted_at],
            ['label' => 'Picked Up', 'time' => $delivery?->picked_up_at],
            ['label' => 'On Delivery', 'time' => null],
            ['label' => 'Drop-off', 'time' => $order->delivered_at],
        ];
        $pickupAddress = $order->pickup_address ?? $order->store?->address ?? $order->store?->name ?? 'Pickup location';
        $dropoffAddress = data_get($order->delivery_address_snapshot, 'address_line', $order->dropoff_address);
        $dropoffLandmark = data_get($order->delivery_address_snapshot, 'landmark');
        $isCod = strtolower($order->payment_method) === 'cod';
    @endphp

    <header class="track-order-header">
        <a href="{{ route('customer.orders.index') }}" class="track-order-back" aria-label="Back to orders">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
        </a>
        <h1>Track Order</h1>
        <a href="{{ route('customer.help') }}" class="track-order-help" aria-label="Help and support">?</a>
    </header>

    @if (session('status'))
        <div class="order-section track-order-alert text-sm text-green-700">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="order-section track-order-alert text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    @if ($isCancelled)
        <section class="track-status-card is-cancelled">
            <span class="track-status-icon" aria-hidden="true">×</span>
            <div><small>Order {{ $order->order_number }}</small><strong>{{ $order->status === 'rejected' ? 'Order rejected' : 'Order cancelled' }}</strong>
                @if ($order->cancel_reason)<p>{{ $order->cancel_reason }}</p>@endif
            </div>
        </section>
    @elseif ($isDone)
        <section class="track-status-card is-done">
            <span class="track-status-icon" aria-hidden="true">✓</span>
            <div class="track-status-copy"><small>Order {{ $order->order_number }}</small><strong>Delivered successfully</strong><p id="tracking-status">Your order has arrived. Enjoy!</p></div>
        </section>
    @else
        <section class="track-status-card">
            <span class="track-status-icon" aria-hidden="true">⌖</span>
            <div class="track-status-copy"><small>Estimated delivery · Order {{ $order->order_number }}</small><strong id="delivery-eta">{{ $riderName ? 'Waiting for rider GPS' : 'Waiting for rider assignment' }}</strong><p id="order-live-status">{{ $deliveryStatusMessage }}</p><p id="tracking-status" aria-live="polite">{{ $riderName ? 'Your rider is on the way!' : 'We’ll show your rider here when one is assigned.' }}</p></div>
            <span class="track-live-badge"><i></i> LIVE</span>
        </section>
    @endif

    @unless ($isCancelled)
        <section class="track-map-card" @if ($isDone) data-map-complete @endif>
            <div class="track-map-stop pickup-stop"><span class="track-map-pin pickup-pin">⌖</span><span><small>Pickup location</small><strong>{{ $pickupAddress }}</strong><small>{{ $order->store?->name }}</small></span></div>
            <div class="track-map-stop dropoff-stop"><span class="track-map-pin dropoff-pin">●</span><span><small>Drop-off{{ $dropoffLandmark ? ' / landmark' : '' }}</small><strong>{{ $dropoffAddress }}</strong>@if ($dropoffLandmark)<small>{{ $dropoffLandmark }}</small>@endif</span></div>
            <section class="tracking-panel" id="tracking-panel"
                data-tracking-url="{{ route('customer.orders.tracking', $order) }}"
                data-pickup-lat="{{ $order->store?->latitude ?? '' }}"
                data-pickup-lng="{{ $order->store?->longitude ?? '' }}"
                data-destination-lat="{{ data_get($order->delivery_address_snapshot, 'latitude', $order->customer_lat) }}"
                data-destination-lng="{{ data_get($order->delivery_address_snapshot, 'longitude', $order->customer_lng) }}">
                <div class="customer-live-map" id="customer-live-map" role="region" aria-label="Live map showing the rider and delivery route"></div>
            </section>
        </section>

        <section class="track-progress-card" aria-label="Order progress">
            @foreach ($steps as $index => $step)
                @php $stepComplete = $index <= $currentStep; @endphp
                <div class="track-progress-step {{ $stepComplete ? 'is-complete' : '' }} {{ $index === $currentStep && !$isDone ? 'is-current' : '' }}">
                    <span class="track-progress-marker">{{ $stepComplete ? '✓' : '' }}</span>
                    <span class="track-progress-label">{{ $step['label'] }}</span>
                    @if ($stepComplete && $step['time'])<small>{{ $step['time']->format('g:i A') }}</small>@endif
                </div>
            @endforeach
        </section>
    @endunless

    @if ($riderName)
        <section class="track-rider-card">
            @if ($riderAvatar)
                <img class="track-rider-avatar" src="{{ asset('storage/'.$riderAvatar) }}" alt="">
            @else
                <span class="track-rider-avatar track-rider-initials" aria-hidden="true">{{ strtoupper(substr($riderName, 0, 1)) }}</span>
            @endif
            <div class="track-rider-info"><strong>{{ $riderName }}</strong><small>{{ $riderVehicle }}{{ $rider?->plate_number ? ' · '.$rider->plate_number : '' }}</small></div>
            @if ($riderPhone)
                <a class="track-contact-button" href="sms:{{ $riderPhone }}?body={{ rawurlencode('Hi, I am tracking order '.$order->order_number) }}" aria-label="Message rider">…</a>
                <a class="track-contact-button call-button" href="tel:{{ $riderPhone }}" aria-label="Call rider">☎</a>
            @endif
        </section>
    @endif

    @if (strtolower($order->payment_status) !== 'paid')
        <section class="track-payment-card">
            <span class="track-payment-icon">{{ $isCod ? 'COD' : '₱' }}</span>
            <div class="track-payment-info"><strong>{{ $isCod ? 'Cash on Delivery' : strtoupper($order->payment_method) }}</strong><small>{{ $isCod ? 'Amount due' : ucfirst($order->payment_status) }}</small><b>₱{{ number_format($order->total_amount, 2) }}</b></div>
            <p>{{ $isCod ? 'Please prepare the exact amount for payment when your rider arrives.' : 'Payment status will update when your payment is confirmed.' }}</p>
        </section>
    @endif

    <a class="track-support-card" href="{{ route('customer.help') }}">
        <span class="track-support-shield" aria-hidden="true">✓</span>
        <span><small>MotoBook Help</small><strong>Need help with this delivery?</strong><small>View FAQs and order support guidance.</small></span>
        <b aria-hidden="true">›</b>
    </a>

    <details class="track-order-summary">
        <summary>Order summary <span>₱{{ number_format($order->total_amount, 2) }}</span></summary>
        <div class="order-section-title">Items</div>
        @foreach ($order->items as $item)
            <div class="order-item-row"><span>{{ $item->item_name }} ×{{ $item->quantity }}</span><span>₱{{ number_format($item->subtotal, 2) }}</span></div>
        @endforeach
        <div class="summary-row"><span>Subtotal</span><span>₱{{ number_format($order->subtotal, 2) }}</span></div>
        <div class="summary-row"><span>Delivery fee</span><span>₱{{ number_format($order->delivery_fee, 2) }}</span></div>
        @if ($order->service_fee > 0)<div class="summary-row"><span>Service fee</span><span>₱{{ number_format($order->service_fee, 2) }}</span></div>@endif
        @if ($order->discount_amount > 0)<div class="summary-row"><span>Discount</span><span>-₱{{ number_format($order->discount_amount, 2) }}</span></div>@endif
    </details>

    @if (in_array($order->status, ['delivered', 'completed'], true) && ! $order->review)
        <div class="order-section track-secondary-section">
            <div class="order-section-title">Rate your order</div>
            <form method="POST" action="{{ route('customer.orders.review', $order) }}" class="grid gap-3">
                @csrf
                <label class="grid gap-1 text-sm"><span>Restaurant rating</span><select name="store_rating" required><option value="">Choose a rating</option>@for ($rating = 5; $rating >= 1; $rating--)<option value="{{ $rating }}">{{ $rating }} / 5</option>@endfor</select></label>
                <textarea name="store_comment" maxlength="1000" rows="3" placeholder="Share your feedback (optional)"></textarea>
                @if ($order->rider_id)<label class="grid gap-1 text-sm"><span>Rider rating</span><select name="rider_rating"><option value="">Skip rider rating</option>@for ($rating = 5; $rating >= 1; $rating--)<option value="{{ $rating }}">{{ $rating }} / 5</option>@endfor</select></label><textarea name="rider_comment" maxlength="1000" rows="3" placeholder="Share feedback about your rider (optional)"></textarea>@endif
                <x-primary-button>{{ __('Submit review') }}</x-primary-button>
            </form>
        </div>
    @elseif ($order->review)
        <div class="order-section track-secondary-section"><div class="order-section-title">Your review</div><p>Restaurant: {{ $order->review->store_rating }} / 5</p>@if ($order->review->rider_rating)<p>Rider: {{ $order->review->rider_rating }} / 5</p>@endif @if ($order->review->store_comment)<p>{{ $order->review->store_comment }}</p>@endif @if ($order->review->rider_comment)<p>{{ $order->review->rider_comment }}</p>@endif</div>
    @endif

    @if ($order->status === 'pending')
        <div class="order-section track-secondary-section"><form method="POST" action="{{ route('customer.orders.cancel', $order) }}" class="grid gap-3">@csrf<label for="cancel_reason" class="order-section-title">Cancel pending order</label><input id="cancel_reason" type="text" name="reason" maxlength="500" placeholder="Reason for cancellation" required><x-primary-button>{{ __('Cancel order') }}</x-primary-button></form></div>
    @endif
</body>
</html>
