<?php

use App\Models\Ingredient;
use App\Models\User;

test('authenticated user can create ingredient', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->post(route('ingredients.store'), [
        'name' => 'Papryka',
    ]);

    $response->assertRedirect(route('ingredients.index'));
    expect(Ingredient::query()->where(Ingredient::NAME_COLUMN, 'Papryka')->exists())->toBeTrue();
});

test('authenticated user can update ingredient', function () {
    $user = User::factory()->create();
    $ingredient = Ingredient::query()->create(['name' => 'Cukier']);

    $this->actingAs($user);

    $response = $this->put(route('ingredients.update', $ingredient), [
        'name' => 'Cukier trzcinowy',
    ]);

    $response->assertRedirect(route('ingredients.index'));

    $ingredient->refresh();
    expect($ingredient->name)->toBe('Cukier trzcinowy');
});

test('authenticated user can delete ingredient', function () {
    $user = User::factory()->create();
    $ingredient = Ingredient::query()->create(['name' => 'Majeranek']);

    $this->actingAs($user);

    $response = $this->delete(route('ingredients.destroy', $ingredient));

    $response->assertRedirect(route('ingredients.index'));
    expect(Ingredient::query()->whereKey($ingredient->id)->exists())->toBeFalse();
});