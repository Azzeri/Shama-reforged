<?php

namespace App\Actions\MealPlan;

use App\Models\Meal;
use App\Models\Recipe;
use App\Models\RecipeMealAssignment;
use App\Models\Tag;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Week review of the plan: the 7 days starting at the given date (the same
 * window the calendar shows).
 *
 * One "meal" here is one recipe entered in the plan for a day and a time of
 * day — leftovers included, since the review is about what gets eaten, not
 * bought. Notes are skipped. Each part skips only what it cannot use: health
 * leaves out recipes without a rating, variety those without a main
 * ingredient.
 *
 * Reads only; inserting a suggestion is InsertRecipeIntoPlan.
 */
class BuildWeekReview
{
    public const DAYS = 7;

    public const CARD_HEALTH = 'health';

    public const CARD_GROUPS = 'groups';

    public const CARD_LONG_AGO = 'long-ago';

    /**
     * @return array{start: CarbonInterface, end: CarbonInterface, meals: int, days: int, health: array, variety: array, cards: list<array>}
     */
    public function __invoke(CarbonInterface $start): array
    {
        $start = $start->copy()->startOfDay();
        $end = $start->copy()->addDays(self::DAYS - 1)->endOfDay();

        $meals = $this->mealsBetween($start, $end);
        $entries = $this->entries($meals);
        $health = $this->healthOf($entries);
        $variety = $this->varietyOf($entries);

        return [
            'start' => $start,
            'end' => $end,
            'meals' => $entries->count(),
            'days' => $entries->pluck('date')->unique()->count(),
            'health' => $health,
            'variety' => $variety,
            'cards' => $this->cards($entries, $meals, $health, $variety, $start),
        ];
    }

    /** Only the health numbers — for the entry card on the calendar. */
    public function health(CarbonInterface $start): array
    {
        $start = $start->copy()->startOfDay();

        return $this->healthOf($this->entries($this->mealsBetween($start, $start->copy()->addDays(self::DAYS - 1)->endOfDay())));
    }

    private function mealsBetween(CarbonInterface $start, CarbonInterface $end): Collection
    {
        return Meal::query()
            ->with('recipes.tags')
            ->whereBetween(Meal::DATE_COLUMN, [$start, $end])
            ->orderBy(Meal::DATE_COLUMN)
            ->get();
    }

    /**
     * One row per recipe in the plan.
     *
     * @return Collection<int, array{date: string, type: string, recipe: Recipe}>
     */
    private function entries(Collection $meals): Collection
    {
        return $meals->flatMap(fn (Meal $meal) => $meal->recipes->map(fn (Recipe $recipe) => [
            'date' => $meal->date->toDateString(),
            'type' => $meal->type,
            'recipe' => $recipe,
        ]))->values();
    }

    /**
     * H / M / U counts over the rated meals (N) and the 80/20 verdict.
     * The verdict is decided on integers; percentages are only for display.
     *   missing healthy   = ceil(min% · N) − H   (swapping, N unchanged)
     *   excess unhealthy  = U − floor(max% · N)
     */
    private function healthOf(Collection $entries): array
    {
        $min = (int) config('meals.review.healthy_min_percent', 80);
        $max = (int) config('meals.review.unhealthy_max_percent', 20);

        $counts = $entries->countBy(fn (array $entry) => $entry['recipe']->rating ?? '');
        $healthy = (int) $counts->get(Recipe::RATING_HEALTHY, 0);
        $medium = (int) $counts->get(Recipe::RATING_MEDIUM, 0);
        $unhealthy = (int) $counts->get(Recipe::RATING_UNHEALTHY, 0);
        $rated = $healthy + $medium + $unhealthy;

        return [
            'healthy' => $healthy,
            'medium' => $medium,
            'unhealthy' => $unhealthy,
            'rated' => $rated,
            'unrated' => $entries->count() - $rated,
            'healthyPercent' => $rated ? (int) round($healthy * 100 / $rated) : 0,
            'unhealthyPercent' => $rated ? (int) round($unhealthy * 100 / $rated) : 0,
            'minPercent' => $min,
            'maxPercent' => $max,
            'healthyOk' => $healthy * 100 >= $min * $rated,
            'unhealthyOk' => $unhealthy * 100 <= $max * $rated,
            'missingHealthy' => max(0, intdiv($min * $rated + 99, 100) - $healthy),
            'excessUnhealthy' => max(0, $unhealthy - intdiv($max * $rated, 100)),
            // Too few rated meals: one unhealthy out of four is already 25%.
            'enough' => $rated >= (int) config('meals.review.min_meals', 5),
        ];
    }

    /** Meals per main ingredient (every group, zeros too) and repeats. */
    private function varietyOf(Collection $entries): array
    {
        $tagged = $entries->filter(fn (array $entry) => $entry['recipe']->tags->contains(fn (Tag $tag) => $tag->isDietType()));
        $perTag = $tagged->flatMap(fn (array $entry) => $entry['recipe']->tags->filter->isDietType()->pluck('id'))->countBy();

        $groups = Tag::query()->where(Tag::CATEGORY_COLUMN, Tag::DIET_TYPE)->get()
            ->map(fn (Tag $tag) => [
                'id' => $tag->id,
                'label' => $tag->label(),
                'count' => (int) $perTag->get($tag->id, 0),
                'percent' => $tagged->count() ? (int) round($perTag->get($tag->id, 0) * 100 / $tagged->count()) : 0,
            ])
            ->sortBy(fn (array $group) => [-$group['count'], mb_strtolower($group['label'])])
            ->values();

        $perRecipe = $entries->countBy(fn (array $entry) => $entry['recipe']->id)->sortDesc();
        $topId = $perRecipe->keys()->first();

        return [
            'groups' => $groups->all(),
            'tagged' => $tagged->count(),
            'untagged' => $entries->count() - $tagged->count(),
            'distinct' => $perRecipe->count(),
            'mostRepeated' => $topId !== null && $perRecipe[$topId] >= 3
                ? ['name' => $entries->firstWhere('recipe.id', $topId)['recipe']->name, 'count' => $perRecipe[$topId]]
                : null,
        ];
    }

    /**
     * "Worth improving" cards, in display order. A suggested recipe appears
     * in one card only (the first that wants it).
     *
     * @return list<array{key: string, title: string, body: string, suggestions: list<array>, limit: int, day: ?string}>
     */
    private function cards(Collection $entries, Collection $meals, array $health, array $variety, CarbonInterface $start): array
    {
        $lastPlanned = $this->lastPlannedBefore($start);

        // Active recipes not in this week: longest not eaten first, never planned last.
        $candidates = Recipe::query()->active()
            ->whereNotIn('id', $entries->pluck('recipe.id')->unique())
            ->with('tags')
            ->get()
            ->sortBy(fn (Recipe $recipe) => [
                isset($lastPlanned[$recipe->id]) ? 0 : 1,
                $lastPlanned[$recipe->id] ?? '',
                mb_strtolower($recipe->name),
            ])
            ->values();

        $used = [];
        $take = function (Collection $pool, int $limit) use (&$used, $lastPlanned, $start): array {
            $picked = $pool->reject(fn (Recipe $recipe) => isset($used[$recipe->id]))->take($limit);
            $picked->each(function (Recipe $recipe) use (&$used) {
                $used[$recipe->id] = true;
            });

            return $picked->map(fn (Recipe $recipe) => [
                'id' => $recipe->id,
                'name' => $recipe->name,
                'last' => $this->lastLabel($lastPlanned[$recipe->id] ?? null, $start),
            ])->values()->all();
        };

        $healthyFirst = fn (Collection $pool) => $pool->sortBy(fn (Recipe $recipe) => $recipe->rating === Recipe::RATING_HEALTHY ? 0 : 1)->values();
        $inGroup = fn (Recipe $recipe, array $tagIds) => $recipe->tags->contains(fn (Tag $tag) => in_array($tag->id, $tagIds, true));

        // Groups with no meal this week — only those that have a recipe to offer.
        $missingGroups = $variety['tagged'] >= (int) config('meals.review.min_meals', 5)
            ? collect($variety['groups'])
                ->where('count', 0)
                ->filter(fn (array $group) => $candidates->contains(fn (Recipe $recipe) => $inGroup($recipe, [$group['id']])))
                ->values()
            : collect();
        $missingIds = $missingGroups->pluck('id')->all();

        $cards = [];

        if ($health['enough'] && ($health['missingHealthy'] > 0 || $health['excessUnhealthy'] > 0)) {
            $pool = $candidates->where('rating', Recipe::RATING_HEALTHY)
                ->sortBy(fn (Recipe $recipe) => $inGroup($recipe, $missingIds) ? 0 : 1)
                ->values();
            $suggestions = $take($pool, 3);
            $emptyDays = $this->emptyDays($meals, $start);

            $body = [];
            if ($emptyDays !== []) {
                $body[] = __('Still empty: :days.', ['days' => $this->dayList($emptyDays)]);
            }
            if ($suggestions !== []) {
                $body[] = $emptyDays !== []
                    ? __('Healthy recipes you can put there or swap in for the medium ones:')
                    : __('Healthy recipes you can swap in for the medium or unhealthy ones:');
            }

            $cards[] = [
                'key' => self::CARD_HEALTH,
                'title' => $health['missingHealthy'] > 0
                    ? trans_choice('The :goal goal is :count healthy meal short|The :goal goal is :count healthy meals short', $health['missingHealthy'], ['goal' => $health['minPercent'].'/'.$health['maxPercent']])
                    : trans_choice(':count unhealthy meal over the goal|:count unhealthy meals over the goal', $health['excessUnhealthy']),
                'body' => implode(' ', $body),
                'suggestions' => $suggestions,
                'limit' => 3,
                'day' => null,
            ];
        }

        foreach ($this->streaks($entries) as $streak) {
            $cards[] = [
                'key' => 'streak-'.$streak['recipe']->id,
                'title' => __(':name :count days in a row', ['name' => $streak['recipe']->name, 'count' => count($streak['dates'])]),
                'body' => Str::ucfirst($this->dayList($streak['dates'])).'. '.($streak['oneCooking']
                    ? __('These are portions from one cooking, so no extra shopping, but no variety either.')
                    : __('Consider swapping one of them for something else.')),
                'suggestions' => [],
                'limit' => 0,
                'day' => $streak['dates'][intdiv(count($streak['dates']), 2)],
            ];
        }

        if ($missingGroups->isNotEmpty()) {
            // One recipe per missing group, healthy ones first.
            $suggestions = [];
            foreach ($missingGroups as $group) {
                if (count($suggestions) >= 2) {
                    break;
                }
                $suggestions = [...$suggestions, ...$take($healthyFirst($candidates->filter(fn (Recipe $recipe) => $inGroup($recipe, [$group['id']]))), 1)];
            }

            $top = $variety['groups'][0];

            $cards[] = [
                'key' => self::CARD_GROUPS,
                'title' => __('Not in the plan: :groups', ['groups' => $missingGroups->pluck('label')->map(fn (string $label) => mb_strtolower($label))->implode(', ')]),
                'body' => 60 * $variety['tagged'] <= $top['count'] * 100
                    ? __(':count of :total meals: :group.', ['count' => $top['count'], 'total' => $variety['tagged'], 'group' => mb_strtolower($top['label'])])
                    : '',
                'suggestions' => $suggestions,
                'limit' => 2,
                'day' => null,
            ];
        }

        $threshold = $start->copy()->subDays((int) config('meals.review.long_ago_days', 30))->toDateString();
        $longAgo = $take($candidates->filter(fn (Recipe $recipe) => ! isset($lastPlanned[$recipe->id]) || $lastPlanned[$recipe->id] < $threshold), 3);

        if ($longAgo !== []) {
            $cards[] = [
                'key' => self::CARD_LONG_AGO,
                'title' => __('Long time no see'),
                'body' => __('Recipes that have not been in the plan for over a month.'),
                'suggestions' => $longAgo,
                'limit' => 3,
                'day' => null,
            ];
        }

        return $cards;
    }

    /** recipe id => 'Y-m-d' of the last day it was in the plan before the reviewed week. */
    private function lastPlannedBefore(CarbonInterface $start): array
    {
        $assignments = (new RecipeMealAssignment)->getTable();

        return DB::table($assignments)
            ->join('meals', 'meals.id', '=', $assignments.'.meal_id')
            ->where('meals.'.Meal::DATE_COLUMN, '<', $start)
            ->groupBy($assignments.'.recipe_id')
            ->selectRaw($assignments.'.recipe_id as recipe_id, MAX(meals.'.Meal::DATE_COLUMN.') as last_date')
            ->pluck('last_date', 'recipe_id')
            ->map(fn (string $date) => Carbon::parse($date)->toDateString())
            ->all();
    }

    /** "last: 3 wk ago" / "not planned yet" */
    private function lastLabel(?string $date, CarbonInterface $start): string
    {
        if ($date === null) {
            return __('not planned yet');
        }

        $days = (int) Carbon::parse($date)->startOfDay()->diffInDays($start);

        $when = match (true) {
            $days < 14 => trans_choice(':count day ago|:count days ago', $days),
            $days < 56 => __(':count wk ago', ['count' => intdiv($days, 7)]),
            default => __(':count mo ago', ['count' => intdiv($days, 30)]),
        };

        return __('last: :when', ['when' => $when]);
    }

    /**
     * The same recipe on N or more days in a row.
     *
     * @return list<array{recipe: Recipe, dates: list<string>, oneCooking: bool}>
     */
    private function streaks(Collection $entries): array
    {
        $minDays = max(2, (int) config('meals.review.streak_days', 4));
        $streaks = [];

        foreach ($entries->groupBy(fn (array $entry) => $entry['recipe']->id) as $recipeEntries) {
            $dates = $recipeEntries->pluck('date')->unique()->sort()->values();
            $run = [];

            foreach ($dates as $index => $date) {
                $follows = $run !== [] && Carbon::parse(end($run))->addDay()->toDateString() === $date;
                $run = $follows ? [...$run, $date] : [$date];
                $runEnds = ! isset($dates[$index + 1]) || Carbon::parse($date)->addDay()->toDateString() !== $dates[$index + 1];

                if ($runEnds && count($run) >= $minDays) {
                    $streaks[] = [
                        'recipe' => $recipeEntries->first()['recipe'],
                        'dates' => $run,
                        'oneCooking' => $recipeEntries
                            ->filter(fn (array $entry) => in_array($entry['date'], $run, true))
                            ->every(fn (array $entry) => $entry['recipe']->pivot->mode !== RecipeMealAssignment::MODE_SINGLE),
                    ];
                }
            }
        }

        return $streaks;
    }

    /** Days of the week, today onwards, with neither a recipe nor a note. @return list<string> */
    private function emptyDays(Collection $meals, CarbonInterface $start): array
    {
        $busy = $meals
            ->filter(fn (Meal $meal) => $meal->recipes->isNotEmpty() || filled($meal->note))
            ->map(fn (Meal $meal) => $meal->date->toDateString())
            ->unique()
            ->all();

        return collect(range(0, self::DAYS - 1))
            ->map(fn (int $offset) => $start->copy()->addDays($offset))
            ->filter(fn (CarbonInterface $day) => $day->gte(now()->startOfDay()) && ! in_array($day->toDateString(), $busy, true))
            ->map(fn (CarbonInterface $day) => $day->toDateString())
            ->values()
            ->all();
    }

    /** "sobota, niedziela, poniedziałek" */
    private function dayList(array $dates): string
    {
        return collect($dates)
            ->map(fn (string $date) => Carbon::parse($date)->locale(app()->getLocale())->isoFormat('dddd'))
            ->implode(', ');
    }
}
