@extends('layouts.admin', ['pageCss' => 'admin-dashboard'])
@section('title', 'Rider Cash-outs · MotoBook Admin')
@section('page-title', 'Rider Cash-outs')
@section('content')
    <p class="mb-5 text-sm text-gray-600">Send pending requests to the rider's GCash account, then mark them paid. Rejecting a request returns the reserved amount to the rider's earnings wallet.</p>

    <table class="ledger">
        <thead><tr><th>Rider</th><th>Amount</th><th>GCash account</th><th>Requested</th><th>Status</th><th>Review</th></tr></thead>
        <tbody>
            @forelse ($cashOuts as $cashOut)
                <tr>
                    <td class="font-medium">{{ $cashOut->user->name }}<br><span class="text-text-dim">{{ $cashOut->user->email }}</span></td>
                    <td>₱{{ number_format($cashOut->amount, 2) }}</td>
                    <td>{{ $cashOut->account_name }}<br><span class="text-text-dim">{{ $cashOut->account_number }}</span></td>
                    <td class="text-text-dim">{{ $cashOut->created_at->format('M j, Y · g:i A') }}</td>
                    <td><span class="status-label {{ $cashOut->status === 'paid' ? 'ok' : ($cashOut->status === 'rejected' ? 'danger' : 'pending') }}">{{ ucfirst($cashOut->status) }}</span></td>
                    <td>
                        @if ($cashOut->status === 'pending')
                            <div class="flex flex-wrap gap-2">
                                <form method="POST" action="{{ route('admin.cash-outs.paid', $cashOut) }}">@csrf @method('PATCH')<button type="submit" class="table-link">Mark paid</button></form>
                                <form method="POST" action="{{ route('admin.cash-outs.reject', $cashOut) }}">@csrf @method('PATCH')<input type="text" name="review_note" maxlength="500" placeholder="Reason (optional)"><button type="submit" class="table-link danger">Reject</button></form>
                            </div>
                        @else
                            <span class="text-text-dim">{{ $cashOut->reviewer?->name ?? 'Reviewed' }}@if ($cashOut->review_note) · {{ $cashOut->review_note }}@endif</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-text-dim">No cash-out requests yet.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="mt-5">{{ $cashOuts->links() }}</div>
@endsection
