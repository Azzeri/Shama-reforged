<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * A recipe planned for a meal. Mode:
 *  - single: one meal — the shopping list gets servingsPerMeal / servings
 *    of every ingredient;
 *  - full: the whole recipe is cooked that day — full amounts on that day;
 *  - leftover: eaten from a "full" entry on an earlier day (cooked_from_id),
 *    nothing to buy. Deleted together with the cooking entry.
 */
class RecipeMealAssignment extends Pivot
{
    public const MODE_SINGLE = 'single';

    public const MODE_FULL = 'full';

    public const MODE_LEFTOVER = 'leftover';

    public const MODES = [self::MODE_SINGLE, self::MODE_FULL, self::MODE_LEFTOVER];

    protected $table = 'recipe_meal_assignments';

    public $incrementing = true;

    protected $fillable = ['recipe_id', 'meal_id', 'mode', 'cooked_from_id'];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function meal(): BelongsTo
    {
        return $this->belongsTo(Meal::class);
    }

    public function cookedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'cooked_from_id');
    }

    public function leftovers(): HasMany
    {
        return $this->hasMany(self::class, 'cooked_from_id');
    }

    /**
     * Share of the recipe's ingredient amounts this entry puts on the
     * shopping list: 1 for a recipe cooked whole, 0 for leftovers, and one
     * meal's worth (2 of 6 servings = 1/3) otherwise.
     */
    public static function shoppingFactor(?string $mode, int $servings): float
    {
        return match ($mode) {
            self::MODE_FULL => 1.0,
            self::MODE_LEFTOVER => 0.0,
            default => min(1.0, Recipe::servingsPerMeal() / max(1, $servings)),
        };
    }
}
