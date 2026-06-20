<?php

namespace App\Http\Controllers;

use App\Models\Meal;
use App\Models\Recipe;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MealController extends Controller
{
    public function index(Request $request): View
    {
        $anchorDate = $request->filled('week')
            ? Carbon::parse($request->string('week')->toString())
            : now();

        $weekStart = $anchorDate->copy()->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        $meals = Meal::query()
            ->with('recipes:id,name')
            ->whereBetween('date', [$weekStart->copy()->startOfDay(), $weekEnd->copy()->endOfDay()])
            ->orderBy('date')
            ->get();

        $days = collect(range(0, 6))->map(function (int $offset) use ($weekStart, $meals) {
            $day = $weekStart->copy()->addDays($offset);

            return [
                'date' => $day,
                'meals' => $meals->filter(fn (Meal $meal) => $meal->date->isSameDay($day))->values(),
            ];
        });

        return view('meals.index', [
            'days' => $days,
            'weekStart' => $weekStart,
            'previousWeek' => $weekStart->copy()->subWeek()->toDateString(),
            'nextWeek' => $weekStart->copy()->addWeek()->toDateString(),
            'isCurrentWeek' => $weekStart->isSameWeek(now()),
        ]);
    }

    public function create(Request $request): View
    {
        return view('meals.create', [
            'recipeOptions' => Recipe::query()->get(['id', Recipe::NAME_COLUMN])->sortBy(Recipe::NAME_COLUMN)->values(),
            'mealRecipeRows' => old('recipes', [['recipe_id' => '']]),
            'typeOptions' => Meal::TYPE_LABELS,
            'prefilledDate' => old('date', $request->query('date', now()->format('Y-m-d\TH:i'))),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateMeal($request);

        $meal = Meal::query()->create([
            'type' => $validated['type'],
            'date' => Carbon::parse($validated['date']),
        ]);

        $this->syncRecipes($meal, $validated['recipes'] ?? []);

        return redirect()
            ->route('meals.index')
            ->with('status', 'Posiłek został utworzony.');
    }

    public function show(Meal $meal): View
    {
        $meal->load('recipes:id,name');

        return view('meals.show', [
            'meal' => $meal,
        ]);
    }

    public function day(string $date): View
    {
        $day = Carbon::parse($date)->startOfDay();

        $meals = Meal::query()
            ->with('recipes:id,name')
            ->whereDate('date', $day->toDateString())
            ->orderBy('date')
            ->get();

        return view('meals.day', [
            'day' => $day,
            'meals' => $meals,
        ]);
    }

    public function edit(Meal $meal): View
    {
        $meal->load('recipes:id,name');

        $mealRecipeRows = old('recipes');
        if (! is_array($mealRecipeRows)) {
            $mealRecipeRows = $meal->recipes
                ->map(fn (Recipe $recipe) => [
                    'recipe_id' => (string) $recipe->id,
                ])
                ->values()
                ->all();
        }

        if ($mealRecipeRows === []) {
            $mealRecipeRows = [['recipe_id' => '']];
        }

        return view('meals.edit', [
            'meal' => $meal,
            'recipeOptions' => Recipe::query()->get(['id', Recipe::NAME_COLUMN])->sortBy(Recipe::NAME_COLUMN)->values(),
            'mealRecipeRows' => $mealRecipeRows,
            'typeOptions' => Meal::TYPE_LABELS,
        ]);
    }

    public function update(Request $request, Meal $meal): RedirectResponse
    {
        $validated = $this->validateMeal($request);

        $meal->update([
            'type' => $validated['type'],
            'date' => Carbon::parse($validated['date']),
        ]);

        $this->syncRecipes($meal, $validated['recipes'] ?? []);

        return redirect()
            ->route('meals.index')
            ->with('status', 'Posiłek został zaktualizowany.');
    }

    public function destroy(Meal $meal): RedirectResponse
    {
        $meal->delete();

        return redirect()
            ->route('meals.index')
            ->with('status', 'Posiłek został usunięty.');
    }

    /**
     * @return array{type: string, date: string, recipes?: array<int, array{recipe_id?: string}>}
     */
    private function validateMeal(Request $request): array
    {
        return $request->validate([
            'type' => ['required', 'string', Rule::in(Meal::TYPES)],
            'date' => ['required', 'date'],
            'recipes' => ['required', 'array', 'min:1'],
            'recipes.*.recipe_id' => ['required', 'integer', 'distinct', 'exists:recipes,id'],
        ]);
    }

    /**
     * @param  array<int, array{recipe_id?: string}>  $recipes
     */
    private function syncRecipes(Meal $meal, array $recipes): void
    {
        $syncPayload = [];

        foreach ($recipes as $recipeRow) {
            $recipeId = (int) ($recipeRow['recipe_id'] ?? 0);

            if ($recipeId <= 0) {
                continue;
            }

            $syncPayload[$recipeId] = [];
        }

        $meal->recipes()->sync($syncPayload);
    }
}