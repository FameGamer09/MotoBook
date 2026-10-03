@extends('layouts.app')

@section('title', 'Transactions')

@section('content')
<div class="space-y-5">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-ink tracking-tight">Transactions</h2>
            <p class="text-sm text-ink-subtle mt-1">Search and review sales history, refunds, and payment records.</p>
        </div>
        <a href="{{ route('pos.index') }}" class="btn-secondary">
            <i data-lucide="scan-line" class="w-4 h-4"></i>
            New Sale
        </a>
    </div>

    <div class="card">
        <div class="card-header !py-3">
            <form method="GET" action="{{ route('transactions.index') }}" class="w-full flex flex-col lg:flex-row lg:flex-wrap gap-3" role="search">
                <label class="sr-only" for="search">Invoice search</label>
                <div class="relative flex-1 lg:max-w-xs">
                    <i data-lucide="search" class="w-4 h-4 text-ink-muted absolute left-3 top-1/2 -translate-y-1/2"></i>
                    <input id="search" type="text" name="search" value="{{ $search }}" placeholder="Search invoice #..."
                           class="input !pl-9" aria-label="Search invoice">
                </div>
                <label class="sr-only" for="status">Status</label>
                <select id="status" name="status" class="select lg:w-36" aria-label="Filter status">
                    <option value="">All Status</option>
                    <option value="completed" {{ $status == 'completed' ? 'selected' : '' }}>Completed</option>
                    <option value="pending" {{ $status == 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="cancelled" {{ $status == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    <option value="refunded" {{ $status == 'refunded' ? 'selected' : '' }}>Refunded</option>
                </select>
                <div class="flex gap-2 items-center">
                    <input type="date" name="date_from" value="{{ $dateFrom }}"
                           class="input lg:w-36" aria-label="From date" title="From date">
                    <span class="text-ink-muted hidden lg:inline">to</span>
                    <input type="date" name="date_to" value="{{ $dateTo }}"
                           class="input lg:w-36" aria-label="To date" title="To date">
                </div>
                <div class="flex gap-2 lg:ml-auto">
                    <button type="submit" class="btn-secondary" aria-label="Apply filters">
                        <i data-lucide="filter" class="w-4 h-4"></i>
                        <span class="hidden sm:inline">Filter</span>
                    </button>
                    <a href="{{ route('transactions.index') }}" class="btn-ghost" aria-label="Reset filters">
                        <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                    </a>
                </div>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col">Invoice</th>
                        <th scope="col">Date</th>
                        <th scope="col">Cashier</th>
                        <th scope="col" class="text-right">Total</th>
                        <th scope="col" class="text-center">Payment</th>
                        <th scope="col" class="text-center">Status</th>
                        <th scope="col" class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $transaction)
                    <tr>
                        <td class="font-mono text-sm">
                            <a href="{{ route('transactions.show', $transaction) }}" class="font-semibold text-brand-700 hover:text-brand-800 hover:underline underline-offset-2">
                                #{{ $transaction->invoice_number }}
                            </a>
                        </td>
                        <td class="text-ink-muted tabular-nums">{{ $transaction->created_at->format('M d, Y H:i') }}</td>
                        <td>
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded-full bg-brand-600/15 text-brand-700 grid place-items-center text-[10px] font-semibold">
                                    {{ strtoupper(substr($transaction->user->name ?? 'U', 0, 1)) }}
                                </div>
                                <span class="text-sm text-ink">{{ $transaction->user->name }}</span>
                            </div>
                        </td>
                        <td class="text-right font-semibold tabular-nums text-ink">${{ number_format($transaction->total, 2) }}</td>
                        <td class="text-center">
                            <span class="inline-flex items-center gap-1 text-xs font-medium text-ink-muted">
                                @if($transaction->payment_method === 'cash')
                                    <i data-lucide="banknote" class="w-3.5 h-3.5 text-status-success"></i>
                                @elseif($transaction->payment_method === 'card')
                                    <i data-lucide="credit-card" class="w-3.5 h-3.5 text-brand-700"></i>
                                @else
                                    <i data-lucide="smartphone" class="w-3.5 h-3.5 text-status-warning"></i>
                                @endif
                                {{ ucfirst($transaction->payment_method) }}
                            </span>
                        </td>
                        <td class="text-center">
                            @switch($transaction->status)
                                @case('completed') <span class="badge-success"><i data-lucide="check" class="w-3 h-3"></i>Completed</span> @break
                                @case('pending')   <span class="badge-warning"><i data-lucide="clock" class="w-3 h-3"></i>Pending</span> @break
                                @case('cancelled') <span class="badge-danger"><i data-lucide="x" class="w-3 h-3"></i>Cancelled</span> @break
                                @case('refunded')  <span class="badge-info"><i data-lucide="refresh-ccw" class="w-3 h-3"></i>Refunded</span> @break
                                @default            <span class="badge-neutral">{{ ucfirst($transaction->status) }}</span>
                            @endswitch
                        </td>
                        <td class="text-right">
                            <a href="{{ route('transactions.show', $transaction) }}" class="btn-sm btn-ghost !px-2 text-brand-700 hover:text-brand-800 hover:bg-brand-50" aria-label="View transaction">
                                <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                View
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="py-12 text-center">
                            <div class="inline-flex flex-col items-center gap-2">
                                <div class="w-11 h-11 rounded-full bg-surface-subtle text-ink-muted grid place-items-center">
                                    <i data-lucide="receipt-text" class="w-5 h-5"></i>
                                </div>
                                <div class="text-sm font-medium text-ink">No transactions found</div>
                                <div class="text-xs text-ink-subtle">Adjust filters or process a new sale.</div>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($transactions->hasPages())
        <div class="card-footer">
            {{ $transactions->onEachSide(1)->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
