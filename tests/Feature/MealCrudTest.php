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
    $response->assertSee(route('meals.edit', $meal), false);
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

test('authenticated user can update meal and recipe assignments', function () {
    $user = User::factory()->create();
    $meal = Meal::query()->create([
        'type' => 'lunch',
        'date' => Carbon::parse('2026-06-20 13:00:00'),
    ]);

    $previousRecipe = Recipe::query()->create([
        'name' => 'Zupa pomidorowa',
        'content' => 'Podaj gorącą.',
    ]);
    $newRecipe = Recipe::query()->create([
        'name' => 'Kotlet schabowy',
        'content' => 'Usmaż na złoto.',
    ]);

    $meal->recipes()->attach($previousRecipe->id);

    $this->actingAs($user);

    $response = $this->put(route('meals.update', $meal), [
        'type' => 'dinner',
        'date' => '2026-06-20T19:15',
        'recipes' => [
            ['recipe_id' => (string) $newRecipe->id],
        ],
    ]);

    $response->assertRedirect(route('meals.index'));

    $meal->refresh();
    expect($meal->type)->toBe('dinner');
    expect($meal->date->format('Y-m-d H:i'))->toBe('2026-06-20 19:15');
    expect($meal->recipes()->count())->toBe(1);
    expect($meal->recipes->contains(fn (Recipe $recipe) => $recipe->name === 'Kotlet schabowy'))->toBeTrue();
    expect($meal->recipes->contains(fn (Recipe $recipe) => $recipe->name === 'Zupa pomidorowa'))->toBeFalse();
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