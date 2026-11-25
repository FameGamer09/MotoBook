@props(['rider', 'canGoOnline', 'hasActiveDelivery', 'active' => 'home'])

<nav class="rider-bottom-nav" aria-label="Rider navigation">
    <a class="rider-nav-item {{ $active === 'home' ? 'active' : '' }}" href="{{ route('rider.dashboard') }}" @if ($active === 'home') aria-current="page" @endif>
        <svg class="rider-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/></svg>
        <span>Home</span>
    </a>
    <a class="rider-nav-item {{ in_array($active, ['orders', 'requests'], true) ? 'active' : '' }}" href="{{ route('rider.history') }}" @if (in_array($active, ['orders', 'requests'], true)) aria-current="page" @endif>
        <svg class="rider-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4.5V3h6v1.5M9 10h6M9 14h6M9 18h3"/></svg>
        <span>Orders</span>
    </a>
    <form method="POST" action="{{ route('rider.status.toggle') }}" class="rider-nav-online-form">
        @csrf
        <button class="rider-nav-online" type="submit" @disabled($rider?->status === 'online' ? false : ! $canGoOnline)>
            <span class="rider-nav-online-icon">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21.8 2.2a.8.8 0 0 0-.86-.18L2.6 9.18a.8.8 0 0 0 .05 1.5l7.2 2.4 2.4 7.2a.8.8 0 0 0 1.5.05l7.16-18.34a.8.8 0 0 0-.11-.79ZM12.43 12.7l-1.42-1.42 6.45-6.45-5.03 7.87Z"/></svg>
            </span>
            <span>{{ $rider?->status === 'online' ? 'Go Offline' : ($hasActiveDelivery ? 'On delivery' : ($rider?->is_verified ? 'Go Online' : 'Pending')) }}</span>
        </button>
    </form>
    <a class="rider-nav-item {{ in_array($active, ['earnings', 'wallet'], true) ? 'active' : '' }}" href="{{ route('rider.earnings') }}" @if (in_array($active, ['earnings', 'wallet'], true)) aria-current="page" @endif>
        <svg class="rider-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="6" width="18" height="15" rx="2"/><path d="M16 14h5M3 9h18M7 3h10v3"/></svg>
        <span>Earnings</span>
    </a>
    <a class="rider-nav-item {{ in_array($active, ['profile', 'settings'], true) ? 'active' : '' }}" href="{{ route('rider.profile') }}" @if (in_array($active, ['profile', 'settings'], true)) aria-current="page" @endif>
        <svg class="rider-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4.5 21a7.5 7.5 0 0 1 15 0"/></svg>
        <span>Profile</span>
    </a>
</nav>
