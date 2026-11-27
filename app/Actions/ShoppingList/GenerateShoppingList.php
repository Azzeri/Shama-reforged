<?php

namespace App\Actions\ShoppingList;

use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\RecipeMealAssignment;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Support\MealPlan\SemiProducts;
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
 *
 * Amounts follow the plan entry's mode: one meal = servingsPerMeal of the
 * recipe's servings, a recipe cooked whole = full amounts on the cooking
 * day, leftovers = nothing. Entries marked "have it ready" add nothing.
 *
 * A semi-product (an ingredient with its own recipe) follows the choice made
 * for the meal: bought ready like any ingredient, made at home — then the
 * linked recipe's ingredients are added instead, scaled to the amount the
 * dish needs — or already there, which adds nothing.
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
                ->map(fn (ShoppingListItem $item) => "{$item->meal_id}:{$item->recipe_id}:{$item->ingredient_id}:{$item->semi_ingredient_id}")
                ->flip();

            $meals = Meal::query()
                ->with(['recipes' => fn ($query) => $query->with('ingredients.recipe.ingredients')])
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
                    $factor = RecipeMealAssignment::shoppingFactor($recipe->pivot->mode, (int) $recipe->servings, (bool) $recipe->pivot->skip_shopping);

                    if ($factor <= 0) {
                        continue;
                    }

                    $semiChoices = SemiProducts::choicesFor($recipe);

                    foreach ($this->rowsFor($recipe->ingredients, $factor, $semiChoices) as $row) {
                        /** @var Ingredient $ingredient */
                        $ingredient = $row['ingredient'];

                        if ($ingredient->skip_shopping) {
                            continue;
                        }

                        if ($bought->has("{$meal->id}:{$recipe->id}:{$ingredient->id}:{$row['semi']?->id}")) {
                            $kept++;

                            continue;
                        }

                        $pivot = $ingredient->pivot;
                        $quantity = trim((string) ($pivot->quantity ?? ''));
                        $total = $pivot->totalAmount();
                        [$amount, $unit] = Quantity::normalize($total !== null ? round($total * $row['factor'], 2) : null, $pivot->unit);

                        $shoppingList->items()->create([
                            'name' => $ingredient->name,
                            'quantity' => $amount === null && $quantity !== '' ? $quantity : null,
                            'amount' => $amount,
                            'unit' => $amount !== null ? $unit : null,
                            'is_optional' => $row['optional'] || $pivot->is_optional,
                            'substitute' => filled($pivot->substitute) ? $pivot->substitute : null,
                            'is_checked' => false,
                            'needed_on' => $meal->date->toDateString(),
                            'recipe_id' => $recipe->id,
                            'meal_id' => $meal->id,
                            'ingredient_id' => $ingredient->id,
                            'semi_ingredient_id' => $row['semi']?->id,
                        ]);

                        $added++;
                    }
                }
            }

            return ['added' => $added, 'kept' => $kept];
        });
    }

    /**
     * What a planned recipe needs bought: its ingredients, with home-made
     * semi-products replaced by the ingredients of their recipe (one level —
     * a semi-product in there is bought) and the ones already at home left out.
     *
     * @param  array<int, string>  $semiChoices  ingredient id => buy | make | have
     * @return list<array{ingredient: Ingredient, factor: float, optional: bool, semi: ?Ingredient}>
     */
    private function rowsFor(iterable $ingredients, float $factor, array $semiChoices): array
    {
        $rows = [];

        foreach ($ingredients as $ingredient) {
            $choice = $semiChoices[$ingredient->id] ?? SemiProducts::BUY;

            if ($choice === SemiProducts::HAVE) {
                continue;
            }

            if ($choice !== SemiProducts::MAKE || ! $ingredient->recipe) {
                $rows[] = ['ingredient' => $ingredient, 'factor' => $factor, 'optional' => false, 'semi' => null];

                continue;
            }

            $total = $ingredient->pivot->totalAmount();
            $ratio = SemiProducts::ratio($ingredient, $total !== null ? $total * $factor : null, $ingredient->pivot->unit);

            foreach ($ingredient->recipe->ingredients as $part) {
                $rows[] = ['ingredient' => $part, 'factor' => $ratio, 'optional' => (bool) $ingredient->pivot->is_optional, 'semi' => $ingredient];
            }
        }

        return $rows;
    }
}
