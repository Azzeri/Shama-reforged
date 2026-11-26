<?php

namespace App\Actions\Ingredients;

use App\Models\Ingredient;
use App\Models\PantryItem;
use App\Models\RecipeIngredientAssignment;
use App\Models\ShoppingListItem;
use Illuminate\Support\Facades\DB;

/**
 * Merges $source into $target in one transaction: recipe ingredients, the
 * shopping list and pantry links move to the target, the source disappears.
 * A recipe that has both keeps one row: amounts in the same unit are summed,
 * otherwise the target's row wins. The plan uses recipes, so it follows
 * automatically. $keepSettingsOf decides whose section / timing / unit
 * weights / "always at home" survive.
 */
class MergeIngredients
{
    public const KEEP_TARGET = 'target';

    public const KEEP_SOURCE = 'source';

    /** @return array{recipes: int, shopping: int} what moves, for the confirmation text */
    public function preview(Ingredient $source): array
    {
        return [
            'recipes' => RecipeIngredientAssignment::query()->where('ingredient_id', $source->id)->distinct()->count('recipe_id'),
            'shopping' => ShoppingListItem::query()->where('ingredient_id', $source->id)->count(),
        ];
    }

    public function __invoke(Ingredient $source, Ingredient $target, string $keepSettingsOf = self::KEEP_TARGET): Ingredient
    {
        abort_if($source->is($target), 422, 'Cannot merge an ingredient with itself.');

        return DB::transaction(function () use ($source, $target, $keepSettingsOf) {
            $targetRows = RecipeIngredientAssignment::query()->where('ingredient_id', $target->id)->get()->keyBy('recipe_id');

            foreach (RecipeIngredientAssignment::query()->where('ingredient_id', $source->id)->get() as $row) {
                $existing = $targetRows->get($row->recipe_id);

                if (! $existing) {
                    $row->update(['ingredient_id' => $target->id]);

                    continue;
                }

                if ($existing->unit === $row->unit && $row->totalAmount() !== null) {
                    $existing->update(['amount' => ($existing->totalAmount() ?? 0) + $row->totalAmount()]);
                }

                $row->delete();
            }

            ShoppingListItem::query()->where('ingredient_id', $source->id)
                ->update(['ingredient_id' => $target->id, 'name' => $target->name]);

            PantryItem::query()->where('ingredient_id', $source->id)->update(['ingredient_id' => $target->id]);

            if ($keepSettingsOf === self::KEEP_SOURCE) {
                $target->fill($source->only([
                    Ingredient::CATEGORY_COLUMN,
                    Ingredient::PURCHASE_TIMING_COLUMN,
                    Ingredient::UNIT_WEIGHTS_COLUMN,
                    Ingredient::SKIP_SHOPPING_COLUMN,
                ]))->save();
            }

            $source->delete();

            return $target->fresh();
        });
    }
}
