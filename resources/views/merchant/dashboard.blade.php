<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Merchant Dashboard · MotoBook</title>
    @vite(['resources/css/app.css', 'resources/css/pages/merchant-dashboard.css'])
</head>
<body class="merchant-shell">
    <aside class="merchant-sidebar">
        <div class="merchant-brand"><span class="merchant-brand-mark"></span><span><b>Moto</b>Book</span></div>

        <div class="merchant-store-switcher">
            <span class="merchant-store-kicker">Your store</span>
            <strong>{{ $store->name }}</strong>
            <span class="merchant-store-status {{ $store->is_open ? 'is-open' : 'is-closed' }}">
                <span></span>{{ $store->is_open ? 'Open' : 'Closed' }}
            </span>
        </div>

        <nav class="merchant-nav" aria-label="Merchant navigation">
            <a href="#dashboard" class="merchant-nav-link is-active"><span>⌂</span>Dashboard</a>
            <a href="{{ route('merchant.orders.index') }}" class="merchant-nav-link"><span>▤</span>Orders <b>{{ $stats['pending_orders'] }}</b></a>
            <a href="{{ route('merchant.menu.index') }}" class="merchant-nav-link"><span>◈</span>Menu</a>
            <a href="{{ route('merchant.store.edit') }}" class="merchant-nav-link"><span>□</span>Store profile</a>
            <a href="{{ route('merchant.sales.index') }}" class="merchant-nav-link"><span>◒</span>Sales</a>
        </nav>

        <div class="merchant-sidebar-footer">
            <a href="{{ route('merchant.settings.edit') }}" class="merchant-nav-link"><span>⚙</span>Settings</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="merchant-logout"><span>↪</span>Log out</button>
            </form>
        </div>
    </aside>

    <main class="merchant-main" id="dashboard">
        <header class="merchant-topbar">
            <div>
                <p class="merchant-eyebrow">Merchant workspace</p>
                <h1>Good morning, {{ auth()->user()->name }}</h1>
            </div>
            <div class="merchant-topbar-actions">
                <button class="merchant-icon-button" type="button" aria-label="Notifications">♧<i></i></button>
                <div class="merchant-user-chip"><span>{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>{{ auth()->user()->name }}</div>
            </div>
        </header>

        <div class="merchant-content">
            <section class="merchant-hero">
                <div>
                    <span class="merchant-overline">{{ $store->is_verified ? 'Verified store' : 'Verification pending' }}</span>
                    <h2>{{ $store->name }}</h2>
                    <p>{{ $store->category ?: 'Restaurant' }} <span>·</span> {{ $store->address }}</p>
                </div>
                <a href="{{ route('merchant.store.edit') }}" class="merchant-primary-button">Edit store profile <span>→</span></a>
            </section>

            @if (session('status'))
                <div class="merchant-alert">{{ session('status') }}</div>
            @endif
            <form method="POST" action="{{ route('merchant.store.toggle') }}" class="merchant-availability-bar">
                @csrf
                <div><strong>Store availability</strong><span>{{ $store->is_open ? 'Customers can place orders now.' : 'Your store is hidden from new orders.' }}</span></div>
                <button type="submit" class="merchant-toggle {{ $store->is_open ? 'is-on' : '' }}" aria-label="{{ $store->is_open ? 'Close store' : 'Open store' }}"><span></span><b>{{ $store->is_open ? 'Open' : 'Closed' }}</b></button>
            </form>

            <section class="merchant-stat-grid" aria-label="Store summary">
                <article class="merchant-stat-card accent-stat"><span>Today’s sales</span><strong>₱{{ number_format($stats['today_sales'], 2) }}</strong><small>Across completed orders</small></article>
                <article class="merchant-stat-card"><span>Today’s orders</span><strong>{{ number_format($stats['today_orders']) }}</strong><small>Orders received today</small></article>
                <article class="merchant-stat-card pending-stat"><span>Needs attention</span><strong>{{ number_format($stats['pending_orders']) }}</strong><small>Waiting for your response</small></article>
                <article class="merchant-stat-card"><span>Menu items</span><strong>{{ number_format($stats['menu_items']) }}</strong><small>Listed in your store</small></article>
            </section>

            <section class="merchant-work-grid">
                <div class="merchant-panel" id="orders">
                    <div class="merchant-panel-heading"><div><span class="merchant-overline">Live queue</span><h3>Recent orders</h3></div><a href="{{ route('merchant.orders.index') }}">View all <span>→</span></a></div>
                    @if ($recentOrders->isEmpty())
                        <div class="merchant-empty"><span>▤</span><strong>No orders yet</strong><p>New customer orders will appear here.</p></div>
                    @else
                        <div class="merchant-order-list">
                            @foreach ($recentOrders as $order)
                                @php
                                    $statusClass = match ($order->status) {
                                        'pending' => 'pending',
                                        'accepted', 'preparing' => 'preparing',
                                        'ready_for_pickup', 'assigned' => 'ready',
                                        'rejected', 'cancelled' => 'cancelled',
                                        default => 'complete',
                                    };
                                @endphp
                                <a class="merchant-order-row" href="{{ route('merchant.orders.show', $order) }}">
                                    <span class="merchant-order-id">{{ $order->order_number }}<small>{{ $order->created_at->diffForHumans() }}</small></span>
                                    <span class="merchant-order-customer">{{ $order->customer?->name ?? 'Customer' }}<small>{{ $order->items->sum('quantity') }} item(s)</small></span>
                                    <strong>₱{{ number_format($order->total_amount, 2) }}</strong>
                                    <span class="merchant-status {{ $statusClass }}"><i></i>{{ str_replace('_', ' ', ucfirst($order->status)) }}</span>
                                    <span class="merchant-row-arrow">→</span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="merchant-panel" id="menu">
                    <div class="merchant-panel-heading"><div><span class="merchant-overline">Catalog</span><h3>Menu snapshot</h3></div><a href="#menu">Manage <span>→</span></a></div>
                    @if ($menuItems->isEmpty())
                        <div class="merchant-empty"><span>◈</span><strong>Your menu is empty</strong><p>Add items so customers can order.</p></div>
                    @else
                        <div class="merchant-menu-list">
                            @foreach ($menuItems as $item)
                                <div class="merchant-menu-row"><span class="merchant-menu-thumb">🍽</span><span><strong>{{ $item->name }}</strong><small>{{ $item->is_available ? 'Available' : 'Hidden from customers' }}</small></span><b>₱{{ number_format($item->price, 2) }}</b><i class="{{ $item->is_available ? 'available' : 'unavailable' }}"></i></div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>

            <section class="merchant-bottom-grid">
                <div class="merchant-panel merchant-chart-panel" id="sales">
                    <div class="merchant-panel-heading"><div><span class="merchant-overline">Performance</span><h3>Sales overview</h3></div><a href="{{ route('merchant.sales.index') }}">Detailed report <span>→</span></a></div>
                    <div class="merchant-chart"><div class="chart-labels"><span>₱2k</span><span>₱1k</span><span>₱0</span></div><div class="chart-plot"><div class="chart-gridline"></div><div class="chart-gridline"></div><div class="chart-gridline"></div><svg viewBox="0 0 640 180" preserveAspectRatio="none" aria-label="Sales trend"><path d="M0 145 C55 140, 75 118, 125 125 S190 91, 245 110 S310 72, 365 92 S425 45, 475 67 S540 35, 590 52 S620 25, 640 30" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round"/><path d="M0 145 C55 140, 75 118, 125 125 S190 91, 245 110 S310 72, 365 92 S425 45, 475 67 S540 35, 590 52 S620 25, 640 30 V180 H0 Z" fill="currentColor" opacity=".08"/></svg><div class="chart-days"><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span></div></div></div>
                </div>
                <div class="merchant-panel merchant-store-panel" id="store">
                    <div class="merchant-panel-heading"><div><span class="merchant-overline">Store health</span><h3>Store profile</h3></div><a href="#store">Edit <span>→</span></a></div>
                    <div class="merchant-profile-line"><span>Verification</span><strong class="{{ $store->is_verified ? 'text-ok' : 'text-pending' }}">{{ $store->is_verified ? 'Verified' : 'Pending review' }}</strong></div>
                    <div class="merchant-profile-line"><span>Customer rating</span><strong>★ {{ number_format($store->rating, 1) }}</strong></div>
                    <div class="merchant-profile-line"><span>Delivery location</span><strong>{{ $store->latitude && $store->longitude ? 'Configured' : 'Needs setup' }}</strong></div>
                </div>
            </section>
        </div>
    </main>
</body>
</html>
