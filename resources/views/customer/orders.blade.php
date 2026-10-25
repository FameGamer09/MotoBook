<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <title>My Orders · MotoBook</title>
    @vite(['resources/css/app.css', 'resources/css/pages/orders.css', 'resources/css/pages/customer-experience.css'])
</head>
<body class="orders-shell cx-page" data-theme="{{ (auth()->user()->customer_settings['dark_mode'] ?? false) ? 'dark' : 'light' }}">
    <main class="orders-content">
        <header class="orders-header">
            <div class="orders-heading-copy">
                <span class="cx-eyebrow">Your MotoBook activity</span>
                <h1>My Orders</h1>
                <p>Follow your deliveries and revisit past orders.</p>
            </div>
            <a class="orders-browse-link" href="{{ route('customer.restaurants') }}" aria-label="Browse restaurants">
                <span>Browse restaurants</span>
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </a>
        </header>

        @if ($orders->isEmpty())
            <section class="orders-empty cx-card" aria-labelledby="orders-empty-title">
                <span class="orders-empty-icon" aria-hidden="true">
                    <svg viewBox="0 0 48 48" fill="none"><path d="M15 8h18l3 5v27H12V13l3-5Z" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round"/><path d="M18 8c0 3 2.4 5 6 5s6-2 6-5M18 22h12M18 29h12M18 36h7" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg>
                </span>
                <span class="cx-eyebrow">A good meal is just around the corner</span>
                <h2 id="orders-empty-title">Your order history starts here</h2>
                <p class="cx-copy">When you place an order, you’ll find its status, delivery updates, and receipt here.</p>
                <a class="cx-button orders-empty-cta" href="{{ route('customer.restaurants') }}">
                    Explore nearby restaurants
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </a>
            </section>
        @else
            <section class="orders-list" aria-label="Your orders">
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
                    <a href="{{ route('customer.orders.show', $order) }}" class="order-card orders-list-card">
                        <span class="order-card-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none"><path d="M7 4h10l2 3v13H5V7l2-3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M9 4c0 2 1 3 3 3s3-1 3-3M9 11h6m-6 4h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                        </span>
                        <span class="orders-list-copy">
                            <span class="order-card-store">{{ $order->store->name }}</span>
                            <span class="order-card-meta">{{ $order->order_number }} <span aria-hidden="true">·</span> {{ $order->items->sum('quantity') }} items <span aria-hidden="true">·</span> ₱{{ number_format($order->total_amount, 2) }}</span>
                            <span class="order-card-meta">{{ $order->created_at->format('M j, Y \a\t g:i A') }}</span>
                        </span>
                        <span class="orders-list-end">
                            <span class="order-card-status {{ $statusClass }}">{{ $statusLabel }}</span>
                            <svg class="orders-list-arrow" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m9 18 6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                    </a>
                @endforeach
            </section>
        @endif
    </main>

    @include('customer.partials.bottom-nav', ['active' => 'orders'])
</body>
</html>
