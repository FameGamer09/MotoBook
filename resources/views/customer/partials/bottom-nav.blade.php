<nav class="cx-bottom" aria-label="Customer navigation">
    <a class="{{ ($active ?? '') === 'home' ? 'active' : '' }}" href="{{ route('customer.restaurants') }}"><span>⌂</span>Home</a>
    <a class="{{ ($active ?? '') === 'customize' ? 'active' : '' }}" href="{{ route('customer.custom-delivery.create') }}"><span>▦</span>Customize</a>
    <a class="{{ ($active ?? '') === 'orders' ? 'active' : '' }}" href="{{ route('customer.orders.index') }}"><span>▣</span>Orders</a>
    <a class="{{ ($active ?? '') === 'inbox' ? 'active' : '' }}" href="{{ route('customer.notifications') }}"><span>♧</span>Inbox</a>
    <a class="{{ ($active ?? '') === 'profile' ? 'active' : '' }}" href="{{ route('customer.profile') }}"><span>●</span>Profile</a>
</nav>
