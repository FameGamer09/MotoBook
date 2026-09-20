@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
        <form method="GET" action="{{ route('dashboard') }}">
            <select name="period" onchange="this.form.submit()" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                <option value="today" {{ $period == 'today' ? 'selected' : '' }}>Today</option>
                <option value="week" {{ $period == 'week' ? 'selected' : '' }}>This Week</option>
                <option value="month" {{ $period == 'month' ? 'selected' : '' }}>This Month</option>
                <option value="year" {{ $period == 'year' ? 'selected' : '' }}>This Year</option>
            </select>
        </form>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="text-sm font-medium text-gray-500">Total Revenue</div>
            <div class="text-2xl font-bold text-gray-900 mt-1">${{ number_format($totalRevenue, 2) }}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <div class="text-sm font-medium text-gray-500">Transactions</div>
            <div class="text-2xl font-bold text-gray-900 mt-1">{{ $totalTransactions }}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <div class="text-sm font-medium text-gray-500">Avg. Transaction</div>
            <div class="text-2xl font-bold text-gray-900 mt-1">${{ number_format($avgTransaction, 2) }}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <div class="text-sm font-medium text-gray-500">Low Stock Items</div>
            <div class="text-2xl font-bold {{ $lowStockProducts->count() > 0 ? 'text-red-600' : 'text-green-600' }} mt-1">
                {{ $lowStockProducts->count() }}
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Recent Transactions -->
        <div class="lg:col-span-2 bg-white rounded-lg shadow">
            <div class="p-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Recent Transactions</h2>
            </div>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cashier</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($recentTransactions as $transaction)
                    <tr>
                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                            <a href="{{ route('transactions.show', $transaction) }}" class="hover:text-indigo-600">
                                {{ $transaction->invoice_number }}
                            </a>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{{ $transaction->created_at->format('M d, H:i') }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{{ $transaction->user->name }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-right text-gray-900">${{ number_format($transaction->total, 2) }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-center">
                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">
                                {{ ucfirst($transaction->status) }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4 py-4 text-center text-gray-500">No transactions yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            <div class="p-4 border-t border-gray-200">
                <a href="{{ route('transactions.index') }}" class="text-indigo-600 hover:text-indigo-900 text-sm">View all transactions →</a>
            </div>
        </div>

        <!-- Low Stock Alert -->
        <div class="bg-white rounded-lg shadow">
            <div class="p-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Low Stock Alert</h2>
            </div>
            <div class="p-4 space-y-3">
                @forelse($lowStockProducts as $product)
                <div class="flex items-center justify-between p-3 bg-red-50 rounded-lg">
                    <div>
                        <div class="font-medium text-sm text-gray-900">{{ $product->name }}</div>
                        <div class="text-xs text-gray-500">{{ $product->category->name }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-lg font-bold text-red-600">{{ $product->quantity }}</div>
                        <div class="text-xs text-gray-500">left</div>
                    </div>
                </div>
                @empty
                <p class="text-center text-gray-500 py-4">All products are well-stocked!</p>
                @endforelse
            </div>
            <div class="p-4 border-t border-gray-200">
                <a href="{{ route('products.index') }}" class="text-indigo-600 hover:text-indigo-900 text-sm">Manage inventory →</a>
            </div>
        </div>
    </div>

    <!-- Top Products & Daily Sales -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
        <div class="bg-white rounded-lg shadow">
            <div class="p-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Top Products (Last 30 Days)</h2>
            </div>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Sold</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Revenue</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($topProducts as $product)
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-900">{{ $product->name }}</td>
                        <td class="px-4 py-3 text-sm text-right text-gray-900">{{ $product->total_sold }}</td>
                        <td class="px-4 py-3 text-sm text-right text-gray-900">${{ number_format($product->revenue, 2) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="px-4 py-4 text-center text-gray-500">No sales data yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="bg-white rounded-lg shadow">
            <div class="p-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Daily Sales (Last 7 Days)</h2>
            </div>
            <div class="p-4">
                @if($dailySales->count() > 0)
                <div class="space-y-2">
                    @foreach($dailySales as $day)
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">{{ \Carbon\Carbon::parse($day->date)->format('D, M d') }}</span>
                        <div class="flex-1 mx-4 bg-gray-200 rounded-full h-4">
                            <div class="bg-indigo-600 h-4 rounded-full" style="width: {{ ($day->sales / $dailySales->max('sales')) * 100 }}%"></div>
                        </div>
                        <span class="text-sm font-medium text-gray-900">${{ number_format($day->sales, 2) }}</span>
                    </div>
                    @endforeach
                </div>
                @else
                <p class="text-center text-gray-500 py-4">No sales data yet.</p>
                @endif
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="mt-6 grid grid-cols-1 md:grid-cols-4 gap-4">
        <a href="{{ route('pos.index') }}" class="flex items-center justify-center p-4 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
            New Sale (POS)
        </a>
        <a href="{{ route('products.index') }}" class="flex items-center justify-center p-4 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
            <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
            </svg>
            Manage Products
        </a>
        <a href="{{ route('transactions.index') }}" class="flex items-center justify-center p-4 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
            <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            View Transactions
        </a>
        <a href="{{ route('categories.index') }}" class="flex items-center justify-center p-4 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
            <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
            </svg>
            Categories
        </a>
    </div>
</div>
@endsection
