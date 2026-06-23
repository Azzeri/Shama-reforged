<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'category'])]
class Tag extends Model
{
    use HasFactory;

    public const NAME_COLUMN = 'name';
    public const CATEGORY_COLUMN = 'category';

    public const MEAL_TYPE = 'meal_type';
    public const DIET_TYPE = 'diet_type';

    public const MEAL_TYPE_NAMES = ['sniadanie', 'lunch', 'obiad', 'kolacja', 'deser'];
    public const DIET_TYPE_NAMES = [
        'wieprzowina',
        'kurczak',
        'wołowina',
        'wege',
        'ryba',
        'owoce morza',
    ];

    public function recipes(): BelongsToMany
    {
        return $this->belongsToMany(Recipe::class, 'recipe_tag_assignments')
            ->withTimestamps();
    }

    public function isMealType(): bool
    {
        return $this->category === self::MEAL_TYPE;
    }

    public function isDietType(): bool
    {
        return $this->category === self::DIET_TYPE;
    }
}