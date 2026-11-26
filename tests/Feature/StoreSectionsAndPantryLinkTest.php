<?php

use App\Models\Ingredient;
use App\Models\PantryGroup;
use App\Models\PantryItem;
use App\Models\Recipe;
use App\Models\Setting;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Models\User;
use App\Support\ShoppingList\ShoppingListBuilder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    app()->setLocale('pl');
    Carbon::setTestNow(Carbon::parse('2026-11-18 10:00'));
    $this->actingAs(User::factory()->create());
});

afterEach(fn () => Carbon::setTestNow());

function sspItem(Ingredient $ingredient, float $amount, string $unit = 'g', array $extra = []): ShoppingListItem
{
    return ShoppingList::main()->items()->create([
        'name' => $ingredient->name, 'ingredient_id' => $ingredient->id, 'amount' => $amount, 'unit' => $unit,
        'needed_on' => '2026-11-18', 'is_checked' => false, ...$extra,
    ]);
}

function sspList(): array
{
    $items = ShoppingList::main()->items()->with(['recipe', 'ingredient'])->get();
    $pantry = PantryItem::query()->where(fn ($q) => $q->toBuy()->orWhereNotNull('ingredient_id'))->get();

    return app(ShoppingListBuilder::class)->build($items, now(), $pantry);
}

// --- Store section order ---------------------------------------------------

test('the section order is saved for the whole household, used by the shopping list and can be restored', function () {
    $milk = Ingredient::query()->create(['name' => 'Mleko', 'category' => Ingredient::CATEGORY_DAIRY]);
    $apple = Ingredient::query()->create(['name' => 'Jabłko', 'category' => Ingredient::CATEGORY_PRODUCE]);
    sspItem($milk, 1, 'l');
    sspItem($apple, 2, 'pcs');

    $default = collect(sspList()['upcoming']['categories'])->pluck('category')->all();
    expect($default)->toBe([Ingredient::CATEGORY_DAIRY, Ingredient::CATEGORY_PRODUCE]);

    $page = Livewire::test('pages::settings.store-sections')
        ->assertSee('Kolejność działów')
        ->call('place', Ingredient::CATEGORY_PRODUCE, 0)
        ->assertDispatched('saved');

    expect(Ingredient::sectionOrder()[0])->toBe(Ingredient::CATEGORY_PRODUCE)
        ->and(collect(sspList()['upcoming']['categories'])->pluck('category')->all())->toBe([Ingredient::CATEGORY_PRODUCE, Ingredient::CATEGORY_DAIRY]);

    // Same order for another user of the household.
    $this->actingAs(User::factory()->create());
    expect(Livewire::test('pages::settings.store-sections')->get('order')[0])->toBe(Ingredient::CATEGORY_PRODUCE);

    $page->call('move', Ingredient::CATEGORY_PRODUCE, 1);
    expect(Ingredient::sectionOrder()[1])->toBe(Ingredient::CATEGORY_PRODUCE);

    $page->call('restoreDefault');
    expect(Setting::get(Ingredient::SECTION_ORDER_SETTING))->toBeNull()
        ->and(Ingredient::sectionOrder())->toBe(Ingredient::defaultSectionOrder());
});

test('household supplies follow the section order on the page', function () {
    $group = PantryGroup::query()->create(['name' => 'Łazienka']);
    PantryItem::query()->create(['name' => 'Papier toaletowy', 'pantry_group_id' => $group->id, 'status' => PantryItem::STATUS_OUT]);
    $apple = Ingredient::query()->create(['name' => 'Jabłko', 'category' => Ingredient::CATEGORY_PRODUCE]);
    sspItem($apple, 2, 'pcs');

    Livewire::test('pages::shopping-list.index')->assertSeeInOrder(['Owoce i warzywa', 'Zapasy domowe']);

    Setting::put(Ingredient::SECTION_ORDER_SETTING, [Ingredient::SECTION_HOUSEHOLD]);

    Livewire::test('pages::shopping-list.index')->assertSeeInOrder(['Zapasy domowe', 'Owoce i warzywa']);
});

// --- Pantry ↔ ingredient ----------------------------------------------------

test('a recipe ingredient linked to a household product in stock goes to "Probably at home"', function () {
    $flour = Ingredient::query()->create(['name' => 'Mąka pszenna', 'category' => Ingredient::CATEGORY_GRAINS]);
    $group = PantryGroup::query()->create(['name' => 'Kuchnia']);
    $product = PantryItem::query()->create(['name' => 'Mąka', 'pantry_group_id' => $group->id, 'ingredient_id' => $flour->id]);
    $row = sspItem($flour, 320);

    $list = sspList();

    expect($list['upcoming']['count'])->toBe(0)
        ->and($list['atHome'])->toHaveCount(1)
        ->and($list['atHome'][0]['quantity'])->toBe('320 g')
        ->and($list['progress']['total'])->toBe(0);

    $page = Livewire::test('pages::shopping-list.index')
        ->assertSee('Pewnie masz w domu')
        ->assertSee('Kupię jednak')
        ->assertSee('Skończyło się');

    // "Kupię jednak": back to its aisle on this list only.
    $page->call('buyAnyway', [$row->id]);
    $list = sspList();
    expect($list['atHome'])->toBeEmpty()
        ->and(collect($list['upcoming']['categories'])->pluck('category')->all())->toBe([Ingredient::CATEGORY_GRAINS])
        ->and($product->fresh()->status)->toBe(PantryItem::STATUS_OK);
});

test('"Ran out" marks the product as out and shows one row with the recipe amount', function () {
    $milk = Ingredient::query()->create(['name' => 'Mleko 2%', 'category' => Ingredient::CATEGORY_DAIRY]);
    $group = PantryGroup::query()->create(['name' => 'Kuchnia']);
    $product = PantryItem::query()->create(['name' => 'Mleko 2%', 'pantry_group_id' => $group->id, 'ingredient_id' => $milk->id]);
    $recipe = Recipe::query()->create(['name' => 'Owsianka']);
    sspItem($milk, 200, 'ml', ['recipe_id' => $recipe->id]);

    Livewire::test('pages::shopping-list.index')->call('ranOut', $product->id);

    $list = sspList();

    expect($product->fresh()->status)->toBe(PantryItem::STATUS_OUT)
        ->and($list['atHome'])->toBeEmpty()
        ->and($list['upcoming']['count'])->toBe(0)
        ->and($list['household'])->toHaveCount(1)
        ->and($list['household'][0]['quantity'])->toBe('200 ml')
        ->and($list['household'][0]['subtitle'])->toBe('brak w domu · Owsianka');
});

test('a low product also needed by a recipe is one row; ticking it restocks and buys the recipe amount', function () {
    $milk = Ingredient::query()->create(['name' => 'Mleko 2%', 'category' => Ingredient::CATEGORY_DAIRY]);
    $group = PantryGroup::query()->create(['name' => 'Kuchnia']);
    $product = PantryItem::query()->create(['name' => 'Mleko 2%', 'pantry_group_id' => $group->id, 'ingredient_id' => $milk->id, 'status' => PantryItem::STATUS_LOW]);
    $row = sspItem($milk, 200, 'ml');

    $list = sspList();
    expect($list['household'])->toHaveCount(1)
        ->and($list['upcoming']['count'])->toBe(0)
        ->and(collect($list['upcoming']['categories']))->toBeEmpty();

    Livewire::test('pages::shopping-list.index')->call('restock', $product->id, [$row->id]);

    expect($product->fresh()->status)->toBe(PantryItem::STATUS_OK)
        ->and($row->fresh()->is_checked)->toBeTrue();
});

test('a product is linked to an ingredient in its sheet, with a proposal when the names match', function () {
    $flour = Ingredient::query()->create(['name' => 'Mąka pszenna']);
    $group = PantryGroup::query()->create(['name' => 'Kuchnia']);
    $product = PantryItem::query()->create(['name' => 'Mąka pszenna', 'pantry_group_id' => $group->id]);

    Livewire::test('shopping-list.pantry')
        ->call('openEdit', $product->id)
        ->assertSee('Masz składnik Mąka pszenna. Powiązać?')
        ->call('linkIngredient', $flour->id)
        ->assertSee('używany w 0 przepisach')
        ->call('save')
        ->assertHasNoErrors();

    expect($product->fresh()->ingredient_id)->toBe($flour->id);
});
