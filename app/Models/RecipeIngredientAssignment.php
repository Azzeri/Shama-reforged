<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class RecipeIngredientAssignment extends Pivot
{
    protected $table = 'recipe_ingredient_assignments';

    protected $fillable = ['recipe_id', 'ingredient_id', 'quantity', 'amount_me', 'amount_wife', 'unit'];

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
            'amount_me' => 'float',
            'amount_wife' => 'float',
        ];
    }

    public function displayQuantityFor(string $profile): ?string
    {
        $amount = $this->{"amount_{$profile}"};

        if ($amount !== null && $this->unit) {
            return $this->formatAmount($amount) . " {$this->unit}";
        }

        return $this->quantity ?: null;
    }

    /**
     * Combined amount needed to cover both people's portions — what the
     * shopping list actually needs to buy. Null when neither profile has an
     * amount recorded (falls back to the free-text quantity upstream).
     */
    public function totalAmount(): ?float
    {
        if ($this->amount_me === null && $this->amount_wife === null) {
            return null;
        }

        return ($this->amount_me ?? 0) + ($this->amount_wife ?? 0);
    }

    private function formatAmount(float $amount): string
    {
        return rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');
    }
}
