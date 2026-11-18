<?php

namespace App\Actions\ShoppingList;

use App\Models\Meal;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Support\ShoppingList\Quantity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Fills the shopping list with the ingredients of the meals planned on the
 * given dates. Idempotent per date: unbought generated rows for those dates
 * are rebuilt from the current plan (so regenerating after changing the plan
 * updates the list instead of doubling it), while anything already bought
 * stays and isn't added again. Manually added items are never touched.
 * Ingredients marked "skip on the shopping list" (salt, water...) are left out.
 */
class GenerateShoppingList
{
    /**
     * @param  list<string>  $dates  Y-m-d
     * @return array{added: int, kept: int}
     */
    public function __invoke(array $dates): array
    {
        $dates = collect($dates)
            ->map(fn (string $date) => Carbon::parse($date)->toDateString())
            ->unique()
            ->values();

        if ($dates->isEmpty()) {
            return ['added' => 0, 'kept' => 0];
        }

        $shoppingList = ShoppingList::main();

        return DB::transaction(function () use ($dates, $shoppingList) {
            $generated = $shoppingList->items()
                ->whereNotNull('meal_id')
                ->whereIn(ShoppingListItem::NEEDED_ON_COLUMN, $dates->all());

            (clone $generated)->where(ShoppingListItem::IS_CHECKED_COLUMN, false)->delete();

            $bought = (clone $generated)
                ->where(ShoppingListItem::IS_CHECKED_COLUMN, true)
                ->get()
                ->map(fn (ShoppingListItem $item) => "{$item->meal_id}:{$item->recipe_id}:{$item->ingredient_id}")
                ->flip();

            $meals = Meal::query()
                ->with(['recipes' => fn ($query) => $query->with('ingredients')])
                ->where(function ($query) use ($dates) {
                    foreach ($dates as $date) {
                        $query->orWhereDate(Meal::DATE_COLUMN, $date);
                    }
                })
                ->get();

            $added = 0;
            $kept = 0;

            foreach ($meals as $meal) {
                foreach ($meal->recipes as $recipe) {
                    foreach ($recipe->ingredients as $ingredient) {
                        if ($ingredient->skip_shopping) {
                            continue;
                        }

                        if ($bought->has("{$meal->id}:{$recipe->id}:{$ingredient->id}")) {
                            $kept++;

                            continue;
                        }

                        $pivot = $ingredient->pivot;
                        $quantity = trim((string) ($pivot->quantity ?? ''));
                        [$amount, $unit] = Quantity::normalize($pivot->totalAmount(), $pivot->unit);

                        $shoppingList->items()->create([
                            'name' => $ingredient->name,
                            'quantity' => $amount === null && $quantity !== '' ? $quantity : null,
                            'amount' => $amount,
                            'unit' => $amount !== null ? $unit : null,
                            'is_checked' => false,
                            'needed_on' => $meal->date->toDateString(),
                            'recipe_id' => $recipe->id,
                            'meal_id' => $meal->id,
                            'ingredient_id' => $ingredient->id,
                        ]);

                        $added++;
                    }
                }
            }

            return ['added' => $added, 'kept' => $kept];
        });
    }
}
