<?php

namespace App\Services;

use App\Models\CustomerCart;
use App\Models\MenuItem;
use App\Models\Store;
use Illuminate\Support\Collection;

class CartService
{
    private const SESSION_KEY = 'cart';

    private const DELIVERY_FEE = 30.00;

    private const SERVICE_FEE = 5.00;

    /**
     * Add a configured item to the cart. Each unique combination of
     * menu_item_id + selected option_ids is its own "line" — so the same
     * item ordered two different ways (e.g. different drink choices)
     * stays as two separate cart lines, while identical configurations
     * merge their quantity together.
     */
    public function addItem(MenuItem $menuItem, int $quantity = 1, array $optionIds = []): void
    {
        $cart = $this->getRaw();

        if ($cart['store_id'] && $cart['store_id'] !== $menuItem->store_id) {
            $cart = ['store_id' => null, 'lines' => []];
        }

        $cart['store_id'] = $menuItem->store_id;

        $lineKey = $this->makeLineKey($menuItem->id, $optionIds);

        if (isset($cart['lines'][$lineKey])) {
            $cart['lines'][$lineKey]['quantity'] += $quantity;
        } else {
            $cart['lines'][$lineKey] = [
                'menu_item_id' => $menuItem->id,
                'option_ids' => $optionIds,
                'quantity' => $quantity,
            ];
        }

        $this->save($cart);
    }

    public function updateQuantity(string $lineKey, int $quantity): void
    {
        $cart = $this->getRaw();

        if ($quantity <= 0) {
            unset($cart['lines'][$lineKey]);
        } elseif (isset($cart['lines'][$lineKey])) {
            $cart['lines'][$lineKey]['quantity'] = $quantity;
        }

        if (empty($cart['lines'])) {
            $cart['store_id'] = null;
        }

        $this->save($cart);
    }

    public function removeItem(string $lineKey): void
    {
        $this->updateQuantity($lineKey, 0);
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);

        if (auth()->check() && auth()->user()->role === 'customer') {
            CustomerCart::where('user_id', auth()->id())->delete();
        }
    }

    public function getStore(): ?Store
    {
        $storeId = $this->getRaw()['store_id'] ?? null;

        return $storeId ? Store::withoutGlobalScopes()->find($storeId) : null;
    }

    /**
     * Returns a collection of fully-resolved cart lines:
     * ['line_key', 'menu_item' => MenuItem, 'quantity', 'selected_options' => Collection<MenuItemOption>,
     *  'unit_price' => base + sum of option deltas, 'line_total' => unit_price * quantity]
     * Silently drops any line whose menu item no longer exists/is unavailable.
     */
    public function getItems(): Collection
    {
        $lines = $this->getRaw()['lines'] ?? [];

        if (empty($lines)) {
            return collect();
        }

        $menuItemIds = collect($lines)->pluck('menu_item_id')->unique();

        $menuItems = MenuItem::withoutGlobalScopes()
            ->whereIn('id', $menuItemIds)
            ->where('is_available', true)
            ->with('optionGroups.options')
            ->get()
            ->keyBy('id');

        return collect($lines)
            ->map(function ($line, $lineKey) use ($menuItems) {
                $menuItem = $menuItems->get($line['menu_item_id']);

                if (! $menuItem) {
                    return null;
                }

                $allOptions = $menuItem->optionGroups->flatMap->options;
                $selectedOptions = $allOptions->whereIn('id', $line['option_ids']);

                $unitPrice = $menuItem->price + $selectedOptions->sum('price_delta');

                return [
                    'line_key' => $lineKey,
                    'menu_item' => $menuItem,
                    'quantity' => $line['quantity'],
                    'selected_options' => $selectedOptions->values(),
                    'unit_price' => $unitPrice,
                    'line_total' => $unitPrice * $line['quantity'],
                ];
            })
            ->filter()
            ->values();
    }

    public function itemCount(): int
    {
        return collect($this->getRaw()['lines'] ?? [])->sum('quantity');
    }

    public function totals(): array
    {
        $subtotal = $this->getItems()->sum('line_total');
        $deliveryFee = $subtotal > 0 ? self::DELIVERY_FEE : 0;
        $serviceFee = $subtotal > 0 ? self::SERVICE_FEE : 0;

        return [
            'subtotal' => round($subtotal, 2),
            'delivery_fee' => $deliveryFee,
            'service_fee' => $serviceFee,
            'total' => round($subtotal + $deliveryFee + $serviceFee, 2),
        ];
    }

    private function makeLineKey(int $menuItemId, array $optionIds): string
    {
        sort($optionIds);

        return md5($menuItemId.'-'.implode(',', $optionIds));
    }

    private function getRaw(): array
    {
        $emptyCart = ['store_id' => null, 'lines' => []];

        if (! auth()->check() || auth()->user()->role !== 'customer') {
            return session(self::SESSION_KEY, $emptyCart);
        }

        $savedCart = CustomerCart::where('user_id', auth()->id())->first();
        $sessionCart = session(self::SESSION_KEY);

        if (! $savedCart && is_array($sessionCart) && ! empty($sessionCart['lines'])) {
            $savedCart = CustomerCart::create([
                'user_id' => auth()->id(),
                'store_id' => $sessionCart['store_id'] ?? null,
                'lines' => $sessionCart['lines'],
            ]);
        }

        $cart = $savedCart
            ? ['store_id' => $savedCart->store_id, 'lines' => $savedCart->lines ?? []]
            : $emptyCart;

        session([self::SESSION_KEY => $cart]);

        return $cart;
    }

    private function save(array $cart): void
    {
        session([self::SESSION_KEY => $cart]);

        if (auth()->check() && auth()->user()->role === 'customer') {
            if (empty($cart['lines'])) {
                CustomerCart::where('user_id', auth()->id())->delete();
            } else {
                CustomerCart::updateOrCreate(
                    ['user_id' => auth()->id()],
                    ['store_id' => $cart['store_id'] ?? null, 'lines' => $cart['lines']]
                );
            }
        }
    }
}
