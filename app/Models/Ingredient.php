<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'purchase_timing', 'category'])]
class Ingredient extends Model
{
    use HasFactory;

    public const NAME_COLUMN = 'name';

    public const PURCHASE_TIMING_COLUMN = 'purchase_timing';

    public const CATEGORY_COLUMN = 'category';

    /**
     * When this ingredient needs to be bought, relative to when it's used.
     * A plain string (not a DB enum) on purpose — new cases can be added
     * here later without a schema migration.
     */
    public const PURCHASE_TIMING_FRESH = 'fresh';

    public const PURCHASE_TIMING_ADVANCE = 'advance';

    public const PURCHASE_TIMINGS = [
        self::PURCHASE_TIMING_FRESH,
        self::PURCHASE_TIMING_ADVANCE,
    ];

    public static function purchaseTimingLabel(string $timing): string
    {
        return match ($timing) {
            self::PURCHASE_TIMING_FRESH => __('Buy fresh, day before'),
            self::PURCHASE_TIMING_ADVANCE => __('Can buy for the whole week'),
            default => ucfirst($timing),
        };
    }

    /**
     * Grocery aisle grouping used to organize the shopping list. A plain
     * string (not a DB enum) for the same reason as purchase timing — new
     * categories can be added here later without a migration. Order here
     * doubles as the display order on the shopping list, with uncategorized
     * always last since it's a catch-all rather than a real aisle.
     */
    public const CATEGORY_DAIRY = 'dairy';

    public const CATEGORY_BREAD = 'bread';

    public const CATEGORY_MEAT_FISH = 'meat_fish';

    public const CATEGORY_PRODUCE = 'produce';

    public const CATEGORY_PANTRY = 'pantry';

    public const CATEGORY_FROZEN = 'frozen';

    public const CATEGORY_UNCATEGORIZED = 'uncategorized';

    public const CATEGORIES = [
        self::CATEGORY_DAIRY,
        self::CATEGORY_BREAD,
        self::CATEGORY_MEAT_FISH,
        self::CATEGORY_PRODUCE,
        self::CATEGORY_PANTRY,
        self::CATEGORY_FROZEN,
        self::CATEGORY_UNCATEGORIZED,
    ];

    public static function categoryLabel(string $category): string
    {
        return match ($category) {
            self::CATEGORY_DAIRY => __('Dairy & eggs'),
            self::CATEGORY_BREAD => __('Bread'),
            self::CATEGORY_MEAT_FISH => __('Meat, fish & deli'),
            self::CATEGORY_PRODUCE => __('Fruit & vegetables'),
            self::CATEGORY_PANTRY => __('Pantry & spices'),
            self::CATEGORY_FROZEN => __('Frozen'),
            self::CATEGORY_UNCATEGORIZED => __('Uncategorized'),
            default => ucfirst($category),
        };
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => Str::ucfirst(trim($value)),
        );
    }

    public function recipes(): BelongsToMany
    {
        return $this->belongsToMany(Recipe::class, 'recipe_ingredient_assignments')
            ->using(RecipeIngredientAssignment::class)
            ->withPivot('quantity')
            ->withTimestamps();
    }
}