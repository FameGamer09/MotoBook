@extends('layouts.admin', ['pageCss' => 'admin-riders'])
@section('title', 'Riders · MotoBook Admin')
@section('page-title', 'Riders')
@section('content')

    <table class="ledger">
        <thead>
            <tr><th>Name</th><th>Vehicle</th><th>Plate</th><th>Status</th><th>Verification</th><th></th></tr>
        </thead>
        <tbody>
            @foreach ($riders as $rider)
                <tr>
                    <td class="font-medium">{{ $rider->user->name }}</td>
                    <td class="text-text-dim">{{ ucfirst($rider->vehicle_type) }}</td>
                    <td class="text-text-dim">{{ $rider->plate_number ?? '—' }}</td>
                    <td>
                        @if ($rider->user->status === 'banned')
                            <span class="status-label danger">Banned</span>
                        @else
                            <span class="status-label {{ $rider->status === 'online' ? 'ok' : '' }}">{{ ucfirst($rider->status) }}</span>
                        @endif
                    </td>
                    <td>
                        @if ($rider->is_verified)
                            <span class="status-dot ok"></span><span class="status-label ok">Verified</span>
                        @else
                            <span class="status-dot pending"></span><span class="status-label pending">Pending</span>
                        @endif
                    </td>
                    <td><a href="{{ route('admin.riders.show', $rider) }}" class="table-link">Review</a></td>
                </tr>
            @endforeach
        </tbody>
    </table>

@endsection
