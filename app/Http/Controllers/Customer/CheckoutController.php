<?php

namespace App\Http\Controllers\Customer;

use App\Exceptions\InsufficientWalletFundsException;
use App\Exceptions\OrderException;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Order;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    public function __construct(private CartService $cart) {}

    public function show(Request $request)
    {
        $items = $this->cart->getItems();

        if ($items->isEmpty()) {
            return redirect()->route('customer.cart')->withErrors('Your cart is empty.');
        }

        $store = $this->cart->getStore();

        if (! $store) {
            $this->cart->clear();

            return redirect()->route('customer.cart')->withErrors('The restaurant for your cart is no longer available.');
        }

        return view('customer.checkout', [
            'items' => $items,
            'store' => $store,
            'totals' => $this->cart->totals(),
            'addresses' => $request->user()->addresses,
            'wallet' => $request->user()->wallet,
            'preferredPayment' => $request->user()->customer_settings['preferred_payment'] ?? 'cod',
            'gcashNumber' => $request->user()->customer_settings['gcash_number'] ?? null,
        ]);
    }

    public function placeOrder(Request $request, OrderService $orderService)
    {
        $validated = $request->validate([
            'address_id' => ['required', 'exists:addresses,id'],
            'payment_method' => ['required', 'in:cod,wallet,gcash'],
            'note' => ['nullable', 'string', 'max:500'],
            'promo_code' => ['nullable', 'string', 'max:64'],
        ]);

        $address = Address::where('user_id', $request->user()->id)->findOrFail($validated['address_id']);

        if (
            ! is_numeric($address->latitude)
            || ! is_numeric($address->longitude)
            || $address->latitude < -90
            || $address->latitude > 90
            || $address->longitude < -180
            || $address->longitude > 180
        ) {
            return redirect()->route('customer.addresses')
                ->withErrors(['address_id' => 'Add a valid delivery location to this address before placing your order.']);
        }

        $items = $this->cart->getItems();
        if ($items->isEmpty()) {
            return redirect()->route('customer.cart')->withErrors('Your cart is empty.');
        }

        $store = $this->cart->getStore();
        if (! $store) {
            $this->cart->clear();

            return redirect()->route('customer.cart')->withErrors('The restaurant for your cart is no longer available.');
        }

        $totals = $this->cart->totals();

        $cartItems = $items->map(fn ($row) => [
            'menu_item_id' => $row['menu_item']->id,
            'quantity' => $row['quantity'],
            'option_ids' => $row['selected_options']->pluck('id')->all(),
        ])->toArray();

        $addressSnapshot = [
            'label' => $address->label,
            'address_line' => $address->address_line,
            'landmark' => $address->landmark,
            'latitude' => $address->latitude,
            'longitude' => $address->longitude,
        ];

        try {
            $order = DB::transaction(function () use ($orderService, $store, $cartItems, $address, $addressSnapshot, $validated, $totals, $request) {
                $order = $orderService->placeOrder(
                    store: $store,
                    cartItems: $cartItems,
                    deliveryAddressId: $address->id,
                    deliveryAddressSnapshot: $addressSnapshot,
                    paymentMethod: $validated['payment_method'],
                    customerNote: $validated['note'] ?? null,
                    promoCode: isset($validated['promo_code']) ? trim($validated['promo_code']) : null,
                    deliveryFee: $totals['delivery_fee'],
                    serviceFee: $totals['service_fee'],
                );

                $payment = [
                    'method' => $validated['payment_method'],
                    'amount' => $order->total_amount,
                    'status' => $validated['payment_method'] === 'wallet' ? 'paid' : 'pending',
                    'paid_at' => $validated['payment_method'] === 'wallet' ? now() : null,
                ];

                if ($validated['payment_method'] === 'wallet') {
                    $request->user()->wallet()->firstOrCreate([], ['balance' => 0])->debit(
                        $order->total_amount,
                        "Payment for order #{$order->order_number}",
                        $order
                    );
                    $order->update(['payment_status' => 'paid']);
                }

                $order->payments()->create($payment);

                return $order;
            });
        } catch (OrderException|InsufficientWalletFundsException $e) {
            return back()->withErrors($e->getMessage());
        }

        $this->cart->clear();

        return redirect()->route('customer.checkout.confirmation', $order);
    }

    public function confirmation(Request $request, Order $order)
    {
        abort_unless($order->customer_id === $request->user()->id, 403);

        return view('customer.checkout-confirmation', ['order' => $order->load('items', 'store')]);
    }
}
