<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Services\CartService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private CartService $cart) {}

    public function show()
    {
        return view('customer.cart', [
            'items' => $this->cart->getItems(),
            'store' => $this->cart->getStore(),
            'totals' => $this->cart->totals(),
        ]);
    }

    /**
     * Used for items WITHOUT any option groups — a simple direct add,
     * same as before. Items WITH option groups go through the item
     * detail page instead (see ItemDetailController::addToCart).
     */
    public function add(Request $request, MenuItem $menuItem)
    {
        abort_unless($menuItem->is_available, 404);

        $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $this->cart->addItem($menuItem, (int) $request->input('quantity'));

        if ($request->wantsJson()) {
            return response()->json([
                'item_count' => $this->cart->itemCount(),
                'totals' => $this->cart->totals(),
            ]);
        }

        return back()->with('status', "{$menuItem->name} added to cart.");
    }

    public function updateQuantity(Request $request, string $lineKey)
    {
        $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:99']]);

        $this->cart->updateQuantity($lineKey, $request->quantity);

        return response()->json([
            'item_count' => $this->cart->itemCount(),
            'totals' => $this->cart->totals(),
            'removed' => $request->quantity <= 0,
        ]);
    }

    public function remove(string $lineKey)
    {
        $this->cart->removeItem($lineKey);

        return response()->json([
            'item_count' => $this->cart->itemCount(),
            'totals' => $this->cart->totals(),
        ]);
    }
}
