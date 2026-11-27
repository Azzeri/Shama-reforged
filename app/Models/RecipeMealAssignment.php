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
 *
 * skip_shopping ("have it ready", e.g. a portion from the freezer): the entry
 * stays in the plan but puts nothing on the shopping list, whatever its mode.
 */
class RecipeMealAssignment extends Pivot
{
    public const MODE_SINGLE = 'single';

    public const MODE_FULL = 'full';

    public const MODE_LEFTOVER = 'leftover';

    public const MODES = [self::MODE_SINGLE, self::MODE_FULL, self::MODE_LEFTOVER];

    protected $table = 'recipe_meal_assignments';

    public $incrementing = true;

    protected $fillable = ['recipe_id', 'meal_id', 'mode', 'skip_shopping', 'cooked_from_id'];

    protected $attributes = ['skip_shopping' => false];

    protected function casts(): array
    {
        return ['skip_shopping' => 'boolean'];
    }

    /** "mam gotowe · bez zakupów" */
    public static function readyCaption(): string
    {
        return __('have it ready').' · '.__('no shopping');
    }

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
     * shopping list: 1 for a recipe cooked whole, 0 for leftovers and for
     * "have it ready" entries, and one meal's worth (2 of 6 servings = 1/3)
     * otherwise.
     */
    public static function shoppingFactor(?string $mode, int $servings, bool $skipShopping = false): float
    {
        if ($skipShopping) {
            return 0.0;
        }

        return match ($mode) {
            self::MODE_FULL => 1.0,
            self::MODE_LEFTOVER => 0.0,
            default => min(1.0, Recipe::servingsPerMeal() / max(1, $servings)),
        };
    }
}
