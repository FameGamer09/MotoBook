<?php

namespace App\Services;

use App\Exceptions\OrderException;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\Store;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(private readonly RiderAssignmentService $riderAssignmentService)
    {
    }

    /**
     * "Place Order" (Customer) + "Verify Multi-Tenant Data & Stock" (System).
     *
     * $cartItems format: [['menu_item_id' => 1, 'quantity' => 2], ...]
     */
    public function placeOrder(
        Store $store,
        array $cartItems,
        ?int $deliveryAddressId,
        array $deliveryAddressSnapshot,
        string $paymentMethod = 'cod',
        ?string $customerNote = null,
        ?string $promoCode = null,
        float $deliveryFee = 0,
        float $serviceFee = 0,
    ): Order {
        if (empty($cartItems)) {
            throw new OrderException('Cart is empty.');
        }

        return DB::transaction(function () use (
            $store, $cartItems, $deliveryAddressId, $deliveryAddressSnapshot,
            $paymentMethod, $customerNote, $promoCode, $deliveryFee, $serviceFee
        ) {
            $store = Store::withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($store->id);

            // --- "Verify Multi-Tenant Data & Stock: Valid?" ---
            if (! $store->is_verified || ! $store->is_open) {
                throw new OrderException('This store is not currently accepting orders.');
            }

            $subtotal = 0;
            $lineItems = [];

            foreach ($cartItems as $cartItem) {
                $menuItem = MenuItem::withoutGlobalScopes()
                    ->lockForUpdate()
                    ->where('store_id', $store->id)
                    ->where('id', $cartItem['menu_item_id'])
                    ->first();

                if (! $menuItem) {
                    throw new OrderException("Menu item #{$cartItem['menu_item_id']} does not belong to this store.");
                }

                if (! $menuItem->is_available) {
                    throw new OrderException("\"{$menuItem->name}\" is currently unavailable.");
                }

                $quantity = filter_var($cartItem['quantity'] ?? null, FILTER_VALIDATE_INT);

                if ($quantity === false || $quantity < 1 || $quantity > 99) {
                    throw new OrderException('One or more cart quantities are invalid.');
                }

                $menuItem->loadMissing('optionGroups.options');
                $optionIds = collect($cartItem['option_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
                $availableOptions = $menuItem->optionGroups->flatMap->options->where('is_available', true);
                $selectedOptions = $availableOptions->whereIn('id', $optionIds);

                if ($selectedOptions->count() !== $optionIds->count()) {
                    throw new OrderException("One or more options for \"{$menuItem->name}\" are invalid.");
                }

                foreach ($menuItem->optionGroups as $group) {
                    $selectedCount = $selectedOptions->where('menu_item_option_group_id', $group->id)->count();

                    if ($group->is_required && $selectedCount === 0) {
                        throw new OrderException("Please select an option for \"{$group->name}\".");
                    }

                    if ($group->selection_type === 'single' && $selectedCount > 1) {
                        throw new OrderException("Only one option is allowed for \"{$group->name}\".");
                    }

                    if ($group->max_selections && $selectedCount > $group->max_selections) {
                        throw new OrderException("Too many selections for \"{$group->name}\".");
                    }
                }

                $unitPrice = round((float) $menuItem->price + (float) $selectedOptions->sum('price_delta'), 2);
                $lineSubtotal = round($unitPrice * $quantity, 2);
                $subtotal += $lineSubtotal;

                $lineItems[] = [
                    'menu_item_id' => $menuItem->id,
                    'item_name' => $menuItem->name,
                    'unit_price' => $unitPrice,
                    'quantity' => $quantity,
                    'subtotal' => $lineSubtotal,
                    'notes' => $cartItem['notes'] ?? null,
                    'options_snapshot' => $selectedOptions->map(fn ($option) => [
                        'id' => $option->id,
                        'name' => $option->name,
                        'price_delta' => $option->price_delta,
                    ])->values()->all(),
                ];
            }

            $subtotal = round($subtotal, 2);

            // --- apply promotion, if any ---
            $discount = 0;
            $promotion = null;

            if ($promoCode) {
                $promotion = Promotion::where('code', $promoCode)
                    ->where(function ($q) use ($store) {
                        $q->whereNull('store_id')->orWhere('store_id', $store->id);
                    })
                    ->lockForUpdate()
                    ->first();

                if (! $promotion || ! $promotion->isValidNow()) {
                    throw new OrderException('This promo code is invalid or expired.');
                }

                if ($subtotal < $promotion->min_order_amount) {
                    throw new OrderException("Minimum order of ₱{$promotion->min_order_amount} required for this promo.");
                }

                $discount = $promotion->discount_type === 'percentage'
                    ? $subtotal * ($promotion->discount_value / 100)
                    : $promotion->discount_value;

                if ($promotion->max_discount_amount) {
                    $discount = min($discount, $promotion->max_discount_amount);
                }

                $discount = min($discount, $subtotal);
                $discount = round($discount, 2);
            }

            if ($deliveryFee < 0 || $serviceFee < 0) {
                throw new OrderException('Order fees cannot be negative.');
            }

            $total = round(max(0, $subtotal - $discount) + $deliveryFee + $serviceFee, 2);

            // --- create the order (status defaults to 'pending' = awaiting merchant) ---
            $order = Order::create([
                'store_id' => $store->id,
                'merchant_id' => $store->user_id,
                'merchant_lat' => $store->latitude,
                'merchant_lng' => $store->longitude,
                'pickup_address' => $store->address,
                'delivery_address_id' => $deliveryAddressId,
                'delivery_address_snapshot' => $deliveryAddressSnapshot,
                'customer_lat' => $deliveryAddressSnapshot['latitude'] ?? null,
                'customer_lng' => $deliveryAddressSnapshot['longitude'] ?? null,
                'dropoff_address' => $deliveryAddressSnapshot['address_line'] ?? null,
                'status' => 'pending',
                'payment_method' => $paymentMethod,
                'payment_status' => 'unpaid',
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'service_fee' => $serviceFee,
                'discount_amount' => $discount,
                'total_amount' => $total,
                'customer_note' => $customerNote,
                'delivery_notes' => $customerNote,
            ]);

            foreach ($lineItems as $item) {
                $order->items()->create($item);
            }

            if ($promotion) {
                $promotion->increment('used_count');
                $order->promotionRedemptions()->create([
                    'promotion_id' => $promotion->id,
                    'user_id' => $order->customer_id,
                    'discount_amount' => $discount,
                ]);
            }

            return $order->fresh('items');
        });
    }

    /**
     * "Accept / Process Order" (Merchant) -> "Set State: Preparing" (System)
     */
    public function acceptOrder(Order $order): Order
    {
        return $this->transitionOrder($order, 'pending', [
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);
    }

    /**
     * "Reject Order / Notify Customer"
     */
    public function rejectOrder(Order $order, ?string $reason = null): Order
    {
        return DB::transaction(function () use ($order, $reason) {
            $lockedOrder = $this->lockOrder($order);
            $this->assertStatus($lockedOrder, 'pending');
            $lockedOrder->update([
                'status' => 'rejected',
                'cancel_reason' => $reason,
            ]);
            $this->refundWalletPayment($lockedOrder, 'Refund for rejected order');

            return $lockedOrder->fresh();
        });
    }

    /**
     * "Update Order Status (Preparing)"
     */
    public function markPreparing(Order $order): Order
    {
        return $this->transitionOrder($order, 'accepted', ['status' => 'preparing']);
    }

    /**
     * "Update Order Status (Ready for Pickup)" -> triggers rider assignment
     */
    public function markReadyForPickup(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $lockedOrder = $this->lockOrder($order);
            $this->assertStatus($lockedOrder, 'preparing');
            $lockedOrder->update([
                'status' => 'ready_for_pickup',
                'ready_at' => now(),
            ]);

            $this->riderAssignmentService->assignNearestRider($lockedOrder);

            return $lockedOrder->fresh();
        });
    }

    /**
     * Customer/system final confirmation after delivery.
     */
    public function completeOrder(Order $order): Order
    {
        return $this->transitionOrder($order, 'delivered', ['status' => 'completed']);
    }

    public function cancelOrder(Order $order, string $reason): Order
    {
        return DB::transaction(function () use ($order, $reason) {
            $lockedOrder = $this->lockOrder($order);

            if ($lockedOrder->status !== 'pending') {
                throw new OrderException('This order can only be cancelled while awaiting merchant acceptance.');
            }

            if (auth()->id() !== $lockedOrder->customer_id) {
                throw new OrderException("You cannot cancel another customer's order.");
            }

            $lockedOrder->update([
                'status' => 'cancelled',
                'cancel_reason' => $reason,
                'cancelled_at' => now(),
            ]);
            $this->refundWalletPayment($lockedOrder, 'Refund for cancelled order');

            return $lockedOrder->fresh();
        });
    }

    private function assertStatus(Order $order, string $expected): void
    {
        if ($order->status !== $expected) {
            throw new OrderException(
                "Order #{$order->order_number} must be '{$expected}' for this action, currently '{$order->status}'."
            );
        }
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function transitionOrder(Order $order, string $expected, array $attributes): Order
    {
        return DB::transaction(function () use ($order, $expected, $attributes) {
            $lockedOrder = $this->lockOrder($order);
            $this->assertStatus($lockedOrder, $expected);
            $lockedOrder->update($attributes);

            return $lockedOrder->fresh();
        });
    }

    private function lockOrder(Order $order): Order
    {
        return Order::withoutGlobalScopes()
            ->lockForUpdate()
            ->findOrFail($order->id);
    }

    private function refundWalletPayment(Order $order, string $description): void
    {
        if ($order->payment_method !== 'wallet' || $order->payment_status !== 'paid') {
            return;
        }

        $wallet = $order->customer->wallet;

        if (! $wallet) {
            throw new OrderException('The customer wallet is unavailable, so this payment cannot be refunded automatically.');
        }

        $wallet->credit($order->total_amount, $description, $order);
        $order->payments()
            ->where('method', 'wallet')
            ->where('status', 'paid')
            ->update(['status' => 'refunded']);
        $order->update(['payment_status' => 'refunded']);
    }
}
