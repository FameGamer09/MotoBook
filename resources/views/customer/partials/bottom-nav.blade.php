<nav class="cx-bottom" aria-label="Customer navigation">
    <a class="{{ ($active ?? '') === 'home' ? 'active' : '' }}" href="{{ route('customer.restaurants') }}" @if(($active ?? '') === 'home') aria-current="page" @endif>
        <span class="cx-bottom-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" focusable="false"><path d="M3 10.8 12 3l9 7.8v9a1.2 1.2 0 0 1-1.2 1.2h-4.9v-6.2H9.1V21H4.2A1.2 1.2 0 0 1 3 19.8z"/></svg>
        </span>
        <span class="cx-bottom-label">Home</span>
    </a>
    <a class="{{ ($active ?? '') === 'customize' ? 'active' : '' }}" href="{{ route('customer.custom-delivery.create') }}" @if(($active ?? '') === 'customize') aria-current="page" @endif>
        <span class="cx-bottom-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" focusable="false"><path d="M3 3h7v7H3zm11 0h7v7h-7zM3 14h7v7H3zm11 0h3v3h-3z"/><path d="M19 14v3h3v2h-3v3h-2v-3h-3v-2h3v-3z"/></svg>
        </span>
        <span class="cx-bottom-label">Customize</span>
    </a>
    <a class="cx-bottom-orders {{ ($active ?? '') === 'orders' ? 'active' : '' }}" href="{{ route('customer.orders.index') }}" @if(($active ?? '') === 'orders') aria-current="page" @endif>
        <span class="cx-bottom-orders-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" focusable="false"><path d="M8 3.5A2.5 2.5 0 0 1 10.5 1h3A2.5 2.5 0 0 1 16 3.5H18a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-14a2 2 0 0 1 2-2zm2.5-.5a.5.5 0 0 0-.5.5v1h5v-1a.5.5 0 0 0-.5-.5zM10 12.6l-1.4 1.4 3.1 3.1 5.7-5.7-1.4-1.4-4.3 4.3z"/></svg>
        </span>
        <span class="cx-bottom-label">Orders</span>
    </a>
    <a class="{{ ($active ?? '') === 'inbox' ? 'active' : '' }}" href="{{ route('customer.notifications') }}" @if(($active ?? '') === 'inbox') aria-current="page" @endif>
        <span class="cx-bottom-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" focusable="false"><path d="M12 22a2.5 2.5 0 0 0 2.4-1.8H9.6A2.5 2.5 0 0 0 12 22m7-5-1.7-2.1V10a5.3 5.3 0 0 0-4.3-5.2V4a1 1 0 0 0-2 0v.8A5.3 5.3 0 0 0 6.7 10v4.9L5 17v1h14z"/></svg>
        </span>
        <span class="cx-bottom-label">Inbox</span>
        @if($hasUnreadInboxMessages)
            <span class="cx-bottom-dot" aria-hidden="true"></span>
        @endif
    </a>
    <a class="{{ ($active ?? '') === 'profile' ? 'active' : '' }}" href="{{ route('customer.profile') }}" @if(($active ?? '') === 'profile') aria-current="page" @endif>
        <span class="cx-bottom-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" focusable="false"><path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10m0 2c-5 0-9 2.5-9 5.5V22h18v-2.5c0-3-4-5.5-9-5.5"/></svg>
        </span>
        <span class="cx-bottom-label">Profile</span>
    </a>
</nav>
