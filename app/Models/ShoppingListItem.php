<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'quantity', 'is_checked', 'notes', 'week_day', 'shopping_list_id', 'recipe_id', 'meal_id'])]
class ShoppingListItem extends Model
{
    use HasFactory;

    public const WEEK_DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    public const WEEK_DAY_LABELS = [
        'monday' => 'Poniedziałek',
        'tuesday' => 'Wtorek',
        'wednesday' => 'Środa',
        'thursday' => 'Czwartek',
        'friday' => 'Piątek',
        'saturday' => 'Sobota',
        'sunday' => 'Niedziela',
    ];

    public const NAME_COLUMN = 'name';

    public const QUANTITY_COLUMN = 'quantity';

    public const IS_CHECKED_COLUMN = 'is_checked';

    public const WEEK_DAY_COLUMN = 'week_day';

    protected function casts(): array
    {
        return [
            'is_checked' => 'boolean',
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
}