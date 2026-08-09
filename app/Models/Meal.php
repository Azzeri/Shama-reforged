<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['type', 'date', 'note'])]
class Meal extends Model
{
    use HasFactory;

    public const TYPE_COLUMN = 'type';

    public const DATE_COLUMN = 'date';

    public const NOTE_COLUMN = 'note';

    public const TYPES = ['breakfast', 'lunch', 'dinner', 'supper', 'dessert'];

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'breakfast' => __('Breakfast'),
            'lunch' => __('Lunch'),
            'dinner' => __('Dinner'),
            'dessert' => __('Dessert'),
            'supper' => __('Supper'),
            default => ucfirst($type),
        };
    }

    /**
     * Type labels in the canonical meal-of-the-day order (matches TYPES).
     * Used to sort anything keyed by label instead of by type — e.g. the
     * "meal type" tag pills, whose names are free text set by the user.
     */
    public static function orderedTypeLabels(): array
    {
        return array_map(fn (string $type) => self::typeLabel($type), self::TYPES);
    }

    protected function casts(): array
    {
        return [
            'date' => 'datetime',
        ];
    }

    public function recipes(): BelongsToMany
    {
        return $this->belongsToMany(Recipe::class, 'recipe_meal_assignments')
            ->using(RecipeMealAssignment::class)
            ->withTimestamps();
    }
}