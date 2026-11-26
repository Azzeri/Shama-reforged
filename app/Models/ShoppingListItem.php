<?php

namespace App\Models;

use App\Support\ShoppingList\Quantity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Date;

#[Fillable(['name', 'quantity', 'is_checked', 'checked_at', 'notes', 'needed_on', 'shopping_list_id', 'recipe_id', 'meal_id', 'ingredient_id', 'amount', 'unit', 'is_optional', 'substitute', 'buy_anyway'])]
class ShoppingListItem extends Model
{
    use HasFactory;

    public const NAME_COLUMN = 'name';

    public const QUANTITY_COLUMN = 'quantity';

    public const IS_CHECKED_COLUMN = 'is_checked';

    public const NEEDED_ON_COLUMN = 'needed_on';

    public const CHECKED_AT_COLUMN = 'checked_at';

    protected function casts(): array
    {
        return [
            'is_checked' => 'boolean',
            'checked_at' => 'datetime',
            'amount' => 'float',
            'is_optional' => 'boolean',
            'buy_anyway' => 'boolean',
        ];
    }

    /**
     * Stored as a plain Y-m-d string (not the default date cast's datetime
     * format) so string comparisons and whereIn on dates behave the same in
     * SQLite and MySQL.
     */
    protected function neededOn(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value !== null ? Date::parse($value)->startOfDay() : null,
            set: fn ($value) => filled($value) ? Date::parse($value)->toDateString() : null,
        );
    }

    public function shoppingList(): BelongsTo
    {
        return $this->belongsTo(ShoppingList::class);
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function meal(): BelongsTo
    {
        return $this->belongsTo(Meal::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function displayQuantity(): ?string
    {
        if (filled($this->quantity)) {
            return $this->quantity;
        }

        return Quantity::format($this->amount, $this->unit);
    }
}
