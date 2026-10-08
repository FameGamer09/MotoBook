<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>Settings · MotoBook</title>@vite(['resources/css/app.css','resources/css/pages/customer-experience.css'])</head><body class="cx-page" data-theme="{{ ($settings['dark_mode'] ?? false) ? 'dark' : 'light' }}"><main class="cx-wrap">
    <header class="cx-topbar"><a class="cx-back" href="{{ route('customer.profile') }}">‹</a><h1>Settings</h1></header>@if(session('status'))<div class="cx-alert">{{ session('status') }}</div>@endif
    <form method="POST" action="{{ route('customer.settings.update') }}">@csrf @method('PATCH')<section class="cx-card"><span class="cx-eyebrow">Preferences</span>
        <label class="cx-choice"><input type="checkbox" name="dark_mode" value="1" @checked($settings['dark_mode'] ?? false)><span><strong>Dark mode</strong><small class="cx-copy">Use a darker color theme on customer pages.</small></span></label>
        <label class="cx-choice"><input type="checkbox" name="order_notifications" value="1" @checked($settings['order_notifications'] ?? true)><span><strong>Order notifications</strong><small class="cx-copy">Show order and delivery updates in your inbox.</small></span></label>
        <button class="cx-button" type="submit">Save settings</button>
    </section></form>
    <section class="cx-card"><span class="cx-eyebrow">Account</span><div class="cx-row"><span class="cx-icon">✉</span><span class="cx-row-copy"><strong>{{ auth()->user()->email }}</strong><small>Login email</small></span></div><a class="cx-button secondary" style="margin-top:14px" href="{{ route('profile.edit') }}">Change password</a></section>
</main>@include('customer.partials.bottom-nav',['active'=>'profile'])</body></html>
