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
    $group = $advanceItems->first()['items']->first();

    expect($advanceItems)->toHaveCount(1)
        ->and($group['displayQuantity'])->toBe('500 g')
        ->and($group['ids'])->toHaveCount(2);
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
    $advanceGroup = $advanceItems->first()['items']->first();

    expect($monday['items'])->toHaveCount(1)
        ->and($monday['items']->first()['displayQuantity'])->toBe('500 g')
        ->and($advanceItems)->toHaveCount(1)
        ->and($advanceGroup['displayQuantity'])->toBe('500 g');
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

// ---------------------------------------------------------------------
// Grouping by ingredient category within a day
// ---------------------------------------------------------------------

test('a day with ingredients from several categories splits into one section per category', function () {
    $user = User::factory()->create();
    $milk = Ingredient::query()->create(['name' => 'Mleko', 'category' => Ingredient::CATEGORY_DAIRY]);
    $bread = Ingredient::query()->create(['name' => 'Chleb', 'category' => Ingredient::CATEGORY_BREAD]);

    shoppingListItem(['name' => 'Mleko', 'ingredient_id' => $milk->id, 'amount' => 1, 'unit' => 'l', 'week_day' => 'monday']);
    shoppingListItem(['name' => 'Chleb', 'ingredient_id' => $bread->id, 'amount' => 1, 'unit' => 'pcs', 'week_day' => 'monday']);

    $this->actingAs($user);

    $monday = Livewire::test('pages::shopping-list.index')->instance()->activeByDay->firstWhere('day', 'monday');

    expect($monday['categories'])->toHaveCount(2)
        ->and($monday['categories']->pluck('category')->all())->toBe([Ingredient::CATEGORY_DAIRY, Ingredient::CATEGORY_BREAD]);
});

test('category sections follow the canonical Ingredient::CATEGORIES order, not insertion order', function () {
    $user = User::factory()->create();
    $bread = Ingredient::query()->create(['name' => 'Chleb', 'category' => Ingredient::CATEGORY_BREAD]);
    $milk = Ingredient::query()->create(['name' => 'Mleko', 'category' => Ingredient::CATEGORY_DAIRY]);
    $veg = Ingredient::query()->create(['name' => 'Marchew', 'category' => Ingredient::CATEGORY_PRODUCE]);

    // Created in bread, dairy, produce order — canonical order is dairy, bread, produce.
    shoppingListItem(['name' => 'Chleb', 'ingredient_id' => $bread->id, 'amount' => 1, 'unit' => 'pcs', 'week_day' => 'monday']);
    shoppingListItem(['name' => 'Mleko', 'ingredient_id' => $milk->id, 'amount' => 1, 'unit' => 'l', 'week_day' => 'monday']);
    shoppingListItem(['name' => 'Marchew', 'ingredient_id' => $veg->id, 'amount' => 300, 'unit' => 'g', 'week_day' => 'monday']);

    $this->actingAs($user);

    $monday = Livewire::test('pages::shopping-list.index')->instance()->activeByDay->firstWhere('day', 'monday');

    expect($monday['categories']->pluck('category')->all())
        ->toBe([Ingredient::CATEGORY_DAIRY, Ingredient::CATEGORY_BREAD, Ingredient::CATEGORY_PRODUCE]);
});

test('items with no linked ingredient fall into the uncategorized section', function () {
    $user = User::factory()->create();

    shoppingListItem(['name' => 'Coś ręcznie dodanego', 'ingredient_id' => null, 'quantity' => '1 szt', 'week_day' => 'monday']);

    $this->actingAs($user);

    $monday = Livewire::test('pages::shopping-list.index')->instance()->activeByDay->firstWhere('day', 'monday');

    expect($monday['categories'])->toHaveCount(1)
        ->and($monday['categories']->first()['category'])->toBe(Ingredient::CATEGORY_UNCATEGORIZED);
});

test('an empty category section is dropped rather than shown with no items', function () {
    $user = User::factory()->create();
    $milk = Ingredient::query()->create(['name' => 'Mleko', 'category' => Ingredient::CATEGORY_DAIRY]);

    shoppingListItem(['name' => 'Mleko', 'ingredient_id' => $milk->id, 'amount' => 1, 'unit' => 'l', 'week_day' => 'monday']);

    $this->actingAs($user);

    $monday = Livewire::test('pages::shopping-list.index')->instance()->activeByDay->firstWhere('day', 'monday');

    expect($monday['categories'])->toHaveCount(1);
    expect($monday['categories']->pluck('category')->all())->not->toContain(Ingredient::CATEGORY_BREAD);
});

test('merging by ingredient and unit still happens within each category', function () {
    $user = User::factory()->create();
    $salt = Ingredient::query()->create(['name' => 'Sól', 'category' => Ingredient::CATEGORY_PANTRY]);

    shoppingListItem(['name' => 'Sól', 'ingredient_id' => $salt->id, 'amount' => 1, 'unit' => 'g', 'week_day' => 'monday']);
    shoppingListItem(['name' => 'Sól', 'ingredient_id' => $salt->id, 'amount' => 0.5, 'unit' => 'g', 'week_day' => 'monday']);

    $this->actingAs($user);

    $monday = Livewire::test('pages::shopping-list.index')->instance()->activeByDay->firstWhere('day', 'monday');
    $pantry = $monday['categories']->firstWhere('category', Ingredient::CATEGORY_PANTRY);

    expect($pantry['items'])->toHaveCount(1)
        ->and($pantry['items']->first()['displayQuantity'])->toBe('1.5 g');
});

test('the bought section also splits into category subsections', function () {
    $user = User::factory()->create();
    $milk = Ingredient::query()->create(['name' => 'Mleko', 'category' => Ingredient::CATEGORY_DAIRY]);
    $bread = Ingredient::query()->create(['name' => 'Chleb', 'category' => Ingredient::CATEGORY_BREAD]);

    shoppingListItem(['name' => 'Mleko', 'ingredient_id' => $milk->id, 'amount' => 1, 'unit' => 'l', 'week_day' => 'monday', 'is_checked' => true]);
    shoppingListItem(['name' => 'Chleb', 'ingredient_id' => $bread->id, 'amount' => 1, 'unit' => 'pcs', 'week_day' => 'monday', 'is_checked' => true]);

    $this->actingAs($user);

    $monday = Livewire::test('pages::shopping-list.index')->instance()->boughtByDay->firstWhere('day', 'monday');

    expect($monday['categories'])->toHaveCount(2)
        ->and($monday['categories']->pluck('category')->all())->toBe([Ingredient::CATEGORY_DAIRY, Ingredient::CATEGORY_BREAD]);
});

test('the "can buy anytime" section also splits into category subsections', function () {
    $user = User::factory()->create();
    $milk = Ingredient::query()->create([
        'name' => 'Mleko UHT', 'purchase_timing' => Ingredient::PURCHASE_TIMING_ADVANCE, 'category' => Ingredient::CATEGORY_DAIRY,
    ]);
    $flour = Ingredient::query()->create([
        'name' => 'Mąka', 'purchase_timing' => Ingredient::PURCHASE_TIMING_ADVANCE, 'category' => Ingredient::CATEGORY_PANTRY,
    ]);

    shoppingListItem(['name' => 'Mleko UHT', 'ingredient_id' => $milk->id, 'amount' => 1, 'unit' => 'l', 'week_day' => 'monday']);
    shoppingListItem(['name' => 'Mąka', 'ingredient_id' => $flour->id, 'amount' => 500, 'unit' => 'g', 'week_day' => 'tuesday']);

    $this->actingAs($user);

    $advanceItems = Livewire::test('pages::shopping-list.index')->instance()->advanceItems;

    expect($advanceItems)->toHaveCount(2)
        ->and($advanceItems->pluck('category')->all())->toBe([Ingredient::CATEGORY_DAIRY, Ingredient::CATEGORY_PANTRY]);
});

// ---------------------------------------------------------------------
// Items with no week day land in "Can buy anytime this week"
// ---------------------------------------------------------------------

test('an item with no week day assigned appears in the can buy anytime section', function () {
    $user = User::factory()->create();

    shoppingListItem(['name' => 'Ręcznik papierowy', 'ingredient_id' => null, 'quantity' => '1 szt', 'week_day' => null]);

    $this->actingAs($user);

    $advanceItems = Livewire::test('pages::shopping-list.index')->instance()->advanceItems;
    $names = $advanceItems->flatMap(fn (array $section) => $section['items'])->map(fn (array $group) => $group['item']->name);

    expect($names)->toContain('Ręcznik papierowy');
});

test('a no-day item does not appear in any day-specific section', function () {
    $user = User::factory()->create();

    shoppingListItem(['name' => 'Ręcznik papierowy', 'ingredient_id' => null, 'quantity' => '1 szt', 'week_day' => null]);

    $this->actingAs($user);

    $activeByDay = Livewire::test('pages::shopping-list.index')->instance()->activeByDay;

    expect($activeByDay)->toBeEmpty();
});

test('the shopping list page no longer shows a separate "no day assigned" section', function () {
    $user = User::factory()->create();

    shoppingListItem(['name' => 'Ręcznik papierowy', 'ingredient_id' => null, 'quantity' => '1 szt', 'week_day' => null]);

    $this->actingAs($user);

    Livewire::test('pages::shopping-list.index')
        ->assertDontSee('No day assigned')
        ->assertSee('Ręcznik papierowy')
        ->assertSee('Can buy anytime this week');
});
