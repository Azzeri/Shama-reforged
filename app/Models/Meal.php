<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['type', 'date'])]
class Meal extends Model
{
    use HasFactory;

    public const TYPE_COLUMN = 'type';

    public const DATE_COLUMN = 'date';

    public const TYPES = ['breakfast', 'lunch', 'dinner', 'dessert'];

    public const TYPE_LABELS = [
        'breakfast' => 'Śniadanie',
        'lunch' => 'Obiad',
        'dinner' => 'Kolacja',
        'dessert' => 'Deser',
    ];

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