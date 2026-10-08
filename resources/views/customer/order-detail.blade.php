<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Order {{ $order->order_number }} · MotoBook</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    @vite(['resources/css/app.css', 'resources/css/pages/order-detail.css'])
    @vite('resources/css/pages/customer-experience.css')
    @vite('resources/js/customer-order-tracking.js')
</head>
<body class="order-detail-shell" data-theme="{{ (auth()->user()->customer_settings['dark_mode'] ?? false) ? 'dark' : 'light' }}">

    @php
        $isCancelled = in_array($order->status, ['rejected', 'cancelled']);
        $isDone = in_array($order->status, ['delivered', 'completed']);
        $managementRider = $managementTracking['rider'] ?? null;
        $deliveryStatus = $managementTracking['delivery_status'] ?? $order->delivery?->status;
        $deliveryStatusMessage = match ($deliveryStatus) {
            'assigned' => 'Rider assigned to your order',
            'accepted' => 'Rider accepted your delivery',
            'en_route_to_pickup' => 'Rider is heading to the restaurant',
            'arrived_at_pickup' => 'Rider has arrived at the restaurant',
            'picked_up' => 'Your order has been picked up',
            'en_route_to_customer' => 'Your rider is on the way to you',
            'arrived_at_customer' => 'Your rider has arrived',
            'delivered' => 'Delivered!',
            default => null,
        };

        // ordered progression, used to decide which timeline steps are "complete"
        $progression = ['pending', 'accepted', 'preparing', 'ready_for_pickup', 'assigned', 'out_for_delivery', 'delivered'];
        $currentIndex = array_search($order->status, $progression);
        if ($order->status === 'completed') $currentIndex = count($progression) - 1;

        $steps = [
            ['label' => 'Order Placed', 'time' => $order->created_at, 'at' => 0],
            ['label' => 'Preparing', 'time' => $order->accepted_at, 'at' => 2],
            ['label' => 'Rider Assigned', 'time' => null, 'at' => 4],
            ['label' => 'Out for Delivery', 'time' => null, 'at' => 5],
            ['label' => 'Delivered', 'time' => $order->delivered_at, 'at' => 6],
        ];
    @endphp

    <div class="order-detail-header">
        <a href="{{ route('customer.orders.index') }}" class="order-detail-back">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div>
            <div class="order-detail-title">{{ $order->store->name }}</div>
            <div class="order-detail-sub">{{ $order->order_number }}</div>
        </div>
    </div>

    @if (session('status'))
        <div class="order-section text-sm text-green-700">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="order-section text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    @if ($isCancelled)
        <div class="status-banner-big cancelled">
            <div class="status-banner-big-icon">✕</div>
            <div class="status-banner-big-text">
                {{ $order->status === 'rejected' ? 'Order Rejected by Merchant' : 'Order Cancelled' }}
            </div>
            @if ($order->cancel_reason)
                <div class="text-[12.5px] mt-1">{{ $order->cancel_reason }}</div>
            @endif
        </div>
    @elseif ($isDone)
        <div class="status-banner-big done">
            <div class="status-banner-big-icon">✅</div>
            <div class="status-banner-big-text">Delivered!</div>
        </div>
    @else
        <div class="status-banner-big active">
            <div class="status-banner-big-icon">🛵</div>
            <div class="status-banner-big-text" id="order-live-status">{{ $deliveryStatusMessage ?? 'Your order is on its way' }}</div>
        </div>
    @endif

    @unless ($isCancelled)
        <div class="timeline">
            @foreach ($steps as $i => $step)
                @php $complete = $currentIndex >= $step['at']; @endphp
                <div class="timeline-step">
                    @if (!$loop->last)
                        <div class="timeline-line {{ $complete ? 'complete' : 'pending' }}"></div>
                    @endif
                    <div class="timeline-dot {{ $complete ? 'complete' : 'pending' }}">{{ $complete ? '✓' : '' }}</div>
                    <div>
                        <div class="timeline-label {{ $complete ? '' : 'pending' }}">{{ $step['label'] }}</div>
                        @if ($complete && $step['time'])
                            <div class="timeline-time">{{ $step['time']->format('g:i A') }}</div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endunless

    @if (($order->delivery && $order->delivery->rider) || $managementRider)
        <div class="rider-card">
            <div class="rider-avatar">🏍️</div>
            <div>
                <div class="rider-name">{{ $managementRider['name'] ?? $order->delivery?->rider?->user?->name }}</div>
                <div class="rider-vehicle">{{ $managementRider ? "Assigned through MotoBook Management" : ucfirst($order->delivery->rider->vehicle_type) }}</div>
            </div>
            @if (!empty($managementRider['phone']))<a href="tel:{{ $managementRider['phone'] }}" aria-label="Call rider">Call</a>@endif
        </div>
        @if (in_array($order->status, ['assigned', 'out_for_delivery']))
            <section class="tracking-panel" id="tracking-panel"
                data-tracking-url="{{ route('customer.orders.tracking', $order) }}"
                data-destination-lat="{{ $order->delivery_address_snapshot['latitude'] ?? '' }}"
                data-destination-lng="{{ $order->delivery_address_snapshot['longitude'] ?? '' }}">
                <div class="tracking-panel-heading"><strong>Live rider tracking</strong><span class="tracking-live-indicator"></span></div>
                <p class="tracking-status" id="tracking-status" aria-live="polite">Waiting for rider location…</p>
                <div class="customer-live-map" id="customer-live-map" role="region" aria-label="Live rider location map"></div>
                <small class="tracking-map-note">Location updates while your rider is sharing GPS.</small>
            </section>
        @endif
    @endif

    <div class="order-section">
        <div class="order-section-title">Items</div>
        @foreach ($order->items as $item)
            <div class="order-item-row">
                <span>{{ $item->item_name }} ×{{ $item->quantity }}</span>
                <span>₱{{ number_format($item->subtotal, 2) }}</span>
            </div>
        @endforeach
    </div>

    <div class="order-section">
        <div class="summary-row"><span>Subtotal</span><span>₱{{ number_format($order->subtotal, 2) }}</span></div>
        <div class="summary-row"><span>Delivery Fee</span><span>₱{{ number_format($order->delivery_fee, 2) }}</span></div>
        @if ($order->service_fee > 0)
            <div class="summary-row"><span>Service Fee</span><span>₱{{ number_format($order->service_fee, 2) }}</span></div>
        @endif
        @if ($order->discount_amount > 0)
            <div class="summary-row"><span>Discount</span><span>-₱{{ number_format($order->discount_amount, 2) }}</span></div>
        @endif
        <div class="summary-divider"></div>
        <div class="summary-total-row">
            <span class="summary-total-label">Total</span>
            <span class="summary-total-value">₱{{ number_format($order->total_amount, 2) }}</span>
        </div>
    </div>

    <div class="order-section">
        <div class="order-section-title">Delivery Details</div>
        <div class="text-[13px]">{{ $order->payment_method === 'cod' ? 'Cash on Delivery' : strtoupper($order->payment_method) }} · {{ ucfirst($order->payment_status) }}</div>
        @if ($order->delivery_address_snapshot)
            <div class="text-[12.5px] text-text-dim mt-1">{{ $order->delivery_address_snapshot['address_line'] ?? '' }}</div>
        @endif
    </div>

    @if (in_array($order->status, ['delivered', 'completed'], true) && ! $order->review)
        <div class="order-section">
            <div class="order-section-title">Rate your order</div>
            <form method="POST" action="{{ route('customer.orders.review', $order) }}" class="grid gap-3">
                @csrf
                <label class="grid gap-1 text-sm">
                    <span>Restaurant rating</span>
                    <select name="store_rating" required>
                        <option value="">Choose a rating</option>
                        @for ($rating = 5; $rating >= 1; $rating--)
                            <option value="{{ $rating }}">{{ $rating }} / 5</option>
                        @endfor
                    </select>
                </label>
                <textarea name="store_comment" maxlength="1000" rows="3" placeholder="Share your feedback (optional)"></textarea>
                @if ($order->rider_id)
                    <label class="grid gap-1 text-sm">
                        <span>Rider rating</span>
                        <select name="rider_rating">
                            <option value="">Skip rider rating</option>
                            @for ($rating = 5; $rating >= 1; $rating--)
                                <option value="{{ $rating }}">{{ $rating }} / 5</option>
                            @endfor
                        </select>
                    </label>
                    <textarea name="rider_comment" maxlength="1000" rows="3" placeholder="Share feedback about your rider (optional)"></textarea>
                @endif
                <x-primary-button>{{ __('Submit review') }}</x-primary-button>
            </form>
        </div>
    @elseif ($order->review)
        <div class="order-section">
            <div class="order-section-title">Your review</div>
            <p>Restaurant: {{ $order->review->store_rating }} / 5</p>
            @if ($order->review->rider_rating)
                <p>Rider: {{ $order->review->rider_rating }} / 5</p>
            @endif
            @if ($order->review->store_comment)
                <p>{{ $order->review->store_comment }}</p>
            @endif
            @if ($order->review->rider_comment)
                <p>{{ $order->review->rider_comment }}</p>
            @endif
        </div>
    @endif

    @if ($order->status === 'pending')
        <div class="order-section">
            <form method="POST" action="{{ route('customer.orders.cancel', $order) }}" class="grid gap-3">
                @csrf
                <label for="cancel_reason" class="order-section-title">Cancel pending order</label>
                <input id="cancel_reason" type="text" name="reason" maxlength="500" placeholder="Reason for cancellation" required>
                <x-primary-button>{{ __('Cancel order') }}</x-primary-button>
            </form>
        </div>
    @endif

</body>
</html>
