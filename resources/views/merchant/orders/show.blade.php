<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $order->order_number }} · MotoBook Merchant</title>
    @vite(['resources/css/app.css', 'resources/css/pages/merchant-orders.css'])
</head>
<body class="merchant-orders-shell">
    <aside class="merchant-sidebar merchant-orders-sidebar">
        <a href="{{ route('merchant.dashboard') }}" class="merchant-brand"><span class="merchant-brand-mark"></span><span><b>Moto</b>Book</span></a>
        <div class="merchant-store-switcher"><span class="merchant-store-kicker">Merchant workspace</span><strong>{{ auth()->user()->store?->name }}</strong><span class="merchant-store-status is-open"><span></span>Open</span></div>
        <nav class="merchant-nav" aria-label="Merchant navigation"><a href="{{ route('merchant.dashboard') }}" class="merchant-nav-link"><span>⌂</span>Dashboard</a><a href="{{ route('merchant.orders.index') }}" class="merchant-nav-link is-active"><span>▤</span>Orders</a><a href="{{ route('merchant.menu.index') }}" class="merchant-nav-link"><span>◈</span>Menu</a><a href="{{ route('merchant.store.edit') }}" class="merchant-nav-link"><span>□</span>Store profile</a><a href="{{ route('merchant.sales.index') }}" class="merchant-nav-link"><span>◒</span>Sales</a></nav>
        <div class="merchant-sidebar-footer"><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="merchant-logout"><span>↪</span>Log out</button></form></div>
    </aside>

    <main class="merchant-orders-main">
        <header class="merchant-orders-header"><div><p class="merchant-eyebrow">Order detail</p><h1>{{ $order->order_number }}</h1></div><a href="{{ route('merchant.orders.index') }}" class="merchant-back-link">← All orders</a></header>
        <div class="merchant-orders-content merchant-detail-layout">
            @if (session('status')) <div class="merchant-alert detail-alert">{{ session('status') }}</div> @endif
            @if ($errors->any()) <div class="merchant-error detail-alert">{{ $errors->first() }}</div> @endif

            <section class="merchant-detail-main">
                <div class="merchant-detail-heading"><div><span class="merchant-overline">{{ $order->created_at->format('M j, Y · g:i A') }}</span><h2>{{ $order->customer?->name ?? 'Customer' }}</h2></div><span class="merchant-big-status {{ $order->status }}">{{ str_replace('_', ' ', ucfirst($order->status)) }}</span></div>
                <div class="merchant-detail-card"><div class="merchant-card-title">Items ordered</div>@foreach ($order->items as $item)<div class="merchant-detail-item"><span><strong>{{ $item->quantity }} × {{ $item->item_name }}</strong>@if ($item->options_snapshot)<small>{{ collect($item->options_snapshot)->pluck('name')->join(', ') }}</small>@endif</span><b>₱{{ number_format($item->subtotal, 2) }}</b></div>@endforeach<div class="merchant-total-line"><span>Subtotal</span><b>₱{{ number_format($order->subtotal, 2) }}</b></div><div class="merchant-total-line"><span>Delivery fee</span><b>₱{{ number_format($order->delivery_fee, 2) }}</b></div>@if ($order->service_fee > 0)<div class="merchant-total-line"><span>Service fee</span><b>₱{{ number_format($order->service_fee, 2) }}</b></div>@endif @if ($order->discount_amount > 0)<div class="merchant-total-line"><span>Discount</span><b>-₱{{ number_format($order->discount_amount, 2) }}</b></div>@endif<div class="merchant-total-line grand-total"><span>Total</span><b>₱{{ number_format($order->total_amount, 2) }}</b></div></div>
                @if ($order->customer_note)<div class="merchant-detail-card merchant-note"><div class="merchant-card-title">Customer note</div><p>{{ $order->customer_note }}</p></div>@endif
            </section>

            <aside class="merchant-detail-side">
                <section class="merchant-detail-card"><div class="merchant-card-title">Next action</div>@if ($order->status === 'pending')<p class="merchant-action-copy">Review the items and accept this order to start preparation.</p><form method="POST" action="{{ route('merchant.orders.accept', $order) }}">@csrf<button class="merchant-action-button primary" type="submit">Accept order <span>→</span></button></form><details class="merchant-reject"><summary>Reject order</summary><form method="POST" action="{{ route('merchant.orders.reject', $order) }}">@csrf<textarea name="reason" required maxlength="500" placeholder="Reason for rejection"></textarea><button class="merchant-action-button danger" type="submit">Reject order</button></form></details>@elseif ($order->status === 'accepted')<p class="merchant-action-copy">The order is accepted. Start preparing it when your kitchen begins.</p><form method="POST" action="{{ route('merchant.orders.preparing', $order) }}">@csrf<button class="merchant-action-button primary" type="submit">Start preparing <span>→</span></button></form>@elseif ($order->status === 'preparing')<p class="merchant-action-copy">Mark this order ready when it is packed for pickup.</p><form method="POST" action="{{ route('merchant.orders.ready', $order) }}">@csrf<button class="merchant-action-button primary" type="submit">Ready for pickup <span>→</span></button></form>@else<p class="merchant-action-copy">This order is {{ str_replace('_', ' ', $order->status) }}. No merchant action is required right now.</p>@endif</section>
                <section class="merchant-detail-card"><div class="merchant-card-title">Delivery information</div><div class="merchant-info-row"><span>Payment</span><strong>{{ strtoupper($order->payment_method) }} · {{ ucfirst($order->payment_status) }}</strong></div><div class="merchant-info-row"><span>Address</span><strong>{{ $order->delivery_address_snapshot['address_line'] ?? 'No address' }}</strong></div>@if ($order->delivery?->rider)<div class="merchant-info-row"><span>Rider</span><strong>{{ $order->delivery->rider->user->name }}</strong></div>@endif</section>
            </aside>
        </div>
    </main>
</body>
</html>
