<?php

use App\Models\Ingredient;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Models\User;
use Livewire\Livewire;

function shoppingListItem(array $attributes): ShoppingListItem
{
    $shoppingList = ShoppingList::query()->firstOrCreate(['id' => 1], ['name' => 'Main shopping list']);

    return $shoppingList->items()->create(array_merge([
        'name' => 'Item',
        'is_checked' => false,
        'week_day' => 'monday',
    ], $attributes));
}

test('two items on the same day with the same ingredient and unit merge into one tile with the summed amount', function () {
    $user = User::factory()->create();
    $ingredient = Ingredient::query()->create(['name' => 'Sól', 'purchase_timing' => Ingredient::PURCHASE_TIMING_FRESH]);

    shoppingListItem(['name' => 'Sól', 'ingredient_id' => $ingredient->id, 'amount' => 10, 'unit' => 'g', 'week_day' => 'monday']);
    shoppingListItem(['name' => 'Sól', 'ingredient_id' => $ingredient->id, 'amount' => 5, 'unit' => 'g', 'week_day' => 'monday']);

    $this->actingAs($user);

    $monday = Livewire::test('pages::shopping-list.index')->instance()->activeByDay->firstWhere('day', 'monday');

    expect($monday['items'])->toHaveCount(1);

    $group = $monday['items']->first();
    expect($group['ids'])->toHaveCount(2)
        ->and($group['displayQuantity'])->toBe('15 g');
});

test('the same ingredient in different units on the same day stays as two separate tiles', function () {
    $user = User::factory()->create();
    $ingredient = Ingredient::query()->create(['name' => 'Mleko', 'purchase_timing' => Ingredient::PURCHASE_TIMING_FRESH]);

    shoppingListItem(['name' => 'Mleko', 'ingredient_id' => $ingredient->id, 'amount' => 1, 'unit' => 'l', 'week_day' => 'monday']);
    shoppingListItem(['name' => 'Mleko', 'ingredient_id' => $ingredient->id, 'amount' => 200, 'unit' => 'ml', 'week_day' => 'monday']);

    $this->actingAs($user);

    $monday = Livewire::test('pages::shopping-list.index')->instance()->activeByDay->firstWhere('day', 'monday');

    expect($monday['items'])->toHaveCount(2);
});

test('the same ingredient and unit on different days are not merged across days', function () {
    $user = User::factory()->create();
    $ingredient = Ingredient::query()->create(['name' => 'Sól', 'purchase_timing' => Ingredient::PURCHASE_TIMING_FRESH]);

    shoppingListItem(['name' => 'Sól', 'ingredient_id' => $ingredient->id, 'amount' => 10, 'unit' => 'g', 'week_day' => 'monday']);
    shoppingListItem(['name' => 'Sól', 'ingredient_id' => $ingredient->id, 'amount' => 5, 'unit' => 'g', 'week_day' => 'tuesday']);

    $this->actingAs($user);

    $byDay = Livewire::test('pages::shopping-list.index')->instance()->activeByDay;

    $monday = $byDay->firstWhere('day', 'monday');
    $tuesday = $byDay->firstWhere('day', 'tuesday');

    expect($monday['items'])->toHaveCount(1)
        ->and($monday['items']->first()['displayQuantity'])->toBe('10 g')
        ->and($tuesday['items'])->toHaveCount(1)
        ->and($tuesday['items']->first()['displayQuantity'])->toBe('5 g');
});

test('manually added items without a linked ingredient are never merged, even with the same name and unit', function () {
    $user = User::factory()->create();

    shoppingListItem(['name' => 'Papryka', 'ingredient_id' => null, 'quantity' => '2 szt', 'week_day' => 'monday']);
    shoppingListItem(['name' => 'Papryka', 'ingredient_id' => null, 'quantity' => '3 szt', 'week_day' => 'monday']);

    $this->actingAs($user);

    $monday = Livewire::test('pages::shopping-list.index')->instance()->activeByDay->firstWhere('day', 'monday');

    expect($monday['items'])->toHaveCount(2);
});

test('a day with a single, non-duplicated item is not affected by grouping', function () {
    $user = User::factory()->create();
    $ingredient = Ingredient::query()->create(['name' => 'Cukinia', 'purchase_timing' => Ingredient::PURCHASE_TIMING_FRESH]);

    shoppingListItem(['name' => 'Cukinia', 'ingredient_id' => $ingredient->id, 'amount' => 100, 'unit' => 'g', 'week_day' => 'monday']);

    $this->actingAs($user);

    $monday = Livewire::test('pages::shopping-list.index')->instance()->activeByDay->firstWhere('day', 'monday');
    $group = $monday['items']->first();

    expect($monday['items'])->toHaveCount(1)
        ->and($group['displayQuantity'])->toBe('100 g')
        ->and($group['ids'])->toHaveCount(1);
});

test('toggling a merged tile checks every underlying row together', function () {
    $user = User::factory()->create();
    $ingredient = Ingredient::query()->create(['name' => 'Sól', 'purchase_timing' => Ingredient::PURCHASE_TIMING_FRESH]);

    $first = shoppingListItem(['name' => 'Sól', 'ingredient_id' => $ingredient->id, 'amount' => 10, 'unit' => 'g', 'week_day' => 'monday']);
    $second = shoppingListItem(['name' => 'Sól', 'ingredient_id' => $ingredient->id, 'amount' => 5, 'unit' => 'g', 'week_day' => 'monday']);

    $this->actingAs($user);

    Livewire::test('pages::shopping-list.index')
        ->call('toggle', [$first->id, $second->id]);

    expect($first->refresh()->is_checked)->toBeTrue()
        ->and($second->refresh()->is_checked)->toBeTrue();
});

test('once checked, a merged tile still shows as a single summed entry in the bought section', function () {
    $user = User::factory()->create();
    $ingredient = Ingredient::query()->create(['name' => 'Sól', 'purchase_timing' => Ingredient::PURCHASE_TIMING_FRESH]);

    shoppingListItem(['name' => 'Sól', 'ingredient_id' => $ingredient->id, 'amount' => 10, 'unit' => 'g', 'week_day' => 'monday', 'is_checked' => true]);
    shoppingListItem(['name' => 'Sól', 'ingredient_id' => $ingredient->id, 'amount' => 5, 'unit' => 'g', 'week_day' => 'monday', 'is_checked' => true]);

    $this->actingAs($user);

    $monday = Livewire::test('pages::shopping-list.index')->instance()->boughtByDay->firstWhere('day', 'monday');

    expect($monday['items'])->toHaveCount(1)
        ->and($monday['items']->first()['displayQuantity'])->toBe('15 g');
});

test('the "can buy anytime" section merges the same ingredient and unit across different days', function () {
    $user = User::factory()->create();
    $ingredient = Ingredient::query()->create(['name' => 'Mąka', 'purchase_timing' => Ingredient::PURCHASE_TIMING_ADVANCE]);

    shoppingListItem(['name' => 'Mąka', 'ingredient_id' => $ingredient->id, 'amount' => 200, 'unit' => 'g', 'week_day' => 'monday']);
    shoppingListItem(['name' => 'Mąka', 'ingredient_id' => $ingredient->id, 'amount' => 300, 'unit' => 'g', 'week_day' => 'thursday']);

    $this->actingAs($user);

    $advanceItems = Livewire::test('pages::shopping-list.index')->instance()->advanceItems;

    expect($advanceItems)->toHaveCount(1)
        ->and($advanceItems->first()['displayQuantity'])->toBe('500 g')
        ->and($advanceItems->first()['ids'])->toHaveCount(2);
});

test('an advance ingredient still appears merged inside its own day section too', function () {
    $user = User::factory()->create();
    $ingredient = Ingredient::query()->create(['name' => 'Mąka', 'purchase_timing' => Ingredient::PURCHASE_TIMING_ADVANCE]);

    shoppingListItem(['name' => 'Mąka', 'ingredient_id' => $ingredient->id, 'amount' => 200, 'unit' => 'g', 'week_day' => 'monday']);
    shoppingListItem(['name' => 'Mąka', 'ingredient_id' => $ingredient->id, 'amount' => 300, 'unit' => 'g', 'week_day' => 'monday']);

    $this->actingAs($user);

    $component = Livewire::test('pages::shopping-list.index');
    $monday = $component->instance()->activeByDay->firstWhere('day', 'monday');
    $advanceItems = $component->instance()->advanceItems;

    expect($monday['items'])->toHaveCount(1)
        ->and($monday['items']->first()['displayQuantity'])->toBe('500 g')
        ->and($advanceItems)->toHaveCount(1)
        ->and($advanceItems->first()['displayQuantity'])->toBe('500 g');
});

test('checked items are excluded from the merged group, even sharing an ingredient and unit with active ones', function () {
    $user = User::factory()->create();
    $ingredient = Ingredient::query()->create(['name' => 'Sól', 'purchase_timing' => Ingredient::PURCHASE_TIMING_FRESH]);

    shoppingListItem(['name' => 'Sól', 'ingredient_id' => $ingredient->id, 'amount' => 10, 'unit' => 'g', 'week_day' => 'monday', 'is_checked' => false]);
    shoppingListItem(['name' => 'Sól', 'ingredient_id' => $ingredient->id, 'amount' => 5, 'unit' => 'g', 'week_day' => 'monday', 'is_checked' => true]);

    $this->actingAs($user);

    $monday = Livewire::test('pages::shopping-list.index')->instance()->activeByDay->firstWhere('day', 'monday');

    expect($monday['items'])->toHaveCount(1)
        ->and($monday['items']->first()['displayQuantity'])->toBe('10 g');
});
