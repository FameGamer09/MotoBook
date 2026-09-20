<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\InventoryMovement;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class PosController extends Controller
{
    public function index(): View
    {
        $products = Product::with('category')
            ->where('is_active', true)
            ->where('quantity', '>', 0)
            ->orderBy('name')
            ->get();

        $categories = \App\Models\Category::with('products')->get();

        return view('pos.index', compact('products', 'categories'));
    }

    public function search(Request $request)
    {
        $search = $request->get('q');
        
        $products = Product::with('category')
            ->where('is_active', true)
            ->where('quantity', '>', 0)
            ->where(function($query) use ($search) {
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
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'payment_method' => 'required|in:cash,card,digital',
            'discount' => 'nullable|numeric|min:0',
        ]);

        $items = $validated['items'];
        $paymentMethod = $validated['payment_method'];
        $discount = $validated['discount'] ?? 0;

        return DB::transaction(function () use ($items, $paymentMethod, $discount) {
            $subtotal = 0;
            $transactionItems = [];

            // Validate stock and calculate subtotal
            foreach ($items as $item) {
                $product = Product::findOrFail($item['product_id']);
                
                if ($product->quantity < $item['quantity']) {
                    return response()->json([
                        'error' => "Insufficient stock for {$product->name}. Available: {$product->quantity}"
                    ], 422);
                }

                $itemSubtotal = $product->price * $item['quantity'];
                $subtotal += $itemSubtotal;

                $transactionItems[] = [
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->price,
                    'subtotal' => $itemSubtotal,
                ];
            }

            $tax = 0; // Can be configured
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
                $product = Product::findOrFail($item['product_id']);
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
                'message' => 'Sale completed successfully!'
            ]);
        });
    }
}