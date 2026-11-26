<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A household product with a stock state. "low" / "out" puts it into the
 * "Zapasy domowe" group of the shopping list; ticking it off there sets it
 * back to "ok".
 */
#[Fillable(['name', 'pantry_group_id', 'ingredient_id', 'status'])]
class PantryItem extends Model
{
    public const STATUS_OK = 'ok';

    public const STATUS_LOW = 'low';

    public const STATUS_OUT = 'out';

    public const STATUSES = [self::STATUS_OK, self::STATUS_LOW, self::STATUS_OUT];

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_LOW => __('Low'),
            self::STATUS_OUT => __('Out'),
            default => __('In stock'),
        };
    }

    /** "The same product as" this recipe ingredient (optional). */
    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(PantryGroup::class, 'pantry_group_id');
    }

    public function needsBuying(): bool
    {
        return $this->status !== self::STATUS_OK;
    }

    public function scopeToBuy($query)
    {
        return $query->where('status', '!=', self::STATUS_OK);
    }
}
