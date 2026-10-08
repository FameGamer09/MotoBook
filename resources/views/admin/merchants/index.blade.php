@extends('layouts.admin', ['pageCss' => 'admin-merchants'])
@section('title', 'Merchants · MotoBook Admin')
@section('page-title', 'Merchants')
@section('content')

    <table class="ledger">
        <thead>
            <tr><th>Store</th><th>Owner</th><th>Category</th><th>Status</th><th>Verification</th><th></th></tr>
        </thead>
        <tbody>
            @foreach ($stores as $store)
                <tr>
                    <td class="font-medium">{{ $store->name }}</td>
                    <td class="text-text-dim">{{ $store->owner->name }}</td>
                    <td class="text-text-dim">{{ $store->category }}</td>
                    <td>
                        @if ($store->owner->status === 'banned')
                            <span class="status-label danger">Banned</span>
                        @else
                            <span class="status-label {{ $store->is_open ? 'ok' : '' }}">{{ $store->is_open ? 'Open' : 'Closed' }}</span>
                        @endif
                    </td>
                    <td>
                        @if ($store->is_verified)
                            <span class="status-dot ok"></span><span class="status-label ok">Verified</span>
                        @else
                            <span class="status-dot pending"></span><span class="status-label pending">Pending</span>
                        @endif
                    </td>
                    <td><a href="{{ route('admin.merchants.show', $store) }}" class="table-link">Review</a></td>
                </tr>
            @endforeach
        </tbody>
    </table>

@endsection
