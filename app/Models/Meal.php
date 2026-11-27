<?php

namespace App\Models;

use Carbon\CarbonInterface;
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

    /** "z soboty" — where leftovers come from (Polish needs the genitive, hence one key per day). */
    public static function fromDayLabel(CarbonInterface $date): string
    {
        return match ($date->dayOfWeekIso) {
            1 => __('from Monday'),
            2 => __('from Tuesday'),
            3 => __('from Wednesday'),
            4 => __('from Thursday'),
            5 => __('from Friday'),
            6 => __('from Saturday'),
            default => __('from Sunday'),
        };
    }

    /**
     * Name of the "meal type" tag matching a meal type, as stored in the
     * tags table (English, e.g. "Breakfast"). Not translated — use
     * typeLabel() for display.
     */
    public static function tagName(string $type): string
    {
        return ucfirst($type);
    }

    /**
     * Meal-type tag names in the canonical meal-of-the-day order (matches
     * TYPES), for sorting the "meal type" tag pills.
     */
    public static function orderedTagNames(): array
    {
        return array_map(fn (string $type) => self::tagName($type), self::TYPES);
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
            ->withPivot('id', 'mode', 'skip_shopping', 'semi_choices', 'cooked_from_id')
            ->orderBy('recipe_meal_assignments.id')
            ->withTimestamps();
    }
}
