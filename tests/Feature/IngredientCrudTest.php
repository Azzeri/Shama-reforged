<?php

use App\Models\Ingredient;
use App\Models\User;
use Livewire\Livewire;

test('authenticated user can create ingredient', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::ingredient.index')
        ->set('newIngredientName', 'Papryka')
        ->call('createIngredient');

    expect(Ingredient::query()->where(Ingredient::NAME_COLUMN, 'Papryka')->exists())->toBeTrue();
});

test('authenticated user can update ingredient', function () {
    $user = User::factory()->create();
    $ingredient = Ingredient::query()->create(['name' => 'Cukier']);

    $this->actingAs($user);

    Livewire::test('pages::ingredient.index')
        ->call('editIngredient', $ingredient->id)
        ->set('editIngredientName', 'Cukier trzcinowy')
        ->call('saveIngredient');

    $ingredient->refresh();
    expect($ingredient->name)->toBe('Cukier trzcinowy');
});

test('ingredient names are trimmed and capitalized on save', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::ingredient.index')
        ->set('newIngredientName', '   papryka czerwona   ')
        ->call('createIngredient');

    expect(Ingredient::query()->where(Ingredient::NAME_COLUMN, 'Papryka czerwona')->exists())->toBeTrue();
});

test('authenticated user can delete ingredient', function () {
    $user = User::factory()->create();
    $ingredient = Ingredient::query()->create(['name' => 'Majeranek']);

    $this->actingAs($user);

    Livewire::test('pages::ingredient.index')
        ->call('confirmDelete', $ingredient)
        ->call('delete');

    expect(Ingredient::query()->whereKey($ingredient->id)->exists())->toBeFalse();
});

test('a new ingredient defaults to the uncategorized category', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::ingredient.index')
        ->set('newIngredientName', 'Papryka')
        ->call('createIngredient');

    $ingredient = Ingredient::query()->where('name', 'Papryka')->firstOrFail();
    expect($ingredient->category)->toBe(Ingredient::CATEGORY_UNCATEGORIZED);
});

test('a category can be chosen explicitly when creating an ingredient', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::ingredient.index')
        ->set('newIngredientName', 'Mleko')
        ->set('newIngredientCategory', Ingredient::CATEGORY_DAIRY)
        ->call('createIngredient');

    $ingredient = Ingredient::query()->where('name', 'Mleko')->firstOrFail();
    expect($ingredient->category)->toBe(Ingredient::CATEGORY_DAIRY);
});

test('editing an ingredient pre-fills its current category', function () {
    $user = User::factory()->create();
    $ingredient = Ingredient::query()->create(['name' => 'Ser', 'category' => Ingredient::CATEGORY_DAIRY]);

    $this->actingAs($user);

    Livewire::test('pages::ingredient.index')
        ->call('editIngredient', $ingredient->id)
        ->assertSet('editIngredientCategory', Ingredient::CATEGORY_DAIRY);
});

test('an ingredient category can be changed when editing', function () {
    $user = User::factory()->create();
    $ingredient = Ingredient::query()->create(['name' => 'Chleb', 'category' => Ingredient::CATEGORY_UNCATEGORIZED]);

    $this->actingAs($user);

    Livewire::test('pages::ingredient.index')
        ->call('editIngredient', $ingredient->id)
        ->set('editIngredientCategory', Ingredient::CATEGORY_BREAD)
        ->call('saveIngredient');

    expect($ingredient->refresh()->category)->toBe(Ingredient::CATEGORY_BREAD);
});

test('the ingredient category must be a known category', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::ingredient.index')
        ->set('newIngredientName', 'Papryka')
        ->set('newIngredientCategory', 'not-a-real-category')
        ->call('createIngredient')
        ->assertHasErrors(['newIngredientCategory']);
});
