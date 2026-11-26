<?php

namespace App\Support\LiveSync;

use App\Models\CustomList;
use App\Models\PantryItem;
use App\Models\ShoppingListItem;
use Illuminate\Database\Eloquent\Builder;

/**
 * A cheap fingerprint of the data a shared screen shows (row count + last
 * change of each table involved). Phones poll it instead of re-rendering the
 * whole page; when it changes, the page refreshes itself. Counts catch
 * deletions, the newest updated_at catches additions and edits.
 */
class DataVersion
{
    public const SHOPPING = 'shopping';

    public const CUSTOM_LIST = 'custom-list';

    /** Returned for a custom list that no longer exists. */
    public const GONE = 'gone';

    public static function for(string $scope, ?int $id = null): ?string
    {
        $parts = match ($scope) {
            self::SHOPPING => [
                self::fingerprint(ShoppingListItem::query(), 'is_checked'),
                self::fingerprint(PantryItem::query(), "CASE WHEN status = 'ok' THEN 0 ELSE 1 END"),
            ],
            self::CUSTOM_LIST => $id && ($list = CustomList::query()->find($id))
                ? [self::fingerprint($list->items()->getQuery()->reorder(), 'is_checked'), (string) $list->updated_at]
                : null,
            default => false,
        };

        return match ($parts) {
            false => null,
            null => self::GONE,
            default => md5(implode('|', $parts)),
        };
    }

    /**
     * Row count + newest updated_at + a sum over a state column — the sum
     * catches a tick and an untick within the same second.
     */
    private static function fingerprint(Builder $query, string $stateSql): string
    {
        $row = $query->toBase()
            ->selectRaw("COUNT(*) as aggregate_count, MAX(updated_at) as last_change, SUM({$stateSql}) as state_sum")
            ->first();

        return ($row->aggregate_count ?? 0).'@'.($row->last_change ?? '-').'#'.($row->state_sum ?? 0);
    }
}
