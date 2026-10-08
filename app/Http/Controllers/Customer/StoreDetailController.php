<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\Request;

class StoreDetailController extends Controller
{
    public function show(Request $request, Store $store)
    {
        abort_unless($store->is_verified, 404);

        $store->load(['menuCategories' => function ($query) {
            $query->orderBy('sort_order');
        }, 'menuCategories.menuItems' => function ($query) {
            $query->where('is_available', true)->withCount('optionGroups');
        }]);
        $uncategorizedItems = $store->menuItems()
            ->whereNull('menu_category_id')
            ->where('is_available', true)
            ->withCount('optionGroups')
            ->get();

        return view('customer.store-detail', [
            'store' => $store,
            'isFavorite' => $request->user()->favoriteStores()->whereKey($store->id)->exists(),
            'uncategorizedItems' => $uncategorizedItems,
        ]);
    }
}
