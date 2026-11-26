@extends('layouts.admin', ['pageCss' => 'admin-dashboard'])
@section('title', 'Security Top-ups · MotoBook Admin')
@section('page-title', 'Rider Security Top-ups')
@section('content')
    <p class="mb-5 text-sm text-gray-600">Verify the rider's payment before approving. Approval credits the separate security deposit balance.</p>

    <table class="ledger">
        <thead>
            <tr><th>Rider</th><th>Amount</th><th>Payment</th><th>Reference</th><th>Requested</th><th>Status</th><th>Review</th></tr>
        </thead>
        <tbody>
            @forelse ($topUps as $topUp)
                <tr>
                    <td class="font-medium">{{ $topUp->user->name }}<br><span class="text-text-dim">{{ $topUp->user->email }}</span></td>
                    <td>₱{{ number_format($topUp->amount, 2) }}</td>
                    <td>{{ $topUp->payment_method === 'gcash' ? 'GCash' : 'Cash' }}</td>
                    <td class="text-text-dim">{{ $topUp->payment_reference ?: '—' }}</td>
                    <td class="text-text-dim">{{ $topUp->created_at->format('M j, Y · g:i A') }}</td>
                    <td><span class="status-label {{ $topUp->status === 'approved' ? 'ok' : ($topUp->status === 'rejected' ? 'danger' : 'pending') }}">{{ ucfirst($topUp->status) }}</span></td>
                    <td>
                        @if ($topUp->status === 'pending')
                            <div class="flex flex-wrap gap-2">
                                <form method="POST" action="{{ route('admin.security-top-ups.approve', $topUp) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="table-link">Approve</button>
                                </form>
                                <form method="POST" action="{{ route('admin.security-top-ups.reject', $topUp) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="table-link danger">Reject</button>
                                </form>
                            </div>
                        @else
                            <span class="text-text-dim">{{ $topUp->reviewer?->name ?? 'Reviewed' }} @if ($topUp->balance_after) · ₱{{ number_format($topUp->balance_after, 2) }} balance @endif</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-text-dim">No top-up requests yet.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="mt-5">{{ $topUps->links() }}</div>
@endsection
