<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Checkout · MotoBook</title>
    @vite(['resources/css/app.css', 'resources/css/pages/checkout.css'])
    @vite('resources/css/pages/customer-experience.css')
</head>
<body class="checkout-shell" data-theme="{{ (auth()->user()->customer_settings['dark_mode'] ?? false) ? 'dark' : 'light' }}">

    <div class="checkout-header">
        <a href="{{ route('customer.cart') }}" class="checkout-back-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div class="checkout-title">Checkout</div>
    </div>

    @if ($errors->any())
        <div class="mx-5 mb-3 text-[13px] text-danger">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('customer.checkout.place') }}" id="checkout-form">
        @csrf

        <div class="checkout-section">
            <div class="checkout-section-title">Delivery Address</div>

            @forelse ($addresses as $address)
                <label class="address-option">
                    <input type="radio" name="address_id" value="{{ $address->id }}" {{ $address->is_default || $loop->first ? 'checked' : '' }} required>
                    <div class="address-option-card">
                        <div class="address-option-label">{{ $address->label }}</div>
                        <div class="address-option-line">{{ $address->address_line }}</div>
                        @if ($address->latitude === null || $address->longitude === null)
                            <div class="text-xs text-danger">Delivery pin needed — <a href="{{ route('customer.addresses') }}">update this address</a>.</div>
                        @endif
                    </div>
                </label>
            @empty
                <p class="no-address-note">
                    You don't have a saved address yet.
                    <a href="{{ route('customer.addresses') }}">Add one first →</a>
                </p>
            @endforelse
        </div>

        <div class="checkout-section">
            <div class="checkout-section-title">Payment Method</div>

            <label class="payment-option">
                <input type="radio" name="payment_method" value="cod" @checked($preferredPayment === 'cod')>
                <div class="payment-option-card">
                    <span>💵 Cash on Delivery</span>
                </div>
            </label>

            <label class="payment-option">
                <input type="radio" name="payment_method" value="wallet" @checked($preferredPayment === 'wallet') {{ !$wallet ? 'disabled' : '' }}>
                <div class="payment-option-card">
                    <span>👛 MotoBook Wallet</span>
                    <span class="payment-balance">Balance: ₱{{ number_format($wallet->balance ?? 0, 2) }}</span>
                </div>
            </label>

            <label class="payment-option">
                <input type="radio" name="payment_method" value="gcash" @checked($preferredPayment === 'gcash')>
                <div class="payment-option-card">
                    <span>📱 GCash</span>
                    <span class="payment-balance">{{ $gcashNumber ? 'Account: '.$gcashNumber : 'Payment stays pending until confirmed' }}</span>
                </div>
            </label>
        </div>

        <div class="checkout-section">
            <div class="checkout-section-title">Promo Code</div>
            <label class="address-option">
                <div class="address-option-card">
                    <input type="text" name="promo_code" value="{{ old('promo_code') }}" maxlength="64" autocomplete="off" placeholder="Enter promo code">
                </div>
            </label>
        </div>

        <div class="checkout-section">
            <div class="checkout-section-title">Order from {{ $store->name }}</div>
            @foreach ($items as $row)
                <div class="checkout-item-row">
                    <span>{{ $row['menu_item']->name }} <span class="qty">×{{ $row['quantity'] }}</span></span>
                    <span>₱{{ number_format($row['line_total'], 2) }}</span>
                </div>
            @endforeach
        </div>

        <div class="checkout-section">
            <div class="summary-row"><span>Subtotal</span><span>₱{{ number_format($totals['subtotal'], 2) }}</span></div>
            <div class="summary-row"><span>Delivery Fee</span><span>₱{{ number_format($totals['delivery_fee'], 2) }}</span></div>
            <div class="summary-row"><span>Service Fee</span><span>₱{{ number_format($totals['service_fee'], 2) }}</span></div>
            @if (old('promo_code'))
                <p class="text-xs text-text-dim">Any discount will be confirmed in your order total after the promo code is validated.</p>
            @endif
            <div class="summary-divider"></div>
            <div class="summary-total-row">
                <span class="summary-total-label">Total</span>
                <span class="summary-total-value">₱{{ number_format($totals['total'], 2) }}</span>
            </div>
        </div>

        <div class="place-order-bar">
            <button type="submit" class="place-order-btn" {{ $addresses->isEmpty() ? 'disabled' : '' }}>
                Place Order
            </button>
        </div>
    </form>

</body>
</html>
