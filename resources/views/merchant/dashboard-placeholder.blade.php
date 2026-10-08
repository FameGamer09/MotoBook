@extends('layouts.auth', ['pageCss' => 'merchant-onboarding'])
@section('title', 'Merchant Dashboard · MotoBook')
@section('content')

    <h1 class="auth-title">Welcome, {{ auth()->user()->name }} 👋</h1>
    <p class="auth-subtitle">
        @if (session('status'))
            {{ session('status') }}
        @else
            Your merchant dashboard is coming soon — order management, menu editing, and earnings will live here.
        @endif
    </p>

    @if ($store)
        <div class="status-banner">
            <strong>{{ $store->name }}</strong> ({{ $store->category }})<br>
            {{ $store->address }}<br>
            Verification status: {{ $store->is_verified ? 'Verified ✅' : 'Pending review ⏳' }}
        </div>
    @endif

    <form method="POST" action="{{ route('logout') }}" class="mt-6">
        @csrf
        <button type="submit" class="btn-outline">Log Out</button>
    </form>

@endsection
