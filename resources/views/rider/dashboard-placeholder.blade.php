@extends('layouts.auth', ['pageCss' => 'rider-onboarding'])
@section('title', 'Rider Dashboard · MotoBook')
@section('content')

    <h1 class="auth-title">Welcome, {{ auth()->user()->name }} 🏍️</h1>
    <p class="auth-subtitle">
        @if (session('status'))
            {{ session('status') }}
        @else
           Wala pa nganiiiii 
        @endif
    </p>

    @if ($rider)
        <div class="status-banner">
            Vehicle: {{ ucfirst($rider->vehicle_type) }} ({{ $rider->plate_number ?? 'not set' }})<br>
            Verification status: {{ $rider->is_verified ? 'Verified ✅' : 'Pending review ⏳' }}
        </div>
    @endif

    @if ($stores->isNotEmpty())
        <div class="status-banner">
            <strong>Partner store status</strong><br>
            @foreach ($stores as $store)
                {{ $store->name }} · <span style="color:{{ $store->is_open ? '#15803d' : '#b91c1c' }}">{{ $store->is_open ? 'Open' : 'Closed' }}</span>@if (!$loop->last)<br>@endif
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('logout') }}" class="mt-6">
        @csrf
        <button type="submit" class="btn-outline">Log Out</button>
    </form>

@endsection
