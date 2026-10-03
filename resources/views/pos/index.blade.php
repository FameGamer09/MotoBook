@extends('layouts.app')

@section('title', 'Point of Sale')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-[1fr_380px] gap-5">
    <div class="space-y-4 min-w-0">
        <div class="card p-4">
            <div class="relative max-w-2xl mx-auto">
                <i data-lucide="search" class="w-4 h-4 text-ink-muted absolute left-3.5 top-1/2 -translate-y-1/2"></i>
                <input type="text" id="searchProducts" placeholder="Search products by name or SKU..."
                       class="input !pl-10 !py-2.5 text-sm"
                       autocomplete="off"
                       aria-label="Search products">
                <div id="searchResults" class="hidden absolute z-20 w-full mt-1.5 rounded-lg bg-white border border-surface-border shadow-md max-h-72 overflow-y-auto" role="listbox"></div>
            </div>
        </div>

        <div class="card p-4">
            <div class="flex items-center justify-between mb-3.5">
                <div class="flex items-center gap-2">
                    <i data-lucide="layout-grid" class="w-4 h-4 text-ink-muted"></i>
                    <h3 class="text-sm font-semibold text-ink tracking-tight">Catalog</h3>
                </div>
                <span id="productCount" class="text-xs text-ink-subtle"></span>
            </div>

            <div class="flex gap-2 overflow-x-auto pb-2 -mx-1 px-1" id="categoryTabs" role="tablist" aria-label="Product categories">
                <button type="button" class="category-btn shrink-0 px-3 py-1.5 text-xs font-medium rounded-md bg-brand-600 text-white shadow-sm transition-colors"
                        data-category="all" role="tab" aria-selected="true">
                    All Products
                </button>
                @foreach($categories as $category)
                <button type="button" class="category-btn shrink-0 px-3 py-1.5 text-xs font-medium rounded-md bg-white text-ink-muted border border-surface-border hover:bg-surface-muted hover:text-ink transition-colors"
                        data-category="{{ $category->id }}" role="tab" aria-selected="false">
                    {{ $category->name }}
                </button>
                @endforeach
            </div>

            <div class="mt-4 grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-3" id="productsGrid">
                @foreach($products as $product)
                <button type="button"
                        class="product-card group relative text-left p-4 rounded-lg border border-surface-border bg-white hover:border-brand-500 hover:shadow-sm transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-brand-500"
                        data-id="{{ $product->id }}"
                        data-name="{{ $product->name }}"
                        data-category="{{ $product->category_id }}"
                        data-price="{{ $product->price }}"
                        data-stock="{{ $product->quantity }}"
                        data-active="{{ $product->is_active ? '1' : '0' }}"
                        aria-label="Add {{ $product->name }} to cart"
                        {{ $product->is_active ? '' : 'disabled' }}>
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <div class="w-9 h-9 rounded-md bg-brand-50 text-brand-700 grid place-items-center group-hover:bg-brand-100 transition-colors shrink-0">
                            <i data-lucide="package" class="w-4 h-4"></i>
                        </div>
                        @if(!$product->is_active)
                            <span class="badge-neutral !py-0">Off</span>
                        @elseif($product->isLowStock())
                            <span class="badge-warning !py-0">Low</span>
                        @endif
                    </div>
                    <div class="font-medium text-sm text-ink truncate">{{ $product->name }}</div>
                    <div class="text-[11px] text-ink-subtle truncate">{{ $product->category->name }}</div>
                    <div class="mt-3 flex items-center justify-between">
                        <span class="text-base font-semibold text-brand-700 tabular-nums">${{ number_format($product->price, 2) }}</span>
                        <span class="text-[11px] {{ $product->quantity <= 5 ? 'text-status-danger' : 'text-ink-muted' }} tabular-nums">
                            {{ $product->quantity }} left
                        </span>
                    </div>
                </button>
                @endforeach
            </div>
        </div>
    </div>

    <aside class="space-y-4">
        <div class="card sticky top-6">
            <div class="card-header !py-4">
                <div class="flex items-center gap-2">
                    <div class="w-9 h-9 rounded-lg bg-brand-600/10 text-brand-700 grid place-items-center">
                        <i data-lucide="shopping-cart" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-ink tracking-tight">Current Sale</h3>
                        <p class="text-[11px] text-ink-subtle"><span id="cartItemCount">0</span> items in cart</p>
                    </div>
                </div>
                <button type="button" id="clearCart" class="btn-sm btn-ghost !px-2 text-status-danger hover:text-status-danger hover:bg-status-danger-soft" aria-label="Clear cart">
                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                    Clear
                </button>
            </div>

            <div class="p-4">
                <div id="cartItems" class="space-y-2 max-h-72 overflow-y-auto mb-4 pr-1">
                    <div class="py-8 flex flex-col items-center gap-2 text-center">
                        <div class="w-11 h-11 rounded-full bg-surface-subtle text-ink-muted grid place-items-center">
                            <i data-lucide="shopping-basket" class="w-5 h-5"></i>
                        </div>
                        <div class="text-sm font-medium text-ink">Cart is empty</div>
                        <div class="text-xs text-ink-subtle">Click a product to add it to the sale.</div>
                    </div>
                </div>

                <div class="space-y-2 border-t border-surface-border pt-4">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-ink-muted">Subtotal</span>
                        <span class="font-medium tabular-nums text-ink" id="cartSubtotal">$0.00</span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-ink-muted">Tax (0%)</span>
                        <span class="font-medium tabular-nums text-ink" id="cartTax">$0.00</span>
                    </div>
                    <div class="flex items-center justify-between text-sm gap-2">
                        <label for="cartDiscount" class="text-ink-muted shrink-0">Discount</label>
                        <div class="relative flex-1 max-w-[130px]">
                            <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-ink-muted text-xs">$</span>
                            <input type="number" id="cartDiscount" value="0" min="0" step="0.01"
                                   class="input !pl-6 !py-1.5 text-sm text-right !rounded-md"
                                   aria-label="Discount amount">
                        </div>
                    </div>
                    <div class="flex items-center justify-between pt-3 border-t border-surface-border">
                        <span class="text-sm font-semibold text-ink">Total</span>
                        <span class="text-xl font-bold tabular-nums text-ink" id="cartTotal">$0.00</span>
                    </div>
                </div>

                <div class="mt-5 space-y-3">
                    <div>
                        <label for="paymentMethod" class="input-label !mb-1.5">Payment Method</label>
                        <select id="paymentMethod" class="select" aria-label="Payment method">
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="digital">Digital Wallet</option>
                        </select>
                    </div>
                    <button type="button" id="completeSale" class="btn-primary w-full !py-2.5" disabled aria-label="Complete the sale">
                        <i data-lucide="check-check" class="w-4 h-4 -ml-0.5"></i>
                        Complete Sale
                    </button>
                    <button type="button" id="holdSale" class="btn-secondary w-full" aria-label="Hold sale for later">
                        <i data-lucide="bookmark-minus" class="w-4 h-4"></i>
                        Hold Sale
                    </button>
                </div>
            </div>
        </div>
    </aside>
</div>

@push('scripts')
<script>
let cart = [];

function cartBadgeCount() {
    const count = cart.reduce((sum, item) => sum + item.quantity, 0);
    const el = document.getElementById('cartItemCount');
    if (el) el.textContent = count;
}

function updateCart() {
    const cartItemsEl = document.getElementById('cartItems');
    const cartSubtotalEl = document.getElementById('cartSubtotal');
    const cartTaxEl = document.getElementById('cartTax');
    const cartDiscountEl = document.getElementById('cartDiscount');
    const cartTotalEl = document.getElementById('cartTotal');
    const completeSaleBtn = document.getElementById('completeSale');

    cartBadgeCount();

    if (cart.length === 0) {
        cartItemsEl.innerHTML = `
            <div class="py-8 flex flex-col items-center gap-2 text-center">
                <div class="w-11 h-11 rounded-full bg-surface-subtle text-ink-muted grid place-items-center">
                    <i data-lucide="shopping-basket" class="w-5 h-5"></i>
                </div>
                <div class="text-sm font-medium text-ink">Cart is empty</div>
                <div class="text-xs text-ink-subtle">Click a product to add it to the sale.</div>
            </div>`;
        cartSubtotalEl.textContent = '$0.00';
        cartTaxEl.textContent = '$0.00';
        cartTotalEl.textContent = '$0.00';
        completeSaleBtn.disabled = true;
        if (window.lucide) window.lucide.createIcons();
        return;
    }

    let subtotal = 0;
    let html = '';

    cart.forEach((item, index) => {
        subtotal += item.price * item.quantity;
        html += `
            <div class="group flex items-center gap-2.5 p-2.5 rounded-lg bg-surface-muted/60 border border-surface-border">
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-medium text-ink truncate">${item.name}</div>
                    <div class="text-xs text-ink-muted mt-0.5 tabular-nums">$${item.price.toFixed(2)} × ${item.quantity}</div>
                </div>
                <div class="flex items-center gap-1">
                    <button type="button" onclick="updateQuantity(${index}, -1)"
                            class="w-7 h-7 grid place-items-center rounded-md bg-white border border-surface-border text-ink-muted hover:text-ink hover:bg-surface-muted transition-colors"
                            aria-label="Decrease quantity">
                        <i data-lucide="minus" class="w-3.5 h-3.5"></i>
                    </button>
                    <span class="w-8 text-center text-sm font-semibold tabular-nums text-ink">${item.quantity}</span>
                    <button type="button" onclick="updateQuantity(${index}, 1)"
                            class="w-7 h-7 grid place-items-center rounded-md bg-white border border-surface-border text-ink-muted hover:text-ink hover:bg-surface-muted transition-colors"
                            aria-label="Increase quantity">
                        <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                    </button>
                </div>
                <div class="text-right shrink-0 w-16">
                    <div class="text-sm font-semibold text-ink tabular-nums">$${(item.price * item.quantity).toFixed(2)}</div>
                    <button type="button" onclick="removeFromCart(${index})"
                            class="text-[11px] text-status-danger hover:underline transition-colors"
                            aria-label="Remove item">
                        Remove
                    </button>
                </div>
            </div>
        `;
    });

    cartItemsEl.innerHTML = html;

    const discount = parseFloat(cartDiscountEl.value) || 0;
    const tax = 0;
    const total = Math.max(0, subtotal - discount + tax);

    cartSubtotalEl.textContent = '$' + subtotal.toFixed(2);
    cartTaxEl.textContent = '$' + tax.toFixed(2);
    cartTotalEl.textContent = '$' + total.toFixed(2);
    completeSaleBtn.disabled = false;
    if (window.lucide) window.lucide.createIcons();
}

function addToCart(id, name, price, stock) {
    const existing = cart.find(item => item.id === id);
    if (existing) {
        if (existing.quantity < stock) {
            existing.quantity++;
        } else {
            alert('Not enough stock available.');
        }
    } else {
        cart.push({ id, name, price, quantity: 1, stock });
    }
    updateCart();
}

function updateQuantity(index, change) {
    const item = cart[index];
    const newQty = item.quantity + change;
    if (newQty > 0 && newQty <= item.stock) {
        item.quantity = newQty;
        updateCart();
    } else if (newQty <= 0) {
        removeFromCart(index);
    }
}

function removeFromCart(index) {
    cart.splice(index, 1);
    updateCart();
}

function clearCart() {
    cart = [];
    document.getElementById('cartDiscount').value = 0;
    updateCart();
}

document.getElementById('searchProducts').addEventListener('input', function (e) {
    const query = e.target.value.trim().toLowerCase();
    const resultsEl = document.getElementById('searchResults');

    if (query.length < 2) {
        resultsEl.classList.add('hidden');
        return;
    }

    const matching = [];
    document.querySelectorAll('.product-card').forEach(card => {
        if (card.dataset.active !== '1') return;
        if (card.dataset.name.toLowerCase().includes(query)) {
            matching.push(card);
        }
    });

    if (matching.length > 0) {
        let html = '';
        matching.slice(0, 6).forEach(card => {
            html += `
                <button type="button" role="option"
                    class="w-full flex items-center gap-3 px-3 py-2.5 hover:bg-surface-muted border-b border-surface-border last:border-0 transition-colors text-left"
                    onclick="addToCart(${card.dataset.id}, '${card.dataset.name.replace(/'/g, '&#39;')}', ${card.dataset.price}, ${card.dataset.stock});
                             document.getElementById('searchResults').classList.add('hidden');
                             document.getElementById('searchProducts').value = '';">
                    <div class="w-8 h-8 rounded-md bg-brand-50 text-brand-700 grid place-items-center shrink-0">
                        <i data-lucide="package" class="w-4 h-4"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium text-ink truncate">${card.dataset.name}</div>
                        <div class="text-[11px] text-ink-subtle tabular-nums">$${parseFloat(card.dataset.price).toFixed(2)} · Stock: ${card.dataset.stock}</div>
                    </div>
                    <i data-lucide="plus-circle" class="w-4 h-4 text-brand-600 shrink-0"></i>
                </button>
            `;
        });
        resultsEl.innerHTML = html;
        resultsEl.classList.remove('hidden');
        if (window.lucide) window.lucide.createIcons();
    } else {
        resultsEl.innerHTML = `
            <div class="px-3 py-4 text-center text-sm text-ink-muted">
                No products match &quot;${query}&quot;.
            </div>`;
        resultsEl.classList.remove('hidden');
    }
});

document.addEventListener('click', function (e) {
    const search = document.getElementById('searchProducts');
    const results = document.getElementById('searchResults');
    if (search && results && !search.contains(e.target) && !results.contains(e.target)) {
        results.classList.add('hidden');
    }
});

document.querySelectorAll('.product-card').forEach(card => {
    card.addEventListener('click', function () {
        if (this.dataset.active !== '1') return;
        addToCart(
            parseInt(this.dataset.id),
            this.dataset.name,
            parseFloat(this.dataset.price),
            parseInt(this.dataset.stock)
        );
    });
});

document.querySelectorAll('.category-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        document.querySelectorAll('.category-btn').forEach(b => {
            b.classList.remove('bg-brand-600', 'text-white', 'shadow-sm');
            b.classList.add('bg-white', 'text-ink-muted', 'border', 'border-surface-border', 'hover:bg-surface-muted', 'hover:text-ink');
            b.setAttribute('aria-selected', 'false');
        });
        this.classList.remove('bg-white', 'text-ink-muted', 'border', 'border-surface-border', 'hover:bg-surface-muted', 'hover:text-ink');
        this.classList.add('bg-brand-600', 'text-white', 'shadow-sm');
        this.setAttribute('aria-selected', 'true');

        const categoryId = this.dataset.category;
        let visible = 0;
        document.querySelectorAll('.product-card').forEach(card => {
            const match = categoryId === 'all' || String(card.dataset.category) === String(categoryId);
            card.style.display = match ? '' : 'none';
            if (match) visible++;
        });
        const countEl = document.getElementById('productCount');
        if (countEl) countEl.textContent = visible + ' products';
    });
});

document.getElementById('cartDiscount').addEventListener('input', updateCart);
document.getElementById('clearCart').addEventListener('click', clearCart);

document.getElementById('completeSale').addEventListener('click', function () {
    if (cart.length === 0) return;

    const items = cart.map(item => ({
        product_id: item.id,
        quantity: item.quantity
    }));

    const paymentMethod = document.getElementById('paymentMethod').value;
    const discount = parseFloat(document.getElementById('cartDiscount').value) || 0;

    this.disabled = true;
    const originalLabel = this.innerHTML;
    this.innerHTML = `<i data-lucide="loader-circle" class="w-4 h-4 -ml-0.5 animate-spin"></i> Processing...`;
    if (window.lucide) window.lucide.createIcons();

    fetch('{{ route('pos.processSale') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ items, payment_method: paymentMethod, discount })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Sale completed! Invoice: ' + data.transaction.invoice_number);
            clearCart();
            window.location.href = '{{ route('transactions.show', ':id') }}'.replace(':id', data.transaction.id);
        } else {
            alert(data.error || 'Error processing sale.');
            this.disabled = false;
            this.innerHTML = originalLabel;
            if (window.lucide) window.lucide.createIcons();
        }
    }.bind(this))
    .catch(error => {
        alert('Error: ' + error.message);
        this.disabled = false;
        this.innerHTML = originalLabel;
        if (window.lucide) window.lucide.createIcons();
    });
});

(function initCount() {
    const all = document.querySelectorAll('.product-card[data-active="1"]');
    const countEl = document.getElementById('productCount');
    if (countEl) countEl.textContent = all.length + ' products';
})();

updateCart();
</script>
@endpush
@endsection
