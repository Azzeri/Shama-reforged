<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'quantity', 'is_checked', 'notes', 'week_day', 'shopping_list_id', 'recipe_id', 'meal_id', 'ingredient_id', 'amount', 'unit'])]
class ShoppingListItem extends Model
{
    use HasFactory;

    public const WEEK_DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    public static function dayLabel(string $day): string
    {
        return match ($day) {
            'monday' => __('Monday'),
            'tuesday' => __('Tuesday'),
            'wednesday' => __('Wednesday'),
            'thursday' => __('Thursday'),
            'friday' => __('Friday'),
            'saturday' => __('Saturday'),
            'sunday' => __('Sunday'),
            default => ucfirst($day),
        };
    }

    public const NAME_COLUMN = 'name';

    public const QUANTITY_COLUMN = 'quantity';

    public const IS_CHECKED_COLUMN = 'is_checked';

    public const WEEK_DAY_COLUMN = 'week_day';

    protected function casts(): array
    {
        return [
            'is_checked' => 'boolean',
            'amount' => 'float',
        ];
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