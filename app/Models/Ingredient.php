<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

#[Fillable(['name'])]
class Ingredient extends Model
{
    use HasFactory;

    public const NAME_COLUMN = 'name';

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