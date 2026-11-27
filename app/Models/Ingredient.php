<?php

namespace App\Models;

use App\Support\Ingredients\IngredientName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'purchase_timing', 'skip_shopping', 'category', 'unit_weights', 'recipe_id', 'homemade_default', 'recipe_yield_amount', 'recipe_yield_unit'])]
class Ingredient extends Model
{
    use HasFactory;

    public const NAME_COLUMN = 'name';

    public const PURCHASE_TIMING_COLUMN = 'purchase_timing';

    public const CATEGORY_COLUMN = 'category';

    public const UNIT_WEIGHTS_COLUMN = 'unit_weights';

    /** Always at home (salt, water...) — never generated onto the shopping list. */
    public const SKIP_SHOPPING_COLUMN = 'skip_shopping';

    /**
     * Units that can have a gram equivalent set per ingredient (e.g. 1 pcs
     * of egg = 50 g). Weight units themselves are excluded.
     */
    public const WEIGHABLE_UNITS = ['pcs', 'pack', 'tbsp', 'tsp', 'cup', 'can', 'bunch', 'ml', 'l'];

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
     * categories can be added here later without a migration. Order here (via
     * defaultSectionOrder(); the household can rearrange it, see sectionOrder())
     * doubles as the display order on the shopping list, with uncategorized
     * always last since it's a catch-all rather than a real aisle.
     */
    public const CATEGORY_DAIRY = 'dairy';

    public const CATEGORY_BREAD = 'bread';

    public const CATEGORY_MEAT_FISH = 'meat_fish';

    public const CATEGORY_PRODUCE = 'produce';

    public const CATEGORY_PANTRY = 'pantry';

    public const CATEGORY_FROZEN = 'frozen';

    public const CATEGORY_SPICES = 'spices';

    public const CATEGORY_GRAINS = 'grains';

    public const CATEGORY_BAKING = 'baking';

    public const CATEGORY_UNCATEGORIZED = 'uncategorized';

    public const CATEGORIES = [
        self::CATEGORY_SPICES,
        self::CATEGORY_GRAINS,
        self::CATEGORY_BAKING,
        self::CATEGORY_PANTRY,
        self::CATEGORY_BREAD,
        self::CATEGORY_MEAT_FISH,
        self::CATEGORY_DAIRY,
        self::CATEGORY_PRODUCE,
        self::CATEGORY_FROZEN,
        self::CATEGORY_UNCATEGORIZED,
    ];

    /** Not an ingredient category: the "Zapasy domowe" group of the shopping list, placed by the same order. */
    public const SECTION_HOUSEHOLD = 'household';

    public const SECTION_ORDER_SETTING = 'store_section_order';

    /** Default order of store sections on the shopping list (household supplies before "uncategorized"). */
    public static function defaultSectionOrder(): array
    {
        $order = array_values(array_diff(self::CATEGORIES, [self::CATEGORY_UNCATEGORIZED]));

        return [...$order, self::SECTION_HOUSEHOLD, self::CATEGORY_UNCATEGORIZED];
    }

    /** The household's order (shared by everyone), new sections appended in their default place. */
    public static function sectionOrder(): array
    {
        $default = self::defaultSectionOrder();
        $saved = array_values(array_intersect((array) Setting::get(self::SECTION_ORDER_SETTING, []), $default));

        return [...$saved, ...array_values(array_diff($default, $saved))];
    }

    public static function sectionLabel(string $section): string
    {
        return $section === self::SECTION_HOUSEHOLD ? __('Household supplies') : self::categoryLabel($section);
    }

    public static function categoryLabel(string $category): string
    {
        return match ($category) {
            self::CATEGORY_DAIRY => __('Dairy & eggs'),
            self::CATEGORY_BREAD => __('Bread'),
            self::CATEGORY_MEAT_FISH => __('Meat, fish & deli'),
            self::CATEGORY_PRODUCE => __('Fruit & veg'),
            self::CATEGORY_PANTRY => __('Pantry'),
            self::CATEGORY_FROZEN => __('Frozen'),
            self::CATEGORY_SPICES => __('Spices'),
            self::CATEGORY_GRAINS => __('Flour, groats & grains'),
            self::CATEGORY_BAKING => __('Sweet & baking'),
            self::CATEGORY_UNCATEGORIZED => __('Uncategorized'),
            default => ucfirst($category),
        };
    }

    protected function casts(): array
    {
        return [
            self::UNIT_WEIGHTS_COLUMN => 'array',
            self::SKIP_SHOPPING_COLUMN => 'boolean',
            'homemade_default' => 'boolean',
            'recipe_yield_amount' => 'float',
        ];
    }

    /**
     * Approximate weight in grams of $amount $unit of this ingredient, or
     * null when no weight is set for that unit.
     */
    public function gramsFor(?string $unit, ?float $amount): ?float
    {
        $perUnit = $unit ? ($this->unit_weights[$unit] ?? null) : null;

        if ($perUnit === null || $amount === null) {
            return null;
        }

        return $amount * (float) $perUnit;
    }

    /** " ≈ 100 g" suffix for display, or an empty string. */
    public function approxWeightLabel(?string $unit, ?float $amount): string
    {
        $grams = $this->gramsFor($unit, $amount);

        return $grams === null ? '' : ' ≈ '.self::formatGrams($grams);
    }

    public static function formatGrams(float $grams): string
    {
        return $grams >= 1000
            ? rtrim(rtrim(number_format($grams / 1000, 2, '.', ''), '0'), '.').' kg'
            : round($grams).' g';
    }

    /** Keeps name_key (the folded name, unique) in step with the name. */
    protected static function booted(): void
    {
        static::saving(function (self $ingredient) {
            if ($ingredient->isDirty('name') || $ingredient->name_key === null) {
                $ingredient->name_key = IngredientName::fold($ingredient->name);
            }
        });
    }

    /** The ingredient with this name regardless of case / diacritics. */
    public static function findByName(string $name): ?self
    {
        $key = IngredientName::fold($name);

        return $key === '' ? null : self::query()->where('name_key', $key)->first();
    }

    /** Existing ingredient with this name, or a new one. */
    public static function findOrCreateByName(string $name): self
    {
        return self::findByName($name) ?? self::query()->create(['name' => $name]);
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => Str::ucfirst(trim($value)),
        );
    }

    /**
     * A semi-product: an ingredient that can also be made at home from the
     * linked recipe (see App\Support\MealPlan\SemiProducts).
     */
    public function isSemiProduct(): bool
    {
        return $this->recipe_id !== null;
    }

    /** The recipe that makes this ingredient. */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function recipes(): BelongsToMany
    {
        return $this->belongsToMany(Recipe::class, 'recipe_ingredient_assignments')
            ->using(RecipeIngredientAssignment::class)
            ->withPivot('quantity')
            ->withTimestamps();
    }
}
