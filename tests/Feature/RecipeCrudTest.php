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
    $this->actingAs(User::factory()->create());
    Recipe::query()->create(['name' => 'Owsianka']);
    Recipe::query()->create(['name' => 'Pierogi']);

    Livewire::test('pages::recipe.index')
        ->set('search', 'owsi')
        ->assertSee('Owsianka')
        ->assertDontSee('Pierogi')
        ->assertSee('1 przepis');
});

test('recipes are filtered by meal, main ingredient, rating and packing', function () {
    $this->actingAs(User::factory()->create());
    $lunch = Tag::query()->firstOrCreate(['name' => 'Lunch', 'category' => Tag::MEAL_TYPE]);
    $fish = Tag::query()->firstOrCreate(['name' => 'Fish', 'category' => Tag::DIET_TYPE]);

    $match = Recipe::query()->create(['name' => 'Pieczona ryba', 'rating' => Recipe::RATING_HEALTHY, 'packable' => true]);
    $match->tags()->attach([$lunch->id, $fish->id]);
    $notPackable = Recipe::query()->create(['name' => 'Zupa rybna', 'rating' => Recipe::RATING_HEALTHY]);
    $notPackable->tags()->attach([$lunch->id, $fish->id]);
    $unhealthy = Recipe::query()->create(['name' => 'Ryba w panierce', 'rating' => Recipe::RATING_UNHEALTHY, 'packable' => true]);
    $unhealthy->tags()->attach([$lunch->id, $fish->id]);
    Recipe::query()->create(['name' => 'Owsianka', 'rating' => Recipe::RATING_HEALTHY, 'packable' => true]);

    $page = Livewire::test('pages::recipe.index')
        ->call('toggleTag', $lunch->id)
        ->call('toggleTag', $fish->id)
        ->call('setRating', Recipe::RATING_HEALTHY)
        ->set('packable', true)
        ->assertSee('Pieczona ryba')
        ->assertDontSee('Zupa rybna')
        ->assertDontSee('Ryba w panierce')
        ->assertDontSee('Owsianka')
        ->assertSet('panelFilterCount', 3);

    $page->call('clearFilters')
        ->assertSet('tagIds', [])
        ->assertSet('rating', null)
        ->assertSet('packable', false)
        ->assertSee('Owsianka');
});

test('the list shows rating, packing and servings of a recipe', function () {
    $this->actingAs(User::factory()->create());
    Recipe::query()->create(['name' => 'Pierogi', 'rating' => Recipe::RATING_MEDIUM, 'packable' => true, 'servings' => 6]);

    Livewire::test('pages::recipe.index')
        ->assertSee('średnie')
        ->assertSee('do spakowania')
        ->assertSee('6 porcji')
        ->assertDontSee('kcal');
});

test('archived recipes leave the list and come back after restoring', function () {
    $this->actingAs(User::factory()->create());
    $recipe = Recipe::query()->create(['name' => 'Stara zupa']);

    Livewire::test('pages::recipe.show', ['recipe' => $recipe])->call('archive');

    $list = Livewire::test('pages::recipe.index')
        ->assertDontSee('Stara zupa')
        ->assertSee('Archiwum (1)')
        ->set('archiveOpen', true)
        ->assertSee('Stara zupa')
        ->assertSee('Przywróć');

    expect($recipe->fresh()->archived_at)->not->toBeNull();

    $list->call('restore', $recipe->id)->assertDontSee('Archiwum (1)');

    expect($recipe->fresh()->archived_at)->toBeNull();
});

test('archiving from the edit form keeps the recipe and its data', function () {
    $this->actingAs(User::factory()->create());
    $recipe = Recipe::query()->create(['name' => 'Stara zupa']);

    Livewire::test('recipe.form', ['recipe' => $recipe])->call('archive')->assertRedirect(route('recipes.index'));

    expect(Recipe::query()->find($recipe->id)?->isArchived())->toBeTrue();
});

test('recipe cards link to the recipe show page', function () {
    $user = User::factory()->create();
    $recipe = Recipe::query()->create(['name' => 'Owsianka']);

    $this->actingAs($user);

    Livewire::test('pages::recipe.index')
        ->assertSeeHtml('href="' . route('recipes.show', $recipe) . '"');
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
    $recipe->ingredients()->attach($ingredient->id, ['amount' => 85, 'unit' => 'g', 'is_optional' => true, 'substitute' => 'płatki jaglane']);

    $this->actingAs($user);

    Livewire::test('pages::recipe.show', ['recipe' => $recipe])
        ->assertOk()
        ->assertSee('Owsianka')
        ->assertSee('Śniadanie')
        ->assertSee('Płatki owsiane')
        ->assertSee('85 g')
        ->assertSee('Przepis na 2 porcje')
        ->assertSee('opcjonalnie')
        ->assertSee('lub: płatki jaglane')
        ->assertSee('https://example.com/owsianka', false);
});

test('calories are no longer shown even when the recipe has them', function () {
    $user = User::factory()->create();
    $recipe = Recipe::query()->create(['name' => 'Owsianka', 'calories_me' => 400]);

    $this->actingAs($user);

    Livewire::test('pages::recipe.show', ['recipe' => $recipe])
        ->assertDontSee('kcal')
        ->assertDontSee('Wartości odżywcze');
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

test('deleting a recipe permanently removes it and redirects to the recipe index', function () {
    $this->actingAs(User::factory()->create());
    $recipe = Recipe::query()->create(['name' => 'Owsianka']);

    Livewire::test('recipe.form', ['recipe' => $recipe])
        ->call('delete')
        ->assertRedirect(route('recipes.index'));

    expect(Recipe::query()->find($recipe->id))->toBeNull();
});

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
    $this->actingAs(User::factory()->create());

    Livewire::test('recipe.form')
        ->set('recipeName', 'Owsianka')
        ->set('recipeIngredients.0.name', 'Płatki owsiane')
        ->call('save');

    $pivot = Recipe::query()->where('name', 'Owsianka')->firstOrFail()->ingredients->firstWhere('name', 'Płatki owsiane')->pivot;

    expect($pivot->amount)->toBeNull()->and($pivot->unit)->toBeNull();
});

test('ingredients are saved with one amount for the whole recipe, the servings, optional flag and substitute', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('recipe.form')
        ->set('recipeName', 'Owsianka')
        ->call('incrementServings')
        ->call('incrementServings')
        ->set('recipeIngredients.0.name', 'Płatki owsiane')
        ->set('recipeIngredients.0.amount', '1,5')
        ->set('recipeIngredients.0.unit', 'cup')
        ->set('recipeIngredients.0.optional', true)
        ->set('recipeIngredients.0.substitute', 'płatki jaglane')
        ->call('save')
        ->assertHasNoErrors();

    $recipe = Recipe::query()->where('name', 'Owsianka')->firstOrFail();
    $pivot = $recipe->ingredients->firstWhere('name', 'Płatki owsiane')->pivot;

    expect($recipe->servings)->toBe(4)
        ->and($pivot->amount)->toEqual(1.5)
        ->and($pivot->unit)->toBe('cup')
        ->and($pivot->is_optional)->toBeTrue()
        ->and($pivot->substitute)->toBe('płatki jaglane')
        ->and($pivot->amount_me)->toBeNull();
});

test('an ingredient amount without a valid unit discards the amount and the unit', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('recipe.form')
        ->set('recipeName', 'Owsianka')
        ->set('recipeIngredients.0.name', 'Płatki owsiane')
        ->set('recipeIngredients.0.amount', '50')
        ->set('recipeIngredients.0.unit', '')
        ->call('save');

    $pivot = Recipe::query()->where('name', 'Owsianka')->firstOrFail()->ingredients->firstWhere('name', 'Płatki owsiane')->pivot;

    expect($pivot->amount)->toBeNull()->and($pivot->unit)->toBeNull();
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

test('editing a recipe pre-fills the form from the existing data', function () {
    $user = User::factory()->create();
    $tag = Tag::query()->create(['name' => 'Śniadanie', 'category' => Tag::MEAL_TYPE]);
    $recipe = Recipe::query()->create([
        'name' => 'Owsianka',
        'link' => 'https://example.com',
        'content' => 'Ugotuj płatki.',
        'servings' => 4,
        'rating' => Recipe::RATING_HEALTHY,
        'packable' => true,
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
        ->assertSet('recipeIngredients.0.amount', '85')
        ->assertSet('recipeIngredients.0.unit', 'g')
        ->assertSet('servings', 4)
        ->assertSet('rating', Recipe::RATING_HEALTHY)
        ->assertSet('packable', true);
});

test('editing updates an ingredient amount on the existing pivot row', function () {
    $user = User::factory()->create();
    $recipe = Recipe::query()->create(['name' => 'Owsianka']);
    $ingredient = Ingredient::query()->create(['name' => 'Płatki owsiane']);
    $recipe->ingredients()->attach($ingredient->id, ['amount_me' => 50, 'unit' => 'g']);

    $this->actingAs($user);

    Livewire::test('recipe.form', ['recipe' => $recipe])
        ->assertSet('recipeIngredients.0.amount', '50')
        ->set('recipeIngredients.0.amount', '75')
        ->call('save');

    $recipe->refresh();
    $pivot = $recipe->ingredients->first()->pivot;
    expect($recipe->ingredients)->toHaveCount(1)
        ->and($pivot->amount)->toEqual(75.0)
        ->and($pivot->amount_me)->toEqual(50.0); // old per-person data stays untouched
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


test('a recipe created from a note replaces the note in the plan', function () {
    $this->actingAs(User::factory()->create());
    $lunch = Tag::query()->firstOrCreate(['name' => 'Lunch', 'category' => Tag::MEAL_TYPE]);
    $meal = \App\Models\Meal::query()->create(['date' => '2026-11-18 12:00:00', 'type' => 'lunch', 'note' => 'makaron z pesto']);

    Livewire::test('recipe.form', ['noteDate' => '2026-11-18', 'noteType' => 'lunch'])
        ->assertSet('recipeName', 'Makaron z pesto')
        ->assertSet('recipeTags', [$lunch->id])
        ->assertSee('Tworzysz przepis z notatki')
        ->assertSee('Zapisz i wstaw do planu')
        ->call('save')
        ->assertRedirect(route('meals.day.edit', '2026-11-18'));

    $recipe = Recipe::query()->where('name', 'Makaron z pesto')->sole();
    $meal->refresh();

    expect($meal->note)->toBeNull()
        ->and($meal->recipes->pluck('id')->all())->toBe([$recipe->id])
        ->and($meal->recipes->first()->pivot->mode)->toBe('single');
});
