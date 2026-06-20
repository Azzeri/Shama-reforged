<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class RecipeMealAssignment extends Pivot
{
    protected $table = 'recipe_meal_assignments';
}