<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="color-scheme" content="light">
        <meta name="theme-color" content="#00BFFF">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Log In &middot; {{ config('app.name', 'Motobook') }}</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js" defer></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (window.lucide) {
                    window.lucide.createIcons({ attrs: { 'stroke-width': 1.75, class: 'inline-block shrink-0' } });
                }
                var p = document.getElementById('password');
                var t = document.getElementById('toggle-password');
                if (p && t) {
                    t.addEventListener('click', function () {
                        var isText = p.getAttribute('type') === 'text';
                        p.setAttribute('type', isText ? 'password' : 'text');
                        var target = t.querySelector('[data-lucide]');
                        if (target) {
                            target.setAttribute('data-lucide', isText ? 'eye-off' : 'eye');
                        }
                        if (window.lucide && window.lucide.createIcons) {
                            window.lucide.createIcons({ attrs: { 'stroke-width': 1.75, class: 'inline-block shrink-0' } });
                        }
                        t.setAttribute('aria-pressed', String(!isText));
                    });
                }
            });
        </script>
        <style>
            :root{
                --mb-blue-50:#eff9ff;
                --mb-blue-100:#dff3ff;
                --mb-blue-200:#b9e6ff;
                --mb-blue-300:#7fd2ff;
                --mb-blue-400:#39b9ff;
                --mb-blue-500:#00BFFF;
                --mb-blue-600:#009ce0;
                --mb-blue-700:#007cbf;
                --mb-blue-800:#06618e;
                --mb-blue-900:#0d4f75;
                --mb-slate-50:#f8fafc;
                --mb-slate-100:#f1f5f9;
                --mb-slate-200:#e2e8f0;
                --mb-slate-300:#cbd5e1;
                --mb-slate-400:#94a3b8;
                --mb-slate-500:#64748b;
                --mb-slate-600:#475569;
                --mb-slate-700:#334155;
                --mb-slate-800:#1e293b;
                --mb-slate-900:#0f172a;
                --mb-red-500:#ef4444;
                --mb-red-50:#fef2f2;
                --mb-white:#ffffff;
                --mb-black:#0b1220;
                --mb-shadow-sm:0 1px 2px rgba(15,23,42,.06),0 1px 3px rgba(15,23,42,.08);
                --mb-shadow-md:0 4px 12px rgba(15,23,42,.08),0 2px 4px rgba(15,23,42,.06);
                --mb-shadow-lg:0 20px 45px -15px rgba(0,191,255,.35),0 10px 25px -10px rgba(15,23,42,.12);
                --mb-radius-xs:8px;
                --mb-radius-sm:10px;
                --mb-radius-md:14px;
                --mb-radius-lg:18px;
                --mb-radius-xl:24px;
            }
            *{box-sizing:border-box}
            html,body{margin:0;padding:0;height:100%;min-height:100%;overflow:auto}
            body{
                font-family:'Inter',system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;
                color:var(--mb-slate-900);
                background:
                    radial-gradient(1200px 600px at 90% -10%, rgba(0,191,255,.14), transparent 60%),
                    radial-gradient(900px 500px at -10% 110%, rgba(0,191,255,.12), transparent 60%),
                    linear-gradient(180deg,#ffffff 0%,#f4fbff 55%,#eaf7ff 100%);
                -webkit-font-smoothing:antialiased;
                text-rendering:optimizeLegibility;
                padding:max(12px, env(safe-area-inset-top)) max(16px, env(safe-area-inset-right)) max(12px, env(safe-area-inset-bottom)) max(16px, env(safe-area-inset-left));
            }
            @supports(height: 100dvh){
                html,body{height:100dvh;min-height:100dvh}
                .page{min-height:calc(100dvh - 24px)}
            }
            .page{
                width:100%;
                height:calc(100vh - 24px);
                min-height:calc(100vh - 24px);
                max-width:1180px;
                max-height:calc(100vh - 24px);
                margin:0 auto;
                padding:0;
                display:grid;
                grid-template-columns:1.05fr .95fr;
                gap:28px;
                align-items:stretch;
            }
            .hero{
                position:relative;
                border-radius:var(--mb-radius-xl);
                overflow:hidden;
                background:
                    radial-gradient(520px 320px at 20% 0%, rgba(255,255,255,.55), transparent 60%),
                    linear-gradient(160deg,#e6f7ff 0%, #c9eeff 55%, #a0ddff 100%);
                box-shadow:var(--mb-shadow-md);
                padding:22px 24px 18px;
                display:flex;
                flex-direction:column;
                justify-content:space-between;
                gap:10px;
                border:1px solid rgba(0,191,255,.22);
                min-height:0;
            }
            .hero::after{
                content:"";
                position:absolute;
                width:320px;height:320px;
                right:-80px;bottom:-100px;
                background:radial-gradient(closest-side, rgba(255,255,255,.7), transparent 70%);
                filter:blur(2px);
                pointer-events:none;
            }
            .topbar{
                display:flex;align-items:center;justify-content:space-between;
                position:relative;z-index:1;
                min-height:0;
            }
            .back{
                width:38px;height:38px;border-radius:11px;
                display:inline-flex;align-items:center;justify-content:center;
                background:rgba(255,255,255,.72);
                border:1px solid rgba(0,191,255,.25);
                color:var(--mb-blue-800);
                box-shadow:var(--mb-shadow-sm);
                transition:transform .1s ease, background .15s ease;
                text-decoration:none;
                flex:0 0 38px;
            }
            .back:hover{background:#fff;transform:translateX(-2px)}
            .brand{display:flex;align-items:center;gap:10px;position:relative;z-index:1}
            .brand-ico{
                width:36px;height:36px;border-radius:11px;
                background:linear-gradient(135deg,var(--mb-blue-500),var(--mb-blue-700));
                color:#fff;display:inline-flex;align-items:center;justify-content:center;
                box-shadow:0 8px 20px -6px rgba(0,191,255,.55);
                flex:0 0 36px;
            }
            .brand-ico svg{width:20px;height:20px}
            .brand-name{font-weight:800;font-size:15px;color:var(--mb-blue-900);letter-spacing:.2px}
            .hero-copy{position:relative;z-index:1;padding:4px 0 0;min-height:0}
            .hero-copy h1{
                margin:0 0 6px;font-size:44px;line-height:1.05;letter-spacing:-.02em;
                font-weight:800;color:var(--mb-black);
            }
            .hero-copy p{
                margin:0;font-size:14.5px;line-height:1.6;color:var(--mb-slate-700);max-width:100%
            }
            .hero-copy p strong{color:var(--mb-blue-800);font-weight:700}
            .scooter-wrap{
                position:relative;z-index:1;
                display:flex;align-items:center;justify-content:center;
                padding:0;
                min-height:0;
                flex:1 1 auto;
            }
            .scooter{
                width:auto;
                height:clamp(150px, 32vh, 300px);
                max-width:100%;
                object-fit:contain;
                filter:drop-shadow(0 18px 26px rgba(0,124,191,.22));
                transform:translateY(0);
                animation:float 5.4s ease-in-out infinite;
            }
            @keyframes float{
                0%,100%{transform:translateY(0) rotate(-1deg)}
                50%{transform:translateY(-8px) rotate(1deg)}
            }
            .features{
                position:relative;z-index:1;
                display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;
                min-height:0;
            }
            .feat{
                display:flex;gap:10px;align-items:flex-start;
                background:rgba(255,255,255,.78);
                border:1px solid rgba(0,191,255,.22);
                border-radius:var(--mb-radius-md);
                padding:10px 11px;
                box-shadow:var(--mb-shadow-sm);
                backdrop-filter:blur(4px);
                min-height:0;
            }
            .feat-ic{
                width:32px;height:32px;border-radius:9px;flex:0 0 32px;
                display:inline-flex;align-items:center;justify-content:center;
                color:#fff;background:linear-gradient(135deg,var(--mb-blue-500),var(--mb-blue-700));
                box-shadow:0 6px 14px -4px rgba(0,191,255,.55);
            }
            .feat-ic svg{width:16px;height:16px}
            .feat h4{margin:0 0 2px;font-size:12.5px;font-weight:700;color:var(--mb-blue-900);line-height:1.2}
            .feat p{margin:0;font-size:11.5px;line-height:1.45;color:var(--mb-slate-700)}

            .card{
                width:100%;max-width:440px;justify-self:center;
                align-self:center;
                background:#fff;
                border-radius:var(--mb-radius-xl);
                padding:24px 26px 18px;
                box-shadow:var(--mb-shadow-lg);
                border:1px solid rgba(203,213,225,.5);
                max-height:100%;
                overflow:auto;
            }
            .card h2{
                margin:0;font-size:32px;line-height:1.05;font-weight:800;letter-spacing:-.02em;
                color:var(--mb-slate-900);text-align:center;
            }
            .card .welcome{
                margin:8px 0 0;text-align:center;
                color:var(--mb-slate-600);font-size:14px;line-height:1.5;
            }
            .card .welcome span{display:block}

            .chips{
                display:flex;flex-wrap:wrap;justify-content:center;
                gap:6px;margin:14px 0 12px;
            }
            .chip{
                font-size:11.5px;padding:4px 9px;border-radius:999px;
                background:var(--mb-blue-50);color:var(--mb-blue-800);
                border:1px solid rgba(0,191,255,.28);
                font-weight:600;display:inline-flex;align-items:center;gap:4px;
            }
            .chip svg{width:11px;height:11px}

            .alert{
                border-radius:var(--mb-radius-md);
                padding:10px 12px;display:flex;gap:10px;align-items:flex-start;
                margin:0 0 12px;font-size:13px;line-height:1.5;
            }
            .alert svg{width:18px;height:18px;margin-top:1px;flex:0 0 18px}
            .alert-error{background:var(--mb-red-50);color:#b91c1c;border:1px solid rgba(239,68,68,.22)}
            .alert-ok{background:#efffed;color:#15803d;border:1px solid rgba(34,197,94,.25)}

            .field{margin-bottom:11px}
            .field label{
                display:block;font-size:13px;font-weight:700;color:var(--mb-slate-800);
                margin:0 0 6px;
            }
            .input-group{
                position:relative;display:flex;align-items:center;
                background:#fff;
                border:1.5px solid var(--mb-slate-300);
                border-radius:14px;
                box-shadow:var(--mb-shadow-sm);
                transition:border-color .15s ease,box-shadow .15s ease, transform .05s ease;
            }
            .input-group:hover{border-color:#9fd8f4}
            .input-group:focus-within{
                border-color:var(--mb-blue-500);
                box-shadow:0 0 0 4px rgba(0,191,255,.16), 0 4px 14px -4px rgba(0,191,255,.25);
            }
            .input-ico{
                flex:0 0 42px;height:44px;display:inline-flex;align-items:center;justify-content:center;
                color:var(--mb-slate-500);
            }
            .input-ico svg{width:18px;height:18px}
            .input{
                width:100%;height:44px;border:none;background:transparent;outline:none;
                font:inherit;color:var(--mb-slate-900);font-size:14.5px;
                padding:0 10px 0 0;
            }
            .input::placeholder{color:var(--mb-slate-400);font-weight:500}
            .input-action{
                flex:0 0 42px;height:44px;display:inline-flex;align-items:center;justify-content:center;
                background:transparent;border:none;cursor:pointer;color:var(--mb-slate-500);
                border-top-right-radius:14px;
                border-bottom-right-radius:14px;
                transition:background .15s ease, color .15s ease;
            }
            .input-action:hover{background:var(--mb-blue-50);color:var(--mb-blue-700)}
            .input-action svg{width:18px;height:18px}

            .row-between{display:flex;align-items:center;justify-content:space-between;margin:-2px 0 14px;gap:10px;flex-wrap:wrap}
            .remember{display:inline-flex;align-items:center;gap:7px;color:var(--mb-slate-700);font-size:12.5px;cursor:pointer}
            .remember input{
                width:15px;height:15px;accent-color:var(--mb-blue-500);
                border-radius:4px;flex:0 0 15px;
            }
            .forgot-row{display:flex;align-items:center;justify-content:flex-end}
            .forgot-row a{
                text-decoration:none;color:var(--mb-blue-600);font-size:12.5px;font-weight:700;
                transition:color .15s ease;
            }
            .forgot-row a:hover{color:var(--mb-blue-800)}

            .btn-primary{
                width:100%;border:none;cursor:pointer;
                padding:12px 14px;border-radius:14px;
                color:#fff;font-weight:700;font-size:15px;letter-spacing:.2px;
                background:linear-gradient(180deg,var(--mb-blue-500),var(--mb-blue-600));
                box-shadow:0 12px 24px -10px rgba(0,191,255,.55), 0 2px 4px rgba(0,191,255,.25);
                transition:transform .08s ease, filter .15s ease, box-shadow .2s ease;
                display:inline-flex;align-items:center;justify-content:center;gap:8px;
            }
            .btn-primary:hover{filter:brightness(1.04);box-shadow:0 16px 30px -10px rgba(0,191,255,.55)}
            .btn-primary:active{transform:translateY(1px)}

            .divider{
                display:flex;align-items:center;gap:12px;margin:16px 0 12px;color:var(--mb-slate-500);
                font-size:12.5px;font-weight:600;
            }
            .divider::before,.divider::after{
                content:"";flex:1;height:1px;background:var(--mb-slate-200);
            }
            .socials{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-bottom:12px}
            .social{
                background:#fff;border:1.5px solid var(--mb-slate-200);
                border-radius:14px;height:44px;cursor:pointer;
                display:inline-flex;align-items:center;justify-content:center;
                transition:transform .08s ease, border-color .15s ease, background .15s ease;
                color:var(--mb-slate-700);
            }
            .social:hover{transform:translateY(-1px);border-color:var(--mb-blue-500);background:var(--mb-blue-50)}
            .social svg{width:20px;height:20px}
            .social.google svg{color:#4285F4}
            .social.fb svg{color:#1877F2}
            .social.apple svg{color:#0b1220}

            .signup{
                margin:0;text-align:center;font-size:13px;color:var(--mb-slate-700);
            }
            .signup a{
                color:var(--mb-blue-600);font-weight:800;text-decoration:none;
                transition:color .15s ease;
            }
            .signup a:hover{color:var(--mb-blue-800)}
            .foot{
                margin-top:8px;text-align:center;font-size:11.5px;color:var(--mb-slate-500);line-height:1.5
            }
            .foot a{color:var(--mb-blue-700);font-weight:700;text-decoration:none}

            @media (max-width: 1024px) and (min-height: 860px){
                .page{height:auto;min-height:calc(100vh - 24px);grid-template-columns:1fr;gap:22px;padding:0;max-height:none}
                .hero{padding:24px 24px 18px}
                .scooter{height:min(260px, 34vh)}
                .hero-copy h1{font-size:40px}
            }
            @media (max-width: 1024px) and (max-height: 859px){
                .page{grid-template-columns:1fr;gap:18px;height:auto;min-height:calc(100vh - 24px);max-height:none}
                .hero{padding:20px 20px 14px}
                .scooter{height:min(180px, 26vh)}
                .hero-copy h1{font-size:34px}
                .features{grid-template-columns:1fr;gap:6px}
                .card{align-self:stretch;padding:22px 22px 16px}
            }
            @media (max-width: 640px){
                body{padding-top:max(10px,env(safe-area-inset-top));padding-right:max(12px,env(safe-area-inset-right));padding-bottom:max(10px,env(safe-area-inset-bottom));padding-left:max(12px,env(safe-area-inset-left))}
                .page{padding:0;height:auto;min-height:calc(100dvh - 20px);max-height:none;gap:14px}
                .hero{border-radius:var(--mb-radius-lg);padding:16px 16px 12px}
                .back{width:34px;height:34px;border-radius:10px;flex:0 0 34px}
                .brand-ico{width:32px;height:32px;border-radius:10px;flex:0 0 32px}
                .hero-copy h1{font-size:28px;letter-spacing:-.01em;margin-top:6px}
                .hero-copy p{font-size:13.5px}
                .scooter-wrap{padding:2px 0 0}
                .scooter{height:min(150px, 24vh)}
                .features{grid-template-columns:1fr;gap:6px}
                .card{
                    margin-top:0;border-radius:var(--mb-radius-lg);
                    padding:22px 18px 16px;
                    box-shadow:0 24px 50px -18px rgba(15,23,42,.18);
                }
                .card h2{font-size:28px}
                .card .welcome{font-size:13.5px}
                .chips{margin:12px 0 10px}
                .input, .input-ico, .input-action{height:44px}
                .input-group{border-radius:14px}
                .btn-primary{padding:12px 14px;font-size:14.5px;border-radius:14px}
            }
            @media (min-width: 1440px){
                .page{max-width:1280px;height:calc(100vh - 32px);min-height:calc(100vh - 32px);max-height:calc(100vh - 32px);gap:34px}
                .hero{padding:30px 32px 24px}
                .hero-copy h1{font-size:54px}
                .scooter{height:clamp(200px, 34vh, 360px)}
                .card{padding:30px 30px 22px;max-width:470px}
            }
            @media (max-height: 719px){
                .hero{padding:16px 18px 14px}
                .hero-copy h1{font-size:34px;margin:0 0 4px}
                .hero-copy p{font-size:13px}
                .scooter{height:clamp(130px, 28vh, 220px)}
                .features{gap:6px}
                .feat{padding:8px 9px}
                .feat h4{font-size:11.5px}
                .feat p{font-size:11px}
                .card{padding:18px 20px 14px}
                .card h2{font-size:26px}
                .card .welcome{font-size:13px;margin:6px 0 0}
                .chips{margin:10px 0 8px}
                .field{margin-bottom:9px}
                .row-between{margin:-1px 0 10px}
                .divider{margin:12px 0 10px}
                .socials{margin-bottom:8px}
                .foot{margin-top:6px}
            }
        </style>
    </head>
    <body>
        <main class="page">
            <section class="hero" aria-label="Motobook POS brand preview">
                <div>
                    <div class="topbar">
                        <a class="back" href="#" aria-label="Back" onclick="history.length>1?history.back():window.location.assign('{{ route('login') }}');return false;">
                            <i data-lucide="chevron-left"></i>
                        </a>
                        <div class="brand" aria-label="Motobook brand">
                            <span class="brand-ico" aria-hidden="true"><i data-lucide="bike"></i></span>
                            <span class="brand-name">{{ config('app.name', 'Motobook') }}</span>
                        </div>
                    </div>
                    <div class="hero-copy">
                        <h1>Log In</h1>
                        <p>Welcome back! Please <strong>sign in</strong> to your Motobook POS &amp; Inventory account
                        to run the cash register, manage products, and review transaction history.</p>
                    </div>
                </div>

                <div class="scooter-wrap" aria-hidden="true">
                    <img class="scooter" alt="Blue delivery rider on a scooter"
                         src="{{ asset('images/login-scooter.svg') }}"
                         loading="eager" decoding="async">
                </div>

                <div class="features" aria-label="What you can do after signing in">
                    <div class="feat">
                        <span class="feat-ic" aria-hidden="true"><i data-lucide="scan-line"></i></span>
                        <div>
                            <h4>Cash Register</h4>
                            <p>Fast checkout with barcode + SKU search and receipt printing.</p>
                        </div>
                    </div>
                    <div class="feat">
                        <span class="feat-ic" aria-hidden="true"><i data-lucide="package"></i></span>
                        <div>
                            <h4>Inventory</h4>
                            <p>Product catalog, low-stock alerts and stock adjustments.</p>
                        </div>
                    </div>
                    <div class="feat">
                        <span class="feat-ic" aria-hidden="true"><i data-lucide="receipt"></i></span>
                        <div>
                            <h4>Transactions</h4>
                            <p>Daily sales, refunds and shift reconciliation reports.</p>
                        </div>
                    </div>
                    <div class="feat">
                        <span class="feat-ic" aria-hidden="true"><i data-lucide="pie-chart"></i></span>
                        <div>
                            <h4>Dashboard</h4>
                            <p>Revenue overview, top products and performance trends.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="card" aria-label="Sign in form">
                <h2>Log In</h2>
                <p class="welcome">
                    <span>Welcome back! Please login</span>
                    <span>to your account.</span>
                </p>

                <div class="chips" aria-label="Supported account types">
                    <span class="chip"><i data-lucide="shield"></i>Admin</span>
                    <span class="chip"><i data-lucide="scan-line"></i>Cashier</span>
                    <span class="chip"><i data-lucide="package"></i>Inventory</span>
                </div>

                @if (session('status'))
                    <div class="alert alert-ok" role="status">
                        <i data-lucide="check-circle-2"></i>
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-error" role="alert">
                        <i data-lucide="alert-circle"></i>
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" novalidate>
                    @csrf

                    <div class="field">
                        <label for="email">Email or Phone Number</label>
                        <div class="input-group">
                            <span class="input-ico" aria-hidden="true"><i data-lucide="user-2"></i></span>
                            <input class="input" id="email" name="email" type="text" inputmode="email"
                                   autocomplete="username" required autofocus
                                   placeholder="Enter your Email or Phone Number"
                                   value="{{ old('email') }}">
                        </div>
                    </div>

                    <div class="field">
                        <label for="password">Password</label>
                        <div class="input-group">
                            <span class="input-ico" aria-hidden="true"><i data-lucide="lock"></i></span>
                            <input class="input" id="password" name="password" type="password"
                                   autocomplete="current-password" required
                                   placeholder="Enter your password">
                            <button class="input-action" id="toggle-password" type="button"
                                    aria-label="Show or hide password" aria-pressed="false">
                                <i data-lucide="eye-off"></i>
                            </button>
                        </div>
                    </div>

                    <div class="row-between">
                        <label for="remember_me" class="remember">
                            <input id="remember_me" type="checkbox" name="remember">
                            Remember me
                        </label>
                        @if (Route::has('password.request'))
                            <div class="forgot-row">
                                <a href="{{ route('password.request') }}">Forget Password?</a>
                            </div>
                        @endif
                    </div>

                    <button type="submit" class="btn-primary" aria-label="Log in to Motobook POS">
                        Log In
                    </button>
                </form>

                <div class="divider" role="separator" aria-label="Other sign-in options">or continue with</div>

                <div class="socials" aria-label="Third-party sign in (coming soon)">
                    <button type="button" class="social google" aria-label="Continue with Google"
                            onclick="alert('Google sign-in will be enabled in a future release. Use your email and password.');">
                        <svg viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 33 29.4 37 24 37c-7.2 0-13-5.8-13-13s5.8-13 13-13c3.1 0 5.9 1.1 8.1 2.9l5.7-5.7C34.2 5.1 29.4 3 24 3 12.4 3 3 12.4 3 24s9.4 21 21 21c10.5 0 20-8 20.9-18.3.1-.6.1-1.2.1-1.8v-.5z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 16 18.9 13 24 13c3.1 0 5.9 1.1 8.1 2.9l5.7-5.7C34.2 5.1 29.4 3 24 3 16.3 3 9.7 7.1 6.3 14.7z"/><path fill="#4CAF50" d="M24 45c5.3 0 10.1-2 13.8-5.3l-6.4-5.3c-2 1.5-4.5 2.3-7.4 2.3-5.4 0-9.9-3.6-11.5-8.5l-6.5 5C9.5 40 16.1 45 24 45z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.3-2.3 4.2-4.2 5.4l6.4 5.3C40.4 36 45 30.7 45 24c0-1.3-.1-2.5-.4-3.5z"/></svg>
                    </button>
                    <button type="button" class="social fb" aria-label="Continue with Facebook"
                            onclick="alert('Facebook sign-in will be enabled in a future release. Use your email and password.');">
                        <i data-lucide="facebook"></i>
                    </button>
                    <button type="button" class="social apple" aria-label="Continue with Apple"
                            onclick="alert('Apple sign-in will be enabled in a future release. Use your email and password.');">
                        <i data-lucide="apple"></i>
                    </button>
                </div>

                <p class="signup">
                    Don't have an account?
                    <a href="#" onclick="alert('Self-sign up is disabled. Ask the Super Admin to create a POS account.');return false;">Sign Up</a>
                </p>
                <p class="foot">
                    @lang('All systems operational. Need help? Contact the on-duty shift supervisor or system admin.')
                </p>
            </section>
        </main>
    </body>
</html>
