<?php

use App\Models\Meal;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Support\Carbon;

test('authenticated user can see weekly meal calendar', function () {
    $user = User::factory()->create();
    $meal = Meal::query()->create([
        'type' => 'breakfast',
        'date' => Carbon::parse('2026-06-22 08:00:00'),
    ]);

    $meal->recipes()->attach(
        Recipe::query()->create([
            'name' => 'Owsianka',
            'content' => 'Zalej płatki mlekiem.',
        ])->id
    );

    $this->actingAs($user);

    $response = $this->get(route('meals.index', ['week' => '2026-06-22']));

    $response->assertOk();
    $response->assertSee('Kalendarz posiłków', false);
    $response->assertSee('Owsianka', false);
    $response->assertSee('22', false);
});

test('authenticated user can open day view from calendar', function () {
    $user = User::factory()->create();
    $meal = Meal::query()->create([
        'type' => 'dinner',
        'date' => Carbon::parse('2026-06-23 19:30:00'),
    ]);

    $meal->recipes()->attach(
        Recipe::query()->create([
            'name' => 'Makaron',
            'content' => 'Ugotuj makaron al dente.',
        ])->id
    );

    $this->actingAs($user);

    $response = $this->get(route('meals.day', '2026-06-23'));

    $response->assertOk();
    $response->assertSee('Makaron', false);
    $response->assertSee(route('meals.day.edit', '2026-06-23'), false);
    $response->assertSee('Dodaj posiłek', false);
});

test('authenticated user can update all meals in a day at once', function () {
    $user = User::factory()->create();
    $firstMeal = Meal::query()->create([
        'type' => 'breakfast',
        'date' => Carbon::parse('2026-06-24 08:00:00'),
    ]);
    $secondMeal = Meal::query()->create([
        'type' => 'dinner',
        'date' => Carbon::parse('2026-06-24 19:00:00'),
    ]);

    $oldRecipe = Recipe::query()->create([
        'name' => 'Owsianka stara',
        'content' => 'Opis.',
    ]);
    $newRecipeA = Recipe::query()->create([
        'name' => 'Kanapki',
        'content' => 'Opis.',
    ]);
    $newRecipeB = Recipe::query()->create([
        'name' => 'Sałatka',
        'content' => 'Opis.',
    ]);

    $firstMeal->recipes()->attach($oldRecipe->id);
    $secondMeal->recipes()->attach($oldRecipe->id);

    $this->actingAs($user);

    $response = $this->put(route('meals.day.update', '2026-06-24'), [
        'meals' => [
            [
                'id' => (string) $firstMeal->id,
                'type' => 'lunch',
                'recipes' => [
                    ['recipe_id' => (string) $newRecipeA->id],
                ],
            ],
            [
                'id' => (string) $secondMeal->id,
                'type' => 'dessert',
                'recipes' => [
                    ['recipe_id' => (string) $newRecipeB->id],
                ],
            ],
        ],
    ]);

    $response->assertRedirect(route('meals.day', '2026-06-24'));

    $firstMeal->refresh();
    $secondMeal->refresh();

    expect($firstMeal->type)->toBe('lunch');
    expect($firstMeal->date->format('Y-m-d H:i'))->toBe('2026-06-24 08:00');
    expect($firstMeal->recipes->pluck('id')->all())->toBe([$newRecipeA->id]);

    expect($secondMeal->type)->toBe('dessert');
    expect($secondMeal->date->format('Y-m-d H:i'))->toBe('2026-06-24 19:00');
    expect($secondMeal->recipes->pluck('id')->all())->toBe([$newRecipeB->id]);
});

test('authenticated user can create meal with recipes', function () {
    $user = User::factory()->create();
    $firstRecipe = Recipe::query()->create([
        'name' => 'Jajecznica',
        'content' => 'Usmaż jajka.',
    ]);
    $secondRecipe = Recipe::query()->create([
        'name' => 'Kawa',
        'content' => 'Zaparz kawę.',
    ]);

    $this->actingAs($user);

    $response = $this->post(route('meals.store'), [
        'type' => 'breakfast',
        'date' => '2026-06-20T08:30',
        'recipes' => [
            ['recipe_id' => (string) $firstRecipe->id],
            ['recipe_id' => (string) $secondRecipe->id],
        ],
    ]);

    $response->assertRedirect(route('meals.index'));

    $meal = Meal::query()->where(Meal::TYPE_COLUMN, 'breakfast')->first();
    expect($meal)->not->toBeNull();
    expect($meal?->date?->format('Y-m-d H:i'))->toBe('2026-06-20 08:30');
    expect($meal?->recipes()->count())->toBe(2);
    expect($meal?->recipes->contains(fn (Recipe $recipe) => $recipe->name === 'Jajecznica'))->toBeTrue();
    expect($meal?->recipes->contains(fn (Recipe $recipe) => $recipe->name === 'Kawa'))->toBeTrue();
});

test('authenticated user can delete meal', function () {
    $user = User::factory()->create();
    $meal = Meal::query()->create([
        'type' => 'dessert',
        'date' => Carbon::parse('2026-06-20 20:00:00'),
    ]);

    $meal->recipes()->attach(
        Recipe::query()->create([
            'name' => 'Sernik',
            'content' => 'Upiecz ciasto.',
        ])->id
    );

    $this->actingAs($user);

    $response = $this->delete(route('meals.destroy', $meal));

    $response->assertRedirect(route('meals.index'));
    expect(Meal::query()->whereKey($meal->id)->exists())->toBeFalse();
});