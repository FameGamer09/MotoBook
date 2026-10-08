@extends('layouts.auth', ['pageCss' => 'merchant-onboarding'])
@section('title', 'Set Up Your Store · MotoBook')
@section('content')

    @if ($store)
        <h1 class="auth-title">Store Submitted</h1>
        <p class="auth-subtitle">Your store is waiting for administrator activation and verification. You can sign in here again after approval.</p>
        @if (session('status'))
            <div class="status-banner">{{ session('status') }}</div>
        @endif
        <div class="auth-field">
            <strong>{{ $store->name }}</strong>
            <p>{{ $store->address }}</p>
            <p>{{ $store->is_verified ? 'Store verified' : 'Store verification pending' }}</p>
            <p>{{ auth()->user()->status === 'active' ? 'Account active' : 'Account activation pending' }}</p>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn-primary">Log out</button>
        </form>
    @else
    <h1 class="auth-title">Set Up Your Store</h1>
    <p class="auth-subtitle">Tell customers about your store so they can start ordering.</p>

    @if ($errors->any())
        <div class="error-banner">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('merchant.onboarding') }}" enctype="multipart/form-data">
        @csrf

        <div class="auth-field">
            <label for="name" class="auth-label">Store Name</label>
            <div class="input-group">
                <input type="text" id="name" name="name" class="auth-input auth-input-plain" placeholder="e.g. Big Brew Coffee" value="{{ old('name') }}" required autofocus>
            </div>
        </div>

        <div class="auth-field">
            <label for="category" class="auth-label">Category</label>
            <div class="input-group">
                <select id="category" name="category" class="auth-input auth-input-plain" required>
                    <option value="" disabled {{ old('category') ? '' : 'selected' }}>Select a category</option>
                    @foreach (['Fast Food', 'Cafe', 'Restaurant', 'Bakery', 'Grocery', 'Pharmacy', 'Other'] as $cat)
                        <option value="{{ $cat }}" {{ old('category') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="auth-field">
            <label for="phone" class="auth-label">Store Phone Number</label>
            <div class="input-group">
                <input type="tel" id="phone" name="phone" class="auth-input auth-input-plain" placeholder="09XX XXX XXXX" value="{{ old('phone') }}" required>
            </div>
        </div>

        <div class="auth-field">
            <label for="address" class="auth-label">Store Address</label>
            <div class="input-group">
                <textarea id="address" name="address" rows="3" class="auth-input auth-input-plain" placeholder="Full store address" required>{{ old('address') }}</textarea>
            </div>
        </div>

        <p class="auth-subtitle">Add your store pin so nearby riders can be assigned. Use your current location or enter coordinates from a map.</p>
        <div class="auth-field">
            <label for="latitude" class="auth-label">Store latitude</label>
            <input type="number" step="0.0000001" id="latitude" name="latitude" class="auth-input auth-input-plain" value="{{ old('latitude') }}" placeholder="e.g. 11.5850" required>
        </div>
        <div class="auth-field">
            <label for="longitude" class="auth-label">Store longitude</label>
            <input type="number" step="0.0000001" id="longitude" name="longitude" class="auth-input auth-input-plain" value="{{ old('longitude') }}" placeholder="e.g. 122.7511" required>
        </div>
        <div data-location-picker data-latitude-target="latitude" data-longitude-target="longitude">
            <button type="button" class="btn-secondary">Use my current location</button>
            <p data-location-status role="status" aria-live="polite"></p>
        </div>

        <div class="auth-field">
            <label for="logo" class="auth-label">Store Logo (optional)</label>
            <input type="file" id="logo" name="logo" accept="image/*" class="w-full text-sm text-text-dim">
        </div>

        <button type="submit" class="btn-primary">Create Store</button>
    </form>
    @include('components.location-picker-script')
    @endif

@endsection
