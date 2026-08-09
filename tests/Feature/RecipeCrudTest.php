<?php

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\Tag;
use App\Models\User;
use Livewire\Livewire;

// ---------------------------------------------------------------------
// Index (pages::recipe.index) — listing, search, tag filters, pagination
// ---------------------------------------------------------------------

test('authenticated user can see the recipe list', function () {
    $user = User::factory()->create();
    Recipe::query()->create(['name' => 'Owsianka']);

    $this->actingAs($user);

    Livewire::test('pages::recipe.index')
        ->assertOk()
        ->assertSee('Owsianka');
});

test('searching by name filters the recipe list', function () {
    $user = User::factory()->create();
    Recipe::query()->create(['name' => 'Owsianka bananowa']);
    Recipe::query()->create(['name' => 'Kurczak curry']);

    $this->actingAs($user);

    Livewire::test('pages::recipe.index')
        ->set('searchName', 'Owsianka')
        ->assertSee('Owsianka bananowa')
        ->assertDontSee('Kurczak curry');
});

test('filtering by tag category shows only recipes with that tag', function () {
    $user = User::factory()->create();
    $breakfastTag = Tag::query()->create(['name' => 'Śniadanie', 'category' => Tag::MEAL_TYPE]);

    $breakfast = Recipe::query()->create(['name' => 'Owsianka']);
    $breakfast->tags()->attach($breakfastTag->id);

    Recipe::query()->create(['name' => 'Zupa pomidorowa']);

    $this->actingAs($user);

    Livewire::test('pages::recipe.index')
        ->set('searchCategories', [$breakfastTag->id])
        ->assertSee('Owsianka')
        ->assertDontSee('Zupa pomidorowa');
});

test('combining a name search with a tag filter narrows results further', function () {
    $user = User::factory()->create();
    $breakfastTag = Tag::query()->create(['name' => 'Śniadanie', 'category' => Tag::MEAL_TYPE]);

    $matching = Recipe::query()->create(['name' => 'Owsianka bananowa']);
    $matching->tags()->attach($breakfastTag->id);

    $wrongTag = Recipe::query()->create(['name' => 'Owsianka jaglana']);

    $this->actingAs($user);

    Livewire::test('pages::recipe.index')
        ->set('searchName', 'Owsianka')
        ->set('searchCategories', [$breakfastTag->id])
        ->assertSee('Owsianka bananowa')
        ->assertDontSee('Owsianka jaglana');
});

test('clearFilters resets both the name search and tag filters', function () {
    $user = User::factory()->create();
    $tag = Tag::query()->create(['name' => 'Śniadanie', 'category' => Tag::MEAL_TYPE]);

    $this->actingAs($user);

    Livewire::test('pages::recipe.index')
        ->set('searchName', 'Owsianka')
        ->set('searchCategories', [$tag->id])
        ->call('clearFilters')
        ->assertSet('searchName', '')
        ->assertSet('searchCategories', []);
});

test('activeFiltersCount reflects the number of active filters', function () {
    $user = User::factory()->create();
    $tag = Tag::query()->create(['name' => 'Śniadanie', 'category' => Tag::MEAL_TYPE]);

    $this->actingAs($user);

    $component = Livewire::test('pages::recipe.index');
    expect($component->instance()->activeFiltersCount())->toBe(0);

    $component->set('searchName', 'Owsianka');
    expect($component->instance()->activeFiltersCount())->toBe(1);

    $component->set('searchCategories', [$tag->id]);
    expect($component->instance()->activeFiltersCount())->toBe(2);
});

test('the recipe list paginates at 10 results per page', function () {
    $user = User::factory()->create();

    // Letters, not numbers: recipes are ordered by name as a string, and
    // "Przepis 11" would sort before "Przepis 2" lexicographically.
    foreach (str_split('ABCDEFGHIJK') as $letter) {
        Recipe::query()->create(['name' => "Przepis {$letter}"]);
    }

    $this->actingAs($user);

    Livewire::test('pages::recipe.index')
        ->assertSee('Przepis A')
        ->assertDontSee('Przepis K')
        ->call('nextPage')
        ->assertSee('Przepis K');
});

test('going to details redirects to the recipe show page', function () {
    $user = User::factory()->create();
    $recipe = Recipe::query()->create(['name' => 'Owsianka']);

    $this->actingAs($user);

    Livewire::test('pages::recipe.index')
        ->call('goToDetails', $recipe)
        ->assertRedirect(route('recipes.show', $recipe));
});

// ---------------------------------------------------------------------
// Show (pages::recipe.show) — display, nutrition, back button, delete
// ---------------------------------------------------------------------

test('the show page displays name, source link, tags and ingredient amounts', function () {
    $user = User::factory()->create();
    $tag = Tag::query()->create(['name' => 'Śniadanie', 'category' => Tag::MEAL_TYPE]);
    $recipe = Recipe::query()->create(['name' => 'Owsianka', 'link' => 'https://example.com/owsianka']);
    $recipe->tags()->attach($tag->id);

    $ingredient = Ingredient::query()->create(['name' => 'Płatki owsiane']);
    $recipe->ingredients()->attach($ingredient->id, ['amount_me' => 50, 'amount_wife' => 35, 'unit' => 'g']);

    $this->actingAs($user);

    Livewire::test('pages::recipe.show', ['recipe' => $recipe])
        ->assertOk()
        ->assertSee('Owsianka')
        ->assertSee('Śniadanie')
        ->assertSee('Płatki owsiane')
        ->assertSee('50 g')
        ->assertSee('35 g')
        ->assertSee('https://example.com/owsianka', false);
});

test('the nutrition section is hidden when the recipe has no nutrition data', function () {
    $user = User::factory()->create();
    $recipe = Recipe::query()->create(['name' => 'Owsianka']);

    $this->actingAs($user);

    Livewire::test('pages::recipe.show', ['recipe' => $recipe])
        ->assertDontSee('Wartości odżywcze na porcję');
});

test('the nutrition section is shown when at least one profile has nutrition data', function () {
    $user = User::factory()->create();
    $recipe = Recipe::query()->create(['name' => 'Owsianka', 'calories_me' => 400]);

    $this->actingAs($user);

    Livewire::test('pages::recipe.show', ['recipe' => $recipe])
        ->assertSee('Wartości odżywcze na porcję');
});

test('the back button uses a safe local back query parameter', function () {
    $user = User::factory()->create();
    $recipe = Recipe::query()->create(['name' => 'Owsianka']);

    $this->actingAs($user);

    $this->get(route('recipes.show', $recipe) . '?back=' . urlencode('/shopping-list'))
        ->assertOk()
        ->assertSee('href="/shopping-list"', false);
});

test('the back button falls back to the recipe index for a protocol-relative back parameter', function () {
    $user = User::factory()->create();
    $recipe = Recipe::query()->create(['name' => 'Owsianka']);

    $this->actingAs($user);

    $this->get(route('recipes.show', $recipe) . '?back=' . urlencode('//evil.com/phishing'))
        ->assertOk()
        ->assertSee('href="' . route('recipes.index') . '"', false)
        ->assertDontSee('evil.com');
});

test('the back button falls back to the recipe index for an absolute external back parameter', function () {
    $user = User::factory()->create();
    $recipe = Recipe::query()->create(['name' => 'Owsianka']);

    $this->actingAs($user);

    $this->get(route('recipes.show', $recipe) . '?back=' . urlencode('https://evil.com/phishing'))
        ->assertOk()
        ->assertSee('href="' . route('recipes.index') . '"', false);
});

test('the back button falls back to the recipe index when no back parameter is given', function () {
    $user = User::factory()->create();
    $recipe = Recipe::query()->create(['name' => 'Owsianka']);

    $this->actingAs($user);

    Livewire::test('pages::recipe.show', ['recipe' => $recipe])
        ->assertSee('href="' . route('recipes.index') . '"', false);
});

test('the back button is hidden when showBackButton is false', function () {
    $user = User::factory()->create();
    $recipe = Recipe::query()->create(['name' => 'Owsianka']);

    $this->actingAs($user);

    Livewire::test('pages::recipe.show', ['recipe' => $recipe, 'showBackButton' => false])
        ->assertDontSee('size-10 rounded-2xl bg-white', false);
});

test('deleting a recipe removes it and redirects to the recipe index', function () {
    $user = User::factory()->create();
    $recipe = Recipe::query()->create(['name' => 'Owsianka']);
    $ingredient = Ingredient::query()->create(['name' => 'Płatki owsiane']);
    $recipe->ingredients()->attach($ingredient->id, ['amount_me' => 50, 'unit' => 'g']);

    $this->actingAs($user);

    Livewire::test('pages::recipe.show', ['recipe' => $recipe])
        ->call('delete')
        ->assertRedirect(route('recipes.index'));

    expect(Recipe::query()->whereKey($recipe->id)->exists())->toBeFalse()
        ->and(\Illuminate\Support\Facades\DB::table('recipe_ingredient_assignments')->where('recipe_id', $recipe->id)->exists())->toBeFalse();
});

test('deleting a recipe shows a success toast', function () {
    $user = User::factory()->create();
    $recipe = Recipe::query()->create(['name' => 'Owsianka']);

    $this->actingAs($user);

    Livewire::test('pages::recipe.show', ['recipe' => $recipe])
        ->call('delete')
        ->assertDispatched('toast-show');
});

// ---------------------------------------------------------------------
// Form (recipe.form) — create
// ---------------------------------------------------------------------

test('a recipe can be created with only a name', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('recipe.form')
        ->set('recipeName', 'Owsianka')
        ->call('save')
        ->assertHasNoErrors();

    expect(Recipe::query()->where('name', 'Owsianka')->exists())->toBeTrue();
});

test('the recipe name is required', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('recipe.form')
        ->set('recipeName', '')
        ->call('save')
        ->assertHasErrors(['recipeName' => 'required']);
});

test('the recipe url must be a valid url when provided', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('recipe.form')
        ->set('recipeName', 'Owsianka')
        ->set('recipeUrl', 'not-a-url')
        ->call('save')
        ->assertHasErrors(['recipeUrl' => 'url']);
});

test('creating a recipe attaches the selected tags', function () {
    $user = User::factory()->create();
    $tag = Tag::query()->create(['name' => 'Śniadanie', 'category' => Tag::MEAL_TYPE]);

    $this->actingAs($user);

    Livewire::test('recipe.form')
        ->set('recipeName', 'Owsianka')
        ->set('recipeTags', [$tag->id])
        ->call('save');

    $recipe = Recipe::query()->where('name', 'Owsianka')->firstOrFail();
    expect($recipe->tags->pluck('id')->all())->toBe([$tag->id]);
});

test('an ingredient with a name but no amount or unit is saved without them', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('recipe.form')
        ->set('recipeName', 'Owsianka')
        ->call('addIngredient')
        ->set('recipeIngredients.0.name', 'Płatki owsiane')
        ->call('save');

    $recipe = Recipe::query()->where('name', 'Owsianka')->firstOrFail();
    $pivot = $recipe->ingredients->firstWhere('name', 'Płatki owsiane')->pivot;

    expect($pivot->amount_me)->toBeNull()
        ->and($pivot->amount_wife)->toBeNull()
        ->and($pivot->unit)->toBeNull();
});

test('an ingredient amount for only one profile leaves the other profile null', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('recipe.form')
        ->set('recipeName', 'Owsianka')
        ->call('addIngredient')
        ->set('recipeIngredients.0.name', 'Płatki owsiane')
        ->set('recipeIngredients.0.amount_me', '50')
        ->set('recipeIngredients.0.unit', 'g')
        ->call('save');

    $recipe = Recipe::query()->where('name', 'Owsianka')->firstOrFail();
    $pivot = $recipe->ingredients->firstWhere('name', 'Płatki owsiane')->pivot;

    expect($pivot->amount_me)->toEqual(50.0)
        ->and($pivot->amount_wife)->toBeNull()
        ->and($pivot->unit)->toBe('g');
});

test('an ingredient amount without a valid unit discards both profile amounts and the unit', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('recipe.form')
        ->set('recipeName', 'Owsianka')
        ->call('addIngredient')
        ->set('recipeIngredients.0.name', 'Płatki owsiane')
        ->set('recipeIngredients.0.amount_me', '50')
        ->set('recipeIngredients.0.unit', '')
        ->call('save');

    $recipe = Recipe::query()->where('name', 'Owsianka')->firstOrFail();
    $pivot = $recipe->ingredients->firstWhere('name', 'Płatki owsiane')->pivot;

    expect($pivot->amount_me)->toBeNull()
        ->and($pivot->amount_wife)->toBeNull()
        ->and($pivot->unit)->toBeNull();
});

test('removeIngredient drops the row before it is ever saved', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('recipe.form')
        ->set('recipeName', 'Owsianka')
        ->call('addIngredient')
        ->set('recipeIngredients.0.name', 'Do usunięcia')
        ->call('removeIngredient', 0)
        ->call('save');

    $recipe = Recipe::query()->where('name', 'Owsianka')->firstOrFail();
    expect($recipe->ingredients)->toBeEmpty()
        ->and(Ingredient::query()->where('name', 'Do usunięcia')->exists())->toBeFalse();
});

test('nutrition values are saved independently for each profile', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('recipe.form')
        ->set('recipeName', 'Owsianka')
        ->set('recipeNutrition.me.calories', '500')
        ->set('recipeNutrition.me.protein', '20')
        ->set('recipeNutrition.wife.calories', '350')
        ->set('recipeNutrition.wife.protein', '15')
        ->call('save');

    $recipe = Recipe::query()->where('name', 'Owsianka')->firstOrFail();

    expect($recipe->calories_me)->toBe(500)
        ->and((float) $recipe->protein_me)->toEqual(20.0)
        ->and($recipe->calories_wife)->toBe(350)
        ->and((float) $recipe->protein_wife)->toEqual(15.0);
});

test('saving reuses an existing ingredient by name instead of creating a duplicate', function () {
    $user = User::factory()->create();
    Ingredient::query()->create(['name' => 'Cukier']);

    $this->actingAs($user);

    Livewire::test('recipe.form')
        ->set('recipeName', 'Ciasto')
        ->call('addIngredient')
        ->set('recipeIngredients.0.name', 'cukier')
        ->call('save');

    expect(Ingredient::query()->where('name', 'Cukier')->count())->toBe(1);
});

test('ingredient names are capitalized on save', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('recipe.form')
        ->set('recipeName', 'Ciasto')
        ->call('addIngredient')
        ->set('recipeIngredients.0.name', 'mąka pszenna')
        ->call('save');

    expect(Ingredient::query()->where('name', 'Mąka pszenna')->exists())->toBeTrue();
});

test('creating a recipe redirects to its show page with a success toast', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('recipe.form')
        ->set('recipeName', 'Owsianka')
        ->call('save')
        ->assertDispatched('toast-show');

    $recipe = Recipe::query()->where('name', 'Owsianka')->firstOrFail();

    Livewire::test('recipe.form')
        ->set('recipeName', 'Inny przepis')
        ->call('save')
        ->assertRedirect(route('recipes.show', Recipe::query()->where('name', 'Inny przepis')->firstOrFail()));
});

// ---------------------------------------------------------------------
// Form (recipe.form) — edit
// ---------------------------------------------------------------------

test('editing a recipe pre-fills the form from the existing data', function () {
    $user = User::factory()->create();
    $tag = Tag::query()->create(['name' => 'Śniadanie', 'category' => Tag::MEAL_TYPE]);
    $recipe = Recipe::query()->create([
        'name' => 'Owsianka',
        'link' => 'https://example.com',
        'content' => 'Ugotuj płatki.',
        'calories_me' => 400,
    ]);
    $recipe->tags()->attach($tag->id);

    $ingredient = Ingredient::query()->create(['name' => 'Płatki owsiane']);
    $recipe->ingredients()->attach($ingredient->id, ['amount_me' => 50, 'amount_wife' => 35, 'unit' => 'g']);

    $this->actingAs($user);

    Livewire::test('recipe.form', ['recipe' => $recipe])
        ->assertSet('recipeName', 'Owsianka')
        ->assertSet('recipeUrl', 'https://example.com')
        ->assertSet('recipeContent', 'Ugotuj płatki.')
        ->assertSet('recipeTags', [$tag->id])
        ->assertSet('recipeIngredients.0.name', 'Płatki owsiane')
        ->assertSet('recipeIngredients.0.amount_me', 50.0)
        ->assertSet('recipeIngredients.0.amount_wife', 35.0)
        ->assertSet('recipeIngredients.0.unit', 'g')
        ->assertSet('recipeNutrition.me.calories', 400);
});

test('editing updates an ingredient amount on the existing pivot row', function () {
    $user = User::factory()->create();
    $recipe = Recipe::query()->create(['name' => 'Owsianka']);
    $ingredient = Ingredient::query()->create(['name' => 'Płatki owsiane']);
    $recipe->ingredients()->attach($ingredient->id, ['amount_me' => 50, 'unit' => 'g']);

    $this->actingAs($user);

    Livewire::test('recipe.form', ['recipe' => $recipe])
        ->set('recipeIngredients.0.amount_me', '75')
        ->call('save');

    $recipe->refresh();
    expect($recipe->ingredients)->toHaveCount(1)
        ->and($recipe->ingredients->first()->pivot->amount_me)->toEqual(75.0);
});

test('editing to remove an ingredient detaches it from the recipe', function () {
    $user = User::factory()->create();
    $recipe = Recipe::query()->create(['name' => 'Owsianka']);
    $oats = Ingredient::query()->create(['name' => 'Płatki owsiane']);
    $milk = Ingredient::query()->create(['name' => 'Mleko']);
    $recipe->ingredients()->attach($oats->id, ['amount_me' => 50, 'unit' => 'g']);
    $recipe->ingredients()->attach($milk->id, ['amount_me' => 200, 'unit' => 'g']);

    $this->actingAs($user);

    Livewire::test('recipe.form', ['recipe' => $recipe])
        ->call('removeIngredient', 1)
        ->call('save');

    $recipe->refresh();
    expect($recipe->ingredients->pluck('name')->all())->toBe(['Płatki owsiane']);
});

test('editing replaces the previous tag set with the newly selected tags', function () {
    $user = User::factory()->create();
    $oldTag = Tag::query()->create(['name' => 'Śniadanie', 'category' => Tag::MEAL_TYPE]);
    $newTag = Tag::query()->create(['name' => 'Obiad', 'category' => Tag::MEAL_TYPE]);

    $recipe = Recipe::query()->create(['name' => 'Owsianka']);
    $recipe->tags()->attach($oldTag->id);

    $this->actingAs($user);

    Livewire::test('recipe.form', ['recipe' => $recipe])
        ->set('recipeTags', [$newTag->id])
        ->call('save');

    $recipe->refresh();
    expect($recipe->tags->pluck('id')->all())->toBe([$newTag->id]);
});

test('editing an existing recipe shows an "updated" toast rather than "added"', function () {
    $user = User::factory()->create();
    $recipe = Recipe::query()->create(['name' => 'Owsianka']);

    $this->actingAs($user);

    Livewire::test('recipe.form', ['recipe' => $recipe])
        ->set('recipeName', 'Owsianka bananowa')
        ->call('save')
        ->assertDispatched('toast-show');

    expect($recipe->refresh()->name)->toBe('Owsianka bananowa');
});
