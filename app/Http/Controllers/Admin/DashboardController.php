<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Rider;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $stats = [
            'total_users' => User::count(),
            'orders_30d' => Order::withoutGlobalScopes()->where('created_at', '>=', now()->subDays(30))->count(),
            'revenue_30d' => Order::withoutGlobalScopes()
                ->where('created_at', '>=', now()->subDays(30))
                ->where('payment_status', 'paid')
                ->sum('total_amount'),
        ];

        $pendingMerchants = Store::withoutGlobalScopes()
            ->where(function ($query) {
                $query->where('is_verified', false)
                    ->orWhereHas('owner', fn ($ownerQuery) => $ownerQuery->where('status', 'inactive'));
            })
            ->with('owner')
            ->latest()
            ->limit(5)
            ->get();

        $pendingRiders = Rider::withoutGlobalScopes()
            ->where(function ($query) {
                $query->where('is_verified', false)
                    ->orWhereHas('user', fn ($userQuery) => $userQuery->where('status', 'inactive'));
            })
            ->with('user')
            ->latest()
            ->limit(5)
            ->get();

        $stats['pending_count'] = Store::withoutGlobalScopes()
            ->where(function ($query) {
                $query->where('is_verified', false)
                    ->orWhereHas('owner', fn ($ownerQuery) => $ownerQuery->where('status', 'inactive'));
            })
            ->count()
            + Rider::withoutGlobalScopes()
                ->where(function ($query) {
                    $query->where('is_verified', false)
                        ->orWhereHas('user', fn ($userQuery) => $userQuery->where('status', 'inactive'));
                })
                ->count();

        return view('admin.dashboard', [
            'stats' => $stats,
            'pendingMerchants' => $pendingMerchants,
            'pendingRiders' => $pendingRiders,
        ]);
    }
}
