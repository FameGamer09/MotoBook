@extends('layouts.auth', ['pageCss' => 'addresses'])
@section('title', 'Saved Addresses · MotoBook')
@section('content')

    <button type="button" class="back-btn" onclick="window.location='{{ route('customer.profile') }}'">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
    </button>

    <h1 class="auth-title">Saved Addresses</h1>
    <p class="auth-subtitle">Manage where you'd like your orders delivered.</p>

    @if (session('status'))
        <div class="status-banner">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="error-banner">{{ $errors->first() }}</div>
    @endif

    @foreach ($addresses as $address)
        <div class="address-card {{ $address->is_default ? 'is-default' : '' }}">
            <div class="address-icon">📍</div>
            <div class="flex-1">
                <div class="address-label-row">
                    <span class="address-label">{{ $address->label }}</span>
                    @if ($address->is_default)
                        <span class="address-default-tag">Default</span>
                    @endif
                </div>
                <div class="address-line">
                    {{ $address->address_line }}
                    @if ($address->landmark)<br>{{ $address->landmark }}@endif
                </div>
                <form method="POST" action="{{ route('customer.addresses.location', $address) }}" class="mt-3">
                    @csrf @method('PATCH')
                    <div class="auth-field">
                        <label for="latitude-{{ $address->id }}" class="auth-label">Delivery latitude</label>
                        <input type="number" step="0.0000001" id="latitude-{{ $address->id }}" name="latitude" class="auth-input auth-input-plain" value="{{ $address->latitude }}" placeholder="e.g. 11.5850" required>
                    </div>
                    <div class="auth-field">
                        <label for="longitude-{{ $address->id }}" class="auth-label">Delivery longitude</label>
                        <input type="number" step="0.0000001" id="longitude-{{ $address->id }}" name="longitude" class="auth-input auth-input-plain" value="{{ $address->longitude }}" placeholder="e.g. 122.7511" required>
                    </div>
                    <div data-location-picker data-latitude-target="latitude-{{ $address->id }}" data-longitude-target="longitude-{{ $address->id }}">
                        <button type="button" class="btn-secondary">Use my current location</button>
                        <p data-location-status role="status" aria-live="polite"></p>
                    </div>
                    <button type="submit" class="btn-secondary mt-2">Save delivery location</button>
                </form>
                <div class="address-actions">
                    @unless ($address->is_default)
                        <form method="POST" action="{{ route('customer.addresses.default', $address) }}">
                            @csrf @method('PATCH')
                            <button type="submit">Set as Default</button>
                        </form>
                    @endunless
                    <form method="POST" action="{{ route('customer.addresses.destroy', $address) }}" onsubmit="return confirm('Remove this address?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="danger">Remove</button>
                    </form>
                </div>
            </div>
        </div>
    @endforeach

    <p class="auth-label mt-6 mb-3">Add a New Address</p>
    <p class="auth-subtitle">Set a delivery pin so riders can find you. Use your current location or enter coordinates from a map.</p>
    <form method="POST" action="{{ route('customer.addresses.store') }}">
        @csrf
        <div class="auth-field">
            <label for="label" class="auth-label">Label</label>
            <input type="text" id="label" name="label" class="auth-input auth-input-plain" placeholder="e.g. Home, Work" required>
        </div>
        <div class="auth-field">
            <label for="address_line" class="auth-label">Address</label>
            <textarea id="address_line" name="address_line" rows="2" class="auth-input auth-input-plain" placeholder="Full address" required></textarea>
        </div>
        <div class="auth-field">
            <label for="landmark" class="auth-label">Landmark (optional)</label>
            <input type="text" id="landmark" name="landmark" class="auth-input auth-input-plain" placeholder="e.g. Near the plaza">
        </div>
        <div class="auth-field">
            <label for="new-latitude" class="auth-label">Delivery latitude</label>
            <input type="number" step="0.0000001" id="new-latitude" name="latitude" class="auth-input auth-input-plain" value="{{ old('latitude') }}" placeholder="e.g. 11.5850" required>
        </div>
        <div class="auth-field">
            <label for="new-longitude" class="auth-label">Delivery longitude</label>
            <input type="number" step="0.0000001" id="new-longitude" name="longitude" class="auth-input auth-input-plain" value="{{ old('longitude') }}" placeholder="e.g. 122.7511" required>
        </div>
        <div data-location-picker data-latitude-target="new-latitude" data-longitude-target="new-longitude">
            <button type="button" class="btn-secondary">Use my current location</button>
            <p data-location-status role="status" aria-live="polite"></p>
        </div>
        <button type="submit" class="btn-primary">Add Address</button>
    </form>

    @include('components.location-picker-script')
@endsection
