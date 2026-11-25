@props(['store', 'favoriteStoreIds' => [], 'compact' => false])

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
    $isFavorite = in_array($store->id, $favoriteStoreIds);
@endphp

<article class="store-card {{ $compact ? 'store-card-compact' : '' }}">
    <a class="store-card-link" href="{{ route('customer.restaurants.show', $store) }}">
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
            <div class="store-stats">★ {{ number_format($store->rating, 1) }} · 20–30 min</div>
            <div class="store-fee">Delivery fee shown at checkout</div>
        </div>
    </a>
    <form class="store-card-favorite-form" method="POST" action="{{ route('customer.favorites.toggle', $store) }}">
        @csrf
        <button class="store-fav" type="submit" aria-label="{{ $isFavorite ? 'Remove favorite' : 'Add favorite' }}">{{ $isFavorite ? '♥' : '♡' }}</button>
    </form>
</article>
