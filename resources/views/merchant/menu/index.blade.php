<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Menu · MotoBook Merchant</title>
    @vite(['resources/css/app.css', 'resources/css/pages/merchant-menu.css'])
</head>
<body class="merchant-menu-shell">
    <aside class="merchant-sidebar merchant-menu-sidebar">
        <a href="{{ route('merchant.dashboard') }}" class="merchant-brand"><span class="merchant-brand-mark"></span><span><b>Moto</b>Book</span></a>
        <div class="merchant-store-switcher"><span class="merchant-store-kicker">Merchant workspace</span><strong>{{ $store->name }}</strong><span class="merchant-store-status {{ $store->is_open ? 'is-open' : 'is-closed' }}"><span></span>{{ $store->is_open ? 'Open' : 'Closed' }}</span></div>
        <nav class="merchant-nav"><a href="{{ route('merchant.dashboard') }}" class="merchant-nav-link"><span>⌂</span>Dashboard</a><a href="{{ route('merchant.orders.index') }}" class="merchant-nav-link"><span>▤</span>Orders</a><a href="{{ route('merchant.menu.index') }}" class="merchant-nav-link is-active"><span>◈</span>Menu</a><a href="{{ route('merchant.store.edit') }}" class="merchant-nav-link"><span>□</span>Store profile</a><a href="{{ route('merchant.sales.index') }}" class="merchant-nav-link"><span>◒</span>Sales</a></nav>
        <div class="merchant-sidebar-footer"><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="merchant-logout"><span>↪</span>Log out</button></form></div>
    </aside>
    <main class="merchant-menu-main">
        <header class="merchant-menu-header"><div><p class="merchant-eyebrow">Catalog management</p><h1>Menu</h1></div><a href="{{ route('merchant.dashboard') }}" class="merchant-menu-back">← Dashboard</a></header>
        <div class="merchant-menu-content">
            @if (session('status'))<div class="merchant-alert">{{ session('status') }}</div>@endif
            @if ($errors->any())<div class="merchant-error">{{ $errors->first() }}</div>@endif
            <div class="merchant-menu-layout">
                <section>
                    <div class="merchant-menu-heading"><div><span class="merchant-overline">Your catalog</span><h2>{{ $items->count() }} menu item{{ $items->count() === 1 ? '' : 's' }}</h2></div><a href="#new-item" class="merchant-primary-button">+ Add item</a></div>
                    <div class="merchant-item-list">
                        @forelse ($items as $item)
                            <article class="merchant-item-row"><div class="merchant-item-image">@if ($item->image)<img src="{{ asset('storage/'.$item->image) }}" alt="{{ $item->name }}">@else🍽@endif</div><div class="merchant-item-copy"><strong>{{ $item->name }}</strong><small>{{ $item->category?->name ?? 'Uncategorized' }} · {{ $item->description ?: 'No description yet' }}</small><span class="merchant-item-meta">{{ $item->is_available ? 'Available to customers' : 'Hidden from customers' }} @if ($item->prep_time_minutes) · {{ $item->prep_time_minutes }} min prep @endif</span></div><b class="merchant-item-price">₱{{ number_format($item->price, 2) }}</b><a class="merchant-item-edit" href="{{ route('merchant.menu.items.edit', $item) }}">Edit</a><a class="merchant-item-edit" href="{{ route('merchant.menu.items.options', $item) }}">Options</a><form method="POST" action="{{ route('merchant.menu.items.destroy', $item) }}" onsubmit="return confirm('Remove this menu item?')">@csrf @method('DELETE')<button class="merchant-item-delete" type="submit" aria-label="Remove {{ $item->name }}">×</button></form></article>
                        @empty
                            <div class="merchant-empty"><span>◈</span><strong>Your menu is empty</strong><p>Add your first menu item to start receiving orders.</p></div>
                        @endforelse
                    </div>
                </section>
                <aside class="merchant-menu-side">
                    <section class="merchant-form-card" id="new-item"><div class="merchant-form-card-heading"><span class="merchant-overline">{{ $editingItem ? 'Edit item' : 'New item' }}</span><h3>{{ $editingItem ? 'Update menu item' : 'Add to your menu' }}</h3></div><form method="POST" action="{{ $editingItem ? route('merchant.menu.items.update', $editingItem) : route('merchant.menu.items.store') }}" enctype="multipart/form-data">@csrf @if($editingItem) @method('PATCH') @endif<div class="merchant-form-field"><label for="name">Item name</label><input id="name" name="name" required value="{{ old('name', $editingItem?->name) }}" placeholder="e.g. Classic Burger"></div><div class="merchant-form-two"><div class="merchant-form-field"><label for="price">Price</label><input id="price" name="price" type="number" min="0" step="0.01" required value="{{ old('price', $editingItem?->price) }}"></div><div class="merchant-form-field"><label for="menu_category_id">Category</label><select id="menu_category_id" name="menu_category_id"><option value="">Uncategorized</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('menu_category_id', $editingItem?->menu_category_id) == $category->id)>{{ $category->name }}</option>@endforeach</select></div></div><div class="merchant-form-field"><label for="description">Description</label><textarea id="description" name="description" rows="3" placeholder="Tell customers what is inside">{{ old('description', $editingItem?->description) }}</textarea></div><div class="merchant-form-two"><div class="merchant-form-field"><label for="prep_time_minutes">Prep time</label><input id="prep_time_minutes" name="prep_time_minutes" type="number" min="0" max="1440" value="{{ old('prep_time_minutes', $editingItem?->prep_time_minutes) }}" placeholder="Minutes"></div><div class="merchant-form-field"><label for="highlight_badge">Badge</label><input id="highlight_badge" name="highlight_badge" value="{{ old('highlight_badge', $editingItem?->highlight_badge) }}" placeholder="Best seller"></div></div><div class="merchant-form-field"><label for="image">Item image</label><input id="image" name="image" type="file" accept="image/*"> </div><label class="merchant-check"><input type="checkbox" name="is_available" value="1" @checked(old('is_available', $editingItem ? $editingItem->is_available : true))> Available for customer orders</label><button class="merchant-form-submit" type="submit">{{ $editingItem ? 'Save item' : 'Add item' }} <span>→</span></button>@if($editingItem)<a class="merchant-form-cancel" href="{{ route('merchant.menu.index') }}">Cancel editing</a>@endif</form></section>
                    <section class="merchant-form-card"><div class="merchant-form-card-heading"><span class="merchant-overline">Organize</span><h3>Categories</h3></div><form method="POST" action="{{ route('merchant.menu.categories.store') }}" class="merchant-category-add">@csrf<input name="name" required placeholder="New category"><button type="submit">+</button></form><div class="merchant-category-list">@forelse($categories as $category)<div><span>{{ $category->name }} <small>{{ $category->menu_items_count }}</small></span><form method="POST" action="{{ route('merchant.menu.categories.destroy', $category) }}" onsubmit="return confirm('Remove this category?')">@csrf @method('DELETE')<button type="submit">×</button></form></div>@empty<p>No categories yet.</p>@endforelse</div></section>
                </aside>
            </div>
        </div>
    </main>
</body>
</html>
