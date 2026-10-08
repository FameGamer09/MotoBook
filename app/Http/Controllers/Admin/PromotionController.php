<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SavePromotionRequest;
use App\Models\Promotion;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    public function index(): View
    {
        return view('admin.promotions.index', [
            'promotions' => Promotion::with('store')->latest()->paginate(15),
            'stores' => Store::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(SavePromotionRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['code'] = filled($validated['code'] ?? null) ? Str::upper(trim($validated['code'])) : null;
        $validated['min_order_amount'] = $validated['min_order_amount'] ?? 0;
        $validated['is_active'] = $request->boolean('is_active');

        Promotion::create($validated);

        return redirect()->route('admin.promotions.index')->with('status', 'Promotion created.');
    }

    public function update(SavePromotionRequest $request, Promotion $promotion): RedirectResponse
    {
        $validated = $request->validated();
        $validated['code'] = filled($validated['code'] ?? null) ? Str::upper(trim($validated['code'])) : null;
        $validated['min_order_amount'] = $validated['min_order_amount'] ?? 0;
        $validated['is_active'] = $request->boolean('is_active');

        $promotion->update($validated);

        return redirect()->route('admin.promotions.index')->with('status', 'Promotion updated.');
    }

    public function toggle(Promotion $promotion): RedirectResponse
    {
        $promotion->update(['is_active' => ! $promotion->is_active]);

        return back()->with('status', $promotion->is_active ? 'Promotion activated.' : 'Promotion deactivated.');
    }
}
