<?php

namespace App\Services;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemOption;
use App\Models\MenuItemOptionGroup;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Management owns the restaurant catalog. This service mirrors that catalog
 * into the transactional Laravel tables so cart and order foreign keys stay local.
 */
class ManagementCatalogSync
{
    public function sync(): bool
    {
        if (app()->environment('testing')) {
            return false;
        }

        return Cache::remember('management_catalog_synced', now()->addSeconds(15), fn () => $this->syncNow());
    }

    private function syncNow(): bool
    {
        try {
            $sourceStores = DB::connection('management')->table('partnership_stores')
                ->leftJoin('store_categories', 'store_categories.id', '=', 'partnership_stores.category_id')
                ->select('partnership_stores.*', 'store_categories.name as category_name')
                ->orderBy('partnership_stores.id')
                ->get();

            $sourceItems = DB::connection('management')->table('store_menu_items')
                ->orderBy('id')->get()->groupBy('store_id');

            $sourceGroups = DB::connection('management')->table('store_menu_item_option_groups')
                ->orderBy('id')->get()->groupBy('item_id');

            $sourceOptions = DB::connection('management')->table('store_menu_item_options')
                ->orderBy('id')->get()->groupBy('group_id');

            DB::transaction(function () use ($sourceStores, $sourceItems, $sourceGroups, $sourceOptions) {
                $seenStoreIds = [];
                $seenItemIds = [];
                $seenGroupIds = [];
                $seenOptionIds = [];

                foreach ($sourceStores as $sourceStore) {
                    $sourceStoreId = (int) $sourceStore->id;
                    $seenStoreIds[] = $sourceStoreId;
                    $localStore = Store::withoutGlobalScopes()->where('management_store_id', $sourceStoreId)->first();

                    $owner = User::firstOrCreate(
                        ['email' => "management-store-{$sourceStoreId}@motobook.internal"],
                        [
                            'name' => $sourceStore->owner_name ?: $sourceStore->store_name.' Management',
                            'password' => Str::random(64),
                            'role' => 'merchant',
                            'status' => 'active',
                        ]
                    );

                    $attributes = [
                        'user_id' => $localStore?->user_id ?? $owner->id,
                        'name' => $sourceStore->store_name,
                        'slug' => Str::slug($sourceStore->store_name).'-managed-'.$sourceStoreId,
                        'description' => null,
                        'category' => $sourceStore->category_name ?: 'Restaurant',
                        'phone' => $sourceStore->contact_phone,
                        'address' => $sourceStore->branch_address,
                        'latitude' => $sourceStore->latitude,
                        'longitude' => $sourceStore->longitude,
                        'is_open' => $sourceStore->status === 'open',
                        'is_verified' => true,
                        'commission_rate' => $sourceStore->commission_rate ?? 0,
                        'management_store_id' => $sourceStoreId,
                    ];

                    if ($localStore) {
                        $localStore->update($attributes);
                    } else {
                        $localStore = Store::withoutGlobalScopes()->create($attributes);
                    }

                    foreach ($sourceItems->get($sourceStoreId, collect()) as $sourceItem) {
                        $sourceItemId = (int) $sourceItem->id;
                        $seenItemIds[] = $sourceItemId;
                        $categoryName = trim((string) ($sourceItem->category ?? 'Main')) ?: 'Main';
                        $category = MenuCategory::withoutGlobalScopes()->firstOrCreate(
                            ['store_id' => $localStore->id, 'name' => $categoryName],
                            ['sort_order' => 0]
                        );
                        $image = ! empty($sourceItem->image_path)
                            ? '../admin/'.ltrim((string) $sourceItem->image_path, '/')
                            : null;
                        $itemAttributes = [
                            'store_id' => $localStore->id,
                            'menu_category_id' => $category->id,
                            'name' => $sourceItem->item_name,
                            'description' => $sourceItem->description ?? null,
                            'price' => $sourceItem->price,
                            'image' => $image,
                            'is_available' => (bool) $sourceItem->is_available,
                            'management_menu_item_id' => $sourceItemId,
                        ];

                        $item = MenuItem::withoutGlobalScopes()->where('management_menu_item_id', $sourceItemId)->first();
                        if ($item) {
                            $item->update($itemAttributes);
                        } else {
                            $item = MenuItem::withoutGlobalScopes()->create($itemAttributes);
                        }

                        foreach ($sourceGroups->get($sourceItemId, collect()) as $sourceGroup) {
                            $sourceGroupId = (int) $sourceGroup->id;
                            $seenGroupIds[] = $sourceGroupId;
                            $groupAttributes = [
                                'menu_item_id' => $item->id,
                                'name' => $sourceGroup->group_name,
                                'selection_type' => $sourceGroup->selection_type === 'checkbox' ? 'multiple' : 'single',
                                'is_required' => (bool) $sourceGroup->is_required,
                                'max_selections' => $sourceGroup->max_select ?: null,
                                'sort_order' => $sourceGroup->sort_order ?? 0,
                                'management_option_group_id' => $sourceGroupId,
                            ];
                            $group = MenuItemOptionGroup::withoutGlobalScopes()->where('management_option_group_id', $sourceGroupId)->first();
                            if ($group) {
                                $group->update($groupAttributes);
                            } else {
                                $group = MenuItemOptionGroup::create($groupAttributes);
                            }

                            foreach ($sourceOptions->get($sourceGroupId, collect()) as $sourceOption) {
                                $sourceOptionId = (int) $sourceOption->id;
                                $seenOptionIds[] = $sourceOptionId;
                                $optionAttributes = [
                                    'menu_item_option_group_id' => $group->id,
                                    'name' => $sourceOption->option_name,
                                    'price_delta' => $sourceOption->price_delta,
                                    'is_default' => false,
                                    'is_available' => (bool) $sourceOption->is_available,
                                    'sort_order' => $sourceOption->sort_order ?? 0,
                                    'management_option_id' => $sourceOptionId,
                                ];
                                $option = MenuItemOption::where('management_option_id', $sourceOptionId)->first();
                                if ($option) {
                                    $option->update($optionAttributes);
                                } else {
                                    MenuItemOption::create($optionAttributes);
                                }
                            }
                        }
                    }
                }

                MenuItem::withoutGlobalScopes()->whereNotNull('management_menu_item_id')
                    ->whereNotIn('management_menu_item_id', $seenItemIds ?: [0])->update(['is_available' => false]);
                Store::withoutGlobalScopes()->whereNotNull('management_store_id')
                    ->whereNotIn('management_store_id', $seenStoreIds ?: [0])->update(['is_open' => false, 'is_verified' => false]);
                MenuItemOptionGroup::withoutGlobalScopes()->whereNotNull('management_option_group_id')
                    ->whereNotIn('management_option_group_id', $seenGroupIds ?: [0])->delete();
                MenuItemOption::whereNotNull('management_option_id')
                    ->whereNotIn('management_option_id', $seenOptionIds ?: [0])->update(['is_available' => false]);
            });

            return true;
        } catch (Throwable $exception) {
            Log::warning('Management catalog sync failed; serving the latest local catalog copy.', [
                'exception' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
