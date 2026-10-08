<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Support\Facades\DB;

class MerchantController extends Controller
{
    public function index()
    {
        $stores = Store::withoutGlobalScopes()
            ->with('owner')
            ->orderBy('is_verified')
            ->orderByDesc('created_at')
            ->get();

        return view('admin.merchants.index', ['stores' => $stores]);
    }

    public function show(Store $store)
    {
        $store->loadMissing('owner', 'menuItems');

        return view('admin.merchants.show', ['store' => $store]);
    }

    public function verify(Store $store)
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($store): void {
            $store->update(['is_verified' => true]);
            $store->owner()->where('status', 'inactive')->update(['status' => 'active']);
        });

        return back()->with('status', "{$store->name} has been verified and its owner activated.");
    }

    public function unverify(Store $store)
    {
        $store->update(['is_verified' => false]);

        return back()->with('status', "{$store->name} verification revoked.");
    }

    public function ban(Store $store)
    {
        DB::transaction(function () use ($store): void {
            $store->owner->update(['status' => 'banned']);
            $store->update(['is_open' => false]);
        });

        return back()->with('status', "{$store->name}'s account has been banned.");
    }

    public function activate(Store $store)
    {
        $store->owner->update(['status' => 'active']);

        return back()->with('status', "{$store->name}'s account has been reactivated.");
    }
}
