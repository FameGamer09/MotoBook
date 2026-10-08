<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Order Placed · MotoBook</title>
    @vite(['resources/css/app.css', 'resources/css/pages/checkout-confirmation.css'])
    @vite('resources/css/pages/customer-experience.css')
</head>
<body class="confirm-shell" data-theme="{{ (auth()->user()->customer_settings['dark_mode'] ?? false) ? 'dark' : 'light' }}">

    <div class="confirm-icon">✅</div>
    <div class="confirm-title">Order Placed!</div>
    <p class="confirm-subtitle">{{ $store->name }} has received your order and will start preparing it shortly.</p>

    <div class="confirm-card">
        <div class="confirm-row"><span class="label">Order Number</span><span class="value">{{ $order->order_number }}</span></div>
        <div class="confirm-row"><span class="label">Items</span><span class="value">{{ $order->items->sum('quantity') }}</span></div>
        <div class="confirm-row"><span class="label">Payment Method</span><span class="value">{{ strtoupper($order->payment_method) }}</span></div>
        <div class="confirm-row"><span class="label">Payment Status</span><span class="value">{{ ucfirst($order->payment_status) }}</span></div>
        <div class="confirm-row"><span class="label">Total</span><span class="value">₱{{ number_format($order->total_amount, 2) }}</span></div>
    </div>

    <div class="confirm-actions">
        <a href="{{ route('customer.orders.show', $order) }}" style="display:block;text-align:center;padding:14px;border-radius:16px;background:#14C7E0;color:white;font-weight:700;text-decoration:none;font-size:15px;">Track Order</a>
        <a href="{{ route('customer.restaurants') }}" style="display:block;text-align:center;padding:14px;border-radius:16px;border:2px solid #14C7E0;color:#14C7E0;font-weight:700;text-decoration:none;font-size:15px;">Back to Home</a>
    </div>

</body>
</html>
