<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->get('search');
        $categoryId = $request->get('category');

        $products = Product::with('category')
            ->when($search, fn($q) => $q->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"))
            ->when($categoryId, fn($q) => $q->where('category_id', $categoryId))
            ->orderByDesc('id')
            ->paginate(10);

        $categories = Category::all();

        return view('products.index', compact('products', 'categories', 'search', 'categoryId'));
    }

    public function create(): View
    {
        $categories = Category::all();
        return view('products.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'sku' => 'required|string|max:255|unique:products,sku',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'quantity' => 'required|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $product = Product::create($validated);

        // Record inventory movement
        if ($product->quantity > 0) {
            $product->inventoryMovements()->create([
                'user_id' => auth()->id(),
                'type' => 'in',
                'quantity' => $product->quantity,
                'previous_quantity' => 0,
                'new_quantity' => $product->quantity,
                'notes' => 'Initial stock',
            ]);
        }

        return redirect()->route('products.index')->with('success', 'Product created successfully.');
    }

    public function show(Product $product): View
    {
        $product->load(['category', 'inventoryMovements.user']);
        return view('products.show', compact('product'));
    }

    public function edit(Product $product): View
    {
        $categories = Category::all();
        return view('products.edit', compact('product', 'categories'));
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'sku' => 'required|string|max:255|unique:products,sku,' . $product->id,
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'quantity' => 'required|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $previousQuantity = $product->quantity;
        $product->update($validated);

        // Record inventory movement if quantity changed
        if ($product->quantity !== $previousQuantity) {
            $type = $product->quantity > $previousQuantity ? 'in' : 'out';
            $quantityChange = abs($product->quantity - $previousQuantity);

            $product->inventoryMovements()->create([
                'user_id' => auth()->id(),
                'type' => $type,
                'quantity' => $quantityChange,
                'previous_quantity' => $previousQuantity,
                'new_quantity' => $product->quantity,
                'notes' => 'Updated via product edit',
            ]);
        }

        return redirect()->route('products.index')->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return redirect()->route('products.index')->with('success', 'Product deleted successfully.');
    }
}