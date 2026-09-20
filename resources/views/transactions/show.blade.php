@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Transaction Details</h1>
        <a href="{{ route('transactions.index') }}" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Back</a>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">{{ $transaction->invoice_number }}</h2>
                    <p class="text-sm text-gray-500">{{ $transaction->created_at->format('F d, Y \a\t H:i:s') }}</p>
                </div>
                <span class="px-3 py-1 text-sm font-medium rounded-full
                    {{ $transaction->status === 'completed' ? 'bg-green-100 text-green-800' : '' }}
                    {{ $transaction->status === 'pending' ? 'bg-yellow-100 text-yellow-800' : '' }}
                    {{ $transaction->status === 'cancelled' ? 'bg-red-100 text-red-800' : '' }}
                    {{ $transaction->status === 'refunded' ? 'bg-purple-100 text-purple-800' : '' }}">
                    {{ ucfirst($transaction->status) }}
                </span>
            </div>
        </div>

        <div class="p-6">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-6">
                <div>
                    <dt class="text-sm font-medium text-gray-500">Cashier</dt>
                    <dd class="text-base text-gray-900">{{ $transaction->user->name }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Payment Method</dt>
                    <dd class="text-base text-gray-900">{{ ucfirst($transaction->payment_method) }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Subtotal</dt>
                    <dd class="text-base text-gray-900">${{ number_format($transaction->subtotal, 2) }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Tax</dt>
                    <dd class="text-base text-gray-900">${{ number_format($transaction->tax, 2) }}</dd>
                </div>
            </div>

            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Price</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Qty</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($transaction->items as $item)
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-900">{{ $item->product->name }}</td>
                        <td class="px-4 py-3 text-sm text-right text-gray-900">${{ number_format($item->unit_price, 2) }}</td>
                        <td class="px-4 py-3 text-sm text-center text-gray-900">{{ $item->quantity }}</td>
                        <td class="px-4 py-3 text-sm text-right text-gray-900">${{ number_format($item->subtotal, 2) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-4 py-4 text-center text-gray-500">No items found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="mt-6 flex justify-end">
                <dl class="space-y-2 text-right">
                    <div class="flex justify-between gap-8">
                        <dt class="text-gray-500">Subtotal:</dt>
                        <dd class="text-gray-900">${{ number_format($transaction->subtotal, 2) }}</dd>
                    </div>
                    @if($transaction->discount > 0)
                    <div class="flex justify-between gap-8">
                        <dt class="text-gray-500">Discount:</dt>
                        <dd class="text-green-600">-${{ number_format($transaction->discount, 2) }}</dd>
                    </div>
                    @endif
                    @if($transaction->tax > 0)
                    <div class="flex justify-between gap-8">
                        <dt class="text-gray-500">Tax:</dt>
                        <dd class="text-gray-900">${{ number_format($transaction->tax, 2) }}</dd>
                    </div>
                    @endif
                    <div class="flex justify-between gap-8 pt-2 border-t">
                        <dt class="text-lg font-semibold text-gray-900">Total:</dt>
                        <dd class="text-lg font-semibold text-gray-900">${{ number_format($transaction->total, 2) }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</div>
@endsection