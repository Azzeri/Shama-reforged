<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class RecipeController extends Controller
{
    public function index(Request $request): View
    {
        $this->ensureDefaultTags();
        $search = trim((string) $request->query('q', ''));
        $selectedTagIds = collect($request->input('tags', []))
            ->filter(fn ($value) => is_scalar($value) && (string) $value !== '')
            ->map(fn ($value) => (int) $value)
            ->filter(fn (int $value) => $value > 0)
            ->values();

        $recipesQuery = Recipe::query()
            ->with(['ingredients:id,name', 'tags:id,name'])
            ->latest()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(Recipe::NAME_COLUMN, 'like', '%'.$search.'%');
            })
            ->when($selectedTagIds->isNotEmpty(), function ($query) use ($selectedTagIds) {
                $query->whereHas('tags', function ($tagQuery) use ($selectedTagIds) {
                    $tagQuery->whereIn('tags.id', $selectedTagIds->all());
                });
            });

        $recipes = $recipesQuery->paginate(12)->withQueryString();

        return view('recipes.index', [
            'recipes' => $recipes,
            'mealTypeTags' => $this->getMealTypeTags(),
            'dietTypeTags' => $this->getDietTypeTags(),
            'search' => $search,
            'selectedTagIds' => $selectedTagIds,
        ]);
    }

    public function create(): View
    {
        $this->ensureDefaultTags();

        return view('recipes.create', [
            'ingredientOptions' => Ingredient::query()->get(['id', Ingredient::NAME_COLUMN])->sortBy(Ingredient::NAME_COLUMN)->values(),
            'ingredientRows' => old('ingredients', [['ingredient_id' => '', 'ingredient_name' => '', 'custom_name' => '', 'quantity' => '']]),
            'mealTypeTags' => $this->getMealTypeTags(),
            'dietTypeTags' => $this->getDietTypeTags(),
            'selectedTagIds' => old('tags', []),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureDefaultTags();
        $validated = $this->validateRecipe($request);

        $recipe = Recipe::query()->create([
            'name' => $validated['name'],
            'content' => $validated['content'] ?? null,
            'link' => $validated['link'] ?? null,
        ]);

        $this->syncIngredients($recipe, $validated['ingredients'] ?? []);
        $this->syncTags($recipe, $validated['tags'] ?? []);

        return redirect()
            ->route('recipes.index')
            ->with('status', 'Przepis został utworzony.');
    }

    public function show(Recipe $recipe): View
    {
        $recipe->load(['ingredients:id,name', 'tags:id,name']);

        return view('recipes.show', [
            'recipe' => $recipe,
        ]);
    }

    public function edit(Recipe $recipe): View
    {
        $this->ensureDefaultTags();
        $recipe->load(['ingredients:id,name', 'tags:id,name']);

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
            'mealTypeTags' => $this->getMealTypeTags(),
            'dietTypeTags' => $this->getDietTypeTags(),
            'selectedTagIds' => old('tags', $recipe->tags->pluck('id')->all()),
        ]);
    }

    public function update(Request $request, Recipe $recipe): RedirectResponse
    {
        $this->ensureDefaultTags();
        $validated = $this->validateRecipe($request);

        $recipe->update([
            'name' => $validated['name'],
            'content' => $validated['content'] ?? null,
            'link' => $validated['link'] ?? null,
        ]);

        $this->syncIngredients($recipe, $validated['ingredients'] ?? []);
        $this->syncTags($recipe, $validated['tags'] ?? []);

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
     * @return array{name: string, content?: string|null, link?: string|null, ingredients?: array<int, array{ingredient_id?: string, ingredient_name?: string, custom_name?: string, quantity?: string}>, tags?: array<int, int|string>}
     */
    private function validateRecipe(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'link' => ['nullable', 'url:http,https', 'max:2048'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer', 'exists:tags,id'],
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

    /**
     * @param  array<int, int|string>  $tags
     */
    private function syncTags(Recipe $recipe, array $tags): void
    {
        $tagIds = collect($tags)
            ->map(fn ($tagId) => (int) $tagId)
            ->filter(fn (int $tagId) => $tagId > 0)
            ->values();

        $recipe->tags()->sync($tagIds->all());
    }

    private function ensureDefaultTags(): void
    {
        // Create meal type tags
        foreach (Tag::MEAL_TYPE_NAMES as $tagName) {
            Tag::query()->firstOrCreate(
                [Tag::NAME_COLUMN => $tagName],
                [Tag::CATEGORY_COLUMN => Tag::MEAL_TYPE]
            );
        }

        // Create diet type tags
        foreach (Tag::DIET_TYPE_NAMES as $tagName) {
            Tag::query()->firstOrCreate(
                [Tag::NAME_COLUMN => $tagName],
                [Tag::CATEGORY_COLUMN => Tag::DIET_TYPE]
            );
        }
    }

    /**
     * @return Collection<int, Tag>
     */
    private function getMealTypeTags(): Collection
    {
        return Tag::query()
            ->where(Tag::CATEGORY_COLUMN, Tag::MEAL_TYPE)
            ->orderBy(Tag::NAME_COLUMN)
            ->get(['id', Tag::NAME_COLUMN, Tag::CATEGORY_COLUMN]);
    }

    /**
     * @return Collection<int, Tag>
     */
    private function getDietTypeTags(): Collection
    {
        return Tag::query()
            ->where(Tag::CATEGORY_COLUMN, Tag::DIET_TYPE)
            ->orderBy(Tag::NAME_COLUMN)
            ->get(['id', Tag::NAME_COLUMN, Tag::CATEGORY_COLUMN]);
    }

    /**
     * @return Collection<int, Tag>
     */
    private function getAllTags(): Collection
    {
        return Tag::query()
            ->orderBy(Tag::CATEGORY_COLUMN)
            ->orderBy(Tag::NAME_COLUMN)
            ->get(['id', Tag::NAME_COLUMN, Tag::CATEGORY_COLUMN]);
    }
}