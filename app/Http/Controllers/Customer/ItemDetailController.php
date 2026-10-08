<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Services\CartService;
use Illuminate\Http\Request;

class ItemDetailController extends Controller
{
    public function __construct(private CartService $cart) {}

    public function show(MenuItem $menuItem)
    {
        abort_unless($menuItem->is_available, 404);

        $menuItem->load(['optionGroups.options' => fn ($query) => $query->where('is_available', true), 'store']);

        return view('customer.item-detail', ['item' => $menuItem]);
    }

    public function addToCart(Request $request, MenuItem $menuItem)
    {
        abort_unless($menuItem->is_available, 404);

        $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'options' => ['array'],
        ]);

        $quantity = (int) $request->input('quantity');

        // flatten the nested {group_id: option_id or [option_ids]} structure
        // into a plain list of selected option IDs
        $optionIds = collect($request->input('options', []))
            ->flatMap(fn ($value) => is_array($value) ? $value : [$value])
            ->map(fn ($v) => (int) $v)
            ->values()
            ->all();

        // server-side validation: every required single-select group must
        // have exactly one of ITS OWN options chosen — never trust the
        // client to have enforced this correctly
        $menuItem->loadMissing('optionGroups.options');

        foreach ($menuItem->optionGroups as $group) {
            $groupOptionIds = $group->options->pluck('id');
            $selectedInGroup = collect($optionIds)->intersect($groupOptionIds);

            if ($group->is_required && $selectedInGroup->isEmpty()) {
                return back()->withErrors("Please select an option for \"{$group->name}\".");
            }

            if ($group->selection_type === 'single' && $selectedInGroup->count() > 1) {
                return back()->withErrors("Only one option allowed for \"{$group->name}\".");
            }

            if ($group->max_selections && $selectedInGroup->count() > $group->max_selections) {
                return back()->withErrors("Too many selections for \"{$group->name}\".");
            }
        }

        $this->cart->addItem($menuItem, $quantity, $optionIds);

        return redirect()->route('customer.restaurants.show', $menuItem->store)
            ->with('status', "{$menuItem->name} added to cart.");
    }
}
