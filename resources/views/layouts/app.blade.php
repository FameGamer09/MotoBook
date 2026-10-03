<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Motobook') }} &middot; @yield('title', 'Dashboard')</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])

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
                    <div class="w-9 h-9 rounded-md bg-brand-600/25 text-brand-300 grid place-items-center">
                        <i data-lucide="bike" class="w-5 h-5"></i>
                    </div>
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
                        <button type="button" class="btn-ghost !p-2" aria-label="Notifications">
                            <i data-lucide="bell" class="w-5 h-5"></i>
                        </button>
                        <button type="button" class="btn-ghost !p-2" aria-label="Search">
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
    </body>
</html>
