<!DOCTYPE html>
<html lang="en" data-rider-theme-root data-rider-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Profile · MotoBook Rider</title>
    @vite(['resources/css/app.css', 'resources/css/pages/rider-profile.css', 'resources/css/pages/rider-theme.css', 'resources/css/pages/rider-activity.css', 'resources/js/rider-theme.js'])
</head>
<body class="rider-profile-shell rider-profile-page" data-rider-page="profile">
    <header class="rider-profile-header">
        <a href="{{ route('rider.dashboard') }}" aria-label="Back to dashboard">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
        </a>
        <h1>Profile</h1>
        <x-rider.theme-toggle />
    </header>

    <main class="rider-profile-content">
        @if (session('status'))
            <div class="rider-alert">
                {{ session('status') === 'password-updated' ? 'Password updated successfully.' : session('status') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="rider-error" role="alert">{{ $errors->first() }}</div>
        @endif

        <section class="rider-profile-hero">
            <div class="rider-profile-large-avatar">
                @if ($user->avatar)
                    <img src="{{ asset('storage/'.$user->avatar) }}" alt="{{ $user->name }}">
                @else
                    {{ strtoupper(substr($user->name, 0, 1)) }}
                @endif
            </div>
            <div class="rider-profile-summary">
                <h2>{{ $user->name }}</h2>
                <p>Rider</p>
                <div class="rider-profile-rating" aria-label="Rating {{ number_format($rider?->rating ?? 0, 1) }} from {{ $rider?->total_deliveries ?? 0 }} deliveries">
                    <span aria-hidden="true">★ ★</span>
                    <strong>{{ number_format($rider?->rating ?? 0, 1) }}</strong>
                    <span class="rider-profile-rating-count">({{ $rider?->total_deliveries ?? 0 }})</span>
                </div>
                <a class="rider-profile-view-link" href="#rider-information">View Profile</a>
            </div>
        </section>

        <form method="POST" action="{{ route('rider.profile.update') }}" enctype="multipart/form-data" class="rider-profile-options" id="profile-settings">
            @csrf
            @method('PATCH')
            <details class="rider-profile-option" id="rider-information">
                <summary>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20v-1.5A3.5 3.5 0 0 1 7.5 15h9a3.5 3.5 0 0 1 3.5 3.5V20M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/></svg>
                    <span>Rider Information</span>
                    <span class="rider-profile-chevron" aria-hidden="true">›</span>
                </summary>
                <div class="rider-profile-option-content">
                    <label for="rider-name">Name</label>
                    <input id="rider-name" name="name" value="{{ old('name', $user->name) }}" required autocomplete="name">
                    <label for="rider-phone">Phone</label>
                    <input id="rider-phone" name="phone" value="{{ old('phone', $user->phone) }}" autocomplete="tel">
                </div>
            </details>

            <details class="rider-profile-option" id="vehicle-information">
                <summary>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 11 7 6h10l2 5M3 11h18v7H3zM6 18v2m12-2v2"/><circle cx="7" cy="15" r="1"/><circle cx="17" cy="15" r="1"/></svg>
                    <span>Vehicle Information</span>
                    <span class="rider-profile-chevron" aria-hidden="true">›</span>
                </summary>
                <div class="rider-profile-option-content">
                    <label for="vehicle-type">Vehicle type</label>
                    <select id="vehicle-type" name="vehicle_type" required>
                        <option value="motorcycle" @selected(old('vehicle_type', $rider?->vehicle_type ?? '') === 'motorcycle')>Motorcycle</option>
                        <option value="bicycle" @selected(old('vehicle_type', $rider?->vehicle_type ?? '') === 'bicycle')>Bicycle</option>
                        <option value="car" @selected(old('vehicle_type', $rider?->vehicle_type ?? '') === 'car')>Car</option>
                    </select>
                    <label for="plate-number">Plate number</label>
                    <input id="plate-number" name="plate_number" value="{{ old('plate_number', $rider?->plate_number) }}">
                    <label for="license-number">License number</label>
                    <input id="license-number" name="license_number" value="{{ old('license_number', $rider?->license_number) }}">
                </div>
            </details>

            <details class="rider-profile-option" id="rider-documents">
                <summary>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3h10v3h3v15H4V6h3zM9 3v5h6V3M8 14l2.5 2.5L16 11"/></svg>
                    <span>Documents</span>
                    <span class="rider-profile-option-status {{ $rider?->is_verified ? 'verified' : '' }}">{{ $rider?->is_verified ? 'Verified' : 'Pending' }}</span>
                    <span class="rider-profile-chevron" aria-hidden="true">›</span>
                </summary>
                <div class="rider-profile-option-content">
                    <p class="rider-profile-document">
                        {{ $rider?->is_verified ? 'Your rider documents are verified.' : 'Your rider documents are pending verification.' }}
                    </p>
                    <label for="license-photo">Update license photo</label>
                    <input id="license-photo" type="file" name="license_photo" accept="image/*">
                </div>
            </details>

            <button class="rider-profile-save" type="submit">Save Profile Changes</button>
        </form>

        <nav class="rider-profile-account-options" aria-label="Account options">
            <a class="rider-profile-option-link" href="{{ route('rider.wallet') }}">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7.5A2.5 2.5 0 0 1 5.5 5H21v15H5.5A2.5 2.5 0 0 1 3 17.5zM3 8h18M16 13h5"/></svg>
                <span>Wallet &amp; Earnings</span>
                <span class="rider-profile-chevron" aria-hidden="true">›</span>
            </a>
            <details class="rider-profile-option rider-password-option">
                <summary>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3m-4 4v3"/></svg>
                    <span>Change Password</span>
                    <span class="rider-profile-chevron" aria-hidden="true">›</span>
                </summary>
                <form method="POST" action="{{ route('password.update') }}" class="rider-profile-option-content">
                    @csrf
                    @method('PUT')
                    <label for="current-password">Current password</label>
                    <input id="current-password" type="password" name="current_password" required autocomplete="current-password">
                    <label for="new-password">New password</label>
                    <input id="new-password" type="password" name="password" required autocomplete="new-password">
                    <label for="confirm-password">Confirm new password</label>
                    <input id="confirm-password" type="password" name="password_confirmation" required autocomplete="new-password">
                    <button class="rider-profile-save" type="submit">Update Password</button>
                </form>
            </details>
        </nav>

        <form method="POST" action="{{ route('logout') }}" class="rider-profile-logout">
            @csrf
            <button type="submit">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 4H5v16h5m4-4 4-4-4-4m4 4H9"/></svg>
                Logout
            </button>
        </form>
    </main>

    <x-rider.bottom-nav :rider="$rider" :can-go-online="$canGoOnline" :has-active-delivery="$hasActiveDelivery" active="profile" />
</body>
</html>
