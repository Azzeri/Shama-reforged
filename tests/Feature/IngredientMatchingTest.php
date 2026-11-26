<?php

use App\Actions\ShoppingList\GenerateShoppingList;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\Recipe;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Models\User;
use App\Support\ShoppingList\ShoppingListBuilder;
use Livewire\Livewire;

beforeEach(function () {
    app()->setLocale('pl');
    $this->actingAs(User::factory()->create());
});

test('names are normalised: case, diacritics and Polish endings', function () {
    Ingredient::query()->create(['name' => 'Pomidor', 'category' => Ingredient::CATEGORY_PRODUCE]);
    Ingredient::query()->create(['name' => 'Pomidory suszone']);

    $form = Livewire::test('recipe.form')
        ->set('recipeIngredients.0.name', 'pomidory')
        ->call('focusRow', 0)
        ->assertSee('Czy chodziło o:')
        ->assertSee('Pomidor')
        ->assertSee('Owoce i warzywa')
        ->assertSee('Dodaj „pomidory” jako nowy składnik');

    $form->call('pickIngredient', 0, Ingredient::query()->where('name', 'Pomidor')->value('id'))
        ->assertSet('recipeIngredients.0.name', 'Pomidor')
        ->assertSet('activeRow', null);
});

test('a name differing only in case or diacritics is the existing ingredient, never a new one', function () {
    $tomato = Ingredient::query()->create(['name' => 'Pomidor']);

    Livewire::test('recipe.form')
        ->set('recipeIngredients.0.name', 'pomidor')
        ->call('focusRow', 0)
        ->assertSee('Taki składnik już istnieje')
        ->assertDontSee('jako nowy składnik')
        ->set('recipeName', 'Sałatka')
        ->call('save');

    expect(Ingredient::query()->count())->toBe(1)
        ->and(Recipe::query()->sole()->ingredients->sole()->is($tomato))->toBeTrue();

    Livewire::test('pages::ingredient.index')
        ->call('openNew')
        ->set('name', 'POMIDÓR')
        ->call('save')
        ->assertHasErrors('name');

    expect(Ingredient::query()->count())->toBe(1);
});

test('a row that will create a new ingredient is marked', function () {
    Ingredient::query()->create(['name' => 'Rukola']);

    Livewire::test('recipe.form')
        ->set('recipeIngredients.0.name', 'Smoczy owoc')
        ->call('closeSuggestions')
        ->assertSee('nowy składnik')
        ->assertSee('Zamień')
        ->set('recipeIngredients.0.name', 'rukola')
        ->assertDontSee('nowy składnik');
});

test('a new ingredient with a similar name suggests the existing one', function () {
    $banana = Ingredient::query()->create(['name' => 'Banan']);

    Livewire::test('pages::ingredient.index')
        ->call('openNew')
        ->set('name', 'Banany')
        ->assertSee('Czy chodziło o: Banan?')
        ->set('similarDismissed', true)
        ->assertDontSee('Czy chodziło o: Banan?')
        ->call('save')
        ->assertHasNoErrors();

    expect(Ingredient::query()->pluck('name')->sort()->values()->all())->toBe(['Banan', 'Banany']);
});

test('possible duplicates are listed only for names that differ by inflection', function () {
    Ingredient::query()->create(['name' => 'Banan']);
    Ingredient::query()->create(['name' => 'Banany']);
    Ingredient::query()->create(['name' => 'Śmietana 12%']);
    Ingredient::query()->create(['name' => 'Śmietana 18%']);

    Livewire::test('pages::ingredient.index')
        ->assertSee('Możliwe duplikaty (1)')
        ->call('setFilter', 'duplicates')
        ->assertSee('Banan')
        ->assertSee('Banany')
        ->assertDontSee('Śmietana 18%');
});

test('merging moves recipes, the plan and the shopping list to the target and sums the amounts', function () {
    $banana = Ingredient::query()->create(['name' => 'Banan', 'category' => Ingredient::CATEGORY_PRODUCE]);
    $bananas = Ingredient::query()->create(['name' => 'Banany', 'category' => Ingredient::CATEGORY_UNCATEGORIZED]);

    $smoothie = Recipe::query()->create(['name' => 'Smoothie', 'servings' => 2]);
    $smoothie->ingredients()->attach($banana->id, ['amount' => 100, 'unit' => 'g']);
    $smoothie->ingredients()->attach($bananas->id, ['amount' => 50, 'unit' => 'g']);
    $porridge = Recipe::query()->create(['name' => 'Owsianka', 'servings' => 2]);
    $porridge->ingredients()->attach($bananas->id, ['amount' => 120, 'unit' => 'g']);

    $meal = Meal::query()->create(['date' => '2026-11-20 12:00:00', 'type' => 'breakfast']);
    $meal->recipes()->attach($porridge->id);
    ShoppingList::main()->items()->create(['name' => 'Banany', 'ingredient_id' => $bananas->id, 'amount' => 120, 'unit' => 'g', 'needed_on' => '2026-11-20', 'is_checked' => false]);
    ShoppingList::main()->items()->create(['name' => 'Banan', 'ingredient_id' => $banana->id, 'amount' => 80, 'unit' => 'g', 'needed_on' => '2026-11-20', 'is_checked' => false]);

    Livewire::test('pages::ingredient.index')
        ->call('openMerge', $bananas->id, $banana->id)
        ->assertSee('2 przepisy i 1 pozycja na liście')
        ->call('merge');

    expect(Ingredient::query()->pluck('name')->all())->toBe(['Banan'])
        ->and($smoothie->fresh()->ingredients->sole()->pivot->amount)->toEqual(150.0)
        ->and($porridge->fresh()->ingredients->sole()->is($banana))->toBeTrue()
        ->and(ShoppingListItem::query()->pluck('ingredient_id')->unique()->all())->toBe([$banana->id]);

    $items = ShoppingList::main()->items()->with(['recipe', 'ingredient'])->get();
    $list = app(ShoppingListBuilder::class)->build($items, now()->setDate(2026, 11, 18));
    $day = collect($list['days'])->first();

    expect($day['count'])->toBe(1)
        ->and($day['categories'][0]['groups'][0]['quantity'])->toBe('200 g');

    app(GenerateShoppingList::class)(['2026-11-20']);
    expect(ShoppingListItem::query()->where('meal_id', $meal->id)->sole()->ingredient_id)->toBe($banana->id);
});

test('merging can keep the settings of the ingredient that disappears', function () {
    $banana = Ingredient::query()->create(['name' => 'Banan', 'category' => Ingredient::CATEGORY_UNCATEGORIZED]);
    $bananas = Ingredient::query()->create(['name' => 'Banany', 'category' => Ingredient::CATEGORY_PRODUCE, 'purchase_timing' => Ingredient::PURCHASE_TIMING_ADVANCE, 'unit_weights' => ['pcs' => 120]]);

    Livewire::test('pages::ingredient.index')
        ->call('openMerge', $bananas->id)
        ->assertSee('podobna nazwa')
        ->call('chooseMergeTarget', $banana->id)
        ->set('mergeKeep', 'source')
        ->call('merge');

    $banana->refresh();

    expect($banana->category)->toBe(Ingredient::CATEGORY_PRODUCE)
        ->and($banana->purchase_timing)->toBe(Ingredient::PURCHASE_TIMING_ADVANCE)
        ->and($banana->unit_weights)->toBe(['pcs' => 120])
        ->and(Ingredient::query()->count())->toBe(1);
});
