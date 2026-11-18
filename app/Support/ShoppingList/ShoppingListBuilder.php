<?php

namespace App\Support\ShoppingList;

use App\Models\Ingredient;
use App\Models\ShoppingListItem;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Turns raw shopping list rows into what the page shows:
 *
 *  - "weekly": everything that can be bought up front — ingredients marked
 *    as "can buy for the whole week" plus manual items without a date —
 *    summed per ingredient across all days.
 *  - "days": fresh ingredients per date. The same ingredient needed on
 *    nearby days (within FRESH_MERGE_WINDOW_DAYS) is merged into one row on
 *    the earliest of those days, so batch cooking (same dinner two days in
 *    a row) is one purchase, not two.
 *  - "bought": rows ticked off in the last BOUGHT_VISIBLE_DAYS days.
 *
 * Rows are merged per ingredient. Amounts are summed per unit after
 * normalising kg→g and l→ml; if one ingredient ends up in several units and
 * the ingredient has unit weights, everything convertible is summed in
 * grams. Whatever can't be converted is listed next to it ("200 ml + 1 can").
 */
class ShoppingListBuilder
{
    public const FRESH_MERGE_WINDOW_DAYS = 2;

    public const BOUGHT_VISIBLE_DAYS = 7;

    /**
     * @param  Collection<int, ShoppingListItem>  $items  with ingredient + recipe loaded
     */
    public function build(Collection $items, CarbonInterface $today): array
    {
        $today = Carbon::parse($today)->startOfDay();

        $unchecked = $items->where(ShoppingListItem::IS_CHECKED_COLUMN, false);

        $weeklyItems = $unchecked->filter(fn (ShoppingListItem $item) => $this->isWeekly($item));
        $freshItems = $unchecked->reject(fn (ShoppingListItem $item) => $this->isWeekly($item));

        $weeklyGroups = $this->sortGroups($this->mergeAll($weeklyItems));

        $days = $this->clusterFresh($freshItems)
            ->groupBy(fn (array $group) => $group['date'])
            ->sortKeys()
            ->map(function (Collection $groups, string $date) use ($weeklyGroups) {
                $groups = $this->sortGroups($groups);

                return [
                    'date' => Carbon::parse($date),
                    'count' => $groups->count(),
                    'categories' => $this->byCategory($groups),
                    'coveredCount' => $weeklyGroups
                        ->filter(fn (array $group) => in_array($date, $group['dates'], true))
                        ->count(),
                ];
            })
            ->values();

        $boughtItems = $items
            ->where(ShoppingListItem::IS_CHECKED_COLUMN, true)
            ->filter(fn (ShoppingListItem $item) => ($item->checked_at ?? $item->updated_at)?->gte($today->copy()->subDays(self::BOUGHT_VISIBLE_DAYS)));

        $boughtGroups = $this->mergeAll($boughtItems)
            ->sortByDesc(fn (array $group) => $group['checkedAt'])
            ->values();

        $activeCount = $weeklyGroups->count() + $days->sum('count');

        return [
            'weekly' => [
                'count' => $weeklyGroups->count(),
                'categories' => $this->byCategory($weeklyGroups),
            ],
            'days' => $days,
            'bought' => [
                'count' => $boughtGroups->count(),
                'groups' => $boughtGroups,
            ],
            'progress' => [
                'bought' => $boughtGroups->count(),
                'total' => $activeCount + $boughtGroups->count(),
            ],
            'range' => $this->range($unchecked->concat($boughtItems)),
        ];
    }

    private function isWeekly(ShoppingListItem $item): bool
    {
        return $item->needed_on === null
            || $item->ingredient?->purchase_timing === Ingredient::PURCHASE_TIMING_ADVANCE;
    }

    /** Merge key: same ingredient, or — for manual rows without one — itself. */
    private function key(ShoppingListItem $item): string
    {
        return $item->ingredient_id ? "i{$item->ingredient_id}" : "x{$item->id}";
    }

    /** @return Collection<int, array> */
    private function mergeAll(Collection $items): Collection
    {
        return $items
            ->groupBy(fn (ShoppingListItem $item) => $this->key($item))
            ->map(fn (Collection $group) => $this->group($group))
            ->values();
    }

    /**
     * Fresh rows: per ingredient, walk the dates in order and start a new
     * cluster whenever a date falls outside the window opened by the
     * cluster's first date. Each cluster becomes one row on its first date.
     *
     * @return Collection<int, array>
     */
    private function clusterFresh(Collection $items): Collection
    {
        $groups = collect();

        foreach ($items->groupBy(fn (ShoppingListItem $item) => $this->key($item)) as $rows) {
            $cluster = collect();
            $clusterStart = null;

            foreach ($rows->sortBy(fn (ShoppingListItem $item) => $item->needed_on->toDateString()) as $item) {
                if ($clusterStart && $item->needed_on->diffInDays($clusterStart, true) >= self::FRESH_MERGE_WINDOW_DAYS) {
                    $groups->push($this->group($cluster));
                    $cluster = collect();
                }

                if ($cluster->isEmpty()) {
                    $clusterStart = $item->needed_on;
                }

                $cluster->push($item);
            }

            if ($cluster->isNotEmpty()) {
                $groups->push($this->group($cluster));
            }
        }

        return $groups;
    }

    /**
     * @param  Collection<int, ShoppingListItem>  $items  rows of one merge key
     */
    private function group(Collection $items): array
    {
        $first = $items->first();
        $dates = $items->pluck('needed_on')->filter()
            ->map(fn (CarbonInterface $date) => $date->toDateString())
            ->unique()->sort()->values();

        return [
            'key' => $this->key($first).':'.($dates->first() ?? 'none'),
            'ids' => $items->pluck('id')->sort()->values()->all(),
            'itemId' => $first->id,
            'name' => $first->ingredient?->name ?? $first->name,
            'quantity' => $this->quantityLabel($items, $first->ingredient),
            'subtitle' => $this->subtitle($items, $dates),
            'category' => in_array($first->ingredient?->category, Ingredient::CATEGORIES, true)
                ? $first->ingredient->category
                : Ingredient::CATEGORY_UNCATEGORIZED,
            'date' => $dates->first(),
            'dates' => $dates->all(),
            'checkedAt' => $items->max(fn (ShoppingListItem $item) => $item->checked_at?->timestamp ?? 0),
            'items' => $items->values(),
        ];
    }

    /**
     * Sums the rows' amounts into a display string.
     */
    public function quantityLabel(Collection $items, ?Ingredient $ingredient): ?string
    {
        $sums = [];
        $texts = [];

        foreach ($items as $item) {
            if ($item->amount === null) {
                if (filled($item->quantity)) {
                    $texts[] = trim($item->quantity);
                }

                continue;
            }

            [$amount, $unit] = Quantity::normalize($item->amount, $item->unit);
            $unit ??= '';
            $sums[$unit] = ($sums[$unit] ?? 0) + $amount;
        }

        if (count($sums) > 1 && $ingredient) {
            $grams = 0.0;

            foreach ($sums as $unit => $amount) {
                $converted = $unit === 'g' ? $amount : $ingredient->gramsFor($unit, $amount);

                if ($converted !== null) {
                    $grams += $converted;
                    unset($sums[$unit]);
                }
            }

            if ($grams > 0) {
                $sums = ['g' => $grams] + $sums;
            }
        }

        $parts = collect($sums)
            ->map(fn (float $amount, string $unit) => Quantity::format($amount, $unit !== '' ? $unit : null))
            ->values()
            ->all();

        if (count($sums) === 1 && $ingredient && ! in_array(array_key_first($sums), ['g', ''], true)) {
            $unit = array_key_first($sums);
            $parts[0] .= $ingredient->approxWeightLabel($unit, $sums[$unit]);
        }

        $parts = [...$parts, ...array_unique($texts)];

        return $parts ? implode(' + ', $parts) : null;
    }

    private function subtitle(Collection $items, Collection $dates): ?string
    {
        $recipes = $items->pluck('recipe')->filter()->unique('id')->values();
        $parts = [];

        if ($recipes->count() === 1) {
            $parts[] = $recipes->first()->name;
        } elseif ($recipes->count() > 1) {
            $parts[] = trans_choice(':count recipe|:count recipes', $recipes->count());
        }

        if ($dates->count() > 1 || $recipes->count() > 1) {
            $parts[] = $dates
                ->map(fn (string $date) => Carbon::parse($date)->locale(app()->getLocale())->isoFormat('ddd'))
                ->implode(', ');
        }

        if (! $parts) {
            $notes = $items->pluck('notes')->filter()->unique()->implode(', ');

            return $notes !== '' ? $notes : null;
        }

        return implode(' · ', array_filter($parts));
    }

    private function sortGroups(Collection $groups): Collection
    {
        return $groups->sortBy(fn (array $group) => mb_strtolower($group['name']))->values();
    }

    /**
     * @return Collection<int, array{category: string, label: string, groups: Collection}>
     */
    private function byCategory(Collection $groups): Collection
    {
        $byCategory = $groups->groupBy('category');

        return collect(Ingredient::CATEGORIES)
            ->filter(fn (string $category) => $byCategory->has($category))
            ->map(fn (string $category) => [
                'category' => $category,
                'label' => Ingredient::categoryLabel($category),
                'groups' => $byCategory->get($category)->values(),
            ])
            ->values();
    }

    /** @return array{0: Carbon, 1: Carbon}|null */
    private function range(Collection $items): ?array
    {
        $dates = $items->pluck('needed_on')->filter();

        if ($dates->isEmpty()) {
            return null;
        }

        return [Carbon::parse($dates->min()), Carbon::parse($dates->max())];
    }
}
