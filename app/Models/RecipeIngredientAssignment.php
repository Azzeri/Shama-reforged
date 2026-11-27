<?php

namespace App\Models;

use App\Support\ShoppingList\Quantity;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * An ingredient of a recipe. `amount` is for the whole recipe (all of its
 * servings). The old per-person columns (amount_me / amount_wife) are kept
 * in the DB but no longer shown or edited.
 */
class RecipeIngredientAssignment extends Pivot
{
    protected $table = 'recipe_ingredient_assignments';

    public $incrementing = true;

    protected $fillable = ['recipe_id', 'ingredient_id', 'quantity', 'amount', 'amount_me', 'amount_wife', 'unit', 'is_optional', 'substitute', 'semi_source'];

    /**
     * Units a shopping list total can be summed in. A plain list (not a DB
     * enum) on purpose — new units can be added here later without a
     * migration. Package-style units (szt, opak, puszka...) are intentionally
     * mixed in with weight/volume units: nothing here assumes they're
     * convertible to one another, grouping for totals only ever merges exact
     * matches.
     */
    public const UNITS = ['g', 'kg', 'ml', 'l', 'pcs', 'pack', 'tbsp', 'tsp', 'cup', 'can', 'bunch'];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'amount_me' => 'float',
            'amount_wife' => 'float',
            'is_optional' => 'boolean',
        ];
    }

    /**
     * Amount for the whole recipe. Falls back to the per-person sum for rows
     * written before the switch to servings.
     */
    public function totalAmount(): ?float
    {
        if ($this->amount !== null) {
            return $this->amount;
        }

        if ($this->amount_me === null && $this->amount_wife === null) {
            return null;
        }

        return ($this->amount_me ?? 0) + ($this->amount_wife ?? 0);
    }

    /** "150 g" scaled by $factor (e.g. 3 of 2 servings = 1.5), or the free-text quantity. */
    public function displayQuantity(float $factor = 1.0): ?string
    {
        $amount = $this->totalAmount();

        if ($amount !== null && $this->unit) {
            return Quantity::format(round($amount * $factor, 2), $this->unit);
        }

        return filled($this->quantity) ? $this->quantity : null;
    }
}
