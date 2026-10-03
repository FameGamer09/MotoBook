@extends('layouts.app')

@section('title', 'Categories')

@section('content')
<div class="space-y-5">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-ink tracking-tight">Categories</h2>
            <p class="text-sm text-ink-subtle mt-1">Organize products into searchable groups for faster POS access.</p>
        </div>
    </div>

    <div class="card">
        <div class="card-header !py-4">
            <form method="POST" action="{{ route('categories.store') }}" class="w-full grid grid-cols-1 md:grid-cols-[1fr_1fr_auto] gap-3">
                @csrf
                <div>
                    <label class="sr-only" for="name">Category name</label>
                    <input id="name" type="text" name="name" placeholder="Category name" required
                           class="input" aria-label="Category name">
                </div>
                <div>
                    <label class="sr-only" for="description">Description</label>
                    <input id="description" type="text" name="description" placeholder="Description (optional)"
                           class="input" aria-label="Description">
                </div>
                <button type="submit" class="btn-primary !w-full md:!w-auto" aria-label="Add category">
                    <i data-lucide="plus" class="w-4 h-4 -ml-0.5"></i>
                    Add Category
                </button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Description</th>
                        <th scope="col" class="text-center">Products</th>
                        <th scope="col" class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($categories as $category)
                    <tr>
                        <td class="font-medium text-ink">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-md bg-brand-50 text-brand-700 grid place-items-center">
                                    <i data-lucide="tag" class="w-4 h-4"></i>
                                </div>
                                {{ $category->name }}
                            </div>
                        </td>
                        <td class="text-ink-muted">{{ $category->description ?: '—' }}</td>
                        <td class="text-center">
                            <span class="inline-flex items-center justify-center min-w-[2rem] px-2 py-0.5 rounded-full bg-surface-subtle text-sm font-semibold tabular-nums text-ink">
                                {{ $category->products_count }}
                            </span>
                        </td>
                        <td class="text-right">
                            <form action="{{ route('categories.destroy', $category) }}" method="POST" class="inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-sm btn-ghost !px-2 text-status-danger hover:text-status-danger hover:bg-status-danger-soft"
                                        aria-label="Delete category"
                                        onclick="return confirm('Delete this category? Products inside will remain uncategorized.');">
                                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="py-12 text-center">
                            <div class="inline-flex flex-col items-center gap-2">
                                <div class="w-11 h-11 rounded-full bg-surface-subtle text-ink-muted grid place-items-center">
                                    <i data-lucide="tags" class="w-5 h-5"></i>
                                </div>
                                <div class="text-sm font-medium text-ink">No categories yet</div>
                                <div class="text-xs text-ink-subtle">Add your first category using the form above.</div>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($categories->hasPages())
        <div class="card-footer">
            {{ $categories->onEachSide(1)->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
