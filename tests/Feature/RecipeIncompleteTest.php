<?php

use App\Models\Recipe;
use App\Models\Tag;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->lunch = Tag::query()->firstOrCreate(['name' => 'Lunch', 'category' => Tag::MEAL_TYPE]);
    $this->fish = Tag::query()->firstOrCreate(['name' => 'Fish', 'category' => Tag::DIET_TYPE]);
});

function completeRecipe(string $name, array $tags): Recipe
{
    $recipe = Recipe::query()->create(['name' => $name, 'rating' => Recipe::RATING_HEALTHY, 'packable' => false]);
    $recipe->tags()->sync($tags);

    return $recipe;
}

test('a new recipe starts with "for packing" not set', function () {
    Livewire::test('recipe.form')
        ->set('recipeName', 'Owsianka')
        ->assertSet('packable', null)
        ->call('save')
        ->assertHasNoErrors();

    expect(Recipe::query()->where('name', 'Owsianka')->value('packable'))->toBeNull();
});

test('the form sets "for packing" to yes or no, and tapping the chosen one again clears it', function () {
    $recipe = Recipe::query()->create(['name' => 'Pierogi']);

    Livewire::test('recipe.form', ['recipe' => $recipe])
        ->call('setPackable', false)
        ->assertSet('packable', false)
        ->call('save');

    expect($recipe->fresh()->packable)->toBeFalse();

    Livewire::test('recipe.form', ['recipe' => $recipe->fresh()])
        ->assertSet('packable', false)
        ->call('setPackable', false)
        ->assertSet('packable', null);
});

test('the page lists active recipes missing a label, with counts per missing field', function () {
    completeRecipe('Pełny', [$this->lunch->id, $this->fish->id]);
    Recipe::query()->create(['name' => 'Bez niczego']);
    $noRating = completeRecipe('Bez oceny', [$this->lunch->id, $this->fish->id]);
    $noRating->update(['rating' => null]);
    Recipe::query()->create(['name' => 'Zarchiwizowany', 'archived_at' => now()]);

    $page = Livewire::test('pages::recipe.incomplete')
        ->assertSee('Bez niczego')
        ->assertSee('Bez oceny')
        ->assertDontSee('Pełny')
        ->assertDontSee('Zarchiwizowany');

    expect($page->instance()->filterCounts)->toBe([
        'all' => 2,
        Recipe::FIELD_MEAL => 1,
        Recipe::FIELD_MAIN_INGREDIENT => 1,
        Recipe::FIELD_RATING => 2,
        Recipe::FIELD_PACKABLE => 1,
    ]);

    $page->call('setFilter', Recipe::FIELD_MEAL)
        ->assertSee('Bez niczego')
        ->assertDontSee('Bez oceny');
});

test('filling in the missing labels saves them and the complete recipe can be undone', function () {
    $recipe = Recipe::query()->create(['name' => 'Placki z cukinii']);

    $page = Livewire::test('pages::recipe.incomplete')
        ->call('open', $recipe->id)
        ->assertSet('sheetOpen', true)
        ->assertSet('openFields', Recipe::LABEL_FIELDS)
        ->call('toggleTag', $this->lunch->id)
        ->call('toggleTag', $this->fish->id)
        ->call('setRating', Recipe::RATING_HEALTHY)
        ->call('setPackable', true)
        ->call('closeSheet')
        ->assertSet('sheetOpen', false)
        ->assertSee('„Placki z cukinii” ma komplet oznaczeń.');

    $recipe->refresh()->load('tags');
    expect($recipe->missingLabels())->toBe([])
        ->and($recipe->packable)->toBeTrue();

    $page->call('undoComplete');

    $recipe->refresh()->load('tags');
    expect($recipe->missingLabels())->toBe(Recipe::LABEL_FIELDS);
});

test('desktop "next recipe" moves to the next incomplete recipe', function () {
    $first = Recipe::query()->create(['name' => 'Ananas']);
    $second = Recipe::query()->create(['name' => 'Banan']);

    Livewire::test('pages::recipe.incomplete')
        ->call('select', $first->id)
        ->assertSee('Następny przepis')
        ->call('next')
        ->assertSet('openId', $second->id);
});

test('with nothing missing the page says everything is labelled', function () {
    completeRecipe('Pełny', [$this->lunch->id, $this->fish->id]);

    Livewire::test('pages::recipe.incomplete')
        ->assertSee('Wszystko oznaczone');
});

test('the recipe list links to the page with the number of incomplete recipes', function () {
    Recipe::query()->create(['name' => 'Bez niczego']);
    completeRecipe('Pełny', [$this->lunch->id, $this->fish->id]);

    Livewire::test('pages::recipe.index')
        ->assertSee('Do uzupełnienia (1)');

    $this->get(route('recipes.incomplete'))->assertOk();
});
