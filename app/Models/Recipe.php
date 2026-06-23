<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'content', 'link'])]
class Recipe extends Model
{
    use HasFactory;

    public const NAME_COLUMN = 'name';

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'recipe_ingredient_assignments')
            ->using(RecipeIngredientAssignment::class)
            ->withPivot('quantity')
            ->withTimestamps();
    }

    public function meals(): BelongsToMany
    {
        return $this->belongsToMany(Meal::class, 'recipe_meal_assignments')
            ->using(RecipeMealAssignment::class)
            ->withTimestamps();
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'recipe_tag_assignments')
            ->withTimestamps();
    }
}