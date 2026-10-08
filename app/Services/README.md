# MotoBook Service Layer — Reference

## Where to put these files

```
app/
├── Services/
│   ├── OrderService.php
│   └── RiderAssignmentService.php
└── Exceptions/
    ├── OrderException.php
    └── DeliveryException.php
```

Copy `Services/` and `Exceptions/` directly into your `app/` folder (same
level as `Models/`, `Http/`, etc.) — not nested inside anything else.

## Why a service layer at all?

Controllers should stay thin (validate input, call a service, return a
response). All the actual business rules — "can this order be accepted
right now?", "how do we pick the nearest rider?", "what happens to a
rider's wallet when a delivery completes?" — live here instead, so:

- The same logic works whether it's triggered from a web controller, an
  API controller, a queued job, or `php artisan tinker`.
- Every state transition is guarded (`assertStatus()`), so you can't
  accidentally mark an order "delivered" before it was ever "accepted."
- Multi-step operations (create order + create order items + apply promo)
  are wrapped in `DB::transaction()`, so a failure partway through rolls
  back cleanly instead of leaving half-written data.

## OrderService — maps to your diagram's Customer/Merchant/System lanes

| Method | Diagram step | Who calls it |
|---|---|---|
| `placeOrder()` | Place Order → Verify Multi-Tenant Data & Stock → Valid? | Customer controller |
| `acceptOrder()` | Accept/Process Order → Set State: Preparing | Merchant controller |
| `rejectOrder()` | Reject Order / Notify Customer | Merchant controller |
| `markPreparing()` | Update Order Status (Preparing) | Merchant controller |
| `markReadyForPickup()` | Update Order Status (Ready for Pickup) — **also triggers rider assignment automatically** | Merchant controller |
| `completeOrder()` | Final confirmation after delivery | Customer controller or auto-job |
| `cancelOrder()` | Cancellation (any pre-delivery stage) | Customer/Merchant/Admin controller |

Example controller usage:

```php
// app/Http/Controllers/Merchant/OrderController.php
public function accept(Order $order, OrderService $orders)
{
    $this->authorize('update', $order);

    try {
        $orders->acceptOrder($order);
        return back()->with('success', 'Order accepted.');
    } catch (\App\Exceptions\OrderException $e) {
        return back()->withErrors($e->getMessage());
    }
}
```

## RiderAssignmentService — maps to the System/Rider lanes

| Method | Diagram step |
|---|---|
| `assignNearestRider()` | Assign Nearest Available Rider |
| `riderAccept()` | Accept Order? → Yes |
| `riderReject()` | Accept Order? → No → Reject Order → Return to Available Riders (then re-assigns automatically) |
| `confirmPickup()` | Confirm Pickup |
| `completeDelivery()` | Deliver Order → **credits the rider's wallet automatically** |

### How nearest-rider matching works right now

`findNearestAvailableRider()` pulls all `online` riders with a known GPS
position, computes distance with the Haversine formula, filters to a
5km radius, and picks the closest. This is fine for early development but
**does not scale** — at real volume you'll want to replace this with a
spatial DB query (MySQL `ST_Distance_Sphere`, PostGIS, or a geo-index
service like Redis GEO) so you're not loading every online rider into PHP
memory on every assignment.

### Fare calculation is a placeholder

`calculateBaseFare()` uses a flat ₱30 + ₱8/km formula — this is a
guess to get things working end-to-end. Replace with your actual pricing
model (surge pricing, zone-based rates, etc.) when you're ready; it's
isolated in one method specifically so it's easy to swap out.

## Important: both services use `withoutGlobalScopes()` in specific spots

You'll notice `MenuItem::withoutGlobalScopes()`, `Rider::withoutGlobalScopes()`,
etc. inside these services. This is intentional and safe — the multi-tenant
scopes from the previous step exist to protect **user-facing queries**
(a merchant browsing their dashboard). The service layer runs as trusted
system logic (e.g. verifying a menu item belongs to a specific store
regardless of who's logged in), so it needs to see the full picture rather
than being silently filtered by whoever happens to be authenticated when
the service runs (which could be nobody, in a queued job).

## Next suggested step

Wire these into actual controllers + routes (from the structure we set up
earlier), and/or set up **Laravel Events + Broadcasting** for the
real-time pieces your diagram calls for — "Send Order Confirmation,"
"Stream Rider Location (GPS) in Real-Time," and "Send Notifications" all
need something pushing updates to the frontend, not just DB writes.
