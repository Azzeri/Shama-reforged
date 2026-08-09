<?php

use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\Recipe;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

// The component always computes its week from the `week` query param via
// startOfWeek()/endOfWeek(), so every test pins it to the same known week
// (Mon 2026-06-22 .. Sun 2026-06-28) to keep day math deterministic.
const WEEK_ANCHOR = '2026-06-22';

function createRecipeWithIngredients(string $name, array $ingredients): Recipe
{
    $recipe = Recipe::query()->create(['name' => $name]);

    foreach ($ingredients as $ingredientName => $pivot) {
        $ingredient = Ingredient::query()->create(['name' => $ingredientName]);

        $recipe->ingredients()->attach($ingredient->id, [
            'quantity' => $pivot['quantity'] ?? null,
            'amount_me' => $pivot['amount_me'] ?? null,
            'amount_wife' => $pivot['amount_wife'] ?? null,
            'unit' => $pivot['unit'] ?? null,
        ]);
    }

    return $recipe;
}

function attachMeal(Recipe $recipe, string $type, string $date): Meal
{
    $meal = Meal::query()->create(['type' => $type, 'date' => Carbon::parse($date)]);
    $meal->recipes()->attach($recipe->id);

    return $meal;
}

test('an ingredient with an amount for one profile produces a shopping list item carrying it', function () {
    $user = User::factory()->create();
    $recipe = createRecipeWithIngredients('Owsianka', [
        'Płatki owsiane' => ['amount_me' => 50, 'unit' => 'g'],
    ]);
    attachMeal($recipe, 'breakfast', '2026-06-22');

    $this->actingAs($user);

    Livewire::test('pages::meal.index')
        ->set('week', WEEK_ANCHOR)
        ->set('selectedDaysForShoppingList', ['2026-06-22'])
        ->call('generateShoppingListFromSelectedDays');

    $item = ShoppingListItem::query()->where('name', 'Płatki owsiane')->sole();

    expect($item->amount)->toEqual(50.0)
        ->and($item->unit)->toBe('g')
        ->and($item->quantity)->toBeNull()
        ->and($item->is_checked)->toBeFalse()
        ->and($item->week_day)->toBe('monday')
        ->and($item->recipe_id)->toBe($recipe->id)
        ->and($item->ingredient_id)->not->toBeNull();
});

test('an ingredient with amounts for both profiles sums them into a single total', function () {
    $user = User::factory()->create();
    $recipe = createRecipeWithIngredients('Owsianka', [
        'Płatki owsiane' => ['amount_me' => 50, 'amount_wife' => 35, 'unit' => 'g'],
    ]);
    attachMeal($recipe, 'breakfast', '2026-06-22');

    $this->actingAs($user);

    Livewire::test('pages::meal.index')
        ->set('week', WEEK_ANCHOR)
        ->set('selectedDaysForShoppingList', ['2026-06-22'])
        ->call('generateShoppingListFromSelectedDays');

    $item = ShoppingListItem::query()->where('name', 'Płatki owsiane')->sole();

    expect($item->amount)->toEqual(85.0)
        ->and($item->unit)->toBe('g');
});

test('an ingredient only one profile eats uses just that profile\'s amount as the total', function () {
    $user = User::factory()->create();
    $recipe = createRecipeWithIngredients('Jajecznica', [
        'Oliwa z oliwek' => ['amount_me' => 5, 'amount_wife' => null, 'unit' => 'g'],
    ]);
    attachMeal($recipe, 'supper', '2026-06-22');

    $this->actingAs($user);

    Livewire::test('pages::meal.index')
        ->set('week', WEEK_ANCHOR)
        ->set('selectedDaysForShoppingList', ['2026-06-22'])
        ->call('generateShoppingListFromSelectedDays');

    expect(ShoppingListItem::query()->where('name', 'Oliwa z oliwek')->sole()->amount)->toEqual(5.0);
});

test('an ingredient with only a free-text quantity still produces a shopping list item', function () {
    $user = User::factory()->create();
    $recipe = createRecipeWithIngredients('Zupa', [
        'Sól' => ['quantity' => 'szczypta'],
    ]);
    attachMeal($recipe, 'dinner', '2026-06-22');

    $this->actingAs($user);

    Livewire::test('pages::meal.index')
        ->set('week', WEEK_ANCHOR)
        ->set('selectedDaysForShoppingList', ['2026-06-22'])
        ->call('generateShoppingListFromSelectedDays');

    $item = ShoppingListItem::query()->where('name', 'Sól')->sole();

    expect($item->quantity)->toBe('szczypta')
        ->and($item->amount)->toBeNull()
        ->and($item->unit)->toBeNull();
});

test('an ingredient with neither quantity nor any profile amount is skipped entirely', function () {
    $user = User::factory()->create();
    $recipe = createRecipeWithIngredients('Naleśniki', [
        'Cynamon' => [],
        'Mąka' => ['amount_me' => 200, 'unit' => 'g'],
    ]);
    attachMeal($recipe, 'breakfast', '2026-06-22');

    $this->actingAs($user);

    Livewire::test('pages::meal.index')
        ->set('week', WEEK_ANCHOR)
        ->set('selectedDaysForShoppingList', ['2026-06-22'])
        ->call('generateShoppingListFromSelectedDays');

    expect(ShoppingListItem::query()->where('name', 'Cynamon')->exists())->toBeFalse()
        ->and(ShoppingListItem::query()->where('name', 'Mąka')->exists())->toBeTrue()
        ->and(ShoppingListItem::query()->count())->toBe(1);
});

test('only the selected days are included, not every day with meals in the week', function () {
    $user = User::factory()->create();

    $monday = createRecipeWithIngredients('Owsianka', ['Płatki owsiane' => ['amount_me' => 50, 'unit' => 'g']]);
    attachMeal($monday, 'breakfast', '2026-06-22');

    $wednesday = createRecipeWithIngredients('Kurczak', ['Kurczak' => ['amount_me' => 200, 'unit' => 'g']]);
    attachMeal($wednesday, 'dinner', '2026-06-24');

    $this->actingAs($user);

    Livewire::test('pages::meal.index')
        ->set('week', WEEK_ANCHOR)
        ->set('selectedDaysForShoppingList', ['2026-06-22'])
        ->call('generateShoppingListFromSelectedDays');

    expect(ShoppingListItem::query()->where('name', 'Płatki owsiane')->exists())->toBeTrue()
        ->and(ShoppingListItem::query()->where('name', 'Kurczak')->exists())->toBeFalse();
});

test('selecting several days includes ingredients from all of them', function () {
    $user = User::factory()->create();

    $monday = createRecipeWithIngredients('Owsianka', ['Płatki owsiane' => ['amount_me' => 50, 'unit' => 'g']]);
    attachMeal($monday, 'breakfast', '2026-06-22');

    $wednesday = createRecipeWithIngredients('Kurczak', ['Kurczak' => ['amount_me' => 200, 'unit' => 'g']]);
    attachMeal($wednesday, 'dinner', '2026-06-24');

    $this->actingAs($user);

    Livewire::test('pages::meal.index')
        ->set('week', WEEK_ANCHOR)
        ->set('selectedDaysForShoppingList', ['2026-06-22', '2026-06-24'])
        ->call('generateShoppingListFromSelectedDays');

    expect(ShoppingListItem::query()->where('name', 'Płatki owsiane')->exists())->toBeTrue()
        ->and(ShoppingListItem::query()->where('name', 'Kurczak')->exists())->toBeTrue()
        ->and(ShoppingListItem::query()->count())->toBe(2);
});

test('generating from the full week includes every day, not just visible/selected ones', function () {
    $user = User::factory()->create();

    $monday = createRecipeWithIngredients('Owsianka', ['Płatki owsiane' => ['amount_me' => 50, 'unit' => 'g']]);
    attachMeal($monday, 'breakfast', '2026-06-22');

    $sunday = createRecipeWithIngredients('Rosół', ['Kura' => ['amount_me' => 500, 'unit' => 'g']]);
    attachMeal($sunday, 'dinner', '2026-06-28');

    $this->actingAs($user);

    Livewire::test('pages::meal.index')
        ->set('week', WEEK_ANCHOR)
        ->call('generateShoppingListFromFullWeek');

    expect(ShoppingListItem::query()->where('name', 'Płatki owsiane')->exists())->toBeTrue()
        ->and(ShoppingListItem::query()->where('name', 'Kura')->exists())->toBeTrue()
        ->and(ShoppingListItem::query()->count())->toBe(2);
});

test('a meal date outside the currently viewed week is ignored even if selected', function () {
    $user = User::factory()->create();

    // The previous week — outside 2026-06-22..2026-06-28.
    $recipe = createRecipeWithIngredients('Stara zupa', ['Marchew' => ['amount_me' => 100, 'unit' => 'g']]);
    attachMeal($recipe, 'lunch', '2026-06-15');

    $this->actingAs($user);

    Livewire::test('pages::meal.index')
        ->set('week', WEEK_ANCHOR)
        ->set('selectedDaysForShoppingList', ['2026-06-15'])
        ->call('generateShoppingListFromSelectedDays');

    expect(ShoppingListItem::query()->count())->toBe(0);
});

test('week_day is recorded as the English lowercase day name matching the meal date', function () {
    $user = User::factory()->create();

    $recipe = createRecipeWithIngredients('Kolacja', ['Ser' => ['amount_me' => 100, 'unit' => 'g']]);
    attachMeal($recipe, 'supper', '2026-06-24'); // Wednesday

    $this->actingAs($user);

    Livewire::test('pages::meal.index')
        ->set('week', WEEK_ANCHOR)
        ->set('selectedDaysForShoppingList', ['2026-06-24'])
        ->call('generateShoppingListFromSelectedDays');

    expect(ShoppingListItem::query()->where('name', 'Ser')->sole()->week_day)->toBe('wednesday');
});

test('the same ingredient used in two different recipes produces two separate, independently summed line items', function () {
    $user = User::factory()->create();

    $breakfast = createRecipeWithIngredients('Owsianka', [
        'Mleko' => ['amount_me' => 200, 'amount_wife' => 50, 'unit' => 'g'],
    ]);
    attachMeal($breakfast, 'breakfast', '2026-06-22');

    $dessert = createRecipeWithIngredients('Naleśniki', [
        'Mleko' => ['amount_me' => 300, 'amount_wife' => 100, 'unit' => 'g'],
    ]);
    attachMeal($dessert, 'dessert', '2026-06-22');

    $this->actingAs($user);

    Livewire::test('pages::meal.index')
        ->set('week', WEEK_ANCHOR)
        ->set('selectedDaysForShoppingList', ['2026-06-22'])
        ->call('generateShoppingListFromSelectedDays');

    $items = ShoppingListItem::query()->where('name', 'Mleko')->get();

    expect($items)->toHaveCount(2)
        ->and($items->pluck('amount')->sort()->values()->all())->toEqual([250.0, 400.0])
        ->and($items->pluck('recipe_id')->unique())->toHaveCount(2);
});

test('multiple meals on the same day all contribute their ingredients', function () {
    $user = User::factory()->create();

    $breakfast = createRecipeWithIngredients('Owsianka', ['Płatki owsiane' => ['amount_me' => 50, 'unit' => 'g']]);
    attachMeal($breakfast, 'breakfast', '2026-06-22');

    $dinner = createRecipeWithIngredients('Kurczak z ryżem', ['Ryż' => ['amount_me' => 100, 'unit' => 'g']]);
    attachMeal($dinner, 'dinner', '2026-06-22');

    $this->actingAs($user);

    Livewire::test('pages::meal.index')
        ->set('week', WEEK_ANCHOR)
        ->set('selectedDaysForShoppingList', ['2026-06-22'])
        ->call('generateShoppingListFromSelectedDays');

    expect(ShoppingListItem::query()->count())->toBe(2);
});

test('generating reuses the single shopping list record instead of creating a new one', function () {
    $user = User::factory()->create();

    $recipe = createRecipeWithIngredients('Owsianka', ['Płatki owsiane' => ['amount_me' => 50, 'unit' => 'g']]);
    attachMeal($recipe, 'breakfast', '2026-06-22');

    $this->actingAs($user);

    $component = Livewire::test('pages::meal.index')->set('week', WEEK_ANCHOR);

    $component->set('selectedDaysForShoppingList', ['2026-06-22'])->call('generateShoppingListFromSelectedDays');
    $component->set('selectedDaysForShoppingList', ['2026-06-22'])->call('generateShoppingListFromSelectedDays');

    expect(ShoppingList::query()->count())->toBe(1)
        ->and(ShoppingListItem::query()->count())->toBe(2); // no dedupe: each call re-adds its items
});

test('selecting no days generates nothing and does not redirect', function () {
    $user = User::factory()->create();

    $recipe = createRecipeWithIngredients('Owsianka', ['Płatki owsiane' => ['amount_me' => 50, 'unit' => 'g']]);
    attachMeal($recipe, 'breakfast', '2026-06-22');

    $this->actingAs($user);

    Livewire::test('pages::meal.index')
        ->set('week', WEEK_ANCHOR)
        ->set('selectedDaysForShoppingList', [])
        ->call('generateShoppingListFromSelectedDays')
        ->assertNoRedirect();

    expect(ShoppingListItem::query()->count())->toBe(0)
        ->and(ShoppingList::query()->count())->toBe(0);
});

test('a selected day with no meals adds nothing but still redirects to the shopping list', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::meal.index')
        ->set('week', WEEK_ANCHOR)
        ->set('selectedDaysForShoppingList', ['2026-06-25'])
        ->call('generateShoppingListFromSelectedDays')
        ->assertRedirect(route('shopping-list.index'));

    expect(ShoppingListItem::query()->count())->toBe(0);
});

test('after generating, the selected days are cleared and the user is redirected to the shopping list', function () {
    $user = User::factory()->create();

    $recipe = createRecipeWithIngredients('Owsianka', ['Płatki owsiane' => ['amount_me' => 50, 'unit' => 'g']]);
    attachMeal($recipe, 'breakfast', '2026-06-22');

    $this->actingAs($user);

    Livewire::test('pages::meal.index')
        ->set('week', WEEK_ANCHOR)
        ->set('selectedDaysForShoppingList', ['2026-06-22'])
        ->call('generateShoppingListFromSelectedDays')
        ->assertSet('selectedDaysForShoppingList', [])
        ->assertRedirect(route('shopping-list.index'));
});
