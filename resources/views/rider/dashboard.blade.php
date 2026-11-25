<!DOCTYPE html>
<html lang="en" data-rider-theme-root data-rider-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Dashboard · MotoBook Rider</title>
    @vite(['resources/css/app.css', 'resources/css/pages/rider-dashboard.css', 'resources/css/pages/rider-theme.css'])
    @vite('resources/js/rider-location.js')
    @vite('resources/js/rider-offers.js')
    @vite('resources/js/rider-theme.js')
</head>
<body class="rider-shell" data-rider-page="dashboard">
    <header class="rider-header">
        <a class="rider-header-icon" href="#deliveries" aria-label="View active orders">☰</a>
        <h1>Dashboard</h1>
        <x-rider.theme-toggle />
    </header>
    <main class="rider-content">
        @if (session('status')) <div class="rider-alert">{{ session('status') }}</div> @endif
        @if ($errors->any()) <div class="rider-error">{{ $errors->first() }}</div> @endif
        <section class="rider-profile-row" id="profile">
            <div class="rider-profile-avatar">
                @if (auth()->user()->avatar)
                    <img src="{{ asset('storage/'.auth()->user()->avatar) }}" alt="" class="rider-profile-photo">
                @else
                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                @endif
                <i class="{{ $rider?->status === 'online' ? 'online' : '' }}"></i>
            </div>
            <div class="rider-profile-copy">
                <strong>{{ auth()->user()->name }} @if ($rider?->is_verified)<span class="rider-verified-mark" aria-label="Verified rider">✓</span>@endif</strong>
                <span>{{ $rider?->plate_number ?: 'Rider' }}</span>
                <small>★ {{ number_format($rider?->rating ?? 0, 1) }} <em>★</em> ({{ $rider?->total_deliveries ?? 0 }})</small>
            </div>
            <form method="POST" action="{{ route('rider.status.toggle') }}" class="rider-status-form">
                @csrf
                <button class="rider-online-pill rider-status-toggle {{ $rider?->status === 'online' ? 'online' : '' }}" type="submit" @disabled($rider?->status === 'online' ? false : ! $canGoOnline)>
                    {{ $rider?->status === 'online' ? 'Online' : 'Offline' }} <span aria-hidden="true">{{ $rider?->status === 'online' ? '●' : '○' }}</span>
                </button>
            </form>
        </section>
        <section class="rider-earnings-card rider-dashboard-earnings" id="earnings">
            <div><span>Today's Earnings</span><strong>₱{{ number_format($earnings, 2) }}</strong></div>
            <svg class="rider-dashboard-wallet-icon" viewBox="0 0 48 48" fill="none" aria-hidden="true"><path d="M7 11.5A3.5 3.5 0 0 1 10.5 8H39a3 3 0 0 1 3 3v26a3 3 0 0 1-3 3H10.5A3.5 3.5 0 0 1 7 36.5v-25Z" fill="currentColor" opacity=".28"/><path d="M7 14h31.5A3.5 3.5 0 0 1 42 17.5v16a3.5 3.5 0 0 1-3.5 3.5H10.5A3.5 3.5 0 0 1 7 33.5v-19Z" fill="currentColor" opacity=".42"/><path d="M32 22h12v10H32a5 5 0 0 1 0-10Z" fill="currentColor" opacity=".55"/><circle cx="34" cy="27" r="1.5" fill="#08A6D3"/></svg>
        </section>
        <section class="rider-stats-row rider-dashboard-stats" aria-label="Rider statistics">
            <div><strong>{{ $rider?->total_deliveries ?? 0 }}</strong><span>Completed</span></div>
            <div><strong>{{ $onlineHours }}</strong><span>Online Hours</span></div>
        </section>
        @if ($rider?->status === 'online' && $rider?->is_verified)
            <section class="rider-section rider-offers-section" data-rider-offers data-offers-url="{{ route('rider.offers') }}">
                <div class="rider-section-heading">
                    <div><span class="rider-kicker">Fair rider queue</span><h2>New Order Requests <span class="rider-offer-count">{{ $offers->count() }}</span></h2></div>
                    <button class="rider-notification-toggle" type="button" data-enable-order-notifications>Enable alerts</button>
                </div>
                <p class="rider-queue-note">Orders are offered in turns. Each rider gets 20 seconds to accept before the next rider’s turn.</p>
                @forelse ($offers as $offer)
                    @php
                        $currentOffer = $offer->delivery->offers->firstWhere('status', 'offered');
                        $secondsUntilTurn = $currentOffer?->expires_at
                            ? max(0, now()->diffInSeconds($currentOffer->expires_at, false))
                                + max(0, $offer->queue_position - $currentOffer->queue_position - 1) * 20
                            : 0;
                        $isMyTurn = $offer->status === 'offered' && $offer->expires_at?->isFuture();
                    @endphp
                    <article class="rider-delivery-card rider-offer-card" data-offer-id="{{ $offer->id }}" data-offer-status="{{ $offer->status }}" data-offer-can-accept="{{ $isMyTurn ? '1' : '0' }}">
                        <div class="rider-delivery-top"><span class="rider-order-number">Order {{ $offer->delivery->order->order_number }}</span><span class="rider-delivery-status">{{ $isMyTurn ? 'Your turn' : 'Queue #'.$offer->queue_position }}</span></div>
                        <h3>{{ $offer->delivery->order->store->name }}</h3>
                        <p class="rider-address">{{ $offer->delivery->order->items->sum('quantity') }} item(s) · ₱{{ number_format($offer->delivery->order->total_amount, 2) }}</p>
                        @if ($isMyTurn)
                            <p class="rider-queue-note" data-rider-countdown data-rider-countdown-mode="accept" data-rider-countdown="{{ max(0, now()->diffInSeconds($offer->expires_at, false)) }}">Accept within {{ max(0, now()->diffInSeconds($offer->expires_at, false)) }} seconds.</p>
                            <div class="rider-actions">
                                <form method="POST" action="{{ route('rider.deliveries.accept', $offer->delivery) }}">@csrf<button class="rider-button primary" type="submit">Accept order</button></form>
                                <form method="POST" action="{{ route('rider.deliveries.reject', $offer->delivery) }}">@csrf<button class="rider-button secondary" type="submit">Skip turn</button></form>
                            </div>
                        @else
                            <p class="rider-queue-note" data-rider-countdown data-rider-countdown="{{ $secondsUntilTurn }}">Your turn is expected in about {{ ceil($secondsUntilTurn / 60) }} min {{ $secondsUntilTurn % 60 }} sec.</p>
                        @endif
                    </article>
                @empty
                    <div class="rider-empty rider-offer-empty" data-empty-offers><strong>No open orders</strong><p>New orders will appear here while you’re online.</p></div>
                @endforelse
            </section>
        @endif
        <section class="rider-section" id="deliveries"><div class="rider-section-heading"><div><h2>Current Active Order</h2></div><a class="rider-link-action" href="{{ route('rider.requests') }}">New requests</a></div>
            @forelse($deliveries as $delivery)
                <article class="rider-delivery-card"><div class="rider-delivery-top"><span class="rider-order-number">{{ $delivery->order->order_number }}</span><span class="rider-delivery-status">{{ str_replace('_', ' ', ucfirst($delivery->status)) }}</span></div><h3>{{ $delivery->order->store->name }}</h3><p class="rider-route-line"><span class="rider-route-pin pickup">▣</span><span>Pick up · {{ number_format($delivery->distance_km ?? 0, 1) }} km</span></p><p class="rider-route-line"><span class="rider-route-pin dropoff">●</span><span>{{ $delivery->order->delivery_address_snapshot['address_line'] ?? 'Drop-off address' }} · Drop off</span></p><div class="rider-delivery-summary"><span>{{ $delivery->order->items->sum('quantity') }} item(s)</span><strong>₱{{ number_format($delivery->order->total_amount, 2) }}</strong></div><div class="rider-actions">@if($delivery->status === 'assigned')<form method="POST" action="{{ route('rider.deliveries.accept', $delivery) }}">@csrf<button class="rider-button primary">Accept</button></form><form method="POST" action="{{ route('rider.deliveries.reject', $delivery) }}">@csrf<button class="rider-button secondary">Decline</button></form>@elseif($delivery->status === 'accepted')<form method="POST" action="{{ route('rider.deliveries.startPickup', $delivery) }}">@csrf<button class="rider-button primary">Start pickup</button></form>@elseif($delivery->status === 'en_route_to_pickup')<form method="POST" action="{{ route('rider.deliveries.arrivePickup', $delivery) }}">@csrf<button class="rider-button primary">I've arrived</button></form>@elseif($delivery->status === 'arrived_at_pickup')<form method="POST" action="{{ route('rider.deliveries.confirmPickup', $delivery) }}">@csrf<button class="rider-button primary">Confirm pickup</button></form>@elseif($delivery->status === 'picked_up')<form method="POST" action="{{ route('rider.deliveries.startCustomer', $delivery) }}">@csrf<button class="rider-button primary">Navigate to customer</button></form>@elseif($delivery->status === 'en_route_to_customer')<form method="POST" action="{{ route('rider.deliveries.arriveCustomer', $delivery) }}">@csrf<button class="rider-button primary">I've arrived</button></form>@elseif($delivery->status === 'arrived_at_customer')<form method="POST" action="{{ route('rider.deliveries.complete', $delivery) }}">@csrf<button class="rider-button primary">Complete delivery</button>@endif</div><a class="rider-view-details" href="{{ route('rider.orders.show', $delivery) }}">View Details</a></article>
            @empty
                <div class="rider-empty"><span>✓</span><strong>No active deliveries</strong><p>New delivery requests will appear here.</p></div>
            @endforelse
        </section>
        <section class="rider-section"><div class="rider-section-heading"><div><span class="rider-kicker">Door-to-door courier jobs</span><h2>Custom deliveries</h2></div></div>
            @forelse($customDeliveries as $job)
                <article class="rider-delivery-card"><div class="rider-delivery-top"><span class="rider-order-number">Custom request #{{ $job->id }}</span><span class="rider-delivery-status">{{ str_replace('_', ' ', ucfirst($job->status)) }}</span></div><h3>{{ $job->package_category === 'small_parcel' ? 'Small parcel' : ucfirst($job->package_category) }}</h3><p class="rider-address">Pickup: {{ $job->pickup_address }} @if($job->pickup_landmark) · {{ $job->pickup_landmark }} @endif</p><p class="rider-address">Drop-off: {{ $job->dropoff_address }} @if($job->dropoff_landmark) · {{ $job->dropoff_landmark }} @endif</p><p class="rider-address">{{ $job->description }}</p><div class="rider-delivery-summary"><span>{{ $job->payment_method === 'cod' ? 'Collect COD' : 'GCash payment pending' }}</span><strong>₱{{ number_format($job->total_amount, 2) }}</strong></div><div class="rider-actions">
                    @if($job->status === 'pending_assignment' || $job->status === 'assigned')
                        <form method="POST" action="{{ route('rider.custom-deliveries.accept', $job) }}">@csrf<button class="rider-button primary">Accept</button></form>
                        @if($job->rider_id === $rider?->id)<form method="POST" action="{{ route('rider.custom-deliveries.reject', $job) }}">@csrf<button class="rider-button secondary">Decline</button></form>@endif
                    @elseif($job->status === 'accepted')
                        <form method="POST" action="{{ route('rider.custom-deliveries.pickup', $job) }}">@csrf<button class="rider-button primary">Confirm pickup</button></form>
                    @elseif($job->status === 'picked_up')
                        <form method="POST" action="{{ route('rider.custom-deliveries.complete', $job) }}">@csrf<button class="rider-button primary">Complete delivery</button></form>
                    @endif
                </div></article>
            @empty
                <div class="rider-empty"><strong>No custom delivery requests</strong><p>New requests will appear here while you are online.</p></div>
            @endforelse
        </section>
        <div id="location"><button id="share-location" class="rider-location-button" type="button" data-delivery-id="{{ $deliveries->first()?->id }}" data-location-url="{{ route('rider.location.update') }}" data-custom-location-url="{{ $activeCustomDelivery ? route('rider.custom-deliveries.location',$activeCustomDelivery) : '' }}" @disabled($deliveries->isEmpty() && !$activeCustomDelivery)>{{ $deliveries->isEmpty() && !$activeCustomDelivery ? 'Location sharing available during a delivery' : 'Start live location sharing' }}</button><p class="rider-location-feedback" id="location-feedback" aria-live="polite"></p></div>
        <section class="rider-section rider-store-section"><div class="rider-section-heading"><div><span class="rider-kicker">Partner stores</span><h2>Store status</h2></div></div><div class="rider-store-list">@foreach($stores as $store)<div><span>{{ $store->name }}</span><strong class="{{ $store->is_open ? 'open' : 'closed' }}">{{ $store->is_open ? 'Open' : 'Closed' }}</strong></div>@endforeach</div></section>
    </main>
    <x-rider.bottom-nav :rider="$rider" :can-go-online="$canGoOnline" :has-active-delivery="$hasActiveDelivery" active="home" />
</body>
</html>
