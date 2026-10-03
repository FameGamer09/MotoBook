<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Motobook') }} &middot; @yield('title', 'Sign In')</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js" defer></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (window.lucide) {
                    window.lucide.createIcons({ 'stroke-width': 1.75 });
                }
            });
        </script>
    </head>
    <body class="min-h-screen text-ink antialiased">
        <div class="min-h-screen grid lg:grid-cols-2">
            <div class="hidden lg:flex relative overflow-hidden bg-frame-sidebar text-slate-200 flex-col justify-between p-12 xl:p-16">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-brand-600/25 text-brand-300 grid place-items-center">
                        <i data-lucide="bike" class="w-6 h-6"></i>
                    </div>
                    <div class="leading-tight">
                        <div class="font-semibold text-white tracking-tight text-lg">Motobook</div>
                        <div class="text-xs text-slate-400">Enterprise POS &amp; Inventory Platform</div>
                    </div>
                </div>

                <div class="relative z-10 max-w-md">
                    <h2 class="text-2xl xl:text-3xl font-semibold text-white tracking-tight leading-snug">
                        Streamlined operations.
                        <span class="block text-brand-300">Trusted accuracy.</span>
                    </h2>
                    <p class="mt-4 text-sm leading-relaxed text-slate-400">
                        Single pane of glass for inventory, point-of-sale, and daily reconciliations — built for speed during long shifts and heavy loads.
                    </p>

                    <div class="mt-10 space-y-5">
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-md bg-white/5 grid place-items-center text-brand-300 border border-white/10">
                                <i data-lucide="shield-check" class="w-4 h-4"></i>
                            </div>
                            <div>
                                <div class="text-sm font-medium text-white">Secure role-based access</div>
                                <div class="text-xs text-slate-400 mt-0.5">Granular permissions and audit trail</div>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-md bg-white/5 grid place-items-center text-brand-300 border border-white/10">
                                <i data-lucide="bar-chart-3" class="w-4 h-4"></i>
                            </div>
                            <div>
                                <div class="text-sm font-medium text-white">Real-time visibility</div>
                                <div class="text-xs text-slate-400 mt-0.5">Live sales, stock, and performance</div>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-md bg-white/5 grid place-items-center text-brand-300 border border-white/10">
                                <i data-lucide="zap" class="w-4 h-4"></i>
                            </div>
                            <div>
                                <div class="text-sm font-medium text-white">Built for daily throughput</div>
                                <div class="text-xs text-slate-400 mt-0.5">Fast transactions, minimal clicks</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-xs text-slate-500">
                    &copy; {{ date('Y') }} Motobook. All rights reserved.
                </div>
            </div>

            <div class="flex items-center justify-center bg-slate-50 px-4 py-10 sm:px-6">
                <div class="w-full max-w-sm">
                    <div class="lg:hidden flex items-center justify-center gap-3 mb-8">
                        <div class="w-9 h-9 rounded-md bg-brand-600 text-white grid place-items-center">
                            <i data-lucide="bike" class="w-5 h-5"></i>
                        </div>
                        <div class="font-semibold text-ink tracking-tight text-lg">Motobook</div>
                    </div>

                    <div class="card p-7">
                        {{ $slot }}
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
