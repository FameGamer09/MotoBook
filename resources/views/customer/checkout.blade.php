<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <title>Checkout · MotoBook</title>
    @vite(['resources/css/app.css', 'resources/css/pages/checkout.css', 'resources/css/pages/customer-experience.css'])
</head>
<body class="checkout-shell" data-theme="{{ (auth()->user()->customer_settings['dark_mode'] ?? false) ? 'dark' : 'light' }}">
    @php
        $usableAddresses = $addresses->filter(fn ($address) =>
            is_numeric($address->latitude) && is_numeric($address->longitude)
            && $address->latitude >= -90 && $address->latitude <= 90
            && $address->longitude >= -180 && $address->longitude <= 180
        )->values();
        $oldAddress = $usableAddresses->first(fn ($address) => (int) $address->id === (int) old('address_id'));
        $checkoutAddressId = $oldAddress?->id ?? $usableAddresses->firstWhere('is_default', true)?->id ?? $usableAddresses->first()?->id;
        $walletBalance = (float) ($wallet->balance ?? 0);
        $walletCanPay = $wallet && $walletBalance >= (float) $totals['total'];
        $requestedPayment = old('payment_method', $preferredPayment);
        $checkoutPayment = in_array($requestedPayment, ['cod', 'wallet', 'gcash'], true) ? $requestedPayment : 'cod';
        if ($checkoutPayment === 'wallet' && ! $walletCanPay) $checkoutPayment = 'cod';
    @endphp

    <header class="checkout-header">
        <a href="{{ route('customer.cart') }}" class="checkout-back-btn" aria-label="Back to cart">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <h1 class="checkout-title">Checkout</h1>
    </header>

    @if ($errors->any())
        <div class="checkout-error" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('customer.checkout.place') }}" id="checkout-form">
        @csrf

        <section class="checkout-section" aria-labelledby="checkout-address-title">
            <h2 class="checkout-section-title" id="checkout-address-title">Delivery Address</h2>
            @forelse ($addresses as $address)
                @php $addressIsUsable = $usableAddresses->contains('id', $address->id); @endphp
                <label class="address-option {{ $addressIsUsable ? '' : 'address-option-unavailable' }}">
                    <input type="radio" name="address_id" value="{{ $address->id }}" @checked($address->id === $checkoutAddressId) @disabled(! $addressIsUsable) required>
                    <span class="address-option-card">
                        <span class="address-option-label">{{ $address->label }}</span>
                        <span class="address-option-line">{{ $address->address_line }}</span>
                        @unless ($addressIsUsable)
                            <span class="address-pin-warning">Add a delivery pin to this address before placing the order. Use the address link below.</span>
                        @endunless
                    </span>
                </label>
            @empty
                <p class="no-address-note">You need a delivery address with a map pin before placing this order.</p>
            @endforelse
            @if (! $checkoutAddressId)
                <a class="checkout-address-cta" href="{{ route('customer.addresses') }}">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="10" r="2.5" stroke="currentColor" stroke-width="1.8"/></svg>
                    {{ $addresses->isEmpty() ? 'Add delivery address' : 'Add delivery pin' }}
                </a>
            @endif
        </section>

        <section class="checkout-section" aria-labelledby="checkout-payment-title">
            <h2 class="checkout-section-title" id="checkout-payment-title">Payment Method</h2>
            <div role="radiogroup" aria-labelledby="checkout-payment-title">
                <label class="payment-option">
                    <input type="radio" name="payment_method" value="cod" @checked($checkoutPayment === 'cod') required>
                    <span class="payment-option-card">
                        <span class="payment-option-main">
                            <svg class="payment-method-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M3 9h18M7 14h3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="16.5" cy="14" r="2" stroke="currentColor" stroke-width="1.8"/></svg>
                            <span>Cash on Delivery</span>
                        </span>
                    </span>
                </label>

                <label class="payment-option">
                    <input type="radio" name="payment_method" value="wallet" @checked($checkoutPayment === 'wallet') @disabled(! $walletCanPay)>
                    <span class="payment-option-card">
                        <span class="payment-option-main">
                            <svg class="payment-method-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M3 9h18M16 14h2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                            <span>MotoBook Wallet</span>
                        </span>
                        <span class="payment-balance">{{ $walletCanPay ? 'Balance: ₱'.number_format($walletBalance, 2) : (! $wallet ? 'Wallet unavailable' : 'Insufficient balance · ₱'.number_format($walletBalance, 2)) }}</span>
                    </span>
                </label>

                <label class="payment-option">
                    <input type="radio" name="payment_method" value="gcash" @checked($checkoutPayment === 'gcash')>
                    <span class="payment-option-card">
                        <span class="payment-option-main">
                            <svg class="payment-method-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="6" y="2.5" width="12" height="19" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M9 6h6M10.5 18.5h3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                            <span>GCash</span>
                        </span>
                        <span class="payment-balance">{{ $gcashNumber ? 'Account: '.$gcashNumber : 'Payment stays pending until confirmed' }}</span>
                    </span>
                </label>
            </div>
        </section>

        <section class="checkout-section" aria-labelledby="checkout-promo-title">
            <h2 class="checkout-section-title" id="checkout-promo-title">Promo Code</h2>
            <label class="address-option">
                <span class="address-option-card">
                    <input type="text" name="promo_code" value="{{ old('promo_code') }}" maxlength="64" autocomplete="off" placeholder="Enter promo code" aria-label="Promo code">
                </span>
            </label>
        </section>

        <section class="checkout-section" aria-labelledby="checkout-items-title">
            <h2 class="checkout-section-title" id="checkout-items-title">Order from {{ $store->name }}</h2>
            @foreach ($items as $row)
                <div class="checkout-item-row">
                    <span>{{ $row['menu_item']->name }} <span class="qty">×{{ $row['quantity'] }}</span></span>
                    <span>₱{{ number_format($row['line_total'], 2) }}</span>
                </div>
            @endforeach
        </section>

        <section class="checkout-section" aria-label="Order total">
            <div class="summary-row"><span>Subtotal</span><span>₱{{ number_format($totals['subtotal'], 2) }}</span></div>
            <div class="summary-row"><span>Delivery Fee</span><span>₱{{ number_format($totals['delivery_fee'], 2) }}</span></div>
            <div class="summary-row"><span>Service Fee</span><span>₱{{ number_format($totals['service_fee'], 2) }}</span></div>
            @if (old('promo_code'))
                <p class="text-xs text-text-dim">Any discount will be confirmed in your order total after the promo code is validated.</p>
            @endif
            <div class="summary-divider"></div>
            <div class="summary-total-row"><span class="summary-total-label">Total</span><span class="summary-total-value">₱{{ number_format($totals['total'], 2) }}</span></div>
        </section>

        <div class="place-order-bar">
            <button type="submit" class="place-order-btn" @disabled(! $checkoutAddressId)>
                {{ $checkoutAddressId ? 'Place Order' : 'Add delivery address to continue' }}
            </button>
        </div>
    </form>
</body>
</html>
