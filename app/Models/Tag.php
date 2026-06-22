<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name'])]
class Tag extends Model
{
    use HasFactory;

    public const NAME_COLUMN = 'name';

    public const DEFAULT_NAMES = ['sniadanie', 'lunch', 'obiad', 'kolacja', 'deser'];

    public function recipes(): BelongsToMany
    {
        return $this->belongsToMany(Recipe::class, 'recipe_tag_assignments')
            ->withTimestamps();
    }
}