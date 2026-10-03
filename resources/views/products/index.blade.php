@extends('layouts.app')

@section('title', 'Products')

@section('content')
<div class="space-y-5">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-ink tracking-tight">Products</h2>
            <p class="text-sm text-ink-subtle mt-1">Manage inventory catalog, pricing, and stock levels.</p>
        </div>
        <a href="{{ route('products.create') }}" class="btn-primary" aria-label="Add new product">
            <i data-lucide="plus" class="w-4 h-4 -ml-0.5"></i>
            Add Product
        </a>
    </div>

    <div class="card">
        <div class="card-header !py-3">
            <form method="GET" action="{{ route('products.index') }}" class="w-full flex flex-col lg:flex-row gap-3" role="search">
                <label class="sr-only" for="search">Search</label>
                <div class="relative flex-1 lg:max-w-md">
                    <i data-lucide="search" class="w-4 h-4 text-ink-muted absolute left-3 top-1/2 -translate-y-1/2"></i>
                    <input id="search" type="text" name="search" value="{{ $search }}" placeholder="Search by name or SKU..."
                           class="input !pl-9" aria-label="Search products">
                </div>
                <div class="flex gap-3">
                    <label class="sr-only" for="category">Category</label>
                    <select id="category" name="category" class="select w-full lg:w-44" aria-label="Filter by category">
                        <option value="">All Categories</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ $categoryId == $category->id ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn-secondary" aria-label="Apply filters">
                        <i data-lucide="filter" class="w-4 h-4"></i>
                        <span class="hidden sm:inline">Filter</span>
                    </button>
                    <a href="{{ route('products.index') }}" class="btn-ghost" aria-label="Reset filters">
                        <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                    </a>
                </div>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col">SKU</th>
                        <th scope="col">Name</th>
                        <th scope="col">Category</th>
                        <th scope="col" class="text-right">Price</th>
                        <th scope="col" class="text-right">Qty</th>
                        <th scope="col" class="text-center">Status</th>
                        <th scope="col" class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $product)
                    <tr class="{{ $product->isLowStock() ? 'bg-status-danger-soft/40' : '' }}">
                        <td class="font-mono text-xs text-ink-muted">{{ $product->sku }}</td>
                        <td class="font-medium text-ink">{{ $product->name }}</td>
                        <td class="text-ink-muted">{{ $product->category->name }}</td>
                        <td class="text-right font-semibold tabular-nums text-ink">${{ number_format($product->price, 2) }}</td>
                        <td class="text-right">
                            <span class="inline-flex items-center gap-1 tabular-nums font-semibold {{ $product->isLowStock() ? 'text-status-danger' : 'text-ink' }}">
                                @if($product->isLowStock())
                                    <i data-lucide="alert-triangle" class="w-3.5 h-3.5"></i>
                                @endif
                                {{ $product->quantity }}
                            </span>
                        </td>
                        <td class="text-center">
                            @if($product->is_active)
                                <span class="badge-success">
                                    <i data-lucide="check" class="w-3 h-3"></i>Active
                                </span>
                            @else
                                <span class="badge-neutral">
                                    <i data-lucide="pause" class="w-3 h-3"></i>Inactive
                                </span>
                            @endif
                        </td>
                        <td class="text-right">
                            <div class="inline-flex items-center gap-0.5">
                                <a href="{{ route('products.show', $product) }}" class="btn-sm btn-ghost !px-2" aria-label="View product">
                                    <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                </a>
                                <a href="{{ route('products.edit', $product) }}" class="btn-sm btn-ghost !px-2 text-brand-700 hover:text-brand-800 hover:bg-brand-50" aria-label="Edit product">
                                    <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                                </a>
                                <form action="{{ route('products.destroy', $product) }}" method="POST" class="inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn-sm btn-ghost !px-2 text-status-danger hover:text-status-danger hover:bg-status-danger-soft"
                                            aria-label="Delete product"
                                            onclick="return confirm('Delete this product?');">
                                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="py-12 text-center">
                            <div class="inline-flex flex-col items-center gap-2">
                                <div class="w-11 h-11 rounded-full bg-surface-subtle text-ink-muted grid place-items-center">
                                    <i data-lucide="package-search" class="w-5 h-5"></i>
                                </div>
                                <div class="text-sm font-medium text-ink">No products found</div>
                                <div class="text-xs text-ink-subtle">Try adjusting your filters or add a new product.</div>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($products->hasPages())
        <div class="card-footer">
            {{ $products->onEachSide(1)->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
