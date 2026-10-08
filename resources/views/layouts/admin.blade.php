<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'MotoBook Admin')</title>
    @vite(['resources/css/app.css', 'resources/css/pages/' . ($pageCss ?? 'admin-dashboard') . '.css'])
</head>
<body class="admin-shell">

    <aside class="admin-rail">
        <div class="admin-rail-brand">MotoBook admin</div>
        <nav class="admin-rail-nav">
            <a href="{{ route('admin.dashboard') }}" class="admin-rail-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>
                Dashboard
            </a>
            <a href="{{ route('admin.merchants.index') }}" class="admin-rail-link {{ request()->routeIs('admin.merchants.*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9l1.5-5h15L21 9M3 9v10a1 1 0 001 1h16a1 1 0 001-1V9M3 9h18M9 21v-6h6v6"/></svg>
                Merchants
            </a>
            <a href="{{ route('admin.riders.index') }}" class="admin-rail-link {{ request()->routeIs('admin.riders.*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><circle cx="6" cy="18" r="3"/><circle cx="18" cy="18" r="3"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 18h6l3-9h-4l-2 4H8L6 9"/></svg>
                Riders
            </a>
            <a href="{{ route('admin.customers.index') }}" class="admin-rail-link {{ request()->routeIs('admin.customers.*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><circle cx="9" cy="8" r="3.5"/><path stroke-linecap="round" d="M3 20c0-3.5 3-6 6-6s6 2.5 6 6M16 8a3 3 0 110-6M17 14c2.5 0 4.5 2 4.5 4.5"/></svg>
                Customers
            </a>
            <a href="{{ route('admin.promotions.index') }}" class="admin-rail-link {{ request()->routeIs('admin.promotions.*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M20 13l-7 7-10-10V3h7l10 10z"/><circle cx="7.5" cy="7.5" r="1"/></svg>
                Promotions
            </a>
        </nav>
        <div class="admin-rail-footer">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="admin-rail-link" style="width:100%; text-align:left; background:none; border:0; cursor:pointer;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                    Log out
                </button>
            </form>
        </div>
    </aside>

    <div class="admin-main">
        <div class="admin-topbar">
            <div class="admin-page-title">@yield('page-title', 'Dashboard')</div>
            <div class="text-[13px] text-text-dim">{{ auth()->user()->name }}</div>
        </div>
        <div class="admin-content">
            @if (session('status'))
                <div class="mb-5 text-[13px] text-ok">{{ session('status') }}</div>
            @endif
            @yield('content')
        </div>
    </div>

</body>
</html>
