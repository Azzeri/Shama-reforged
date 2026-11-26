<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A shopping list independent of the meal plan. Shared by the household. */
#[Fillable(['name'])]
class CustomList extends Model
{
    /** Unticked first, ticked at the bottom; each in the order they were added. */
    public function items(): HasMany
    {
        return $this->hasMany(CustomListItem::class)->orderBy('is_checked')->orderBy('id');
    }
}
