<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Orders · MotoBook Merchant</title>
    @vite(['resources/css/app.css', 'resources/css/pages/merchant-orders.css'])
</head>
<body class="merchant-orders-shell">
    <aside class="merchant-sidebar merchant-orders-sidebar">
        <a href="{{ route('merchant.dashboard') }}" class="merchant-brand"><span class="merchant-brand-mark"></span><span><b>Moto</b>Book</span></a>
        <div class="merchant-store-switcher"><span class="merchant-store-kicker">Merchant workspace</span><strong>{{ auth()->user()->store?->name }}</strong><span class="merchant-store-status is-open"><span></span>Open</span></div>
        <nav class="merchant-nav" aria-label="Merchant navigation">
            <a href="{{ route('merchant.dashboard') }}" class="merchant-nav-link"><span>⌂</span>Dashboard</a>
            <a href="{{ route('merchant.orders.index') }}" class="merchant-nav-link is-active"><span>▤</span>Orders</a>
            <a href="{{ route('merchant.dashboard') }}#menu" class="merchant-nav-link"><span>◈</span>Menu</a>
            <a href="{{ route('merchant.dashboard') }}#store" class="merchant-nav-link"><span>□</span>Store profile</a>
            <a href="{{ route('merchant.sales.index') }}" class="merchant-nav-link"><span>◒</span>Sales</a>
        </nav>
        <div class="merchant-sidebar-footer"><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="merchant-logout"><span>↪</span>Log out</button></form></div>
    </aside>

    <main class="merchant-orders-main">
        <header class="merchant-orders-header"><div><p class="merchant-eyebrow">Merchant workspace</p><h1>Orders</h1></div><a href="{{ route('merchant.dashboard') }}" class="merchant-back-link">← Dashboard</a></header>
        <div class="merchant-orders-content">
            @if (session('status')) <div class="merchant-alert">{{ session('status') }}</div> @endif
            @if ($errors->any()) <div class="merchant-error">{{ $errors->first() }}</div> @endif

            <div class="merchant-orders-toolbar">
                <div><span class="merchant-overline">Order queue</span><h2>Manage incoming orders</h2></div>
                <form method="GET" class="merchant-filter"><label for="status">Filter</label><select id="status" name="status" onchange="this.form.submit()"><option value="">All orders</option>@foreach (['pending', 'accepted', 'preparing', 'ready_for_pickup', 'assigned', 'out_for_delivery', 'delivered', 'completed', 'rejected', 'cancelled'] as $status)<option value="{{ $status }}" @selected($activeStatus === $status)>{{ str_replace('_', ' ', ucfirst($status)) }}</option>@endforeach</select></form>
            </div>

            <section class="merchant-orders-panel">
                <div class="merchant-order-table-head"><span>Order</span><span>Customer</span><span>Items</span><span>Total</span><span>Status</span><span></span></div>
                @forelse ($orders as $order)
                    @php $statusClass = match ($order->status) { 'pending' => 'pending', 'accepted', 'preparing' => 'preparing', 'ready_for_pickup', 'assigned' => 'ready', 'rejected', 'cancelled' => 'cancelled', default => 'complete' }; @endphp
                    <a href="{{ route('merchant.orders.show', $order) }}" class="merchant-order-table-row">
                        <span><strong>{{ $order->order_number }}</strong><small>{{ $order->created_at->diffForHumans() }}</small></span>
                        <span>{{ $order->customer?->name ?? 'Customer' }}</span>
                        <span>{{ $order->items->sum('quantity') }} item(s)</span>
                        <strong>₱{{ number_format($order->total_amount, 2) }}</strong>
                        <span class="merchant-status {{ $statusClass }}"><i></i>{{ str_replace('_', ' ', ucfirst($order->status)) }}</span>
                        <span class="merchant-row-arrow">→</span>
                    </a>
                @empty
                    <div class="merchant-empty"><span>▤</span><strong>No matching orders</strong><p>Orders from customers will appear here.</p></div>
                @endforelse
            </section>
            @if ($orders->hasPages()) <div class="merchant-pagination">{{ $orders->links() }}</div> @endif
        </div>
    </main>
</body>
</html>
