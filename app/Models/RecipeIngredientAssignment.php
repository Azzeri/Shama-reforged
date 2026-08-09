<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class RecipeIngredientAssignment extends Pivot
{
    protected $table = 'recipe_ingredient_assignments';

    protected $fillable = ['recipe_id', 'ingredient_id', 'quantity', 'amount', 'unit'];

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
        ];
    }

    public function displayQuantity(): ?string
    {
        if ($this->quantity) {
            return $this->quantity;
        }

        if ($this->amount !== null && $this->unit) {
            $amount = rtrim(rtrim(number_format($this->amount, 2, '.', ''), '0'), '.');

            return "{$amount} {$this->unit}";
        }

        return null;
    }
}