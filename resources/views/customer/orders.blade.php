<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>My Orders · MotoBook</title>
    @vite(['resources/css/app.css', 'resources/css/pages/orders.css'])
    @vite('resources/css/pages/customer-experience.css')
</head>
<body class="orders-shell" data-theme="{{ (auth()->user()->customer_settings['dark_mode'] ?? false) ? 'dark' : 'light' }}">

    <div class="orders-header"><h1>My Orders</h1></div>

    @if ($orders->isEmpty())
        <p class="text-center text-text-dim text-sm py-16">
            You haven't placed any orders yet.<br>
            <a href="{{ route('customer.restaurants') }}" class="text-accent font-bold no-underline">Browse restaurants →</a>
        </p>
    @else
        @foreach ($orders as $order)
            @php
                $deliveryStatus = $order->delivery?->status;
                $deliveryStatusLabel = match ($deliveryStatus) {
                    'assigned' => 'Rider Assigned',
                    'accepted' => 'Rider Accepted',
                    'en_route_to_pickup' => 'Rider Heading to Restaurant',
                    'arrived_at_pickup' => 'Rider at Restaurant',
                    'picked_up' => 'Order Picked Up',
                    'en_route_to_customer' => 'On the Way to You',
                    'arrived_at_customer' => 'Rider Arrived',
                    'delivered' => 'Delivered',
                    default => null,
                };
                $statusClass = match($deliveryStatus ?? $order->status) {
                    'delivered', 'completed' => 'done',
                    'rejected', 'cancelled' => 'cancelled',
                    default => 'in-progress',
                };
                $statusLabel = $deliveryStatusLabel ?? match($order->status) {
                    'pending' => 'Awaiting Confirmation',
                    'accepted' => 'Accepted',
                    'preparing' => 'Preparing',
                    'ready_for_pickup' => 'Ready for Pickup',
                    'assigned' => 'Rider Assigned',
                    'out_for_delivery' => 'Out for Delivery',
                    'delivered' => 'Delivered',
                    'completed' => 'Completed',
                    'rejected' => 'Rejected',
                    'cancelled' => 'Cancelled',
                    default => ucfirst($order->status),
                };
            @endphp
            <a href="{{ route('customer.orders.show', $order) }}" class="order-card">
                <div class="order-card-icon">🍽️</div>
                <div class="flex-1 min-w-0">
                    <div class="order-card-store">{{ $order->store->name }}</div>
                    <div class="order-card-meta">{{ $order->order_number }} · {{ $order->items->sum('quantity') }} items · ₱{{ number_format($order->total_amount, 2) }}</div>
                    <div class="order-card-meta">{{ $order->created_at->format('M j, Y \a\t g:i A') }}</div>
                </div>
                <div class="order-card-status {{ $statusClass }}">{{ $statusLabel }}</div>
            </a>
        @endforeach
    @endif

    <nav class="bottom-nav">
        <a href="{{ route('customer.restaurants') }}" class="nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l9-9 9 9M5 10v10h14V10"/></svg>
            Home
        </a>
        <a href="{{ route('customer.custom-delivery.create') }}" class="nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-5 h-5"><rect x="4" y="4" width="7" height="7" rx="1"/><rect x="13" y="4" width="7" height="7" rx="1"/><rect x="4" y="13" width="7" height="7" rx="1"/><rect x="13" y="13" width="7" height="7" rx="1"/></svg>
            Customize
        </a>
        <a href="{{ route('customer.orders.index') }}" class="nav-item active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            Orders
        </a>
        <a href="{{ route('customer.notifications') }}" class="nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            Inbox
        </a>
        <a href="{{ route('customer.profile') }}" class="nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-5 h-5"><circle cx="12" cy="8" r="4"/><path stroke-linecap="round" d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg>
            Profile
        </a>
    </nav>

</body>
</html>
