<?php

namespace App\Services;

use App\Models\Order;
use App\Models\MenuItemOption;
use App\Models\CustomerNotification;
use App\Models\Rider;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Bridges Laravel customer orders into the Management and A-rider order ledger. */
class ManagementOrderSync
{
    public function push(Order $order): bool
    {
        if (app()->environment('testing')) {
            return false;
        }

        try {
            $order->loadMissing(['store', 'customer', 'items.menuItem', 'rider.user']);
            $managementStoreId = $order->store?->management_store_id;
            if (! $managementStoreId) {
                return false;
            }

            $db = DB::connection('management');
            $db->transaction(function () use ($db, $order, $managementStoreId) {
                $paymentMethod = match ($order->payment_method) {
                    'gcash' => 'gcash',
                    'card' => 'card',
                    default => 'cash',
                };
                $paymentStatus = match ($order->payment_status) {
                    'paid' => 'paid',
                    'refunded' => 'refunded',
                    default => 'pending',
                };
                $managementStatus = $this->toManagementStatus($order->status);
                $managementRiderId = $order->rider?->user?->email
                    ? $db->table('riders')->where('email', $order->rider->user->email)->value('id')
                    : null;
                $commission = round((float) $order->subtotal * (float) ($order->store->commission_rate ?? 0) / 100, 2);
                $snapshot = $order->delivery_address_snapshot ?? [];
                $customerPhone = $order->customer?->phone;
                $row = [
                    'store_id' => $managementStoreId,
                    'customer_name' => $order->customer?->name ?? 'MotoBook Customer',
                    'customer_phone' => $customerPhone,
                    'delivery_address' => trim(implode(', ', array_filter([
                        data_get($snapshot, 'address_line', $order->dropoff_address),
                        data_get($snapshot, 'landmark'),
                    ]))),
                    'google_maps_location' => isset($snapshot['latitude'], $snapshot['longitude'])
                        ? 'https://maps.google.com/?q='.$snapshot['latitude'].','.$snapshot['longitude']
                        : null,
                    'notes' => $order->customer_note,
                    'order_total' => $order->total_amount,
                    'delivery_fee' => $order->delivery_fee,
                    'commission_amount' => $commission,
                    'payment_method' => $paymentMethod,
                    'payment_status' => $paymentStatus,
                    'order_status' => $managementStatus,
                    'placed_at' => $order->created_at,
                    'assigned_at' => $order->rider_id ? ($order->updated_at ?? now()) : null,
                    'in_transit_at' => in_array($order->status, ['out_for_delivery', 'delivered', 'completed'], true) ? ($order->picked_up_at ?? now()) : null,
                    'delivered_at' => $order->delivered_at,
                    'updated_at' => now(),
                ];
                if ($managementRiderId) {
                    $row['rider_id'] = (int) $managementRiderId;
                }

                $legacy = $db->table('orders')->where('order_number', $order->order_number)->first();
                if ($legacy) {
                    $existingRank = $this->statusRank((string) $legacy->order_status);
                    if ($this->statusRank($managementStatus) < $existingRank) {
                        unset($row['order_status']);
                    }
                    $db->table('orders')->where('id', $legacy->id)->update($row);
                    $legacyOrderId = (int) $legacy->id;
                } else {
                    $row['order_number'] = $order->order_number;
                    $row['created_at'] = $order->created_at ?? now();
                    $legacyOrderId = (int) $db->table('orders')->insertGetId($row);
                }

                $existingItems = $db->table('order_items')->where('order_id', $legacyOrderId)->pluck('id');
                if ($existingItems->isNotEmpty()) {
                    $db->table('order_item_options')->whereIn('order_item_id', $existingItems)->delete();
                    $db->table('order_items')->where('order_id', $legacyOrderId)->delete();
                }

                foreach ($order->items as $item) {
                    $legacyItemId = (int) $db->table('order_items')->insertGetId([
                        'order_id' => $legacyOrderId,
                        'menu_item_id' => $item->menuItem?->management_menu_item_id,
                        'item_name_snapshot' => $item->item_name,
                        'qty' => $item->quantity,
                        'unit_price_snapshot' => $item->unit_price,
                        'subtotal_snapshot' => $item->subtotal,
                        'created_at' => $item->created_at ?? now(),
                    ]);

                    foreach (($item->options_snapshot ?? []) as $option) {
                        $localOption = isset($option['id'])
                            ? MenuItemOption::with('group')->find($option['id'])
                            : null;
                        $db->table('order_item_options')->insert([
                            'order_item_id' => $legacyItemId,
                            'group_name_snapshot' => $localOption?->group?->name ?? ($option['group_name'] ?? 'Options'),
                            'option_name_snapshot' => $option['name'] ?? 'Selected option',
                            'price_delta_snapshot' => $option['price_delta'] ?? 0,
                        ]);
                    }
                }

                $db->table('order_events')->insert([
                    'order_id' => $legacyOrderId,
                    'event_type' => 'laravel_order_synced',
                    'notes' => 'Customer order synchronized from MotoBook customer storefront.',
                    'actor_email' => $order->customer?->email,
                    'created_at' => now(),
                ]);
            });

            return true;
        } catch (Throwable $exception) {
            Log::warning('Customer order could not be synchronized to Management.', [
                'order_number' => $order->order_number,
                'exception' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /** Pull the latest operational status and rider GPS written by Management/A-rider. */
    public function pull(Order $order): array
    {
        if (app()->environment('testing')) {
            return [];
        }

        try {
            $legacy = DB::connection('management')->table('orders')
                ->where('order_number', $order->order_number)->first();
            if (! $legacy) {
                return [];
            }

            $status = $this->toLaravelStatus((string) $legacy->order_status);
            $paymentStatus = match ($legacy->payment_status) {
                'paid' => 'paid',
                'refunded' => 'refunded',
                default => 'unpaid',
            };
            $changes = [];
            if ($status && $status !== $order->status) {
                $changes['status'] = $status;
            }
            if ($paymentStatus !== $order->payment_status) {
                $changes['payment_status'] = $paymentStatus;
            }
            if (! empty($legacy->delivered_at) && ! $order->delivered_at) {
                $changes['delivered_at'] = $legacy->delivered_at;
            }
            if ($changes) {
                Order::withoutGlobalScopes()->whereKey($order->id)->update($changes);
                $order->refresh();
                if (isset($changes['status'])) {
                    $this->notifyCustomer($order);
                }
            }

            $rider = null;
            if (! empty($legacy->rider_id)) {
                $riderRow = DB::connection('management')->table('riders')->where('id', $legacy->rider_id)->first();
                if ($riderRow?->email) {
                    $riderProfile = User::where('email', $riderRow->email)->where('role', 'rider')->first()?->riderProfile;
                    if ($riderProfile && $order->rider_id !== $riderProfile->id) {
                        $changes['rider_id'] = $riderProfile->id;
                        Order::withoutGlobalScopes()->whereKey($order->id)->update(['rider_id' => $riderProfile->id]);
                        $order->refresh();
                    }
                }
                $gps = DB::connection('management')->table('gps_tracks')
                    ->where('order_id', $legacy->id)->latest('recorded_at')->first();
                $rider = [
                    'name' => $riderRow->full_name ?? null,
                    'phone' => $riderRow->phone ?? null,
                    'latitude' => $gps ? (float) $gps->latitude : null,
                    'longitude' => $gps ? (float) $gps->longitude : null,
                    'updated_at' => $gps->recorded_at ?? null,
                ];
            }

            return [
                'status' => $status,
                'rider' => $rider,
                'delivery_status' => match ($status) {
                    'assigned' => 'assigned',
                    'out_for_delivery' => 'en_route_to_customer',
                    'delivered', 'completed' => 'delivered',
                    default => null,
                },
            ];
        } catch (Throwable $exception) {
            Log::notice('Management order status is temporarily unavailable.', [
                'order_number' => $order->order_number,
                'exception' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    public function pushLocation(Order $order, float $latitude, float $longitude): bool
    {
        try {
            $order->loadMissing('rider.user');
            $email = $order->rider?->user?->email;
            if (! $email) {
                return false;
            }
            $db = DB::connection('management');
            $legacyOrderId = $db->table('orders')->where('order_number', $order->order_number)->value('id');
            $legacyRiderId = $db->table('riders')->where('email', $email)->value('id');
            if (! $legacyOrderId || ! $legacyRiderId) {
                return false;
            }
            $db->table('gps_tracks')->insert([
                'order_id' => $legacyOrderId,
                'rider_id' => $legacyRiderId,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'recorded_at' => now(),
            ]);

            return true;
        } catch (Throwable $exception) {
            Log::notice('Rider GPS could not be synchronized to Management.', [
                'order_number' => $order->order_number,
                'exception' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function toManagementStatus(string $status): string
    {
        return match ($status) {
            'accepted', 'preparing', 'ready_for_pickup' => 'preparing',
            'assigned' => 'driver_assigned',
            'out_for_delivery' => 'out_for_delivery',
            'delivered', 'completed' => 'delivered',
            'cancelled', 'rejected' => 'cancelled',
            default => 'pending',
        };
    }

    private function toLaravelStatus(string $status): ?string
    {
        return match ($status) {
            'pending', 'placed' => 'pending',
            'preparing' => 'preparing',
            'driver_assigned' => 'assigned',
            'out_for_delivery', 'picked_up' => 'out_for_delivery',
            'delivered' => 'delivered',
            'cancelled' => 'cancelled',
            default => null,
        };
    }

    private function statusRank(string $status): int
    {
        return match ($status) {
            'pending', 'placed' => 0,
            'preparing' => 1,
            'driver_assigned' => 2,
            'out_for_delivery', 'picked_up' => 3,
            'delivered', 'cancelled' => 4,
            default => 0,
        };
    }

    private function notifyCustomer(Order $order): void
    {
        $customer = $order->customer;
        if (! $customer || ($customer->customer_settings['order_notifications'] ?? true) === false) {
            return;
        }

        $messages = [
            'preparing' => ['Order is being prepared', 'The restaurant is preparing your order.'],
            'assigned' => ['Rider assigned', 'Management assigned a rider to your order.'],
            'out_for_delivery' => ['Order picked up', 'Your order is on the way to you.'],
            'delivered' => ['Order delivered', 'Your order has been delivered.'],
            'cancelled' => ['Order cancelled', 'Your order was cancelled by the restaurant or dispatch team.'],
        ];
        if (! isset($messages[$order->status])) {
            return;
        }

        [$title, $message] = $messages[$order->status];
        CustomerNotification::create([
            'user_id' => $order->customer_id,
            'type' => 'order',
            'title' => $title,
            'message' => $message,
            'action_url' => route('customer.orders.show', $order, false),
        ]);
    }
}
