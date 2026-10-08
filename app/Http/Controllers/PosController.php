<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PosController extends Controller
{
    public function index(): View
    {
        $products = Product::with('category')
            ->where('is_active', true)
            ->where('quantity', '>', 0)
            ->orderBy('name')
            ->get();

        $categories = Category::with('products')->get();

        return view('pos.index', compact('products', 'categories'));
    }

    public function search(Request $request)
    {
        $search = $request->get('q');

        $products = Product::with('category')
            ->where('is_active', true)
            ->where('quantity', '>', 0)
            ->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            })
            ->limit(10)
            ->get();

        return response()->json($products);
    }

    public function processSale(Request $request)
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'payment_method' => ['required', 'in:cash,card,digital'],
            'discount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0'],
        ]);

        $items = collect($validated['items'])
            ->groupBy('product_id')
            ->map(fn ($rows, $productId) => [
                'product_id' => (int) $productId,
                'quantity' => $rows->sum('quantity'),
            ])
            ->sortKeys()
            ->values();
        $paymentMethod = $validated['payment_method'];
        $discount = $validated['discount'] ?? 0;

        return DB::transaction(function () use ($items, $paymentMethod, $discount) {
            $subtotal = 0;
            $transactionItems = [];
            $products = [];

            foreach ($items as $item) {
                $product = Product::query()
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->find($item['product_id']);

                if (! $product) {
                    throw ValidationException::withMessages([
                        'items' => 'One of the selected products is no longer available.',
                    ]);
                }

                if ($product->quantity < $item['quantity']) {
                    throw ValidationException::withMessages([
                        'items' => "Insufficient stock for {$product->name}. Available: {$product->quantity}",
                    ]);
                }

                $itemSubtotal = round((float) $product->price * $item['quantity'], 2);
                $subtotal += $itemSubtotal;
                $products[$product->id] = $product;

                $transactionItems[] = [
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->price,
                    'subtotal' => $itemSubtotal,
                ];
            }

            $subtotal = round($subtotal, 2);
            $discount = round((float) $discount, 2);

            if ($discount > $subtotal) {
                throw ValidationException::withMessages([
                    'discount' => 'The discount cannot exceed the sale subtotal.',
                ]);
            }

            $tax = 0;
            $total = $subtotal - $discount + $tax;

            // Create transaction
            $transaction = Transaction::create([
                'user_id' => auth()->id(),
                'invoice_number' => Transaction::generateInvoiceNumber(),
                'subtotal' => $subtotal,
                'tax' => $tax,
                'discount' => $discount,
                'total' => $total,
                'status' => 'completed',
                'payment_method' => $paymentMethod,
            ]);

            // Create transaction items and update inventory
            foreach ($transactionItems as $item) {
                $product = $products[$item['product_id']];
                $previousQuantity = $product->quantity;

                // Create transaction item
                $transaction->items()->create($item);

                // Update product quantity
                $product->quantity -= $item['quantity'];
                $product->save();

                // Record inventory movement
                $product->inventoryMovements()->create([
                    'user_id' => auth()->id(),
                    'type' => 'sale',
                    'quantity' => $item['quantity'],
                    'previous_quantity' => $previousQuantity,
                    'new_quantity' => $product->quantity,
                    'reference' => $transaction->invoice_number,
                    'notes' => 'POS Sale',
                ]);
            }

            return response()->json([
                'success' => true,
                'transaction' => $transaction->load('items.product'),
                'message' => 'Sale completed successfully!',
            ]);
        });
    }
}
