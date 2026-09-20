@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">{{ $product->name }}</h1>
        <div class="flex gap-2">
            <a href="{{ route('products.edit', $product) }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Edit</a>
            <a href="{{ route('products.index') }}" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Back</a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="md:col-span-2 space-y-6">
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Product Details</h2>
                <dl class="grid grid-cols-2 gap-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">SKU</dt>
                        <dd class="text-base text-gray-900">{{ $product->sku }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Category</dt>
                        <dd class="text-base text-gray-900">{{ $product->category->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Price</dt>
                        <dd class="text-base text-gray-900">${{ number_format($product->price, 2) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Cost</dt>
                        <dd class="text-base text-gray-900">${{ number_format($product->cost, 2) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Quantity</dt>
                        <dd class="text-base {{ $product->isLowStock() ? 'text-red-600 font-bold' : 'text-gray-900' }}">{{ $product->quantity }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Status</dt>
                        <dd>
                            @if($product->is_active)
                                <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Active</span>
                            @else
                                <span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">Inactive</span>
                            @endif
                        </dd>
                    </div>
                </dl>
                @if($product->description)
                <div class="mt-4">
                    <dt class="text-sm font-medium text-gray-500">Description</dt>
                    <dd class="mt-1 text-base text-gray-900">{{ $product->description }}</dd>
                </div>
                @endif
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Inventory History</h2>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Qty</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">From → To</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($product->inventoryMovements->sortByDesc('created_at')->take(10) as $movement)
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{{ $movement->created_at->format('M d, Y H:i') }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                <span class="px-2 py-1 text-xs font-medium rounded-full
                                    {{ $movement->type === 'in' ? 'bg-green-100 text-green-800' : '' }}
                                    {{ $movement->type === 'out' ? 'bg-red-100 text-red-800' : '' }}
                                    {{ $movement->type === 'adjustment' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                    {{ $movement->type === 'sale' ? 'bg-blue-100 text-blue-800' : '' }}">
                                    {{ ucfirst($movement->type) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-right text-gray-900">{{ $movement->quantity }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-right text-gray-900">{{ $movement->previous_quantity }} → {{ $movement->new_quantity }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{{ $movement->user->name }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-4 py-4 text-center text-gray-500">No inventory movements yet.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="space-y-6">
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Stock Status</h2>
                <div class="text-center">
                    <div class="text-4xl font-bold {{ $product->isLowStock() ? 'text-red-600' : 'text-green-600' }}">{{ $product->quantity }}</div>
                    <div class="text-sm text-gray-500 mt-1">Current Stock</div>
                </div>
                @if($product->isLowStock())
                <div class="mt-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                    <div class="text-sm text-red-600 font-medium">Low Stock Alert</div>
                    <div class="text-xs text-red-500">Threshold: {{ $product->low_stock_threshold }}</div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection