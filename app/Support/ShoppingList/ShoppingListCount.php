<?php

namespace App\Support\ShoppingList;

use App\Models\PantryItem;
use App\Models\ShoppingList;

/** How many rows are left to buy — the number on the app icon. */
class ShoppingListCount
{
    public static function get(): int
    {
        $items = ShoppingList::main()->items()->with(['recipe:id,name', 'ingredient'])->get();
        $pantry = PantryItem::query()->where(fn ($query) => $query->toBuy()->orWhereNotNull('ingredient_id'))->get();
        $list = app(ShoppingListBuilder::class)->build($items, today(), $pantry);

        return max(0, $list['progress']['total'] - $list['progress']['bought']);
    }
}
