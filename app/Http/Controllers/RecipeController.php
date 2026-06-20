<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\Recipe;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecipeController extends Controller
{
    public function index(): View
    {
        $recipes = Recipe::query()
            ->with('ingredients:id,name')
            ->latest()
            ->paginate(10);

        return view('recipes.index', [
            'recipes' => $recipes,
        ]);
    }

    public function create(): View
    {
        return view('recipes.create', [
            'allIngredientNames' => Ingredient::query()->orderBy('name')->pluck('name')->all(),
            'ingredientRows' => old('ingredients', [['name' => '', 'quantity' => '']]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateRecipe($request);

        $recipe = Recipe::query()->create([
            'name' => $validated['name'],
            'content' => $validated['content'],
        ]);

        $this->syncIngredients($recipe, $validated['ingredients'] ?? []);

        return redirect()
            ->route('recipes.index')
            ->with('status', 'Przepis został utworzony.');
    }

    public function show(Recipe $recipe): View
    {
        $recipe->load('ingredients:id,name');

        return view('recipes.show', [
            'recipe' => $recipe,
        ]);
    }

    public function edit(Recipe $recipe): View
    {
        $recipe->load('ingredients:id,name');

        $ingredientRows = old('ingredients');
        if (! is_array($ingredientRows)) {
            $ingredientRows = $recipe->ingredients
                ->map(fn (Ingredient $ingredient) => [
                    'name' => $ingredient->name,
                    'quantity' => (string) $ingredient->pivot?->quantity,
                ])
                ->values()
                ->all();
        }

        if ($ingredientRows === []) {
            $ingredientRows = [['name' => '', 'quantity' => '']];
        }

        return view('recipes.edit', [
            'recipe' => $recipe,
            'allIngredientNames' => Ingredient::query()->orderBy('name')->pluck('name')->all(),
            'ingredientRows' => $ingredientRows,
        ]);
    }

    public function update(Request $request, Recipe $recipe): RedirectResponse
    {
        $validated = $this->validateRecipe($request);

        $recipe->update([
            'name' => $validated['name'],
            'content' => $validated['content'],
        ]);

        $this->syncIngredients($recipe, $validated['ingredients'] ?? []);

        return redirect()
            ->route('recipes.index')
            ->with('status', 'Przepis został zaktualizowany.');
    }

    public function destroy(Recipe $recipe): RedirectResponse
    {
        $recipe->delete();

        return redirect()
            ->route('recipes.index')
            ->with('status', 'Przepis został usunięty.');
    }

    /**
     * @return array{name: string, content: string, ingredients?: array<int, array{name?: string, quantity?: string}>}
     */
    private function validateRecipe(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'ingredients' => ['nullable', 'array'],
            'ingredients.*.name' => ['nullable', 'string', 'max:255'],
            'ingredients.*.quantity' => ['nullable', 'string', 'max:255'],
        ]);
    }

    /**
     * @param  array<int, array{name?: string, quantity?: string}>  $ingredients
     */
    private function syncIngredients(Recipe $recipe, array $ingredients): void
    {
        $syncPayload = [];

        foreach ($ingredients as $ingredientRow) {
            $name = trim((string) ($ingredientRow['name'] ?? ''));
            $quantity = trim((string) ($ingredientRow['quantity'] ?? ''));

            if ($name === '' || $quantity === '') {
                continue;
            }

            $ingredient = Ingredient::query()->firstOrCreate([
                'name' => $name,
            ]);

            $syncPayload[$ingredient->id] = [
                'quantity' => $quantity,
            ];
        }

        $recipe->ingredients()->sync($syncPayload);
    }
}