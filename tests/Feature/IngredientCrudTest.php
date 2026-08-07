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
