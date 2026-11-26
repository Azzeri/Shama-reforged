<?php

namespace App\Support\ShoppingList;

use App\Models\Ingredient;
use App\Models\PantryItem;
use App\Models\ShoppingListItem;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Turns raw shopping list rows into what the page shows:
 *
 *  - "upcoming" ("Najbliższe zakupy"): everything to buy now — fresh
 *    ingredients needed today or tomorrow (or overdue), plus everything that
 *    can be bought for the whole week (ingredients marked so and manual
 *    items without a date). Merged per ingredient, so the same product from
 *    several recipes/days is one row; rows needed today/tomorrow carry a
 *    "when" label.
 *  - "days": fresh ingredients for the days after tomorrow, one section per
 *    day, merged per ingredient within the day.
 *  - "bought": rows ticked off in the last BOUGHT_VISIBLE_DAYS days.
 *  - "household" ("Zapasy domowe"): household products marked low / out —
 *    one row each, with the amounts of the recipe ingredients linked to it.
 *  - "atHome" ("Pewnie masz w domu"): recipe ingredients linked to a
 *    household product that is in stock — not bought unless "Kupię jednak".
 *
 * Amounts are summed per unit after normalising kg→g and l→ml; if one
 * ingredient ends up in several units and the ingredient has unit weights,
 * everything convertible is summed in grams. Whatever can't be converted is
 * listed next to it ("200 ml + 1 can").
 */
class ShoppingListBuilder
{
    public const BOUGHT_VISIBLE_DAYS = 7;

    /**
     * @param  Collection<int, ShoppingListItem>  $items  with ingredient + recipe loaded
     * @param  Collection<int, PantryItem>|null  $pantry  household products that are low / out or linked to an ingredient
     */
    public function build(Collection $items, CarbonInterface $today, ?Collection $pantry = null): array
    {
        $today = Carbon::parse($today)->startOfDay();
        $tomorrow = $today->copy()->addDay();

        // Rows generated before an ingredient was marked "skip on the shopping
        // list" disappear right away; manually added ones stay (added on purpose).
        $items = $items->reject(fn (ShoppingListItem $item) => $item->meal_id && $item->ingredient?->skip_shopping);

        $unchecked = $items->where(ShoppingListItem::IS_CHECKED_COLUMN, false);

        // Recipe ingredients that are the same product as a household one.
        $pantry ??= collect();
        $linked = $pantry->whereNotNull('ingredient_id')->keyBy('ingredient_id');
        $isLinked = fn (ShoppingListItem $item) => $item->ingredient_id && $linked->has($item->ingredient_id) && ! $item->buy_anyway;
        $linkedItems = $unchecked->filter($isLinked);
        $unchecked = $unchecked->reject($isLinked);

        $atHome = $this->sortGroups($this->mergeAll(
            $linkedItems->filter(fn (ShoppingListItem $item) => ! $linked[$item->ingredient_id]->needsBuying())
        )->map(fn (array $group) => [...$group, 'pantryItemId' => $linked[$group['items'][0]->ingredient_id]->id]));

        $household = $pantry->filter(fn (PantryItem $product) => $product->needsBuying())
            ->sortBy(fn (PantryItem $product) => mb_strtolower($product->name))
            ->map(fn (PantryItem $product) => $this->householdRow($product, $linkedItems->where('ingredient_id', $product->ingredient_id)->values()))
            ->values();

        $isUpcoming = fn (ShoppingListItem $item) => $this->isWeekly($item) || $item->needed_on->lte($tomorrow);

        $upcomingGroups = $this->sortGroups(
            $this->mergeAll($unchecked->filter($isUpcoming))
                ->map(fn (array $group) => [...$group, 'when' => $this->whenLabel($group, $today, $tomorrow)])
        );

        $days = $unchecked->reject($isUpcoming)
            ->groupBy(fn (ShoppingListItem $item) => $item->needed_on->toDateString())
            ->sortKeys()
            ->map(function (Collection $dayItems, string $date) {
                $groups = $this->sortGroups($this->mergeAll($dayItems));

                return [
                    'date' => Carbon::parse($date),
                    'count' => $groups->count(),
                    'categories' => $this->byCategory($groups),
                    'recipes' => $dayItems->pluck('recipe')->filter()->unique('id')->values(),
                ];
            })
            ->values();

        $boughtItems = $items
            ->where(ShoppingListItem::IS_CHECKED_COLUMN, true)
            ->filter(fn (ShoppingListItem $item) => ($item->checked_at ?? $item->updated_at)?->gte($today->copy()->subDays(self::BOUGHT_VISIBLE_DAYS)));

        $boughtGroups = $this->mergeAll($boughtItems)
            ->sortByDesc(fn (array $group) => $group['checkedAt'])
            ->values();

        $activeCount = $upcomingGroups->count() + $days->sum('count') + $household->count();

        return [
            'upcoming' => [
                'count' => $upcomingGroups->count(),
                'categories' => $this->byCategory($upcomingGroups),
            ],
            'days' => $days,
            'bought' => [
                'count' => $boughtGroups->count(),
                'groups' => $boughtGroups,
            ],
            'household' => $household,
            'atHome' => $atHome,
            'progress' => [
                'bought' => $boughtGroups->count(),
                'total' => $activeCount + $boughtGroups->count(),
            ],
            'range' => $this->range($unchecked->concat($boughtItems)),
        ];
    }

    /** One "Zapasy domowe" row: the product, plus what the recipes need of it. */
    private function householdRow(PantryItem $product, Collection $items): array
    {
        $recipes = $items->pluck('recipe')->filter()->unique('id')->pluck('name');
        $status = $product->status === PantryItem::STATUS_OUT ? __('none at home') : __('running low');

        return [
            'key' => "p{$product->id}",
            'pantryItemId' => $product->id,
            'name' => $product->name,
            'ids' => $items->pluck('id')->all(),
            'quantity' => $items->isNotEmpty() ? $this->quantityLabel($items, $items->first()->ingredient) : null,
            'subtitle' => $recipes->isNotEmpty() ? $status.' · '.$recipes->implode(', ') : $status,
        ];
    }

    /** "today" / "tomorrow" for rows of the upcoming section that are needed on a given day. */
    private function whenLabel(array $group, Carbon $today, Carbon $tomorrow): ?string
    {
        $freshDates = collect($group['items'])
            ->reject(fn (ShoppingListItem $item) => $this->isWeekly($item))
            ->pluck('needed_on');

        if ($freshDates->isEmpty()) {
            return null;
        }

        return $freshDates->min()->lte($today) ? 'today' : 'tomorrow';
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
            // Optional only when every merged row is optional (if one recipe needs it, buy it).
            'optional' => $items->every(fn (ShoppingListItem $item) => $item->is_optional),
            'substitute' => $items->pluck('substitute')->filter()->unique()->implode(', ') ?: null,
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

        return collect(array_values(array_diff(Ingredient::sectionOrder(), [Ingredient::SECTION_HOUSEHOLD])))
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
