<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemOption;
use App\Models\MenuItemOptionGroup;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function index(Request $request)
    {
        $store = $request->user()->store;

        if (! $store) {
            return redirect()->route('merchant.onboarding');
        }

        return view('merchant.menu.index', [
            'store' => $store,
            'categories' => $store->menuCategories()->withCount('menuItems')->orderBy('sort_order')->get(),
            'items' => $store->menuItems()->with('category')->latest()->get(),
            'editingItem' => null,
        ]);
    }

    public function edit(Request $request, MenuItem $menuItem)
    {
        $store = $request->user()->store;

        if (! $store) {
            return redirect()->route('merchant.onboarding');
        }

        return view('merchant.menu.index', [
            'store' => $store,
            'categories' => $store->menuCategories()->withCount('menuItems')->orderBy('sort_order')->get(),
            'items' => $store->menuItems()->with('category')->latest()->get(),
            'editingItem' => $menuItem->load('optionGroups.options'),
        ]);
    }

    public function options(Request $request, MenuItem $menuItem)
    {
        return view('merchant.menu.options', [
            'store' => $request->user()->store,
            'item' => $menuItem->load('optionGroups.options'),
        ]);
    }

    public function storeOptionGroup(Request $request, MenuItem $menuItem)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'selection_type' => ['required', 'in:single,multiple'],
            'is_required' => ['nullable', 'boolean'],
            'max_selections' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $validated['is_required'] = $request->boolean('is_required');
        $menuItem->optionGroups()->create($validated);

        return back()->with('status', 'Option group added.');
    }

    public function storeOption(Request $request, MenuItemOptionGroup $group)
    {
        $this->assertGroupBelongsToMerchant($request, $group);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'price_delta' => ['required', 'numeric', 'min:0', 'max:999999.99'],
        ]);
        $group->options()->create($validated);

        return back()->with('status', 'Option added.');
    }

    public function destroyOptionGroup(Request $request, MenuItemOptionGroup $group)
    {
        $this->assertGroupBelongsToMerchant($request, $group);
        $group->delete();

        return back()->with('status', 'Option group removed.');
    }

    public function destroyOption(Request $request, MenuItemOption $option)
    {
        $this->assertGroupBelongsToMerchant($request, $option->group);
        $option->delete();

        return back()->with('status', 'Option removed.');
    }

    public function storeCategory(Request $request)
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:100']]);
        $request->user()->store->menuCategories()->create($validated);

        return back()->with('status', 'Category added.');
    }

    public function destroyCategory(Request $request, MenuCategory $category)
    {
        abort_unless($request->user()->store?->menuCategories()->whereKey($category->id)->exists(), 404);

        $category->delete();

        return back()->with('status', 'Category removed. Items were kept in the menu.');
    }

    public function storeItem(Request $request)
    {
        $store = $request->user()->store;
        $validated = $this->validateItem($request);
        $validated['menu_category_id'] = $this->categoryId($store, $validated['menu_category_id'] ?? null);
        $validated['is_available'] = $request->boolean('is_available');

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('menu-items', 'public');
        }

        $store->menuItems()->create($validated);

        return redirect()->route('merchant.menu.index')->with('status', 'Menu item added.');
    }

    public function updateItem(Request $request, MenuItem $menuItem)
    {
        $store = $request->user()->store;
        $validated = $this->validateItem($request);
        $validated['menu_category_id'] = $this->categoryId($store, $validated['menu_category_id'] ?? null);
        $validated['is_available'] = $request->boolean('is_available');

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('menu-items', 'public');
        }

        $menuItem->update($validated);

        return redirect()->route('merchant.menu.index')->with('status', 'Menu item updated.');
    }

    public function destroyItem(MenuItem $menuItem)
    {
        $menuItem->delete();

        return back()->with('status', 'Menu item removed.');
    }

    private function validateItem(Request $request): array
    {
        return $request->validate([
            'menu_category_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'calories' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'prep_time_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'highlight_badge' => ['nullable', 'string', 'max:50'],
            'image' => ['nullable', 'image', 'max:2048'],
        ]);
    }

    private function categoryId($store, ?int $categoryId): ?int
    {
        if ($categoryId === null) {
            return null;
        }

        return $store->menuCategories()->findOrFail($categoryId)->id;
    }

    private function assertGroupBelongsToMerchant(Request $request, MenuItemOptionGroup $group): void
    {
        abort_unless($request->user()->store->menuItems()->whereKey($group->menu_item_id)->exists(), 404);
    }
}
