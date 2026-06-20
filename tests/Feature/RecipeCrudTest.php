<?php

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\User;

test('authenticated user can create recipe with existing and new ingredients', function () {
    $user = User::factory()->create();
    $salt = Ingredient::query()->create(['name' => 'Sól']);

    $this->actingAs($user);

    $response = $this->post(route('recipes.store'), [
        'name' => 'Pasta al pomodoro',
        'content' => 'Ugotuj makaron i dodaj sos.',
        'ingredients' => [
            ['ingredient_id' => (string) $salt->id, 'custom_name' => '', 'quantity' => '1 łyżeczka'],
            ['ingredient_id' => '__new__', 'custom_name' => 'Pomidor', 'quantity' => '4 sztuki'],
        ],
    ]);

    $response->assertRedirect(route('recipes.index'));

    $recipe = Recipe::query()->get()->firstWhere(Recipe::NAME_COLUMN, 'Pasta al pomodoro');
    expect($recipe)->not->toBeNull();
    expect($recipe->ingredients()->count())->toBe(2);
    expect(Ingredient::query()->get()->contains(fn (Ingredient $ingredient) => $ingredient->name === 'Pomidor'))->toBeTrue();
    expect($recipe->ingredients->firstWhere('id', $salt->id)?->pivot?->quantity)->toBe('1 łyżeczka');
});

test('authenticated user can update recipe and ingredient quantities', function () {
    $user = User::factory()->create();
    $recipe = Recipe::query()->create([
        'name' => 'Sałatka',
        'content' => 'Wymieszaj składniki.',
    ]);

    $recipe->ingredients()->attach(
        Ingredient::query()->create(['name' => 'Ogórek'])->id,
        ['quantity' => '1 sztuka']
    );

    $this->actingAs($user);

    $response = $this->put(route('recipes.update', $recipe), [
        'name' => 'Sałatka grecka',
        'content' => 'Wymieszaj składniki i podawaj schłodzone.',
        'ingredients' => [
            ['ingredient_id' => '__new__', 'custom_name' => 'Feta', 'quantity' => '200 g'],
            ['ingredient_id' => '__new__', 'custom_name' => 'Pomidor', 'quantity' => '2 sztuki'],
        ],
    ]);

    $response->assertRedirect(route('recipes.index'));

    $recipe->refresh();
    expect($recipe->name)->toBe('Sałatka grecka');
    expect($recipe->ingredients()->count())->toBe(2);
    expect($recipe->ingredients->contains(fn (Ingredient $ingredient) => $ingredient->name === 'Feta'))->toBeTrue();
    expect($recipe->ingredients->contains(fn (Ingredient $ingredient) => $ingredient->name === 'Ogórek'))->toBeFalse();
});

test('authenticated user can delete recipe', function () {
    $user = User::factory()->create();
    $recipe = Recipe::query()->create([
        'name' => 'Tost',
        'content' => 'Upiecz chleb.',
    ]);

    $this->actingAs($user);

    $response = $this->delete(route('recipes.destroy', $recipe));

    $response->assertRedirect(route('recipes.index'));
    expect(Recipe::query()->whereKey($recipe->id)->exists())->toBeFalse();
});