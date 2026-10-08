@extends('layouts.admin', ['pageCss' => 'admin-merchants'])
@section('title', $store->name . ' · MotoBook Admin')
@section('page-title', 'Merchants')
@section('content')

    <a href="{{ route('admin.merchants.index') }}" class="table-link mb-4 inline-block">&larr; Back to Merchants</a>

    <div class="detail-card">
        <div class="detail-row">
            <span class="detail-label">Store name</span>
            <span class="detail-value">{{ $store->name }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Owner</span>
            <span class="detail-value">{{ $store->owner->name }} ({{ $store->owner->email }})</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Category</span>
            <span class="detail-value">{{ $store->category }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Phone</span>
            <span class="detail-value">{{ $store->phone }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Address</span>
            <span class="detail-value">{{ $store->address }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Menu items</span>
            <span class="detail-value">{{ $store->menuItems->count() }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Verification status</span>
            <span class="detail-value">
                @if ($store->is_verified)
                    <span class="status-dot ok"></span><span class="status-label ok">Verified</span>
                @elseif ($store->owner->status === 'inactive')
                    <span class="status-label pending">Pending activation</span>
                @else
                    <span class="status-dot pending"></span><span class="status-label pending">Pending</span>
                @endif
            </span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Account status</span>
            <span class="detail-value">
                @if ($store->owner->status === 'banned')
                    <span class="status-label danger">Banned</span>
                @elseif ($store->owner->status === 'inactive')
                    <form method="POST" action="{{ route('admin.merchants.activate', $store) }}">
                        @csrf @method('PATCH')
                        <button type="submit" class="admin-btn admin-btn-primary">Activate Account</button>
                    </form>
                @else
                    <span class="status-label ok">Active</span>
                @endif
            </span>
        </div>
    </div>

    <div class="flex gap-2.5 mt-5">
        @if (! $store->is_verified)
            <form method="POST" action="{{ route('admin.merchants.verify', $store) }}">
                @csrf @method('PATCH')
                <button type="submit" class="admin-btn admin-btn-primary">Approve &amp; Verify</button>
            </form>
        @else
            <form method="POST" action="{{ route('admin.merchants.unverify', $store) }}">
                @csrf @method('PATCH')
                <button type="submit" class="admin-btn admin-btn-ghost">Revoke Verification</button>
            </form>
        @endif

        @if ($store->owner->status === 'banned')
            <form method="POST" action="{{ route('admin.merchants.activate', $store) }}">
                @csrf @method('PATCH')
                <button type="submit" class="admin-btn admin-btn-ghost">Reactivate Account</button>
            </form>
        @else
            <form method="POST" action="{{ route('admin.merchants.ban', $store) }}" onsubmit="return confirm('Ban this merchant account?')">
                @csrf @method('PATCH')
                <button type="submit" class="admin-btn admin-btn-danger">Ban Account</button>
            </form>
        @endif
    </div>

@endsection
