<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>@yield('title', 'MotoBook')</title>
    @vite(['resources/css/app.css', 'resources/css/pages/' . ($pageCss ?? 'login') . '.css'])
    @vite('resources/css/pages/customer-experience.css')
</head>
<body class="auth-shell" data-theme="{{ (auth()->user()?->customer_settings['dark_mode'] ?? false) ? 'dark' : 'light' }}">
    <div class="auth-screen">
        @if (($pageCss ?? 'login') !== 'login')
            <div class="auth-brand">
                <span class="auth-brand-dot"></span>
                <span class="auth-brand-name"><b>Moto</b>Book</span>
            </div>
        @endif

        @yield('content')
    </div>
</body>
</html>
