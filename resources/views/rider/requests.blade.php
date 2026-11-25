<x-rider.page-layout title="New Delivery Requests" active="requests" :rider="$rider" :can-go-online="$canGoOnline" :has-active-delivery="$hasActiveDelivery">
    @if (! $rider->is_verified)
        <div class="rider-empty"><strong>Verification required</strong><p>Your rider documents must be verified before you can receive delivery requests.</p></div>
    @elseif ($rider->status !== 'online')
        <div class="rider-empty"><strong>You're offline</strong><p>Go online to receive new delivery requests.</p></div>
    @else
        <section class="rider-section rider-offers-section" data-rider-offers data-offers-url="{{ route('rider.offers') }}">
            <div class="rider-section-heading">
                <div><span class="rider-kicker">Fair rider queue</span><h2>Restaurant orders <span class="rider-offer-count">{{ $offers->count() }}</span></h2></div>
                <button class="rider-notification-toggle" type="button" data-enable-order-notifications>Enable alerts</button>
            </div>
            <p class="rider-queue-note">Riders get turns to accept an order. Your place in the queue is shown below.</p>
            @forelse ($offers as $offer)
                @php
                    $currentOffer = $offer->delivery->offers->firstWhere('status', 'offered');
                    $isMyTurn = $offer->status === 'offered' && $offer->expires_at?->isFuture();
                    $secondsUntilTurn = $currentOffer?->expires_at
                        ? max(0, now()->diffInSeconds($currentOffer->expires_at, false))
                            + max(0, $offer->queue_position - $currentOffer->queue_position - 1) * 20
                        : 0;
                @endphp
                <article class="rider-delivery-card rider-offer-card" data-offer-id="{{ $offer->id }}" data-offer-status="{{ $offer->status }}" data-offer-can-accept="{{ $isMyTurn ? '1' : '0' }}">
                    <div class="rider-delivery-top"><span class="rider-order-number">Order {{ $offer->delivery->order->order_number }}</span><span class="rider-delivery-status">{{ $isMyTurn ? 'Your turn' : 'Queue #'.$offer->queue_position }}</span></div>
                    <h3>{{ $offer->delivery->order->store->name }}</h3>
                    <p class="rider-route-line">{{ $offer->delivery->order->items->sum('quantity') }} item(s) · ₱{{ number_format($offer->delivery->order->total_amount, 2) }}</p>
                    <p class="rider-address">{{ $offer->delivery->order->pickup_address ?? 'Pickup location' }} → {{ $offer->delivery->order->dropoff_address }}</p>
                    @if ($isMyTurn)
                        <p class="rider-queue-note" data-rider-countdown data-rider-countdown-mode="accept" data-rider-countdown="{{ max(0, now()->diffInSeconds($offer->expires_at, false)) }}">Accept within {{ max(0, now()->diffInSeconds($offer->expires_at, false)) }} seconds.</p>
                        <div class="rider-actions">
                            <form method="POST" action="{{ route('rider.deliveries.accept', $offer->delivery) }}">@csrf<button class="rider-button primary" type="submit">Accept</button></form>
                            <form method="POST" action="{{ route('rider.deliveries.reject', $offer->delivery) }}">@csrf<button class="rider-button secondary" type="submit">Decline</button></form>
                        </div>
                    @else
                        <p class="rider-queue-note" data-rider-countdown data-rider-countdown="{{ $secondsUntilTurn }}">Your turn is expected in about {{ ceil($secondsUntilTurn / 60) }} min {{ $secondsUntilTurn % 60 }} sec.</p>
                    @endif
                </article>
            @empty
                <div class="rider-empty"><strong>No new orders</strong><p>Keep this page open while online. New offers will appear here.</p></div>
            @endforelse
        </section>
        <section class="rider-section">
            <div class="rider-section-heading"><h2>Custom deliveries</h2></div>
            @forelse ($customDeliveries as $job)
                <article class="rider-delivery-card">
                    <div class="rider-delivery-top"><span class="rider-order-number">Custom request #{{ $job->id }}</span><span class="rider-delivery-status">{{ ucfirst(str_replace('_', ' ', $job->status)) }}</span></div>
                    <h3>{{ $job->package_category === 'small_parcel' ? 'Small parcel' : ucfirst($job->package_category) }}</h3>
                    <p class="rider-address">Pickup: {{ $job->pickup_address }} · Drop-off: {{ $job->dropoff_address }}</p>
                    <div class="rider-delivery-summary"><span>{{ $job->payment_method === 'cod' ? 'Collect COD' : 'GCash payment' }}</span><strong>₱{{ number_format($job->total_amount, 2) }}</strong></div>
                    @if (in_array($job->status, ['pending_assignment', 'assigned'], true))
                        <div class="rider-actions">
                            <form method="POST" action="{{ route('rider.custom-deliveries.accept', $job) }}">@csrf<button class="rider-button primary" type="submit">Accept</button></form>
                            @if ($job->rider_id === $rider->id)
                                <form method="POST" action="{{ route('rider.custom-deliveries.reject', $job) }}">@csrf<button class="rider-button secondary" type="submit">Decline</button></form>
                            @endif
                        </div>
                    @endif
                </article>
            @empty
                <div class="rider-empty"><strong>No custom delivery requests</strong></div>
            @endforelse
        </section>
    @endif
</x-rider.page-layout>
