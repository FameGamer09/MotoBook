<x-guest-layout>
    <section class="login-card" aria-labelledby="login-title">
        <div class="login-brand-mark" aria-hidden="true">
            <svg viewBox="0 0 48 48" fill="none"><path d="M7 31h5l4-9h11l5 9h4" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="33" r="5" stroke="currentColor" stroke-width="2.5"/><circle cx="35" cy="33" r="5" stroke="currentColor" stroke-width="2.5"/><path d="m19 22 4-7h7l4 7M25 15l-3-4h-4" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M22 18h9" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg>
        </div>
        <div class="login-brand-name">MotoBook</div>
        <h1 id="login-title" class="login-title">Log In</h1>
        <p class="login-subtitle">Welcome back! Please log in<br class="desktop-break"> to your account.</p>

        <x-auth-session-status class="login-status" :status="session('status')" />

        <form method="POST" action="{{ route('login') }}" class="login-form">
            @csrf
            <div class="login-field">
                <label for="email">Email or Phone Number</label>
                <div class="login-input-wrap">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 21v-2a6 6 0 0 0-12 0v2M14 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/></svg>
                    <input id="email" name="email" type="text" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="Enter your Email or Phone Number" />
                </div>
                <x-input-error :messages="$errors->get('email')" class="login-error" />
            </div>

            <div class="login-field">
                <label for="password">Password</label>
                <div class="login-input-wrap">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 1 1 8 0v3"/></svg>
                    <input id="password" name="password" type="password" required autocomplete="current-password" placeholder="Enter your password" />
                    <button class="login-password-toggle" type="button" aria-label="Show password" aria-pressed="false" data-password-toggle>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 5.2A10.8 10.8 0 0 1 12 5c5 0 8.3 4.2 9 7a9.8 9.8 0 0 1-2.2 3.8M6.2 6.2C3.9 7.6 2.4 9.7 2 12c.7 2.8 4 7 10 7 1 0 2-.2 2.9-.5"/></svg>
                    </button>
                </div>
                <x-input-error :messages="$errors->get('password')" class="login-error" />
            </div>

            <div class="login-options">
                <label class="login-remember" for="remember">
                    <input id="remember" type="checkbox" name="remember" value="1">
                    <span>Remember me</span>
                </label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}">Forgot Password?</a>
                @endif
            </div>

            <button class="login-submit" type="submit">Log In</button>
        </form>

        <div class="login-divider"><span>or continue with</span></div>
        <div class="login-social" aria-label="Social sign-in options">
            <button type="button" class="login-social-button" data-social-provider="Google" aria-label="Continue with Google">
                <svg class="google-mark" viewBox="0 0 48 48" aria-hidden="true"><path fill="#4285F4" d="M43.6 24.5c0-1.4-.1-2.8-.4-4.1H24v7.8h11a9.4 9.4 0 0 1-4.1 6.2v5.1h6.6c3.9-3.6 6.1-8.8 6.1-15Z"/><path fill="#34A853" d="M24 44c5.5 0 10.1-1.8 13.5-4.8l-6.6-5.1c-1.8 1.2-4.1 2-6.9 2-5.3 0-9.8-3.6-11.4-8.4H5.8v5.3A20 20 0 0 0 24 44Z"/><path fill="#FBBC05" d="M12.6 27.7a12 12 0 0 1 0-7.4V15H5.8a20 20 0 0 0 0 18l6.8-5.3Z"/><path fill="#EA4335" d="M24 11.9c3 0 5.7 1 7.8 3.1l5.9-5.9A19.7 19.7 0 0 0 24 4 20 20 0 0 0 5.8 15l6.8 5.3c1.6-4.8 6.1-8.4 11.4-8.4Z"/></svg>
                <span>Google</span>
            </button>
            <button type="button" class="login-social-button" data-social-provider="Facebook" aria-label="Continue with Facebook">
                <svg class="facebook-mark" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M13.6 21v-8.2h2.8l.4-3.2h-3.2v-2c0-.9.3-1.6 1.6-1.6H17V3.1c-.3 0-1.3-.1-2.5-.1-2.5 0-4.2 1.5-4.2 4.3v2.3H7.5v3.2h2.8V21h3.3Z"/></svg>
                <span>Facebook</span>
            </button>
            <button type="button" class="login-social-button" data-social-provider="Apple" aria-label="Continue with Apple">
                <svg class="apple-mark" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M16.5 12.8c0-2 1.6-3 1.7-3.1a3.7 3.7 0 0 0-2.9-1.6c-1.2-.1-2.3.7-2.9.7s-1.5-.7-2.5-.7a3.9 3.9 0 0 0-3.3 2c-1.4 2.4-.4 6 1 8 .7 1 1.4 2 2.4 2s1.3-.6 2.4-.6 1.4.6 2.5.6 1.8-1 2.4-2a9 9 0 0 0 1.1-2.2 3.5 3.5 0 0 1-2-3.1ZM14.6 6.8A3.7 3.7 0 0 0 15.5 4a3.8 3.8 0 0 0-2.5 1.3 3.4 3.4 0 0 0-.9 2.6 3.2 3.2 0 0 0 2.5-1.1Z"/></svg>
                <span>Apple</span>
            </button>
        </div>
        <p class="login-social-note" data-social-note role="status" aria-live="polite" hidden></p>
        <a class="login-phone-link" href="{{ route('login.phone') }}">
            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="6" y="2" width="12" height="20" rx="2"/><path d="M11 18h2"/></svg>
            Sign in with a one-time phone code
        </a>
        <p class="login-signup">Don’t have an account? <a href="{{ route('register') }}">Sign Up</a></p>

        <p class="login-unified">One unified sign-in for every MotoBook panel. If you get stuck, contact the Super Admin or <a href="{{ url('/admin/login.php') }}">open the management sign-in</a>.</p>
    </section>

    <script>
        document.querySelector('[data-password-toggle]')?.addEventListener('click', function () {
            const password = document.getElementById('password');
            const visible = password.type === 'password';
            password.type = visible ? 'text' : 'password';
            this.setAttribute('aria-pressed', String(visible));
            this.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
        });
        document.querySelectorAll('[data-social-provider]').forEach(function (button) {
            button.addEventListener('click', function () {
                const note = document.querySelector('[data-social-note]');
                note.textContent = this.dataset.socialProvider + ' sign-in is not connected yet. Use email or phone sign-in, or contact your administrator to configure it.';
                note.hidden = false;
            });
        });
    </script>
</x-guest-layout>
