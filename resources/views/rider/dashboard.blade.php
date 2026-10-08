<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Dashboard · MotoBook Rider</title>
    @vite(['resources/css/app.css', 'resources/css/pages/rider-dashboard.css'])
    @vite('resources/js/rider-location.js')
</head>
<body class="rider-shell">
    <header class="rider-header">
        <button class="rider-header-icon" type="button" aria-label="Open menu">☰</button>
        <h1>Dashboard</h1>
        <button class="rider-header-icon" type="button" aria-label="Notifications">♧</button>
    </header>
    <main class="rider-content">
        @if (session('status')) <div class="rider-alert">{{ session('status') }}</div> @endif
        @if ($errors->any()) <div class="rider-error">{{ $errors->first() }}</div> @endif
        <section class="rider-profile-row" id="profile"><div class="rider-profile-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}<i></i></div><div class="rider-profile-copy"><strong>{{ auth()->user()->name }}</strong><span>Rider</span><small>★ {{ number_format($rider?->rating ?? 0, 1) }} <em>({{ $rider?->total_deliveries ?? 0 }})</em></small></div><span class="rider-online-pill {{ $rider?->status === 'online' ? 'online' : '' }}">{{ $rider?->status === 'online' ? 'Online' : 'Offline' }}</span></section>
        <section class="rider-earnings-card" id="earnings"><div><span>Today's Earnings</span><strong>₱{{ number_format($earnings, 2) }}</strong></div><span class="rider-wallet-icon">▣</span></section>
        <section class="rider-stats-row"><div><strong>{{ $rider?->total_deliveries ?? 0 }}</strong><span>Completed</span></div><div><strong>{{ $deliveries->count() }}</strong><span>Active orders</span></div><div><strong>{{ $rider?->status === 'online' ? 'Online' : 'Offline' }}</strong><span>Rider status</span></div></section>
        <section class="rider-section" id="deliveries"><div class="rider-section-heading"><div><span class="rider-kicker">Current active order</span><h2>{{ $deliveries->count() ? 'On the road' : 'No active order' }}</h2></div></div>
            @forelse($deliveries as $delivery)
                <article class="rider-delivery-card"><div class="rider-delivery-top"><span class="rider-order-number">{{ $delivery->order->order_number }}</span><span class="rider-delivery-status">{{ str_replace('_', ' ', ucfirst($delivery->status)) }}</span></div><h3>{{ $delivery->order->store->name }}</h3><p class="rider-route-line"><span class="rider-route-pin pickup">▣</span><span>Pick up · {{ number_format($delivery->distance_km ?? 0, 1) }} km</span></p><p class="rider-route-line"><span class="rider-route-pin dropoff">●</span><span>{{ $delivery->order->delivery_address_snapshot['address_line'] ?? 'Drop-off address' }} · Drop off</span></p><div class="rider-delivery-summary"><span>{{ $delivery->order->items->sum('quantity') }} item(s)</span><strong>₱{{ number_format($delivery->order->total_amount, 2) }}</strong></div><div class="rider-actions">@if($delivery->status === 'assigned')<form method="POST" action="{{ route('rider.deliveries.accept', $delivery) }}">@csrf<button class="rider-button primary">Accept</button></form><form method="POST" action="{{ route('rider.deliveries.reject', $delivery) }}">@csrf<button class="rider-button secondary">Decline</button></form>@elseif($delivery->status === 'accepted')<form method="POST" action="{{ route('rider.deliveries.startPickup', $delivery) }}">@csrf<button class="rider-button primary">Start pickup</button></form>@elseif($delivery->status === 'en_route_to_pickup')<form method="POST" action="{{ route('rider.deliveries.arrivePickup', $delivery) }}">@csrf<button class="rider-button primary">I've arrived</button></form>@elseif($delivery->status === 'arrived_at_pickup')<form method="POST" action="{{ route('rider.deliveries.confirmPickup', $delivery) }}">@csrf<button class="rider-button primary">Confirm pickup</button></form>@elseif($delivery->status === 'picked_up')<form method="POST" action="{{ route('rider.deliveries.startCustomer', $delivery) }}">@csrf<button class="rider-button primary">Navigate to customer</button></form>@elseif($delivery->status === 'en_route_to_customer')<form method="POST" action="{{ route('rider.deliveries.arriveCustomer', $delivery) }}">@csrf<button class="rider-button primary">I've arrived</button></form>@elseif($delivery->status === 'arrived_at_customer')<form method="POST" action="{{ route('rider.deliveries.complete', $delivery) }}">@csrf<button class="rider-button primary">Complete delivery</button></form>@endif</div></article>
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
    <nav class="rider-bottom-nav"><a class="active" href="{{ route('rider.dashboard') }}"><span>⌂</span>Home</a><a href="#deliveries"><span>▣</span>Orders</a><form method="POST" action="{{ route('rider.status.toggle') }}">@csrf<button class="online-action" type="submit"><span>➤</span>{{ $rider?->status === 'online' ? 'Go Offline' : 'Go Online' }}</button></form><a href="#earnings"><span>▤</span>Earnings</a><a href="{{ route('rider.profile') }}"><span>◎</span>Profile</a></nav>
</body>
</html>
