<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class RecipeIngredientAssignment extends Pivot
{
    protected $table = 'recipe_ingredient_assignments';

    protected $fillable = ['recipe_id', 'ingredient_id', 'quantity'];
}