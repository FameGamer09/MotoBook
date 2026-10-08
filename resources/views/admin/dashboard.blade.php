@extends('layouts.admin', ['pageCss' => 'admin-dashboard'])
@section('title', 'Dashboard · MotoBook Admin')
@section('page-title', 'Dashboard')
@section('content')

    <div class="stat-row">
        <div>
            <div class="stat-label">Total users</div>
            <div class="stat-value">{{ number_format($stats['total_users']) }}</div>
        </div>
        <div>
            <div class="stat-label">Orders, 30 days</div>
            <div class="stat-value">{{ number_format($stats['orders_30d']) }}</div>
        </div>
        <div>
            <div class="stat-label">Revenue, 30 days</div>
            <div class="stat-value">₱{{ number_format($stats['revenue_30d'], 2) }}</div>
        </div>
        <div>
            <div class="stat-label">Pending review</div>
            <div class="stat-value pending">{{ $stats['pending_count'] }}</div>
        </div>
    </div>

    <div class="section-header">
        <div class="section-title">Pending verifications</div>
    </div>

    @if ($pendingMerchants->isEmpty() && $pendingRiders->isEmpty())
        <p class="empty-state">Nothing pending review right now.</p>
    @else
        <table class="ledger mb-8">
            <thead>
                <tr><th>Name</th><th>Type</th><th>Details</th><th></th></tr>
            </thead>
            <tbody>
                @foreach ($pendingMerchants as $store)
                    <tr>
                        <td><span class="status-dot pending"></span>{{ $store->name }}</td>
                        <td class="text-text-dim">Merchant</td>
                        <td class="text-text-dim">{{ $store->owner->name }} · {{ $store->address }} · {{ $store->owner->status === 'inactive' ? 'Account activation pending' : 'Verification pending' }}</td>
                        <td><a href="{{ route('admin.merchants.show', $store) }}" class="table-link">Review</a></td>
                    </tr>
                @endforeach
                @foreach ($pendingRiders as $rider)
                    <tr>
                        <td><span class="status-dot pending"></span>{{ $rider->user->name }}</td>
                        <td class="text-text-dim">Rider</td>
                        <td class="text-text-dim">{{ ucfirst($rider->vehicle_type) }} · {{ $rider->plate_number ?? 'No plate on file' }} · {{ $rider->user->status === 'inactive' ? 'Account activation pending' : 'Verification pending' }}</td>
                        <td><a href="{{ route('admin.riders.show', $rider) }}" class="table-link">Review</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

@endsection
