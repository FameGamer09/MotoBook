@props(['title', 'active' => 'home', 'rider', 'canGoOnline', 'hasActiveDelivery'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-rider-theme-root data-rider-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#f4f7f8">
    <title>{{ $title }} · MotoBook Rider</title>
    @vite(['resources/css/app.css', 'resources/css/pages/rider-dashboard.css', 'resources/css/pages/rider-theme.css', 'resources/css/pages/rider-activity.css', 'resources/js/rider-theme.js'])
    @if ($active === 'requests')
        @vite('resources/js/rider-offers.js')
    @endif
</head>
<body class="rider-shell rider-app-page" data-rider-page="{{ $active }}">
    <header class="rider-header">
        <a class="rider-header-icon" href="{{ route('rider.dashboard') }}" aria-label="Back to dashboard">‹</a>
        <h1>{{ $title }}</h1>
        <x-rider.theme-toggle />
    </header>
    <main class="rider-content">
        @if (session('status'))<div class="rider-alert">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="rider-error">{{ $errors->first() }}</div>@endif
        {{ $slot }}
    </main>
    <x-rider.bottom-nav :rider="$rider" :can-go-online="$canGoOnline" :has-active-delivery="$hasActiveDelivery" :active="$active" />
</body>
</html>
