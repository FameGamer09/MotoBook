<!DOCTYPE html>
<html lang="en" @if (($pageCss ?? '') === 'rider-onboarding') data-rider-theme-root data-rider-theme="light" @endif>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="theme-color" content="#00b8d2">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="MotoBook">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/motobook-app-192.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/motobook-app-192.png') }}">
    <title>@yield('title', 'MotoBook')</title>
    @vite(['resources/css/app.css', 'resources/css/pages/' . ($pageCss ?? 'login') . '.css'])
    @vite('resources/css/pages/customer-experience.css')
    <script src="{{ asset('js/pwa-install.js') }}" defer></script>
    @if (($pageCss ?? '') === 'rider-onboarding')
        @vite(['resources/css/pages/rider-theme.css', 'resources/js/rider-theme.js'])
    @endif
</head>
<body class="auth-shell" data-theme="{{ (auth()->user()?->customer_settings['dark_mode'] ?? false) ? 'dark' : 'light' }}" @if (($pageCss ?? '') === 'rider-onboarding') data-rider-page="onboarding" @endif>
    <div class="auth-screen">
        @if (($pageCss ?? 'login') !== 'login')
            <div class="auth-brand">
                <span class="auth-brand-dot"></span>
                <span class="auth-brand-name"><b>Moto</b>Book</span>
                @if (($pageCss ?? '') === 'rider-onboarding')
                    <x-rider.theme-toggle class="rider-theme-toggle rider-onboarding-theme-toggle" />
                @endif
            </div>
        @endif

        @yield('content')
    </div>
</body>
</html>
