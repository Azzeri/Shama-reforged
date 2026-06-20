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
            'ingredientOptions' => Ingredient::query()->get(['id', Ingredient::NAME_COLUMN])->sortBy(Ingredient::NAME_COLUMN)->values(),
            'ingredientRows' => old('ingredients', [['ingredient_id' => '', 'ingredient_name' => '', 'custom_name' => '', 'quantity' => '']]),
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
                    'ingredient_id' => (string) $ingredient->id,
                    'ingredient_name' => (string) $ingredient->name,
                    'custom_name' => '',
                    'quantity' => (string) $ingredient->pivot?->quantity,
                ])
                ->values()
                ->all();
        }

        if ($ingredientRows === []) {
            $ingredientRows = [['ingredient_id' => '', 'ingredient_name' => '', 'custom_name' => '', 'quantity' => '']];
        }

        return view('recipes.edit', [
            'recipe' => $recipe,
            'ingredientOptions' => Ingredient::query()->get(['id', Ingredient::NAME_COLUMN])->sortBy(Ingredient::NAME_COLUMN)->values(),
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
     * @return array{name: string, content: string, ingredients?: array<int, array{ingredient_id?: string, ingredient_name?: string, custom_name?: string, quantity?: string}>}
     */
    private function validateRecipe(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'ingredients' => ['nullable', 'array'],
            'ingredients.*.ingredient_id' => ['nullable', 'string', 'max:255'],
            'ingredients.*.ingredient_name' => ['nullable', 'string', 'max:255'],
            'ingredients.*.custom_name' => ['nullable', 'string', 'max:255'],
            'ingredients.*.quantity' => ['nullable', 'string', 'max:255'],
        ]);
    }

    /**
     * @param  array<int, array{ingredient_id?: string, ingredient_name?: string, custom_name?: string, quantity?: string}>  $ingredients
     */
    private function syncIngredients(Recipe $recipe, array $ingredients): void
    {
        $syncPayload = [];

        foreach ($ingredients as $ingredientRow) {
            $ingredientId = trim((string) ($ingredientRow['ingredient_id'] ?? ''));
            $ingredientName = trim((string) ($ingredientRow['ingredient_name'] ?? ''));
            $customName = trim((string) ($ingredientRow['custom_name'] ?? ''));
            $quantity = trim((string) ($ingredientRow['quantity'] ?? ''));

            if ($quantity === '') {
                continue;
            }

            $ingredient = null;

            if ($ingredientId !== '' && $ingredientId !== '__new__') {
                $ingredient = Ingredient::query()->find($ingredientId);
            }

            if (! $ingredient && $ingredientName !== '') {
                $ingredient = Ingredient::query()->firstOrCreate([
                    Ingredient::NAME_COLUMN => $ingredientName,
                ]);
            }

            if (! $ingredient && $customName !== '') {
                $ingredient = Ingredient::query()->firstOrCreate([
                    Ingredient::NAME_COLUMN => $customName,
                ]);
            }

            if (! $ingredient) {
                continue;
            }

            $syncPayload[$ingredient->id] = [
                'quantity' => $quantity,
            ];
        }

        $recipe->ingredients()->sync($syncPayload);
    }
}