@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-ink tracking-tight">Overview</h2>
            <p class="text-sm text-ink-subtle mt-1">Monitor daily performance, inventory health, and transaction activity.</p>
        </div>
        <form method="GET" action="{{ route('dashboard') }}" class="w-full sm:w-auto">
            <label class="sr-only" for="period">Period</label>
            <div class="flex items-center gap-2">
                <span class="text-sm text-ink-muted">
                    <i data-lucide="calendar-days" class="w-4 h-4 inline-block mr-1"></i>Period
                </span>
                <select id="period" name="period" onchange="this.form.submit()" class="select max-w-[180px]" aria-label="Reporting period">
                    <option value="today" {{ $period == 'today' ? 'selected' : '' }}>Today</option>
                    <option value="week" {{ $period == 'week' ? 'selected' : '' }}>This Week</option>
                    <option value="month" {{ $period == 'month' ? 'selected' : '' }}>This Month</option>
                    <option value="year" {{ $period == 'year' ? 'selected' : '' }}>This Year</option>
                </select>
            </div>
        </form>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="stat-card">
            <div class="flex items-start justify-between">
                <div>
                    <div class="stat-label">Total Revenue</div>
                    <div class="stat-value">${{ number_format($totalRevenue, 2) }}</div>
                    <div class="stat-trend text-status-success">
                        <i data-lucide="trending-up" class="w-3.5 h-3.5 mr-1"></i>
                        +12.4% <span class="text-ink-subtle ml-1 font-normal">vs prev</span>
                    </div>
                </div>
                <div class="w-10 h-10 rounded-lg bg-status-info-soft text-status-info-ink grid place-items-center">
                    <i data-lucide="dollar-sign" class="w-5 h-5"></i>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="flex items-start justify-between">
                <div>
                    <div class="stat-label">Transactions</div>
                    <div class="stat-value">{{ $totalTransactions }}</div>
                    <div class="stat-trend text-status-success">
                        <i data-lucide="trending-up" class="w-3.5 h-3.5 mr-1"></i>
                        +8.1% <span class="text-ink-subtle ml-1 font-normal">vs prev</span>
                    </div>
                </div>
                <div class="w-10 h-10 rounded-lg bg-brand-600/10 text-brand-700 grid place-items-center">
                    <i data-lucide="receipt-text" class="w-5 h-5"></i>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="flex items-start justify-between">
                <div>
                    <div class="stat-label">Avg. Transaction</div>
                    <div class="stat-value">${{ number_format($avgTransaction, 2) }}</div>
                    <div class="stat-trend text-ink-subtle">
                        <i data-lucide="minus" class="w-3.5 h-3.5 mr-1"></i>
                        Stable
                    </div>
                </div>
                <div class="w-10 h-10 rounded-lg bg-status-warning-soft text-status-warning-ink grid place-items-center">
                    <i data-lucide="bar-chart-3" class="w-5 h-5"></i>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="flex items-start justify-between">
                <div>
                    <div class="stat-label">Low Stock Items</div>
                    <div class="stat-value {{ $lowStockProducts->count() > 0 ? 'text-status-danger' : 'text-status-success' }}">
                        {{ $lowStockProducts->count() }}
                    </div>
                    <div class="stat-trend {{ $lowStockProducts->count() > 0 ? 'text-status-danger' : 'text-status-success' }}">
                        @if($lowStockProducts->count() > 0)
                            <i data-lucide="alert-triangle" class="w-3.5 h-3.5 mr-1"></i>
                            Needs review
                        @else
                            <i data-lucide="check-circle-2" class="w-3.5 h-3.5 mr-1"></i>
                            All healthy
                        @endif
                    </div>
                </div>
                <div class="w-10 h-10 rounded-lg {{ $lowStockProducts->count() > 0 ? 'bg-status-danger-soft text-status-danger' : 'bg-status-success-soft text-status-success-ink' }} grid place-items-center">
                    <i data-lucide="package-alert" class="w-5 h-5"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="card lg:col-span-2">
            <div class="card-header">
                <div class="flex items-center gap-2">
                    <i data-lucide="clock-3" class="w-4 h-4 text-ink-muted"></i>
                    <h3 class="text-sm font-semibold text-ink tracking-tight">Recent Transactions</h3>
                </div>
                <a href="{{ route('transactions.index') }}" class="btn-sm btn-ghost text-brand-700 hover:text-brand-800 hover:bg-brand-50">
                    View all
                    <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                </a>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col">Invoice</th>
                            <th scope="col">Date</th>
                            <th scope="col">Cashier</th>
                            <th scope="col" class="text-right">Total</th>
                            <th scope="col" class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentTransactions as $transaction)
                        <tr>
                            <td>
                                <a href="{{ route('transactions.show', $transaction) }}" class="font-medium text-brand-700 hover:text-brand-800 hover:underline underline-offset-2 transition-colors">
                                    #{{ $transaction->invoice_number }}
                                </a>
                            </td>
                            <td class="text-ink-muted">{{ $transaction->created_at->format('M d, H:i') }}</td>
                            <td>
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-full bg-brand-600/15 text-brand-700 grid place-items-center text-[11px] font-semibold">
                                        {{ strtoupper(substr($transaction->user->name ?? 'U', 0, 1)) }}
                                    </div>
                                    <span class="text-ink">{{ $transaction->user->name }}</span>
                                </div>
                            </td>
                            <td class="text-right font-semibold tabular-nums">${{ number_format($transaction->total, 2) }}</td>
                            <td class="text-center">
                                <span class="badge-success">
                                    {{ ucfirst($transaction->status) }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="py-10 text-center">
                                <div class="inline-flex flex-col items-center gap-2">
                                    <div class="w-10 h-10 rounded-full bg-surface-subtle text-ink-muted grid place-items-center">
                                        <i data-lucide="inbox" class="w-5 h-5"></i>
                                    </div>
                                    <div class="text-sm text-ink-muted">No transactions yet.</div>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="flex items-center gap-2">
                    <i data-lucide="package-alert" class="w-4 h-4 text-status-danger"></i>
                    <h3 class="text-sm font-semibold text-ink tracking-tight">Low Stock Alert</h3>
                </div>
                <a href="{{ route('products.index') }}" class="btn-sm btn-ghost text-brand-700 hover:text-brand-800 hover:bg-brand-50">
                    Manage
                    <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                </a>
            </div>
            <div class="card-body space-y-2">
                @forelse($lowStockProducts as $product)
                <div class="flex items-center justify-between p-3 rounded-lg bg-status-danger-soft/60 border border-status-danger/10">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-9 h-9 rounded-md bg-white grid place-items-center text-status-danger shrink-0">
                            <i data-lucide="package-x" class="w-4 h-4"></i>
                        </div>
                        <div class="min-w-0">
                            <div class="font-medium text-sm text-ink truncate">{{ $product->name }}</div>
                            <div class="text-xs text-ink-subtle">{{ $product->category->name }}</div>
                        </div>
                    </div>
                    <div class="text-right shrink-0 ml-3">
                        <div class="text-base font-bold text-status-danger tabular-nums">{{ $product->quantity }}</div>
                        <div class="text-[11px] text-ink-subtle uppercase tracking-wide">left</div>
                    </div>
                </div>
                @empty
                <div class="py-8 flex flex-col items-center gap-2 text-center">
                    <div class="w-10 h-10 rounded-full bg-status-success-soft text-status-success-ink grid place-items-center">
                        <i data-lucide="check-circle-2" class="w-5 h-5"></i>
                    </div>
                    <div class="text-sm font-medium text-ink">All products are well-stocked</div>
                    <div class="text-xs text-ink-subtle">Inventory health is within acceptable thresholds.</div>
                </div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="card">
            <div class="card-header">
                <div class="flex items-center gap-2">
                    <i data-lucide="flame" class="w-4 h-4 text-status-warning"></i>
                    <h3 class="text-sm font-semibold text-ink tracking-tight">Top Products (Last 30 Days)</h3>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col">Product</th>
                            <th scope="col" class="text-right">Sold</th>
                            <th scope="col" class="text-right">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($topProducts as $product)
                        <tr>
                            <td class="font-medium text-ink">{{ $product->name }}</td>
                            <td class="text-right tabular-nums text-ink-muted">{{ $product->total_sold }}</td>
                            <td class="text-right tabular-nums font-semibold">${{ number_format($product->revenue, 2) }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="py-10 text-center">
                                <div class="inline-flex flex-col items-center gap-2">
                                    <div class="w-10 h-10 rounded-full bg-surface-subtle text-ink-muted grid place-items-center">
                                        <i data-lucide="bar-chart-2" class="w-5 h-5"></i>
                                    </div>
                                    <div class="text-sm text-ink-muted">No sales data yet.</div>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="flex items-center gap-2">
                    <i data-lucide="activity" class="w-4 h-4 text-brand-700"></i>
                    <h3 class="text-sm font-semibold text-ink tracking-tight">Daily Sales (Last 7 Days)</h3>
                </div>
            </div>
            <div class="card-body">
                @if($dailySales->count() > 0)
                <div class="space-y-3">
                    @php $max = $dailySales->max('sales') ?: 1; @endphp
                    @foreach($dailySales as $day)
                    @php $pct = ($day->sales / $max) * 100; @endphp
                    <div class="grid grid-cols-[100px_1fr_90px] items-center gap-3">
                        <span class="text-xs font-medium text-ink-muted tabular-nums">{{ \Carbon\Carbon::parse($day->date)->format('D, M d') }}</span>
                        <div class="h-7 bg-surface-muted rounded-md overflow-hidden relative">
                            <div class="h-full rounded-md bg-gradient-to-r from-brand-500 to-brand-600 transition-all duration-300" style="width: {{ $pct }}%"></div>
                        </div>
                        <span class="text-xs font-semibold text-ink tabular-nums text-right">${{ number_format($day->sales, 2) }}</span>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="py-8 flex flex-col items-center gap-2 text-center">
                    <div class="w-10 h-10 rounded-full bg-surface-subtle text-ink-muted grid place-items-center">
                        <i data-lucide="bar-chart-2" class="w-5 h-5"></i>
                    </div>
                    <div class="text-sm text-ink-muted">No sales data yet.</div>
                </div>
                @endif
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <a href="{{ route('pos.index') }}" class="btn-primary !justify-start !py-3 group" aria-label="Start a new sale">
            <div class="w-8 h-8 rounded-md bg-white/15 grid place-items-center group-hover:bg-white/20 transition-colors">
                <i data-lucide="scan-line" class="w-4 h-4"></i>
            </div>
            <div class="flex flex-col items-start leading-tight">
                <span class="text-sm font-semibold">New Sale (POS)</span>
                <span class="text-[11px] text-brand-200/80">Open register</span>
            </div>
        </a>
        <a href="{{ route('products.index') }}" class="btn-secondary !justify-start !py-3 group">
            <div class="w-8 h-8 rounded-md bg-surface-subtle text-brand-700 grid place-items-center group-hover:bg-brand-50 transition-colors">
                <i data-lucide="package" class="w-4 h-4"></i>
            </div>
            <div class="flex flex-col items-start leading-tight">
                <span class="text-sm font-semibold text-ink">Manage Products</span>
                <span class="text-[11px] text-ink-subtle">Catalog &amp; stock</span>
            </div>
        </a>
        <a href="{{ route('transactions.index') }}" class="btn-secondary !justify-start !py-3 group">
            <div class="w-8 h-8 rounded-md bg-surface-subtle text-brand-700 grid place-items-center group-hover:bg-brand-50 transition-colors">
                <i data-lucide="receipt" class="w-4 h-4"></i>
            </div>
            <div class="flex flex-col items-start leading-tight">
                <span class="text-sm font-semibold text-ink">View Transactions</span>
                <span class="text-[11px] text-ink-subtle">History &amp; reports</span>
            </div>
        </a>
        <a href="{{ route('categories.index') }}" class="btn-secondary !justify-start !py-3 group">
            <div class="w-8 h-8 rounded-md bg-surface-subtle text-brand-700 grid place-items-center group-hover:bg-brand-50 transition-colors">
                <i data-lucide="tags" class="w-4 h-4"></i>
            </div>
            <div class="flex flex-col items-start leading-tight">
                <span class="text-sm font-semibold text-ink">Categories</span>
                <span class="text-[11px] text-ink-subtle">Product taxonomy</span>
            </div>
        </a>
    </div>
</div>
@endsection
