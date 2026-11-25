<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <title>Profile · MotoBook</title>
    @vite(['resources/css/app.css', 'resources/css/pages/profile.css', 'resources/css/pages/customer-experience.css'])
</head>
<body class="cx-page" data-theme="{{ (auth()->user()->customer_settings['dark_mode'] ?? false) ? 'dark' : 'light' }}">
<main class="cx-wrap">
    <header class="cx-topbar"><h1>Profile</h1></header>
    @if(session('status'))
        <div class="cx-alert">{{ session('status') }}</div>
    @endif

    <section class="cx-card">
        <div class="cx-row">
            @if($user->avatar)
                <img src="{{ asset('storage/'.$user->avatar) }}" alt="{{ $user->name }}" class="cx-icon" style="object-fit:cover">
            @else
                <span class="cx-icon">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
            @endif
            <span class="cx-row-copy">
                <strong style="font-size:18px">{{ $user->name }}</strong>
                <small>{{ $user->email }}</small>
                <small>{{ $user->phone }}</small>
            </span>
            <a class="cx-back" href="{{ route('customer.profile.edit') }}" aria-label="Edit profile">✎</a>
        </div>
        <div class="cx-tabs">
            <a class="active">{{ $ordersCount }} orders</a>
            <a>{{ $reviewsCount }} reviews</a>
            <a>{{ $loyaltyPoints }} points</a>
        </div>
        <a class="cx-button secondary" href="{{ route('customer.profile.edit') }}">Edit profile</a>
    </section>

    <section class="cx-card">
        <span class="cx-eyebrow">My account</span>
        <a class="cx-row cx-profile-action" href="{{ route('customer.orders.index') }}">
            <x-customer.account-icon name="orders" />
            <span class="cx-row-copy"><strong>My orders</strong><small>Track current and past orders</small></span>
            <span class="cx-profile-chevron" aria-hidden="true">›</span>
        </a>
        <a class="cx-row cx-profile-action" href="{{ route('customer.custom-deliveries.index') }}">
            <x-customer.account-icon name="deliveries" />
            <span class="cx-row-copy"><strong>Custom deliveries</strong><small>{{ $customDeliveriesCount }} requests</small></span>
            <span class="cx-profile-chevron" aria-hidden="true">›</span>
        </a>
        <a class="cx-row cx-profile-action" href="{{ route('customer.addresses') }}">
            <x-customer.account-icon name="addresses" />
            <span class="cx-row-copy"><strong>Saved addresses</strong><small>Manage delivery locations</small></span>
            <span class="cx-profile-chevron" aria-hidden="true">›</span>
        </a>
        <a class="cx-row cx-profile-action" href="{{ route('customer.payments') }}">
            <x-customer.account-icon name="payments" />
            <span class="cx-row-copy"><strong>Payment methods</strong><small>Cash, wallet and GCash preferences</small></span>
            <span class="cx-profile-chevron" aria-hidden="true">›</span>
        </a>
    </section>

    <section class="cx-card">
        <span class="cx-eyebrow">Settings and support</span>
        <form method="POST" action="{{ route('customer.settings.update') }}">
            @csrf
            @method('PATCH')
            <label class="cx-choice cx-profile-mode">
                <x-customer.account-icon name="dark-mode" />
                <span class="cx-row-copy"><strong>Dark mode</strong><small class="cx-copy">Change the appearance of customer pages.</small></span>
                <input
                    class="cx-mode-switch"
                    type="checkbox"
                    name="dark_mode"
                    value="1"
                    aria-label="Enable dark mode"
                    @checked(auth()->user()->customer_settings['dark_mode'] ?? false)
                    onchange="this.form.submit()"
                >
            </label>
            <input type="hidden" name="order_notifications" value="{{ (auth()->user()->customer_settings['order_notifications'] ?? true) ? 1 : 0 }}">
        </form>
        <a class="cx-row cx-profile-action" href="{{ route('customer.notifications') }}">
            <x-customer.account-icon name="notifications" />
            <span class="cx-row-copy"><strong>Notifications</strong><small>Order and delivery updates</small></span>
            <span class="cx-profile-chevron" aria-hidden="true">›</span>
        </a>
        <a class="cx-row cx-profile-action" href="{{ route('customer.settings') }}">
            <x-customer.account-icon name="settings" />
            <span class="cx-row-copy"><strong>Settings</strong><small>Preferences and account details</small></span>
            <span class="cx-profile-chevron" aria-hidden="true">›</span>
        </a>
        <a class="cx-row cx-profile-action" href="{{ route('customer.help') }}">
            <x-customer.account-icon name="help" />
            <span class="cx-row-copy"><strong>Help and support</strong><small>FAQs and contact support</small></span>
            <span class="cx-profile-chevron" aria-hidden="true">›</span>
        </a>
    </section>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button class="cx-button secondary" type="submit">Sign out</button>
    </form>
</main>
@include('customer.partials.bottom-nav', ['active' => 'profile'])
</body>
</html>
