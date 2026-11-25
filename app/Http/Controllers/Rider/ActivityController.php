<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use App\Models\CustomDelivery;
use App\Models\Delivery;
use App\Models\Rider;
use App\Models\WalletTransaction;
use App\Services\RiderAssignmentService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityController extends Controller
{
    private const ACTIVE_DELIVERY_STATUSES = [
        'assigned',
        'accepted',
        'en_route_to_pickup',
        'arrived_at_pickup',
        'picked_up',
        'en_route_to_customer',
        'arrived_at_customer',
    ];

    public function requests(Request $request, RiderAssignmentService $service): View
    {
        $rider = $this->rider($request);
        $customDeliveries = CustomDelivery::query()
            ->with('customer')
            ->whereIn('status', ['pending_assignment', 'assigned'])
            ->where(function ($query) use ($rider): void {
                $query->where('rider_id', $rider->id);

                if ($rider->status === 'online') {
                    $query->orWhere('status', 'pending_assignment');
                }
            })
            ->latest()
            ->get();

        return view('rider.requests', [
            'rider' => $rider,
            'offers' => $rider->status === 'online' && $rider->is_verified ? $service->riderOffers($rider) : collect(),
            'customDeliveries' => $customDeliveries,
            ...$this->navigationState($request, $rider),
        ]);
    }

    public function showOrder(Request $request, Delivery $delivery): View
    {
        $rider = $this->rider($request);
        $delivery = $rider->deliveries()
            ->with('order.store', 'order.customer', 'order.items')
            ->findOrFail($delivery->id);

        return view('rider.order-detail', [
            'rider' => $rider,
            'delivery' => $delivery,
            ...$this->navigationState($request, $rider),
        ]);
    }

    public function earnings(Request $request): View
    {
        $period = $request->validate([
            'period' => ['sometimes', 'in:daily,weekly,monthly'],
        ])['period'] ?? 'daily';
        $startAt = match ($period) {
            'weekly' => now()->startOfWeek(),
            'monthly' => now()->startOfMonth(),
            default => today(),
        };
        $rider = $this->rider($request);
        $walletId = $request->user()->wallet?->id ?? 0;
        $periodCredits = WalletTransaction::query()
            ->where('wallet_id', $walletId)
            ->where('type', 'credit')
            ->where('created_at', '>=', $startAt);

        return view('rider.earnings', [
            'rider' => $rider,
            'period' => $period,
            'earnings' => (clone $periodCredits)->sum('amount'),
            'transactions' => $periodCredits->latest()->paginate(10)->withQueryString(),
            ...$this->navigationState($request, $rider),
        ]);
    }

    public function history(Request $request): View
    {
        $rider = $this->rider($request);

        return view('rider.history', [
            'rider' => $rider,
            'deliveries' => $rider->deliveries()
                ->with('order.store')
                ->whereIn('status', ['delivered', 'failed'])
                ->latest()
                ->paginate(8, ['*'], 'deliveries_page'),
            'customDeliveries' => CustomDelivery::query()
                ->where('rider_id', $rider->id)
                ->whereIn('status', ['delivered', 'cancelled'])
                ->latest()
                ->paginate(8, ['*'], 'custom_page'),
            ...$this->navigationState($request, $rider),
        ]);
    }

    public function wallet(Request $request): View
    {
        $rider = $this->rider($request);
        $wallet = $request->user()->wallet;

        return view('rider.wallet', [
            'rider' => $rider,
            'wallet' => $wallet,
            'transactions' => WalletTransaction::query()
                ->where('wallet_id', $wallet?->id ?? 0)
                ->latest()
                ->paginate(10),
            ...$this->navigationState($request, $rider),
        ]);
    }

    public function settings(Request $request): View
    {
        $rider = $this->rider($request);

        return view('rider.settings', [
            'rider' => $rider,
            ...$this->navigationState($request, $rider),
        ]);
    }

    private function rider(Request $request): Rider
    {
        $rider = $request->user()->riderProfile;
        abort_unless($rider, 404);

        return $rider;
    }

    /**
     * @return array{hasActiveDelivery: bool, canGoOnline: bool}
     */
    private function navigationState(Request $request, Rider $rider): array
    {
        $hasActiveDelivery = $rider->deliveries()->whereIn('status', self::ACTIVE_DELIVERY_STATUSES)->exists()
            || CustomDelivery::query()
                ->where('rider_id', $rider->id)
                ->whereIn('status', ['assigned', 'accepted', 'picked_up'])
                ->exists();

        return [
            'hasActiveDelivery' => $hasActiveDelivery,
            'canGoOnline' => $request->user()->status === 'active' && $rider->is_verified && ! $hasActiveDelivery,
        ];
    }
}
