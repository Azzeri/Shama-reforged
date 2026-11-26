<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A shopping list independent of the meal plan. Shared by the household. */
#[Fillable(['name'])]
class CustomList extends Model
{
    public function items(): HasMany
    {
        return $this->hasMany(CustomListItem::class)->orderBy('id');
    }
}
