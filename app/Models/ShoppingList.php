<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class ShoppingList extends Model
{
    use HasFactory;

    /** The app has a single shared list. */
    public static function main(): self
    {
        return self::query()->firstOrCreate(['id' => 1], ['name' => 'Main shopping list']);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShoppingListItem::class);
    }
}
