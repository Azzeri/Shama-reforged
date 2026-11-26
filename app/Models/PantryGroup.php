<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Group of household supplies (Kuchnia, Łazienka...). Created from the product form, removed when empty. */
#[Fillable(['name'])]
class PantryGroup extends Model
{
    public function items(): HasMany
    {
        return $this->hasMany(PantryItem::class);
    }

    /** Drops groups that no longer have any products. */
    public static function pruneEmpty(): void
    {
        self::query()->whereDoesntHave('items')->delete();
    }
}
