@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Product Selection -->
        <div class="lg:col-span-2 space-y-4">
            <div class="flex gap-4">
                <div class="flex-1 relative">
                    <input type="text" id="searchProducts" placeholder="Search products..." 
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-lg">
                    <div id="searchResults" class="hidden absolute z-10 w-full mt-1 bg-white border rounded-lg shadow-lg max-h-64 overflow-y-auto"></div>
                </div>
            </div>

            <!-- Category Tabs -->
            <div class="flex gap-2 overflow-x-auto pb-2" id="categoryTabs">
                <button class="category-btn px-4 py-2 bg-indigo-600 text-white rounded-lg whitespace-nowrap" data-category="all">
                    All
                </button>
                @foreach($categories as $category)
                <button class="category-btn px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 whitespace-nowrap" data-category="{{ $category->id }}">
                    {{ $category->name }}
                </button>
                @endforeach
            </div>

            <!-- Products Grid -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4" id="productsGrid">
                @foreach($products as $product)
                <button type="button" class="product-card p-4 bg-white border-2 border-gray-200 rounded-lg hover:border-indigo-500 hover:shadow-md transition text-left" 
                    data-id="{{ $product->id }}" data-name="{{ $product->name }}" data-price="{{ $product->price }}" data-stock="{{ $product->quantity }}">
                    <div class="font-medium text-gray-900 text-sm truncate">{{ $product->name }}</div>
                    <div class="text-xs text-gray-500 truncate">{{ $product->category->name }}</div>
                    <div class="mt-2 flex items-center justify-between">
                        <span class="text-lg font-bold text-indigo-600">${{ number_format($product->price, 2) }}</span>
                        <span class="text-xs {{ $product->quantity <= 5 ? 'text-red-600' : 'text-gray-500' }}">{{ $product->quantity }} left</span>
                    </div>
                </button>
                @endforeach
            </div>
        </div>

        <!-- Cart -->
        <div class="bg-white rounded-lg shadow border h-fit sticky top-4">
            <div class="p-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Current Sale</h2>
            </div>
            
            <div class="p-4">
                <div id="cartItems" class="space-y-3 max-h-64 overflow-y-auto mb-4">
                    <p class="text-center text-gray-500 py-4">No items in cart</p>
                </div>

                <div class="border-t pt-4 space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Subtotal:</span>
                        <span class="font-medium" id="cartSubtotal">$0.00</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Tax (0%):</span>
                        <span class="font-medium" id="cartTax">$0.00</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Discount:</span>
                        <input type="number" id="cartDiscount" value="0" min="0" step="0.01" 
                            class="w-20 text-right border rounded px-2 py-1">
                    </div>
                    <div class="flex justify-between text-lg font-bold pt-2 border-t">
                        <span>Total:</span>
                        <span id="cartTotal">$0.00</span>
                    </div>
                </div>

                <div class="mt-4 space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                        <select id="paymentMethod" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="digital">Digital</option>
                        </select>
                    </div>
                    <button type="button" id="completeSale" class="w-full px-4 py-3 bg-green-600 text-white font-semibold rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed">
                        Complete Sale
                    </button>
                    <button type="button" id="clearCart" class="w-full px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Clear Cart
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
let cart = [];

function updateCart() {
    const cartItemsEl = document.getElementById('cartItems');
    const cartSubtotalEl = document.getElementById('cartSubtotal');
    const cartTaxEl = document.getElementById('cartTax');
    const cartDiscountEl = document.getElementById('cartDiscount');
    const cartTotalEl = document.getElementById('cartTotal');
    const completeSaleBtn = document.getElementById('completeSale');

    if (cart.length === 0) {
        cartItemsEl.innerHTML = '<p class="text-center text-gray-500 py-4">No items in cart</p>';
        cartSubtotalEl.textContent = '$0.00';
        cartTaxEl.textContent = '$0.00';
        cartTotalEl.textContent = '$0.00';
        completeSaleBtn.disabled = true;
        return;
    }

    let subtotal = 0;
    let html = '';

    cart.forEach((item, index) => {
        subtotal += item.price * item.quantity;
        html += `
            <div class="flex items-center justify-between bg-gray-50 p-2 rounded">
                <div class="flex-1">
                    <div class="font-medium text-sm">${item.name}</div>
                    <div class="text-xs text-gray-500">$${item.price.toFixed(2)} × ${item.quantity}</div>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="updateQuantity(${index}, -1)" class="w-6 h-6 bg-gray-200 rounded hover:bg-gray-300">-</button>
                    <span class="w-6 text-center">${item.quantity}</span>
                    <button type="button" onclick="updateQuantity(${index}, 1)" class="w-6 h-6 bg-gray-200 rounded hover:bg-gray-300">+</button>
                    <button type="button" onclick="removeFromCart(${index})" class="ml-2 text-red-600 hover:text-red-800">×</button>
                </div>
            </div>
        `;
    });

    cartItemsEl.innerHTML = html;

    const discount = parseFloat(cartDiscountEl.value) || 0;
    const tax = 0;
    const total = subtotal - discount + tax;

    cartSubtotalEl.textContent = '$' + subtotal.toFixed(2);
    cartTaxEl.textContent = '$' + tax.toFixed(2);
    cartTotalEl.textContent = '$' + total.toFixed(2);
    completeSaleBtn.disabled = false;
}

function addToCart(id, name, price, stock) {
    const existing = cart.find(item => item.id === id);
    if (existing) {
        if (existing.quantity < stock) {
            existing.quantity++;
        } else {
            alert('Not enough stock!');
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

// Product search
document.getElementById('searchProducts').addEventListener('input', function(e) {
    const query = e.target.value;
    const resultsEl = document.getElementById('searchResults');
    
    if (query.length < 2) {
        resultsEl.classList.add('hidden');
        return;
    }

    // Filter products client-side
    const products = document.querySelectorAll('.product-card');
    const matching = [];
    
    products.forEach(card => {
        const name = card.dataset.name.toLowerCase();
        if (name.includes(query.toLowerCase())) {
            matching.push(card);
        }
    });

    if (matching.length > 0) {
        let html = '';
        matching.slice(0, 5).forEach(card => {
            html += `
                <div class="p-3 hover:bg-gray-50 cursor-pointer border-b" 
                    onclick="addToCart(${card.dataset.id}, '${card.dataset.name}', ${card.dataset.price}, ${card.dataset.stock}); document.getElementById('searchResults').classList.add('hidden'); document.getElementById('searchProducts').value = '';">
                    <div class="font-medium">${card.dataset.name}</div>
                    <div class="text-sm text-gray-500">$${parseFloat(card.dataset.price).toFixed(2)} - Stock: ${card.dataset.stock}</div>
                </div>
            `;
        });
        resultsEl.innerHTML = html;
        resultsEl.classList.remove('hidden');
    } else {
        resultsEl.classList.add('hidden');
    }
});

// Product grid click handlers
document.querySelectorAll('.product-card').forEach(card => {
    card.addEventListener('click', function() {
        addToCart(
            parseInt(this.dataset.id),
            this.dataset.name,
            parseFloat(this.dataset.price),
            parseInt(this.dataset.stock)
        );
    });
});

// Category filter
document.querySelectorAll('.category-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.category-btn').forEach(b => {
            b.classList.remove('bg-indigo-600', 'text-white');
            b.classList.add('bg-gray-200', 'text-gray-700');
        });
        this.classList.remove('bg-gray-200', 'text-gray-700');
        this.classList.add('bg-indigo-600', 'text-white');

        const categoryId = this.dataset.category;
        const products = document.querySelectorAll('.product-card');
        
        products.forEach(card => {
            if (categoryId === 'all') {
                card.style.display = 'block';
            } else {
                // Note: In real implementation, you'd have data-category on cards
                card.style.display = 'block';
            }
        });
    });
});

// Discount change
document.getElementById('cartDiscount').addEventListener('input', updateCart);

// Clear cart
document.getElementById('clearCart').addEventListener('click', clearCart);

// Complete sale
document.getElementById('completeSale').addEventListener('click', function() {
    if (cart.length === 0) return;

    const items = cart.map(item => ({
        product_id: item.id,
        quantity: item.quantity
    }));

    const paymentMethod = document.getElementById('paymentMethod').value;
    const discount = parseFloat(document.getElementById('cartDiscount').value) || 0;

    fetch('{{ route('pos.process') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            items,
            payment_method: paymentMethod,
            discount
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Sale completed! Invoice: ' + data.transaction.invoice_number);
            clearCart();
            window.location.href = '{{ route('transactions.show', ':id') }}'.replace(':id', data.transaction.id);
        } else {
            alert(data.error || 'Error processing sale');
        }
    })
    .catch(error => {
        alert('Error: ' + error.message);
    });
});

// Initialize
updateCart();
</script>
@endpush
@endsection