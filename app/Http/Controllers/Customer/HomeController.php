<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\ManagementCatalogSync;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request, ManagementCatalogSync $catalogSync)
    {
        $catalogSync->sync();
        $query = Store::withoutGlobalScopes()
            ->where('is_verified', true);

        if ($search = $request->query('q')) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        $stores = $query->orderByDesc('rating')->get();

        $categories = Store::withoutGlobalScopes()
            ->where('is_verified', true)
            ->distinct()
            ->pluck('category');

        $defaultAddress = $request->user()->addresses()->where('is_default', true)->first()
            ?? $request->user()->addresses()->first();

        return view('customer.home', [
            'stores' => $stores,
            'categories' => $categories,
            'defaultAddress' => $defaultAddress,
            'search' => $search ?? '',
            'activeCategory' => $category ?? null,
            'favoriteStoreIds' => $request->user()->favoriteStores()->pluck('stores.id')->all(),
            'cartItems' => $request->user()->customerCart?->lines ?? [],
            'unreadNotifications' => $request->user()->customerNotifications()->whereNull('read_at')->count(),
        ]);
    }
}
