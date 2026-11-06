<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#00b8d2">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="MotoBook">
        <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
        <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/motobook-app-192.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('images/motobook-app-192.png') }}">

        <title>{{ config('app.name', 'Motobook') }} &middot; @yield('title', 'Dashboard')</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <script src="{{ asset('js/pwa-install.js') }}" defer></script>

        <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js" defer></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (window.lucide) {
                    window.lucide.createIcons({
                        attrs: {
                            'stroke-width': 1.75,
                            class: 'inline-block shrink-0',
                        },
                    });
                }
                document.addEventListener('alpine:init', function () {
                    if (window.lucide) window.lucide.createIcons();
                });
            });
        </script>
    </head>
    <body class="min-h-screen bg-slate-50 text-ink antialiased">
        <div class="flex min-h-screen">
            <aside class="sidebar-frame hidden lg:flex">
                <div class="sidebar-brand">
                    <img src="{{ asset('images/825310998_1610647237377200_4391978218746131377_n.png') }}" alt="MotoBook" class="h-10 w-10 rounded-md object-cover">
                    <div class="flex flex-col leading-tight">
                        <span class="font-semibold text-white tracking-tight">Motobook</span>
                        <span class="text-[11px] text-slate-400">Inventory &amp; POS</span>
                    </div>
                </div>

                <nav class="sidebar-nav" aria-label="Main navigation">
                    <div class="px-3 pb-2 pt-1 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                        Workspace
                    </div>
                    <a href="{{ route('dashboard') }}"
                       class="sidebar-item {{ request()->routeIs('dashboard') ? 'sidebar-item-active' : '' }}"
                       aria-current="{{ request()->routeIs('dashboard') ? 'page' : 'false' }}">
                        <i data-lucide="layout-dashboard" class="sidebar-icon"></i>
                        Dashboard
                    </a>
                    <a href="{{ route('pos.index') }}"
                       class="sidebar-item {{ request()->routeIs('pos.index') ? 'sidebar-item-active' : '' }}">
                        <i data-lucide="scan-line" class="sidebar-icon"></i>
                        Point of Sale
                    </a>

                    <div class="px-3 pb-2 pt-5 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                        Management
                    </div>
                    <a href="{{ route('products.index') }}"
                       class="sidebar-item {{ request()->routeIs('products.*') ? 'sidebar-item-active' : '' }}">
                        <i data-lucide="package" class="sidebar-icon"></i>
                        Products
                    </a>
                    <a href="{{ route('categories.index') }}"
                       class="sidebar-item {{ request()->routeIs('categories.*') ? 'sidebar-item-active' : '' }}">
                        <i data-lucide="tags" class="sidebar-icon"></i>
                        Categories
                    </a>
                    <a href="{{ route('transactions.index') }}"
                       class="sidebar-item {{ request()->routeIs('transactions.*') ? 'sidebar-item-active' : '' }}">
                        <i data-lucide="receipt" class="sidebar-icon"></i>
                        Transactions
                    </a>
                </nav>

                <div class="px-4 py-3 border-t border-white/10 text-[11px] text-slate-500">
                    v1.0 &middot; {{ config('app.env', 'production') }}
                </div>
            </aside>

            <div class="flex-1 flex flex-col min-w-0">
                <header class="header-frame">
                    <div class="flex items-center gap-3">
                        <button type="button" class="lg:hidden btn-ghost !p-2" aria-label="Toggle navigation" x-data @click="$dispatch('toggle-sidebar')">
                            <i data-lucide="menu" class="w-5 h-5"></i>
                        </button>
                        @isset($header)
                            <div>
                                <h1 class="text-[15px] font-semibold text-ink tracking-tight">{{ $header }}</h1>
                                @isset($headerSubtitle)
                                    <p class="text-xs text-ink-subtle mt-0.5">{{ $headerSubtitle }}</p>
                                @endisset
                            </div>
                        @endisset
                    </div>

                    <div class="flex items-center gap-2">
                        <div class="relative" x-data="dashboardNotifications('{{ route('dashboard.notifications') }}')" x-init="load()" @click.outside="open = false">
                            <button type="button" class="btn-ghost !p-2 relative" aria-label="Notifications" aria-haspopup="true" :aria-expanded="open" @click="open = !open; if (open && !loaded) load()">
                                <i data-lucide="bell" class="w-5 h-5"></i>
                                <span x-show="lowStockCount > 0" x-text="lowStockCount > 9 ? '9+' : lowStockCount" x-cloak class="absolute -right-1 -top-1 min-w-4 rounded-full bg-status-danger px-1 text-[10px] font-bold leading-4 text-white"></span>
                            </button>
                            <section x-show="open" x-transition x-cloak class="absolute right-0 z-50 mt-2 w-[min(90vw,22rem)] overflow-hidden rounded-xl border border-surface-border bg-white shadow-xl" role="region" aria-label="Inventory notifications">
                                <div class="flex items-center justify-between border-b border-surface-border px-4 py-3">
                                    <div><h2 class="text-sm font-semibold text-ink">Notifications</h2><p class="text-xs text-ink-subtle">Low stock and recent stock changes</p></div>
                                    <button type="button" class="btn-ghost !p-1" aria-label="Close notifications" @click="open = false">×</button>
                                </div>
                                <div class="max-h-80 overflow-y-auto p-2">
                                    <p x-show="loading" class="px-3 py-5 text-center text-sm text-ink-subtle">Loading notifications…</p>
                                    <p x-show="!loading && loadError" class="px-3 py-5 text-center text-sm text-status-danger">Could not load notifications. Try again.</p>
                                    <template x-for="notification in notifications" :key="notification.id">
                                        <a :href="notification.url" class="flex gap-3 rounded-lg px-3 py-3 hover:bg-surface-muted">
                                            <span class="mt-1 h-2 w-2 shrink-0 rounded-full" :class="notification.kind === 'warning' ? 'bg-status-danger' : 'bg-brand-600'"></span>
                                            <span class="min-w-0"><strong class="block text-sm font-medium text-ink" x-text="notification.title"></strong><span class="mt-0.5 block text-xs text-ink-subtle" x-text="notification.message"></span></span>
                                        </a>
                                    </template>
                                    <p x-show="!loading && loaded && !loadError && notifications.length === 0" class="px-3 py-6 text-center text-sm text-ink-subtle">No low-stock alerts or recent stock changes.</p>
                                </div>
                            </section>
                        </div>
                        <button type="button" class="btn-ghost !p-2" aria-label="Search" data-open-global-search>
                            <i data-lucide="search" class="w-5 h-5"></i>
                        </button>

                        @auth
                        <div class="relative" x-data="{ open: false }" @click.outside="open = false" @close.stop="open = false">
                            <button type="button"
                                    @click="open = ! open"
                                    class="inline-flex items-center gap-2 rounded-lg border border-surface-border bg-white px-3 py-1.5 text-sm font-medium text-ink hover:bg-surface-muted transition-colors duration-150"
                                    aria-haspopup="menu"
                                    :aria-expanded="open">
                                <div class="w-7 h-7 rounded-full bg-brand-600 text-white grid place-items-center text-xs font-semibold">
                                    {{ strtoupper(substr(Auth::user()->name ?? 'U', 0, 1)) }}
                                </div>
                                <span class="hidden sm:block">{{ Auth::user()->name }}</span>
                                <i data-lucide="chevron-down" class="w-4 h-4 text-ink-subtle"></i>
                            </button>

                            <div x-show="open"
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0 -translate-y-1"
                                 x-transition:enter-end="opacity-100 translate-y-0"
                                 x-transition:leave="transition ease-in duration-100"
                                 x-transition:leave-start="opacity-100 translate-y-0"
                                 x-transition:leave-end="opacity-0 -translate-y-1"
                                 x-cloak
                                 class="absolute right-0 mt-2 w-56 rounded-lg bg-white border border-surface-border shadow-md py-1 z-50"
                                 role="menu"
                                 aria-label="User menu">
                                <div class="px-4 py-3 border-b border-surface-border">
                                    <div class="text-sm font-semibold text-ink">{{ Auth::user()->name }}</div>
                                    <div class="text-xs text-ink-subtle truncate">{{ Auth::user()->email }}</div>
                                </div>
                                <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 px-4 py-2 text-sm text-ink hover:bg-surface-muted transition-colors" role="menuitem">
                                    <i data-lucide="user-circle-2" class="w-4 h-4 text-ink-muted"></i>
                                    Profile
                                </a>
                                <form method="POST" action="{{ route('logout') }}" role="none">
                                    @csrf
                                    <a href="{{ route('logout') }}"
                                       onclick="event.preventDefault(); this.closest('form').submit();"
                                       class="flex items-center gap-2 px-4 py-2 text-sm text-status-danger hover:bg-status-danger-soft transition-colors"
                                       role="menuitem">
                                        <i data-lucide="log-out" class="w-4 h-4"></i>
                                        Log Out
                                    </a>
                                </form>
                            </div>
                        </div>
                        @endauth
                    </div>
                </header>

                @if (View::hasSection('pageHeader'))
                    <div class="px-6 pt-6">
                        @yield('pageHeader')
                    </div>
                @elseif (!View::hasSection('content'))
                    <div class="px-6 pt-6">
                        <h1 class="text-[15px] font-semibold text-ink tracking-tight">@yield('title', 'Dashboard')</h1>
                    </div>
                @endif

                <main class="flex-1 px-6 py-6 overflow-x-hidden">
                    @yield('content')
                </main>
            </div>
        </div>
        <dialog id="global-search-dialog" class="m-auto w-[min(92vw,38rem)] rounded-xl border border-surface-border bg-white p-0 text-ink shadow-2xl backdrop:bg-slate-950/40">
            <form method="GET" action="{{ route('products.index') }}" class="space-y-4 p-5" data-global-search-form>
                <div class="flex items-start justify-between gap-4">
                    <div><h2 class="text-base font-semibold">Search MotoBook</h2><p class="mt-1 text-sm text-ink-subtle">Search inventory or sales records.</p></div>
                    <button type="button" class="btn-ghost !p-1" aria-label="Close search" data-close-global-search>×</button>
                </div>
                <label class="block space-y-1.5"><span class="text-sm font-medium">Search in</span><select class="select w-full" data-global-search-target><option value="{{ route('products.index') }}">Inventory (name or SKU)</option><option value="{{ route('transactions.index') }}">Sales (invoice number)</option></select></label>
                <label class="block space-y-1.5"><span class="text-sm font-medium">Search term</span><input type="search" name="search" required maxlength="100" class="input w-full" placeholder="Type a product, SKU, or invoice number…" data-global-search-input></label>
                <button type="submit" class="btn-primary w-full">Search</button>
            </form>
        </dialog>
    </body>
</html>
