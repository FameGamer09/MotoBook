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
    <title>{{ config('app.name', 'Motobook') }} · @yield('title', 'Sign In')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/css/pages/login.css', 'resources/js/app.js'])
    <script src="{{ asset('js/pwa-install.js') }}" defer></script>
</head>
<body class="login-shell">
    <main class="login-layout">
        {{ $slot }}
        <div class="pwa-install-area">
            <button class="pwa-install-button" type="button" data-pwa-install hidden>Install MotoBook</button>
            <p class="pwa-install-help" data-pwa-install-help role="status" hidden></p>
        </div>
        <p class="login-copyright">© {{ date('Y') }} MotoBook. All rights reserved.</p>
    </main>
</body>
</html>
