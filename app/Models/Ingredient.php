<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'purchase_timing'])]
class Ingredient extends Model
{
    use HasFactory;

    public const NAME_COLUMN = 'name';

    public const PURCHASE_TIMING_COLUMN = 'purchase_timing';

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