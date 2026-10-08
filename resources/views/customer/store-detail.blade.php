<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>{{ $store->name }} · MotoBook</title>
    @vite(['resources/css/app.css', 'resources/css/pages/store-detail.css'])
    @vite('resources/css/pages/customer-experience.css')
</head>
<body class="detail-shell" data-theme="{{ (auth()->user()->customer_settings['dark_mode'] ?? false) ? 'dark' : 'light' }}" style="--store-accent: {{ $store->brand_color ?? '#14C7E0' }}">

    <div class="detail-banner" @if($store->banner) style="background-image:url('{{ asset('storage/'.$store->banner) }}')" @endif>
        @if(!$store->banner)🍽️@endif
        <a href="{{ route('customer.restaurants') }}" class="detail-back-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <a href="{{ route('customer.cart') }}" class="detail-cart-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l2.4 12.4a2 2 0 002 1.6h8.4a2 2 0 002-1.6L21 8H6"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>
        </a>
        <form method="POST" action="{{ route('customer.favorites.toggle',$store) }}" style="position:absolute;right:62px;top:16px;z-index:2">@csrf<button type="submit" aria-label="{{ $isFavorite ? 'Remove from favorites' : 'Add to favorites' }}" style="width:42px;height:42px;border:0;border-radius:50%;background:#fff;color:#e34d63;font-size:23px">{{ $isFavorite ? '♥' : '♡' }}</button></form>
    </div>

    <div class="detail-header">
        @if($store->logo)<img src="{{ asset('storage/'.$store->logo) }}" alt="{{ $store->name }}" class="detail-store-logo">@endif
        <div class="detail-name">{{ $store->name }}</div>
        <div class="detail-meta-row">
            <span>⭐ {{ $store->rating }}</span>
            <span class="dot"></span>
            <span>{{ $store->category }}</span>
            <span class="dot"></span>
            <span>20-30 min</span>
        </div>
        <span class="detail-free-delivery">🛵 Free Delivery</span>
        <span class="detail-store-status {{ $store->is_open ? 'open' : 'closed' }}">{{ $store->is_open ? 'Open for orders' : 'Currently closed' }}</span>
    </div>

    @if (session('status'))
        <div class="status-banner mx-5">{{ session('status') }}</div>
    @endif

    <div class="search-row-mini">
        <div class="search-input-wrap-mini">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4 text-text-dim shrink-0"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="M21 21l-4-4"/></svg>
            <input id="menu-search" type="search" placeholder="Search the {{ $store->name }} menu…">
        </div>
        <button type="button" class="filter-btn-mini">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M7 12h10M10 18h4"/></svg>
        </button>
    </div>

    @if ($store->menuCategories->isNotEmpty())
        <div class="category-row">
            <a href="#top" class="category-chip active">🍽️ All</a>
            @foreach ($store->menuCategories as $category)
                <a href="#category-{{ $category->id }}" class="category-chip">{{ $category->name }}</a>
            @endforeach
        </div>
    @endif

    @forelse ($store->menuCategories as $category)
        @if ($category->menuItems->isNotEmpty())
            <div class="menu-category-title" id="category-{{ $category->id }}" data-menu-category-title>{{ $category->name }}</div>

            @foreach ($category->menuItems as $item)
                @php $hasOptions = $item->option_groups_count > 0; @endphp
                <div class="item-card" data-menu-search="{{ strtolower($item->name.' '.$item->description) }}">
                    <div class="item-card-thumb" style="{{ $item->image ? "background-image:url(".asset($item->management_menu_item_id ? $item->image : 'storage/'.$item->image). ");background-size:cover;background-position:center" : "" }}">
                        🍴
                        <span class="item-fav">🤍</span>
                    </div>
                    <div class="item-card-body">
                        <div class="item-card-name">{{ $item->name }}</div>
                        @if ($item->description)
                            <div class="item-card-desc">{{ $item->description }}</div>
                        @endif
                        @if ($item->calories || $item->prep_time_minutes)
                            <div class="item-card-meta">
                                @if ($item->calories)<span>🔥 {{ $item->calories }} kcal</span>@endif
                                @if ($item->calories && $item->prep_time_minutes)<span class="dot"></span>@endif
                                @if ($item->prep_time_minutes)<span>⏱️ {{ $item->prep_time_minutes }} min</span>@endif
                            </div>
                        @endif
                        <div class="item-card-footer">
                            <span class="item-card-price">₱{{ number_format($item->price, 2) }}</span>
                            @if (!$store->is_open)
                                <span class="item-closed-label">Closed</span>
                            @elseif ($hasOptions)
                                <a href="{{ route('customer.items.show', $item) }}" class="item-add-btn" style="text-decoration:none;">+</a>
                            @else
                                <button type="button" class="item-add-btn" data-add-to-cart="{{ $item->id }}">+</button>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        @endif
    @empty
        <p class="text-center text-text-dim text-sm py-16">This store hasn't added any menu items yet.</p>
    @endforelse

    @if($uncategorizedItems->isNotEmpty())
        <div class="menu-category-title" data-menu-category-title>More items</div>
        @foreach ($uncategorizedItems as $item)
            <div class="item-card" data-menu-search="{{ strtolower($item->name.' '. $item->description) }}">
                <div class="item-card-thumb" style="{{ $item->image ? "background-image:url(".asset($item->management_menu_item_id ? $item->image : 'storage/'.$item->image). ");background-size:cover;background-position:center" : "" }}">🍽️</div>
                <div class="item-card-body">
                    <div class="item-card-name">{{ $item->name }}</div>
                    @if ($item->description)
                        <div class="item-card-desc">{{ $item->description }}</div>
                    @endif
                    <div class="item-card-footer">
                        <span class="item-card-price">₱{{ number_format($item->price, 2) }}</span>
                        @if ($store->is_open)
                            @if ($item->option_groups_count > 0)
                                <a href="{{ route('customer.items.show', $item) }}" class="item-add-btn">+</a>
                            @else
                                <button type="button" class="item-add-btn" data-add-to-cart="{{ $item->id }}">+</button>
                            @endif
                        @else
                            <span class="item-closed-label">Closed</span>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    @endif

    <div class="cart-preview-bar" id="cart-bar" style="display:none;">
        <span id="cart-count">0 items</span>
        <a href="{{ route('customer.cart') }}" style="color:white;font-weight:700;text-decoration:none;">View Cart →</a>
    </div>

    <script>
        const csrfToken = '{{ csrf_token() }}';
        const cartBar = document.getElementById('cart-bar');
        const cartCount = document.getElementById('cart-count');

        function refreshBar(itemCount, total) {
            if (itemCount > 0) {
                cartBar.style.display = 'flex';
                cartCount.textContent = `${itemCount} item${itemCount > 1 ? 's' : ''} · ₱${total.toFixed(2)}`;
            } else {
                cartBar.style.display = 'none';
            }
        }

        document.querySelectorAll('[data-add-to-cart]').forEach(btn => {
            btn.addEventListener('click', async () => {
                const menuItemId = btn.dataset.addToCart;
                const url = @json(route('customer.cart.add', ['menuItem' => '__menu_item_id__'])).replace('__menu_item_id__', menuItemId);
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ quantity: 1 }),
                });
                const data = await res.json();
                refreshBar(data.item_count, data.totals.total);

                btn.textContent = '✓';
                setTimeout(() => { btn.textContent = '+'; }, 600);
            });
        });

        document.getElementById('menu-search')?.addEventListener('input', (event) => {
            const term = event.target.value.trim().toLowerCase();
            document.querySelectorAll('[data-menu-search]').forEach((card) => {
                card.hidden = term !== '' && !card.dataset.menuSearch.includes(term);
            });
            document.querySelectorAll('.menu-category-title').forEach((heading) => {
                const category = heading.nextElementSibling;
                if (category?.matches('[data-menu-search]')) heading.hidden = category.hidden;
            });
        });
    </script>

</body>
</html>
