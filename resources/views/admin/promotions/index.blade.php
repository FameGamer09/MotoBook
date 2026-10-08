@extends('layouts.admin', ['pageCss' => 'admin-dashboard'])
@section('title', 'Promotions · MotoBook Admin')
@section('page-title', 'Promotions')
@section('content')
    @if ($errors->any())
        <div class="mb-5 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <section class="mb-8 rounded-lg border border-gray-200 bg-white p-5">
        <h2 class="mb-4 text-lg font-semibold">Create promotion</h2>
        <form method="POST" action="{{ route('admin.promotions.store') }}" class="grid grid-cols-1 gap-4 md:grid-cols-2">
            @csrf
            <label class="grid gap-1 text-sm">Code (optional)
                <input name="code" value="{{ old('code') }}" maxlength="64" class="rounded border-gray-300" placeholder="WELCOME10">
            </label>
            <label class="grid gap-1 text-sm">Title
                <input name="title" value="{{ old('title') }}" required maxlength="255" class="rounded border-gray-300">
            </label>
            <label class="grid gap-1 text-sm">Discount type
                <select name="discount_type" required class="rounded border-gray-300">
                    <option value="fixed">Fixed amount</option>
                    <option value="percentage">Percentage</option>
                </select>
            </label>
            <label class="grid gap-1 text-sm">Discount value
                <input type="number" name="discount_value" min="0.01" max="999999.99" step="0.01" required class="rounded border-gray-300">
            </label>
            <label class="grid gap-1 text-sm">Minimum order amount
                <input type="number" name="min_order_amount" min="0" max="999999.99" step="0.01" value="0" class="rounded border-gray-300">
            </label>
            <label class="grid gap-1 text-sm">Maximum discount (optional)
                <input type="number" name="max_discount_amount" min="0" max="999999.99" step="0.01" class="rounded border-gray-300">
            </label>
            <label class="grid gap-1 text-sm">Store (blank for all stores)
                <select name="store_id" class="rounded border-gray-300">
                    <option value="">All stores</option>
                    @foreach ($stores as $store)
                        <option value="{{ $store->id }}">{{ $store->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="grid gap-1 text-sm">Usage limit (optional)
                <input type="number" name="usage_limit" min="1" class="rounded border-gray-300">
            </label>
            <label class="grid gap-1 text-sm">Starts at (optional)
                <input type="datetime-local" name="starts_at" class="rounded border-gray-300">
            </label>
            <label class="grid gap-1 text-sm">Ends at (optional)
                <input type="datetime-local" name="ends_at" class="rounded border-gray-300">
            </label>
            <label class="grid gap-1 text-sm md:col-span-2">Description (optional)
                <textarea name="description" maxlength="2000" rows="2" class="rounded border-gray-300"></textarea>
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_active" value="1" checked class="rounded border-gray-300">
                Active
            </label>
            <div class="md:col-span-2">
                <x-primary-button>{{ __('Create promotion') }}</x-primary-button>
            </div>
        </form>
    </section>

    <section class="grid gap-4">
        @forelse ($promotions as $promotion)
            <article class="rounded-lg border border-gray-200 bg-white p-5">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-semibold">{{ $promotion->title }} @if ($promotion->code)<span class="font-mono text-sm text-gray-600">{{ $promotion->code }}</span>@endif</h2>
                        <p class="text-sm text-gray-600">
                            {{ $promotion->store?->name ?? 'All stores' }} ·
                            {{ $promotion->discount_type === 'percentage' ? number_format($promotion->discount_value, 2).'%' : '₱'.number_format($promotion->discount_value, 2) }} ·
                            {{ $promotion->used_count }}{{ $promotion->usage_limit ? ' / '.$promotion->usage_limit : '' }} uses
                        </p>
                    </div>
                    <form method="POST" action="{{ route('admin.promotions.toggle', $promotion) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="table-link {{ $promotion->is_active ? 'danger' : '' }}">
                            {{ $promotion->is_active ? 'Deactivate' : 'Activate' }}
                        </button>
                    </form>
                </div>
                <form method="POST" action="{{ route('admin.promotions.update', $promotion) }}" class="grid grid-cols-1 gap-3 border-t border-gray-100 pt-4 md:grid-cols-2">
                    @csrf
                    @method('PATCH')
                    <input name="code" value="{{ $promotion->code }}" maxlength="64" class="rounded border-gray-300" placeholder="Code (optional)">
                    <input name="title" value="{{ $promotion->title }}" required maxlength="255" class="rounded border-gray-300" aria-label="Promotion title">
                    <select name="discount_type" class="rounded border-gray-300" aria-label="Discount type">
                        <option value="fixed" @selected($promotion->discount_type === 'fixed')>Fixed amount</option>
                        <option value="percentage" @selected($promotion->discount_type === 'percentage')>Percentage</option>
                    </select>
                    <input type="number" name="discount_value" min="0.01" max="999999.99" step="0.01" value="{{ $promotion->discount_value }}" required class="rounded border-gray-300" aria-label="Discount value">
                    <input type="number" name="min_order_amount" min="0" max="999999.99" step="0.01" value="{{ $promotion->min_order_amount }}" class="rounded border-gray-300" aria-label="Minimum order amount">
                    <input type="number" name="max_discount_amount" min="0" max="999999.99" step="0.01" value="{{ $promotion->max_discount_amount }}" class="rounded border-gray-300" aria-label="Maximum discount">
                    <select name="store_id" class="rounded border-gray-300" aria-label="Promotion store">
                        <option value="">All stores</option>
                        @foreach ($stores as $store)
                            <option value="{{ $store->id }}" @selected($promotion->store_id === $store->id)>{{ $store->name }}</option>
                        @endforeach
                    </select>
                    <input type="number" name="usage_limit" min="1" value="{{ $promotion->usage_limit }}" class="rounded border-gray-300" placeholder="Usage limit">
                    <input type="datetime-local" name="starts_at" value="{{ $promotion->starts_at?->format('Y-m-d\TH:i') }}" class="rounded border-gray-300" aria-label="Starts at">
                    <input type="datetime-local" name="ends_at" value="{{ $promotion->ends_at?->format('Y-m-d\TH:i') }}" class="rounded border-gray-300" aria-label="Ends at">
                    <textarea name="description" maxlength="2000" rows="2" class="rounded border-gray-300 md:col-span-2" placeholder="Description">{{ $promotion->description }}</textarea>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="is_active" value="1" @checked($promotion->is_active) class="rounded border-gray-300">
                        Active
                    </label>
                    <div class="md:col-span-2">
                        <x-primary-button>{{ __('Save promotion') }}</x-primary-button>
                    </div>
                </form>
            </article>
        @empty
            <p class="text-sm text-gray-600">No promotions have been created.</p>
        @endforelse
    </section>

    <div class="mt-5">{{ $promotions->links() }}</div>
@endsection
