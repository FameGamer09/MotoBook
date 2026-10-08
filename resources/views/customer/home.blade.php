<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>MotoBook · Food near you</title>@vite(['resources/css/app.css','resources/css/pages/customer-home.css','resources/css/pages/customer-experience.css'])</head>
<body class="home-shell cx-page" data-theme="{{ (auth()->user()->customer_settings['dark_mode'] ?? false) ? 'dark' : 'light' }}">
    <div class="home-header"><div class="home-location-row"><div><div class="home-location">⌖ {{ $defaultAddress?->address_line ?? 'Set your delivery address' }}</div><div class="home-deliver-to">Deliver to <a class="accent" href="{{ route('customer.addresses') }}">{{ $defaultAddress?->label ?? 'Add address' }}⌄</a></div></div><a href="{{ route('customer.cart') }}" class="item-cart-btn" aria-label="Shopping cart">🛒 <small>{{ array_sum(array_column($cartItems, 'quantity')) }}</small></a></div>
        <form method="GET" action="{{ route('customer.restaurants') }}" class="search-row"><div class="search-input-wrap"><span>⌕</span><input type="search" name="q" value="{{ $search }}" placeholder="Search food or restaurants…"></div><button class="filter-btn" type="submit" aria-label="Search">☷</button></form>
    </div>
    <div class="category-row"><a href="{{ route('customer.restaurants') }}" class="category-chip {{ !$activeCategory ? 'active' : '' }}">All</a>@foreach($categories as $cat)<a href="{{ route('customer.restaurants',['category'=>$cat]) }}" class="category-chip {{ $activeCategory===$cat ? 'active' : '' }}">{{ $cat }}</a>@endforeach</div>
    <section class="promo-hero"><span class="cx-eyebrow">MotoBook delivery</span><h2>Food you love,<br>delivered fast.</h2><p>Order from neighborhood restaurants and enjoy it at your door.</p><a class="btn-cta" href="#restaurants">Order now →</a></section>
    <div class="quick-actions"><a href="{{ route('customer.custom-delivery.create') }}" class="quick-action-card"><div class="quick-action-icon">⌁</div><div class="quick-action-label">Custom delivery</div></a><a href="{{ route('customer.custom-deliveries.index') }}" class="quick-action-card"><div class="quick-action-icon">▣</div><div class="quick-action-label">Track a package</div></a><a href="{{ route('customer.notifications') }}" class="quick-action-card"><div class="quick-action-icon">♧</div><div class="quick-action-label">Updates @if($unreadNotifications)<b>{{ $unreadNotifications }}</b>@endif</div></a><a href="{{ route('customer.profile') }}" class="quick-action-card"><div class="quick-action-icon">⌖</div><div class="quick-action-label">Saved places</div></a></div>
    <div class="section-header" id="restaurants"><div class="section-title">{{ $search || $activeCategory ? 'Search results' : 'Restaurants near you' }}</div><span class="cx-copy">{{ $stores->count() }} places</span></div>
    <div class="store-grid">
        @forelse($stores as $store)
            @php
                $storeName = \Illuminate\Support\Str::lower($store->name);
                $storeLogo = match (true) {
                    str_contains($storeName, 'jollibee') => asset('images/stores/jollibee.png'),
                    str_contains($storeName, 'mcdonald'), str_contains($storeName, 'mcdo') => asset('images/stores/mcdonalds.jpg'),
                    str_contains($storeName, 'mercury drug') => asset('images/stores/mercury-drug.jpg'),
                    str_contains($storeName, 'greenwich') => asset('images/stores/greenwich.png'),
                    str_contains($storeName, 'grocery') => asset('images/stores/local-grocery.webp'),
                    default => null,
                };
            @endphp
            <article class="store-card" style="position:relative">
                <a href="{{ route('customer.restaurants.show',$store) }}" style="text-decoration:none;color:inherit">
                    <div class="store-card-image store-logo-tile">
                        <span class="store-brand-fallback" aria-hidden="true" @if($store->logo || $storeLogo) hidden @endif>{{ $store->name }}</span>
                        @if($store->logo || $storeLogo)
                            <img class="store-brand-logo" src="{{ $store->logo ? asset('storage/'.$store->logo) : $storeLogo }}" alt="{{ $store->name }} logo" loading="lazy" onerror="this.hidden=true;this.parentElement.querySelector('.store-brand-fallback').hidden=false">
                        @endif
                        <span class="store-badge {{ $store->is_open ? '' : 'closed' }}">{{ $store->is_open ? 'Open' : 'Closed' }}</span>
                    </div>
                    <div class="store-card-body">
                        <div class="store-name">{{ $store->name }}</div>
                        <div class="store-meta">{{ $store->category ?: 'Restaurant' }}</div>
                        <div class="store-stats">★ {{ number_format($store->rating,1) }} · 20–30 min</div>
                        <div class="store-fee">Delivery fee shown at checkout</div>
                    </div>
                </a>
                <form method="POST" action="{{ route('customer.favorites.toggle',$store) }}" style="position:absolute;right:9px;top:9px">
                    @csrf
                    <button class="store-fav" type="submit" aria-label="{{ in_array($store->id,$favoriteStoreIds) ? 'Remove favorite' : 'Add favorite' }}">{{ in_array($store->id,$favoriteStoreIds) ? '♥' : '♡' }}</button>
                </form>
            </article>
        @empty
            <div class="cx-card" style="grid-column:1/-1;text-align:center;padding:32px"><h2>No restaurants found</h2><p class="cx-copy">Verified restaurants will appear here. Try another search or category.</p></div>
        @endforelse
    </div>
    <p class="store-photo-credits">Store logos are provided for Jollibee, McDonald’s, Mercury Drug, Greenwich Pizza, and Local Grocery Hub.</p>
    @include('customer.partials.bottom-nav',['active'=>'home'])
</body></html>
