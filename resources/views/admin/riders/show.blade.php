@extends('layouts.admin', ['pageCss' => 'admin-riders'])
@section('title', $rider->user->name . ' · MotoBook Admin')
@section('page-title', 'Riders')
@section('content')

    <a href="{{ route('admin.riders.index') }}" class="table-link mb-4 inline-block">&larr; Back to Riders</a>

    <div class="detail-card">
        <div class="detail-row">
            <span class="detail-label">Name</span>
            <span class="detail-value">{{ $rider->user->name }} ({{ $rider->user->email }})</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Vehicle type</span>
            <span class="detail-value">{{ ucfirst($rider->vehicle_type) }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Plate number</span>
            <span class="detail-value">{{ $rider->plate_number ?? 'Not provided' }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">License number</span>
            <span class="detail-value">{{ $rider->license_number ?? 'Not provided' }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">License photo</span>
            <span class="detail-value">
                @if ($rider->license_photo)
                    <a href="{{ route('admin.riders.license', $rider) }}" target="_blank" rel="noopener" class="table-link">View photo</a>
                @elseif ($rider->user->status === 'inactive')
                    <span class="status-label pending">Pending activation</span>
                @else
                    Not uploaded
                @endif
            </span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Total deliveries</span>
            <span class="detail-value">{{ $rider->total_deliveries }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Verification status</span>
            <span class="detail-value">
                @if ($rider->is_verified)
                    <span class="status-dot ok"></span><span class="status-label ok">Verified</span>
                @elseif ($rider->user->status === 'inactive')
                    <form method="POST" action="{{ route('admin.riders.activate', $rider) }}">
                        @csrf @method('PATCH')
                        <button type="submit" class="admin-btn admin-btn-primary">Activate Account</button>
                    </form>
                @else
                    <span class="status-dot pending"></span><span class="status-label pending">Pending</span>
                @endif
            </span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Account status</span>
            <span class="detail-value">
                @if ($rider->user->status === 'banned')
                    <span class="status-label danger">Banned</span>
                @else
                    <span class="status-label ok">Active</span>
                @endif
            </span>
        </div>
    </div>

    <div class="flex gap-2.5 mt-5">
        @if (! $rider->is_verified)
            <form method="POST" action="{{ route('admin.riders.verify', $rider) }}">
                @csrf @method('PATCH')
                <button type="submit" class="admin-btn admin-btn-primary">Approve &amp; Verify</button>
            </form>
        @else
            <form method="POST" action="{{ route('admin.riders.unverify', $rider) }}">
                @csrf @method('PATCH')
                <button type="submit" class="admin-btn admin-btn-ghost">Revoke Verification</button>
            </form>
        @endif

        @if ($rider->user->status === 'banned')
            <form method="POST" action="{{ route('admin.riders.activate', $rider) }}">
                @csrf @method('PATCH')
                <button type="submit" class="admin-btn admin-btn-ghost">Reactivate Account</button>
            </form>
        @else
            <form method="POST" action="{{ route('admin.riders.ban', $rider) }}" onsubmit="return confirm('Ban this rider account?')">
                @csrf @method('PATCH')
                <button type="submit" class="admin-btn admin-btn-danger">Ban Account</button>
            </form>
        @endif
    </div>

@endsection
