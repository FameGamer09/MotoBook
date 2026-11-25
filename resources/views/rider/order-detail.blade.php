<x-rider.page-layout title="Order Details" active="orders" :rider="$rider" :can-go-online="$canGoOnline" :has-active-delivery="$hasActiveDelivery">
    <article class="rider-detail-card">
        <div class="rider-delivery-top"><span class="rider-order-number">Order ID</span><span class="rider-delivery-status">{{ ucfirst(str_replace('_', ' ', $delivery->status)) }}</span></div>
        <h2>{{ $delivery->order->order_number }}</h2>
        <div class="rider-detail-place"><span aria-hidden="true">▣</span><div><small>Pickup from</small><strong>{{ $delivery->order->store->name }}</strong><span>{{ $delivery->order->pickup_address ?? 'Pickup location' }}</span></div></div>
        <div class="rider-detail-place"><span aria-hidden="true">●</span><div><small>Drop off to</small><strong>{{ $delivery->order->customer->name }}</strong><span>{{ $delivery->order->dropoff_address }}</span></div></div>
        <div class="rider-delivery-summary"><span>{{ $delivery->order->items->sum('quantity') }} item(s)</span><strong>₱{{ number_format($delivery->order->total_amount, 2) }}</strong></div>
        <h3>Order items</h3>
        <ul class="rider-detail-items">
            @foreach ($delivery->order->items as $item)
                <li><span>{{ $item->quantity }} × {{ $item->item_name }}</span><strong>₱{{ number_format($item->subtotal, 2) }}</strong></li>
            @endforeach
        </ul>
        <a class="rider-button primary rider-detail-navigation" href="{{ route('rider.orders.navigation', $delivery->order) }}">Navigate to {{ in_array($delivery->status, ['assigned', 'accepted', 'en_route_to_pickup', 'arrived_at_pickup'], true) ? 'pickup' : 'customer' }}</a>
        @if ($delivery->status === 'assigned')
            <div class="rider-actions rider-detail-actions">
                <form method="POST" action="{{ route('rider.deliveries.accept', $delivery) }}">@csrf<button class="rider-button primary" type="submit">Accept order</button></form>
                <form method="POST" action="{{ route('rider.deliveries.reject', $delivery) }}">@csrf<button class="rider-button secondary" type="submit">Decline</button></form>
            </div>
        @elseif ($delivery->status === 'accepted')
            <form method="POST" action="{{ route('rider.deliveries.startPickup', $delivery) }}">@csrf<button class="rider-button primary rider-detail-action" type="submit">Navigate to pickup</button></form>
        @elseif ($delivery->status === 'en_route_to_pickup')
            <form method="POST" action="{{ route('rider.deliveries.arrivePickup', $delivery) }}">@csrf<button class="rider-button primary rider-detail-action" type="submit">I've arrived</button></form>
        @elseif ($delivery->status === 'arrived_at_pickup')
            <form method="POST" action="{{ route('rider.deliveries.confirmPickup', $delivery) }}">@csrf<button class="rider-button primary rider-detail-action" type="submit">Confirm pickup</button></form>
        @elseif ($delivery->status === 'picked_up')
            <form method="POST" action="{{ route('rider.deliveries.startCustomer', $delivery) }}">@csrf<button class="rider-button primary rider-detail-action" type="submit">Navigate to customer</button></form>
        @elseif ($delivery->status === 'en_route_to_customer')
            <form method="POST" action="{{ route('rider.deliveries.arriveCustomer', $delivery) }}">@csrf<button class="rider-button primary rider-detail-action" type="submit">I've arrived</button></form>
        @elseif ($delivery->status === 'arrived_at_customer')
            <form method="POST" action="{{ route('rider.deliveries.complete', $delivery) }}">@csrf<button class="rider-button primary rider-detail-action" type="submit">Complete delivery</button></form>
        @endif
    </article>
</x-rider.page-layout>
