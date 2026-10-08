@extends('layouts.auth', ['pageCss' => 'rider-onboarding'])
@section('title', 'Complete Your Rider Profile · MotoBook')
@section('content')

    @if ($rider->plate_number)
        <h1 class="auth-title">Rider Application Submitted</h1>
        <p class="auth-subtitle">Your documents are waiting for administrator activation and verification.</p>
        @if (session('status'))
            <div class="status-banner">{{ session('status') }}</div>
        @endif
        <div class="auth-field">
            <p>Vehicle: {{ ucfirst($rider->vehicle_type) }}</p>
            <p>Plate: {{ $rider->plate_number }}</p>
            <p>{{ $rider->is_verified ? 'Rider profile verified' : 'Rider verification pending' }}</p>
            <p>{{ auth()->user()->status === 'active' ? 'Account active' : 'Account activation pending' }}</p>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn-primary">Log out</button>
        </form>
    @else
    <h1 class="auth-title">Complete Your Rider Profile</h1>
    <p class="auth-subtitle">We need a few details before you can start accepting deliveries.</p>

    @if ($errors->any())
        <div class="error-banner">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('rider.onboarding') }}" enctype="multipart/form-data">
        @csrf

        <div class="auth-field">
            <label for="vehicle_type" class="auth-label">Vehicle Type</label>
            <div class="input-group">
                <select id="vehicle_type" name="vehicle_type" class="auth-input auth-input-plain" required>
                    <option value="motorcycle" {{ old('vehicle_type', 'motorcycle') === 'motorcycle' ? 'selected' : '' }}>Motorcycle</option>
                    <option value="bicycle" {{ old('vehicle_type') === 'bicycle' ? 'selected' : '' }}>Bicycle</option>
                    <option value="car" {{ old('vehicle_type') === 'car' ? 'selected' : '' }}>Car</option>
                </select>
            </div>
        </div>

        <div class="auth-field">
            <label for="plate_number" class="auth-label">Plate Number</label>
            <div class="input-group">
                <input type="text" id="plate_number" name="plate_number" class="auth-input auth-input-plain" placeholder="e.g. ABC 1234" value="{{ old('plate_number') }}" required autofocus>
            </div>
        </div>

        <div class="auth-field">
            <label for="license_number" class="auth-label">Driver's License Number</label>
            <div class="input-group">
                <input type="text" id="license_number" name="license_number" class="auth-input auth-input-plain" placeholder="License number" value="{{ old('license_number') }}" required>
            </div>
        </div>

        <div class="auth-field">
            <label class="auth-label">License Photo</label>
            <label class="file-upload">
                <input type="file" name="license_photo" accept="image/*" required onchange="document.getElementById('file-name').textContent = this.files[0]?.name || 'Tap to upload a photo of your license'">
                <span id="file-name">Tap to upload a photo of your license</span>
            </label>
        </div>

        <button type="submit" class="btn-primary">Submit for Verification</button>
    </form>
    @endif

@endsection
