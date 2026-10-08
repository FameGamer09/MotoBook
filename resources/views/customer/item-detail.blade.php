<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>{{ $item->name }} · MotoBook</title>
    @vite(['resources/css/app.css', 'resources/css/pages/item-detail.css'])
    @vite('resources/css/pages/customer-experience.css')
</head>
<body class="item-shell" data-theme="{{ (auth()->user()->customer_settings['dark_mode'] ?? false) ? 'dark' : 'light' }}">

    <div class="item-hero">
        🍽️
        <a href="{{ route('customer.restaurants.show', $item->store) }}" class="item-back-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <a href="{{ route('customer.cart') }}" class="item-cart-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l2.4 12.4a2 2 0 002 1.6h8.4a2 2 0 002-1.6L21 8H6"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>
        </a>
    </div>

    @if ($errors->any())
        <div class="error-banner mt-4">{{ $errors->first() }}</div>
    @endif

    <div class="item-header">
        <div class="item-name-row">
            <div class="item-name">{{ $item->name }}</div>
            <div class="item-price" id="live-price">₱{{ number_format($item->price, 2) }}</div>
        </div>
        @if ($item->description)
            <p class="item-desc">{{ $item->description }}</p>
        @endif

        @if ($item->calories || $item->prep_time_minutes || $item->highlight_badge)
            <div class="item-badges">
                @if ($item->calories)
                    <div class="item-badge"><span class="item-badge-icon">🔥</span><span class="item-badge-value">{{ $item->calories }}</span><span class="item-badge-label">Calories</span></div>
                @endif
                @if ($item->prep_time_minutes)
                    <div class="item-badge"><span class="item-badge-icon">⏱️</span><span class="item-badge-value">{{ $item->prep_time_minutes }} mins</span><span class="item-badge-label">Prep Time</span></div>
                @endif
                @if ($item->highlight_badge)
                    <div class="item-badge"><span class="item-badge-icon">🌿</span><span class="item-badge-label">{{ $item->highlight_badge }}</span></div>
                @endif
            </div>
        @endif
    </div>

    <div class="item-divider"></div>

    <form method="POST" action="{{ route('customer.items.addToCart', $item) }}" id="item-form">
        @csrf

        @foreach ($item->optionGroups as $group)
            <div class="option-group">
                <div class="option-group-title">
                    {{ $group->name }}
                    @if ($group->is_required)<span class="required">Required</span>@endif
                </div>

                @if ($group->selection_type === 'single')
                    @foreach ($group->options as $option)
                        <label class="option-card">
                            <input type="radio" name="options[{{ $group->id }}]" value="{{ $option->id }}"
                                   data-delta="{{ $option->price_delta }}" class="price-input"
                                   {{ $option->is_default ? 'checked' : '' }} {{ $group->is_required ? 'required' : '' }}>
                            <div class="option-card-body">
                                <span class="option-card-name">{{ $option->name }}</span>
                                <span style="display:flex; align-items:center; gap:8px;">
                                    <span class="option-card-price">{{ $option->price_delta > 0 ? '+₱'.number_format($option->price_delta, 2) : '' }}</span>
                                    <span class="option-check">✓</span>
                                </span>
                            </div>
                        </label>
                    @endforeach
                @else
                    @foreach ($group->options as $option)
                        <div class="addon-row">
                            <label>
                                <input type="checkbox" name="options[{{ $group->id }}][]" value="{{ $option->id }}"
                                       data-delta="{{ $option->price_delta }}" class="price-input"
                                       {{ $option->is_default ? 'checked' : '' }}>
                                {{ $option->name }}
                            </label>
                            <span class="addon-price">+₱{{ number_format($option->price_delta, 2) }}</span>
                        </div>
                    @endforeach
                @endif
            </div>
        @endforeach

        <div class="qty-bar">
            <div class="qty-stepper-lg">
                <button type="button" class="qty-btn-lg" id="qty-minus">−</button>
                <span class="qty-value-lg" id="qty-value">1</span>
                <button type="button" class="qty-btn-lg" id="qty-plus">+</button>
            </div>
            <input type="hidden" name="quantity" id="qty-input" value="1">
            <button type="submit" class="add-to-cart-btn" id="submit-btn">
                Add to Cart · ₱{{ number_format($item->price, 2) }}
            </button>
        </div>
    </form>

    <script>
        const basePrice = {{ $item->price }};
        let quantity = 1;

        function recalculate() {
            let deltaSum = 0;
            document.querySelectorAll('.price-input:checked').forEach(el => {
                deltaSum += parseFloat(el.dataset.delta) || 0;
            });
            const unitPrice = basePrice + deltaSum;
            const total = unitPrice * quantity;

            document.getElementById('live-price').textContent = `₱${unitPrice.toFixed(2)}`;
            document.getElementById('submit-btn').textContent = `Add to Cart · ₱${total.toFixed(2)}`;
        }

        document.querySelectorAll('.price-input').forEach(el => {
            el.addEventListener('change', recalculate);
        });

        document.getElementById('qty-plus').addEventListener('click', () => {
            quantity++;
            document.getElementById('qty-value').textContent = quantity;
            document.getElementById('qty-input').value = quantity;
            recalculate();
        });

        document.getElementById('qty-minus').addEventListener('click', () => {
            if (quantity > 1) quantity--;
            document.getElementById('qty-value').textContent = quantity;
            document.getElementById('qty-input').value = quantity;
            recalculate();
        });

        recalculate();
    </script>

</body>
</html>
