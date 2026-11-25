<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>MotoBook · Food near you</title>@vite(['resources/css/app.css','resources/css/pages/customer-home.css','resources/css/pages/customer-experience.css'])</head>
<body class="home-shell cx-page" data-theme="{{ (auth()->user()->customer_settings['dark_mode'] ?? false) ? 'dark' : 'light' }}">
    <div class="home-header"><div class="home-location-row"><div class="home-brand-location"><img src="{{ asset('images/825310998_1610647237377200_4391978218746131377_n.png') }}" alt="MotoBook"><div><div class="home-location"><svg class="home-location-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22s8-7.1 8-13a8 8 0 1 0-16 0c0 5.9 8 13 8 13Zm0-9.5a3.5 3.5 0 1 1 0-7 3.5 3.5 0 0 1 0 7Z"/></svg>{{ $defaultAddress?->address_line ?? 'Set your delivery address' }}</div><div class="home-deliver-to">Deliver to <a class="accent" href="{{ route('customer.addresses') }}">{{ $defaultAddress?->label ?? 'Add address' }}⌄</a></div></div></div><a href="{{ route('customer.cart') }}" class="item-cart-btn" aria-label="Shopping cart"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3h2l2.2 11.1a2 2 0 0 0 2 1.6h8.9a2 2 0 0 0 1.9-1.4L22 7H6.1M9.5 20a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm8 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z"/></svg><small>{{ array_sum(array_column($cartItems, 'quantity')) }}</small></a></div>
        <form method="GET" action="{{ route('customer.restaurants') }}" class="search-row"><div class="search-input-wrap"><svg class="search-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 5 5"/></svg><input type="search" name="q" value="{{ $search }}" placeholder="Search food or restaurants…" aria-label="Search food or restaurants"></div><button class="filter-btn" type="button" aria-label="Customize categories" aria-expanded="false" aria-controls="category-preferences" data-category-panel-toggle><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h9m4 0h3M4 17h3m4 0h9M13 4v6M7 14v6"/></svg></button><button class="sr-only" type="submit">Search</button></form>
    </div>
    <div class="category-row"><a href="{{ route('customer.restaurants') }}" class="category-chip {{ !$activeCategory ? 'active' : '' }}">All</a>@foreach($categories as $cat)<a href="{{ route('customer.restaurants',['category'=>$cat,'q'=>$search ?: null]) }}" class="category-chip {{ $activeCategory===$cat ? 'active' : '' }}" data-category-value="{{ $cat }}">{{ $cat }}</a>@endforeach</div>
    <section class="category-preferences" id="category-preferences" aria-label="Customize visible categories" hidden>
        <p>Choose which categories appear in your quick filters.</p>
        <div class="category-preference-list">
            @foreach($categories as $cat)
                <label><input type="checkbox" value="{{ $cat }}" data-category-preference checked><span>{{ $cat }}</span></label>
            @endforeach
        </div>
    </section>
    <section class="promo-hero"><span class="cx-eyebrow">MotoBook delivery</span><h2>Food you love,<br>delivered fast.</h2><p>Order from neighborhood restaurants and enjoy it at your door.</p><a class="btn-cta" href="#restaurants">Order now →</a></section>
    <div class="quick-actions"><a href="{{ route('customer.custom-delivery.create') }}" class="quick-action-card"><div class="quick-action-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h11v10H3zM14 9h4l3 3v4h-7z"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/><path d="M14 13h7"/></svg></div><div class="quick-action-label">Custom delivery</div></a><a href="{{ route('customer.custom-deliveries.index') }}" class="quick-action-card"><div class="quick-action-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Z"/><path d="m4.5 7.7 7.5 4.2 7.5-4.2M12 12v9"/></svg></div><div class="quick-action-label">Track a package</div></a><a href="{{ route('customer.notifications') }}" class="quick-action-card"><div class="quick-action-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4"/></svg></div><div class="quick-action-label">Updates @if($unreadNotifications)<b>{{ $unreadNotifications }}</b>@endif</div></a><a href="{{ route('customer.profile') }}" class="quick-action-card"><div class="quick-action-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s7-6.2 7-12a7 7 0 1 0-14 0c0 5.8 7 12 7 12Z"/><circle cx="12" cy="9" r="2.5"/></svg></div><div class="quick-action-label">Saved places</div></a></div>
    @if($popularStores->isNotEmpty())
        <section class="popular-section" aria-labelledby="popular-shops-title">
            <div class="section-header popular-section-header">
                <h2 class="section-title" id="popular-shops-title"><span aria-hidden="true">🔥</span> Popular Near You</h2>
                <a class="section-link see-all-link" href="#all-shops">See All <span aria-hidden="true">›</span></a>
            </div>
            <div class="store-grid popular-store-grid">
                @foreach($popularStores as $store)
                    <x-customer.store-card :store="$store" :favorite-store-ids="$favoriteStoreIds" compact />
                @endforeach
            </div>
        </section>
    @endif
    <section class="all-shops-section" id="all-shops" aria-labelledby="all-shops-title">
        <div class="section-header" id="restaurants">
            <h2 class="section-title" id="all-shops-title">{{ $search || $activeCategory ? 'Search results' : 'All Shops' }}</h2>
            <span class="cx-copy">{{ $stores->count() }} places</span>
        </div>
        <div class="store-grid">
        @forelse($stores as $store)
            <x-customer.store-card :store="$store" :favorite-store-ids="$favoriteStoreIds" />
        @empty
            <div class="cx-card" style="grid-column:1/-1;text-align:center;padding:32px"><h2>No restaurants found</h2><p class="cx-copy">Verified restaurants will appear here. Try another search or category.</p></div>
        @endforelse
        </div>
    </section>
    <p class="store-photo-credits">Store logos are provided for Jollibee, McDonald’s, Mercury Drug, Greenwich Pizza, and Local Grocery Hub.</p>
    <script>
        const categoryToggle = document.querySelector('[data-category-panel-toggle]');
        const categoryPanel = document.getElementById('category-preferences');

        categoryToggle.addEventListener('click', () => {
            const isExpanded = categoryToggle.getAttribute('aria-expanded') === 'true';
            categoryToggle.setAttribute('aria-expanded', String(!isExpanded));
            categoryPanel.hidden = isExpanded;
        });

        document.querySelectorAll('[data-category-preference]').forEach((preference) => {
            preference.addEventListener('change', () => {
                const chip = [...document.querySelectorAll('[data-category-value]')]
                    .find((categoryChip) => categoryChip.dataset.categoryValue === preference.value);

                if (chip) {
                    chip.hidden = !preference.checked;
                }
            });
        });
    </script>
    @include('customer.partials.bottom-nav',['active'=>'home'])
</body></html>
