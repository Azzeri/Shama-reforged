<?php

namespace App\Actions\MealPlan;

use App\Models\Meal;
use App\Models\Recipe;
use App\Models\Tag;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Builds a Markdown brief to paste into an AI assistant so it can propose a
 * meal plan: instructions + expected JSON output, recent eating history,
 * the slots already taken in the target period, and the full recipe catalog
 * (IDs, meal-type tags, per-person nutrition, ingredients). The AI may also
 * propose dishes outside the catalog — those come back as note-only meals.
 */
class BuildMealPlanReport
{
    public function __invoke(
        CarbonInterface $historyFrom,
        CarbonInterface $historyTo,
        CarbonInterface $planFrom,
        int $planDays,
        string $ingredientsToUse = '',
        string $extraWishes = '',
    ): string {
        $planTo = $planFrom->copy()->addDays($planDays - 1);
        $profiles = collect(Recipe::NUTRITION_PROFILES)
            ->map(fn (string $profile) => Recipe::nutritionProfileLabel($profile));

        $history = $this->mealsBetween($historyFrom, $historyTo);
        $planned = $this->mealsBetween($planFrom, $planTo);
        $recipes = Recipe::query()->with(['tags', 'ingredients'])->orderBy('name')->get();

        $lines = [];
        $lines[] = '# Meal plan request';
        $lines[] = '';
        $lines[] = 'You are helping a two-person household (' . $profiles->implode(' and ') . ') plan their meals.';
        $lines[] = "Plan every meal from {$planFrom->toDateString()} to {$planTo->toDateString()} ({$planDays} days).";
        $lines[] = '';
        $lines[] = '## Rules';
        $lines[] = '- Meal types: breakfast, lunch, dinner (main warm meal), supper (evening meal) — every day. `dessert` only occasionally.';
        $lines[] = '- We cook in batches, so plan it that way:';
        $lines[] = '  - one lunch recipe covers 2 days in a row (same lunch on day N and N+1),';
        $lines[] = '  - one dinner recipe covers 2 days in a row (same dinner on day N and N+1),';
        $lines[] = '  - or the previous day\'s supper is eaten again as the next day\'s lunch.';
        $lines[] = '- Prefer recipes from the "Recipe catalog" below (referenced by numeric `id`) — they have ingredients and nutrition and go straight to the shopping list. Prefer ones tagged with the matching meal type.';
        $lines[] = '- You may also propose your own dishes that are not in the catalog when it makes the plan better (variety, using up ingredients). For those, list the ingredients in the overview so we can shop for them.';
        $lines[] = '- A meal may have more than one recipe (e.g. main + side) when one alone is too small.';
        $lines[] = '- Both people eat the same dishes in different portions; catalog calories are per person and reflect their usual portion sizes. Do NOT derive daily calorie needs from the history — it is incomplete (many meals are recorded only as notes).';
        $lines[] = '- History notes (e.g. "eating out", a dish name) are real meals without a recipe in the app — take them into account for preferences and variety.';
        $lines[] = '- Keep it varied across the week and versus the history, apart from the batch-cooking repeats above and clear favourites.';
        $lines[] = '- Do NOT plan slots listed under "Already planned" — those are taken.';

        if (trim($ingredientsToUse) !== '') {
            $lines[] = '- Prioritise recipes that use these ingredients we already have: ' . $this->oneLine($ingredientsToUse) . '.';
        }

        if (trim($extraWishes) !== '') {
            $lines[] = '- Additional wishes: ' . $this->oneLine($extraWishes);
        }

        $lines[] = '';
        $lines[] = '## Output format';
        $lines[] = 'First a short human-readable overview (one line per day; for your own dishes add their ingredients). Then exactly one JSON code block in this format.';
        $lines[] = 'Catalog recipes go in `recipe_ids`. Your own dishes go in `note` (dish name, max 255 characters) with `recipe_ids: []`.';
        $lines[] = '';
        $lines[] = '```json';
        $lines[] = json_encode(['meals' => [
            ['date' => $planFrom->toDateString(), 'type' => 'breakfast', 'recipe_ids' => [12]],
            ['date' => $planFrom->toDateString(), 'type' => 'dinner', 'recipe_ids' => [34, 56], 'note' => null],
            ['date' => $planFrom->toDateString(), 'type' => 'supper', 'recipe_ids' => [], 'note' => 'Shakshuka with feta and bread'],
        ]], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $lines[] = '```';
        $lines[] = '';
        $lines[] = 'The JSON can be sent to the app: `POST ' . url('/api/meal-plan') . '` with header `Authorization: Bearer <API token>` and the JSON as the body, or pasted into the app\'s import field. Existing meals are never overwritten.';
        $lines[] = '';

        $lines[] = "## Eating history ({$historyFrom->toDateString()} – {$historyTo->toDateString()})";
        $lines = [...$lines, ...$this->dayLines($history, $historyFrom, $historyTo, withEmptyDays: false)];

        $topRecipes = $history->flatMap->recipes->countBy('id')->sortDesc()->take(10);
        $topNotes = $history->pluck('note')->filter()->map(fn ($note) => trim($note))->countBy()->sortDesc()->take(10);

        if ($topRecipes->isNotEmpty() || $topNotes->isNotEmpty()) {
            $lines[] = '';
        }

        if ($topRecipes->isNotEmpty()) {
            $byId = $history->flatMap->recipes->keyBy('id');
            $lines[] = 'Most eaten recipes: ' . $topRecipes->map(fn ($count, $id) => "{$byId[$id]->name} (#{$id}) ×{$count}")->implode(', ') . '.';
        }

        if ($topNotes->isNotEmpty()) {
            $lines[] = 'Most common notes: ' . $topNotes->map(fn ($count, $note) => "\"{$note}\" ×{$count}")->implode(', ') . '.';
        }

        $lines[] = '';
        $lines[] = "## Already planned ({$planFrom->toDateString()} – {$planTo->toDateString()})";
        $plannedLines = $this->dayLines($planned, $planFrom, $planTo, withEmptyDays: false);
        $lines = [...$lines, ...($plannedLines ?: ['Nothing yet.'])];

        $lines[] = '';
        $lines[] = '## Recipe catalog';
        $lines[] = 'Format: `id` | name | meal types | diet | per person: kcal / protein / fat / carbs (g) | ingredients';
        $lines[] = '';

        foreach ($recipes as $recipe) {
            $mealTypes = $recipe->tags->where('category', Tag::MEAL_TYPE)->pluck('name')->implode(', ');
            $diet = $recipe->tags->where('category', Tag::DIET_TYPE)->pluck('name')->implode(', ');
            $nutrition = collect(Recipe::NUTRITION_PROFILES)
                ->filter(fn ($profile) => $recipe->hasNutritionFor($profile))
                ->map(function ($profile) use ($recipe) {
                    $n = $recipe->nutritionFor($profile);

                    return mb_substr(Recipe::nutritionProfileLabel($profile), 0, 1) . ': '
                        . collect([$n['calories'], $n['protein'], $n['fat'], $n['carbs']])->map(fn ($v) => $v === null ? '?' : round($v))->implode('/');
                })
                ->implode('; ');

            $lines[] = "- `{$recipe->id}` | {$recipe->name} | " . ($mealTypes ?: '-') . ' | ' . ($diet ?: '-')
                . ' | ' . ($nutrition ?: '-') . ' | ' . ($recipe->ingredients->pluck('name')->implode(', ') ?: '-');
        }

        return implode("\n", $lines) . "\n";
    }

    private function mealsBetween(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return Meal::query()
            ->with('recipes:id,name,calories_me,calories_wife')
            ->whereBetween(Meal::DATE_COLUMN, [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->orderBy(Meal::DATE_COLUMN)
            ->get();
    }

    /** @return list<string> */
    private function dayLines(Collection $meals, CarbonInterface $from, CarbonInterface $to, bool $withEmptyDays): array
    {
        $lines = [];
        $byDay = $meals->groupBy(fn (Meal $meal) => $meal->date->toDateString());

        for ($day = $from->copy()->startOfDay(); $day->lte($to); $day->addDay()) {
            $dayMeals = $byDay->get($day->toDateString(), collect())
                ->sortBy(fn (Meal $meal) => array_search($meal->type, Meal::TYPES, true));

            if ($dayMeals->isEmpty() && ! $withEmptyDays) {
                continue;
            }

            $lines[] = '';
            $lines[] = "### {$day->toDateString()} ({$day->englishDayOfWeek})";

            foreach ($dayMeals as $meal) {
                $parts = $meal->recipes->map(fn (Recipe $r) => "{$r->name} (#{$r->id})")->all();

                if (filled($meal->note)) {
                    $parts[] = "note: {$meal->note}";
                }

                $lines[] = "- {$meal->type}: " . (implode(', ', $parts) ?: '-');
            }
        }

        return $lines;
    }

    private function oneLine(string $text): string
    {
        return trim(preg_replace('/\s+/', ' ', $text));
    }
}
