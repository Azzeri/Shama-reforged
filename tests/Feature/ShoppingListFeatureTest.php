<?php

use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\Recipe;
use App\Models\ShoppingListItem;
use App\Models\User;
use Illuminate\Support\Carbon;

test('authenticated user can add custom shopping list item', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->post(route('shopping-list.items.store'), [
        'name' => 'Płyn do naczyń',
        'quantity' => '1 butelka',
        'week_day' => 'friday',
        'notes' => 'Poza przepisami',
    ]);

    $response->assertRedirect(route('shopping-list.index'));

    $item = ShoppingListItem::query()->first();
    expect($item)->not->toBeNull();
    expect($item?->name)->toBe('Płyn do naczyń');
    expect($item?->quantity)->toBe('1 butelka');
    expect($item?->week_day)->toBe('friday');
    expect($item?->is_checked)->toBeFalse();
});

test('authenticated user can add custom shopping list item without weekday', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->post(route('shopping-list.items.store'), [
        'name' => 'Ręcznik papierowy',
        'quantity' => '2 rolki',
        'notes' => 'Bez dnia',
    ]);

    $response->assertRedirect(route('shopping-list.index'));

    $item = ShoppingListItem::query()->where(ShoppingListItem::NAME_COLUMN, 'Ręcznik papierowy')->first();
    expect($item)->not->toBeNull();
    expect($item?->week_day)->toBeNull();
});

test('authenticated user can toggle shopping item and move it to bought section', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $created = $this->post(route('shopping-list.items.store'), [
        'name' => 'Mleko',
        'quantity' => '2 l',
        'week_day' => 'monday',
    ]);
    $created->assertRedirect(route('shopping-list.index'));

    $item = ShoppingListItem::query()->first();
    expect($item)->not->toBeNull();

    $toggled = $this->patch(route('shopping-list.items.toggle', $item));
    $toggled->assertRedirect(route('shopping-list.index'));

    $item->refresh();
    expect($item->is_checked)->toBeTrue();
});

test('authenticated user can generate shopping list items from selected day meals', function () {
    $user = User::factory()->create();
    $recipe = Recipe::query()->create([
        'name' => 'Kanapki',
        'content' => 'Przygotuj pieczywo i dodatki.',
    ]);
    $ingredient = Ingredient::query()->create(['name' => 'Ser']);
    $recipe->ingredients()->attach($ingredient->id, ['quantity' => '4 plastry']);

    $meal = Meal::query()->create([
        'type' => 'breakfast',
        'date' => Carbon::parse('2026-06-22 08:00:00'),
    ]);
    $meal->recipes()->attach($recipe->id);

    $this->actingAs($user);

    $response = $this->post(route('shopping-list.generate'), [
        'week_start' => '2026-06-22',
        'mode' => 'selected-days',
        'days' => ['2026-06-22'],
    ]);

    $response->assertRedirect(route('shopping-list.index'));

    $item = ShoppingListItem::query()->first();
    expect($item)->not->toBeNull();
    expect($item?->name)->toBe('Ser');
    expect($item?->quantity)->toBe('4 plastry');
    expect($item?->week_day)->toBe('monday');
});

test('authenticated user can generate shopping list items from full week', function () {
    $user = User::factory()->create();

    $firstRecipe = Recipe::query()->create([
        'name' => 'Owsianka',
        'content' => 'Gotuj płatki.',
    ]);
    $firstIngredient = Ingredient::query()->create(['name' => 'Płatki owsiane']);
    $firstRecipe->ingredients()->attach($firstIngredient->id, ['quantity' => '100 g']);

    $secondRecipe = Recipe::query()->create([
        'name' => 'Sałatka',
        'content' => 'Połącz składniki.',
    ]);
    $secondIngredient = Ingredient::query()->create(['name' => 'Ogórek']);
    $secondRecipe->ingredients()->attach($secondIngredient->id, ['quantity' => '1 sztuka']);

    $mondayMeal = Meal::query()->create([
        'type' => 'breakfast',
        'date' => Carbon::parse('2026-06-22 08:00:00'),
    ]);
    $mondayMeal->recipes()->attach($firstRecipe->id);

    $wednesdayMeal = Meal::query()->create([
        'type' => 'lunch',
        'date' => Carbon::parse('2026-06-24 13:00:00'),
    ]);
    $wednesdayMeal->recipes()->attach($secondRecipe->id);

    $this->actingAs($user);

    $response = $this->post(route('shopping-list.generate'), [
        'week_start' => '2026-06-22',
        'mode' => 'full-week',
    ]);

    $response->assertRedirect(route('shopping-list.index'));
    expect(ShoppingListItem::query()->count())->toBe(2);
    expect(ShoppingListItem::query()->get()->contains(fn (ShoppingListItem $item) => $item->name === 'Płatki owsiane'))->toBeTrue();
    expect(ShoppingListItem::query()->get()->contains(fn (ShoppingListItem $item) => $item->name === 'Ogórek'))->toBeTrue();
});

test('authenticated user can clear unchecked shopping list items', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $this->post(route('shopping-list.items.store'), [
        'name' => 'Mleko',
        'quantity' => '2 l',
    ]);
    $this->post(route('shopping-list.items.store'), [
        'name' => 'Chleb',
        'quantity' => '1 sztuka',
    ]);
    $this->post(route('shopping-list.items.store'), [
        'name' => 'Jajka',
        'quantity' => '10 sztuk',
    ]);

    expect(ShoppingListItem::query()->count())->toBe(3);
    expect(ShoppingListItem::query()->where('is_checked', false)->count())->toBe(3);

    $itemToCheck = ShoppingListItem::query()->first();
    $this->patch(route('shopping-list.items.toggle', $itemToCheck));

    expect(ShoppingListItem::query()->where('is_checked', false)->count())->toBe(2);

    $response = $this->delete(route('shopping-list.clear-unchecked'));

    $response->assertRedirect(route('shopping-list.index'));
    expect(ShoppingListItem::query()->count())->toBe(1);
    expect(ShoppingListItem::query()->first()?->name)->toBe('Mleko');
    expect(ShoppingListItem::query()->first()?->is_checked)->toBeTrue();
});