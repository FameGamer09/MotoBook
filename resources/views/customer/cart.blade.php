<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Order Cart · MotoBook</title>
    @vite(['resources/css/app.css', 'resources/css/pages/cart.css'])
    @vite('resources/css/pages/customer-experience.css')
</head>
<body class="cart-shell" data-theme="{{ (auth()->user()->customer_settings['dark_mode'] ?? false) ? 'dark' : 'light' }}">

    <div class="cart-header">
        <button type="button" class="back-btn" onclick="window.location='{{ request('from') === 'home' ? route('customer.restaurants') : ($store ? route('customer.restaurants.show', $store) : route('customer.restaurants')) }}'">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </button>
        <div class="cart-header-title">Order Cart</div>
        <div class="cart-header-count" id="header-count">{{ $items->sum('quantity') }} Items</div>
    </div>

    @if ($items->isEmpty())
        <div class="cart-empty">
            Your cart is empty.<br>
            <a href="{{ route('customer.restaurants') }}" class="auth-inline-link">Browse restaurants →</a>
        </div>
    @else
        <div id="cart-items" data-update-url-template="{{ route('customer.cart.update', ['lineKey' => '__line_key__']) }}" data-remove-url-template="{{ route('customer.cart.remove', ['lineKey' => '__line_key__']) }}">
            @foreach ($items as $row)
                <div class="cart-item-card" data-item-row="{{ $row['line_key'] }}">
                    <div class="cart-item-thumb">🍴</div>
                    <div class="flex-1">
                        <div class="cart-item-name">{{ $row['menu_item']->name }}</div>
                        @if ($row['selected_options']->isNotEmpty())
                            <div class="cart-item-desc">{{ $row['selected_options']->pluck('name')->join(', ') }}</div>
                        @elseif ($row['menu_item']->description)
                            <div class="cart-item-desc">{{ $row['menu_item']->description }}</div>
                        @endif
                        <div class="cart-item-price">₱{{ number_format($row['unit_price'], 2) }}</div>
                        <div class="qty-stepper">
                            <button type="button" class="qty-btn" data-qty-decrement="{{ $row['line_key'] }}">−</button>
                            <span class="qty-value" id="qty-{{ $row['line_key'] }}">{{ $row['quantity'] }}</span>
                            <button type="button" class="qty-btn" data-qty-increment="{{ $row['line_key'] }}">+</button>
                        </div>
                    </div>
                    <button type="button" class="cart-item-remove" data-remove-item="{{ $row['line_key'] }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2m3 0v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6h14z"/></svg>
                    </button>
                </div>
            @endforeach
        </div>

        <div class="cart-summary-card">
            <div class="summary-row"><span>Subtotal</span><span id="sum-subtotal">₱{{ number_format($totals['subtotal'], 2) }}</span></div>
            <div class="summary-row"><span>Delivery Fee</span><span id="sum-delivery">₱{{ number_format($totals['delivery_fee'], 2) }}</span></div>
            <div class="summary-row"><span>Service Fee</span><span id="sum-service">₱{{ number_format($totals['service_fee'], 2) }}</span></div>
            <div class="summary-divider"></div>
            <div class="summary-total-row">
                <span class="summary-total-label">Total Amount</span>
                <span class="summary-total-value" id="sum-total">₱{{ number_format($totals['total'], 2) }}</span>
            </div>
        </div>

        <a href="{{ route('customer.checkout') }}" class="checkout-btn" id="checkout-btn">Proceed to Checkout →</a>
    @endif

    <script>
        const csrfToken = '{{ csrf_token() }}';
        const cartItems = document.getElementById('cart-items');
        const updateUrl = (key) => cartItems.dataset.updateUrlTemplate.replace('__line_key__', encodeURIComponent(key));
        const removeUrl = (key) => cartItems.dataset.removeUrlTemplate.replace('__line_key__', encodeURIComponent(key));

        async function updateQuantity(lineKey, quantity) {
            const res = await fetch(updateUrl(lineKey), {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ quantity }),
            });
            return res.json();
        }

        function refreshSummary(data) {
            document.getElementById('sum-subtotal').textContent = `₱${data.totals.subtotal.toFixed(2)}`;
            document.getElementById('sum-delivery').textContent = `₱${data.totals.delivery_fee.toFixed(2)}`;
            document.getElementById('sum-service').textContent = `₱${data.totals.service_fee.toFixed(2)}`;
            document.getElementById('sum-total').textContent = `₱${data.totals.total.toFixed(2)}`;
            document.getElementById('header-count').textContent = `${data.item_count} Item${data.item_count === 1 ? '' : 's'}`;

            if (data.item_count === 0) {
                window.location.reload();
            }
        }

        document.querySelectorAll('[data-qty-increment]').forEach(btn => {
            btn.addEventListener('click', async () => {
                const key = btn.dataset.qtyIncrement;
                const el = document.getElementById(`qty-${key}`);
                const newQty = parseInt(el.textContent) + 1;
                const data = await updateQuantity(key, newQty);
                el.textContent = newQty;
                refreshSummary(data);
            });
        });

        document.querySelectorAll('[data-qty-decrement]').forEach(btn => {
            btn.addEventListener('click', async () => {
                const key = btn.dataset.qtyDecrement;
                const el = document.getElementById(`qty-${key}`);
                const newQty = parseInt(el.textContent) - 1;
                const data = await updateQuantity(key, newQty);
                if (newQty <= 0) {
                    document.querySelector(`[data-item-row="${key}"]`).remove();
                } else {
                    el.textContent = newQty;
                }
                refreshSummary(data);
            });
        });

        document.querySelectorAll('[data-remove-item]').forEach(btn => {
            btn.addEventListener('click', async () => {
                const key = btn.dataset.removeItem;
                const res = await fetch(removeUrl(key), {
                    method: 'DELETE',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                });
                const data = await res.json();
                document.querySelector(`[data-item-row="${key}"]`).remove();
                refreshSummary(data);
            });
        });
    </script>

</body>
</html>
