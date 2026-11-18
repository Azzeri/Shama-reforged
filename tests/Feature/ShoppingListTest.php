<?php

use App\Actions\ShoppingList\GenerateShoppingList;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\Recipe;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Models\User;
use App\Support\ShoppingList\Quantity;
use App\Support\ShoppingList\ShoppingListBuilder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

// 2026-11-18 is a Wednesday.
const SL_WED = '2026-11-18';
const SL_THU = '2026-11-19';
const SL_FRI = '2026-11-20';

beforeEach(function () {
    app()->setLocale('pl');
    Carbon::setTestNow(Carbon::parse(SL_WED.' 10:00'));
});

afterEach(fn () => Carbon::setTestNow());

function slIngredient(string $name, string $timing = Ingredient::PURCHASE_TIMING_FRESH, array $extra = []): Ingredient
{
    return Ingredient::query()->create(['name' => $name, 'purchase_timing' => $timing, ...$extra]);
}

function slRecipe(string $name, array $ingredients): Recipe
{
    $recipe = Recipe::query()->create(['name' => $name]);

    foreach ($ingredients as [$ingredient, $amount, $unit]) {
        $recipe->ingredients()->attach($ingredient->id, [
            'amount_me' => $amount,
            'amount_wife' => null,
            'unit' => $unit,
        ]);
    }

    return $recipe;
}

function slMeal(string $date, string $type, Recipe ...$recipes): Meal
{
    $meal = Meal::query()->create(['date' => "{$date} 12:00:00", 'type' => $type]);
    $meal->recipes()->sync(collect($recipes)->pluck('id'));

    return $meal;
}

function slItem(array $attributes): ShoppingListItem
{
    return ShoppingList::main()->items()->create([
        'name' => 'Item',
        'is_checked' => false,
        ...$attributes,
    ]);
}

function slBuild(): array
{
    $items = ShoppingList::main()->items()->with(['recipe', 'ingredient'])->get();

    return app(ShoppingListBuilder::class)->build($items, now());
}

/** All rows of the list as name => quantity, flattened across categories. */
function slRows(iterable $categories): array
{
    return collect($categories)
        ->flatMap(fn (array $category) => $category['groups'])
        ->mapWithKeys(fn (array $group) => [$group['name'] => $group['quantity']])
        ->all();
}

function slDay(array $list, string $date): ?array
{
    return collect($list['days'])->first(fn (array $day) => $day['date']->toDateString() === $date);
}

// --- Quantity -------------------------------------------------------------

test('quantities are parsed into base units and formatted the Polish way', function () {
    expect(Quantity::parse('1,5 kg'))->toBe([1500.0, 'g'])
        ->and(Quantity::parse('2 szt'))->toBe([2.0, 'pcs'])
        ->and(Quantity::parse('300g'))->toBe([300.0, 'g'])
        ->and(Quantity::parse('0.5 l'))->toBe([500.0, 'ml'])
        ->and(Quantity::parse('szczypta'))->toBeNull()
        ->and(Quantity::format(1250, 'g'))->toBe('1,25 kg')
        ->and(Quantity::format(2.5, 'pcs'))->toBe('2,5 szt')
        ->and(Quantity::format(1500, 'ml'))->toBe('1,5 l')
        ->and(Quantity::format(12.4, 'g'))->toBe('12 g')
        ->and(Quantity::format(2.5, 'g'))->toBe('2,5 g')
        ->and(Quantity::format(0.3, 'g'))->toBe('0,3 g')
        ->and(Quantity::format(0.04, 'g'))->toBe('0,1 g');
});

// --- Generation -----------------------------------------------------------

test('generating creates one row per recipe ingredient with the meal date and normalised units', function () {
    $flour = slIngredient('Mąka');
    $milk = slIngredient('Mleko');
    $recipe = slRecipe('Naleśniki', [[$flour, 0.5, 'kg'], [$milk, 1, 'l']]);
    slMeal(SL_THU, 'breakfast', $recipe);

    $result = app(GenerateShoppingList::class)([SL_THU]);

    expect($result['added'])->toBe(2);

    $rows = ShoppingListItem::query()->orderBy('name')->get();
    expect($rows->pluck('needed_on')->map->toDateString()->unique()->all())->toBe([SL_THU])
        ->and($rows->firstWhere('name', 'Mąka')->only(['amount', 'unit']))->toBe(['amount' => 500.0, 'unit' => 'g'])
        ->and($rows->firstWhere('name', 'Mleko')->only(['amount', 'unit']))->toBe(['amount' => 1000.0, 'unit' => 'ml']);
});

test('generating the same days again does not double the list and follows plan changes', function () {
    $egg = slIngredient('Jajko');
    $bread = slIngredient('Chleb');
    $omelette = slRecipe('Omlet', [[$egg, 2, 'pcs']]);
    $toast = slRecipe('Tosty', [[$bread, 100, 'g']]);
    $meal = slMeal(SL_THU, 'breakfast', $omelette, $toast);

    app(GenerateShoppingList::class)([SL_THU]);
    app(GenerateShoppingList::class)([SL_THU]);

    expect(ShoppingListItem::query()->count())->toBe(2);

    $meal->recipes()->sync([$omelette->id]);
    app(GenerateShoppingList::class)([SL_THU]);

    expect(ShoppingListItem::query()->pluck('name')->all())->toBe(['Jajko']);
});

test('regenerating keeps bought rows and does not add them again', function () {
    $egg = slIngredient('Jajko');
    slMeal(SL_THU, 'breakfast', slRecipe('Omlet', [[$egg, 2, 'pcs']]));

    app(GenerateShoppingList::class)([SL_THU]);
    ShoppingListItem::query()->update(['is_checked' => true, 'checked_at' => now()]);

    $result = app(GenerateShoppingList::class)([SL_THU]);

    expect($result)->toBe(['added' => 0, 'kept' => 1])
        ->and(ShoppingListItem::query()->count())->toBe(1);
});

test('generating leaves manual items and other days alone', function () {
    $egg = slIngredient('Jajko');
    slMeal(SL_THU, 'breakfast', slRecipe('Omlet', [[$egg, 2, 'pcs']]));
    slMeal(SL_FRI, 'breakfast', slRecipe('Jajecznica', [[$egg, 3, 'pcs']]));
    slItem(['name' => 'Papier', 'needed_on' => SL_THU]);

    app(GenerateShoppingList::class)([SL_THU, SL_FRI]);
    app(GenerateShoppingList::class)([SL_THU]);

    expect(ShoppingListItem::query()->count())->toBe(3)
        ->and(ShoppingListItem::query()->where('name', 'Papier')->exists())->toBeTrue();
});

test('ingredients without an amount still land on the list', function () {
    $salt = slIngredient('Sól');
    $recipe = Recipe::query()->create(['name' => 'Zupa']);
    $recipe->ingredients()->attach($salt->id, ['quantity' => 'do smaku']);
    slMeal(SL_THU, 'dinner', $recipe);

    app(GenerateShoppingList::class)([SL_THU]);

    expect(ShoppingListItem::query()->first()->displayQuantity())->toBe('do smaku');
});

// --- Building the list: summing & merging ----------------------------------

test('"can buy for the whole week" ingredients are summed across all days into the weekly section', function () {
    $oats = slIngredient('Płatki owsiane', Ingredient::PURCHASE_TIMING_ADVANCE, ['category' => Ingredient::CATEGORY_GRAINS]);
    slItem(['name' => 'Płatki owsiane', 'ingredient_id' => $oats->id, 'amount' => 60, 'unit' => 'g', 'needed_on' => SL_WED, 'recipe_id' => Recipe::query()->create(['name' => 'Owsianka'])->id]);
    slItem(['name' => 'Płatki owsiane', 'ingredient_id' => $oats->id, 'amount' => 105, 'unit' => 'g', 'needed_on' => SL_THU, 'recipe_id' => Recipe::query()->create(['name' => 'Ciastka'])->id]);

    $list = slBuild();
    $group = $list['weekly']['categories'][0]['groups'][0];

    expect($list['weekly']['count'])->toBe(1)
        ->and($list['days'])->toBeEmpty()
        ->and($list['weekly']['categories'][0]['label'])->toBe('Mąka, kasze i zboża')
        ->and($group['quantity'])->toBe('165 g')
        ->and($group['subtitle'])->toBe('2 przepisy · śr, czw')
        ->and($group['ids'])->toHaveCount(2);
});

test('the same fresh ingredient on two consecutive days is merged onto the first day', function () {
    $banana = slIngredient('Banan', extra: ['category' => Ingredient::CATEGORY_PRODUCE]);
    slItem(['name' => 'Banan', 'ingredient_id' => $banana->id, 'amount' => 120, 'unit' => 'g', 'needed_on' => SL_WED]);
    slItem(['name' => 'Banan', 'ingredient_id' => $banana->id, 'amount' => 180, 'unit' => 'g', 'needed_on' => SL_THU]);

    $list = slBuild();

    expect($list['days'])->toHaveCount(1)
        ->and(slRows(slDay($list, SL_WED)['categories']))->toBe(['Banan' => '300 g'])
        ->and(slDay($list, SL_THU))->toBeNull();
});

test('fresh ingredients further apart than the merge window stay on their own days', function () {
    $banana = slIngredient('Banan');
    slItem(['name' => 'Banan', 'ingredient_id' => $banana->id, 'amount' => 120, 'unit' => 'g', 'needed_on' => SL_WED]);
    slItem(['name' => 'Banan', 'ingredient_id' => $banana->id, 'amount' => 180, 'unit' => 'g', 'needed_on' => SL_FRI]);

    $list = slBuild();

    expect(collect($list['days'])->map(fn ($day) => $day['date']->toDateString())->all())->toBe([SL_WED, SL_FRI])
        ->and(slRows(slDay($list, SL_WED)['categories']))->toBe(['Banan' => '120 g'])
        ->and(slRows(slDay($list, SL_FRI)['categories']))->toBe(['Banan' => '180 g']);
});

test('merging chains only within the window started by the first day', function () {
    $banana = slIngredient('Banan');

    foreach ([SL_WED, SL_THU, SL_FRI] as $date) {
        slItem(['name' => 'Banan', 'ingredient_id' => $banana->id, 'amount' => 100, 'unit' => 'g', 'needed_on' => $date]);
    }

    $list = slBuild();

    expect(slRows(slDay($list, SL_WED)['categories']))->toBe(['Banan' => '200 g'])
        ->and(slRows(slDay($list, SL_FRI)['categories']))->toBe(['Banan' => '100 g']);
});

test('days are listed chronologically across a week boundary', function () {
    $milk = slIngredient('Mleko');
    slItem(['name' => 'Mleko', 'ingredient_id' => $milk->id, 'amount' => 1, 'unit' => 'l', 'needed_on' => '2026-11-24']); // next Tuesday
    slItem(['name' => 'Mleko', 'ingredient_id' => $milk->id, 'amount' => 1, 'unit' => 'l', 'needed_on' => '2026-11-21']); // Saturday

    expect(collect(slBuild()['days'])->map(fn ($day) => $day['date']->toDateString())->all())
        ->toBe(['2026-11-21', '2026-11-24']);
});

test('kg and g of the same ingredient are summed', function () {
    $flour = slIngredient('Mąka');
    slItem(['name' => 'Mąka', 'ingredient_id' => $flour->id, 'amount' => 1, 'unit' => 'kg', 'needed_on' => SL_WED]);
    slItem(['name' => 'Mąka', 'ingredient_id' => $flour->id, 'amount' => 250, 'unit' => 'g', 'needed_on' => SL_WED]);

    expect(slRows(slDay(slBuild(), SL_WED)['categories']))->toBe(['Mąka' => '1,25 kg']);
});

test('pieces and grams are summed in grams when the ingredient has a unit weight', function () {
    $egg = slIngredient('Jajko', extra: ['unit_weights' => ['pcs' => 50]]);
    slItem(['name' => 'Jajko', 'ingredient_id' => $egg->id, 'amount' => 2, 'unit' => 'pcs', 'needed_on' => SL_WED]);
    slItem(['name' => 'Jajko', 'ingredient_id' => $egg->id, 'amount' => 30, 'unit' => 'g', 'needed_on' => SL_WED]);

    expect(slRows(slDay(slBuild(), SL_WED)['categories']))->toBe(['Jajko' => '130 g']);
});

test('units that cannot be converted are listed side by side, with free text appended', function () {
    $egg = slIngredient('Jajko');
    slItem(['name' => 'Jajko', 'ingredient_id' => $egg->id, 'amount' => 2, 'unit' => 'pcs', 'needed_on' => SL_WED]);
    slItem(['name' => 'Jajko', 'ingredient_id' => $egg->id, 'amount' => 30, 'unit' => 'g', 'needed_on' => SL_WED]);
    slItem(['name' => 'Jajko', 'ingredient_id' => $egg->id, 'quantity' => 'na zapas', 'needed_on' => SL_WED]);

    expect(slRows(slDay(slBuild(), SL_WED)['categories']))->toBe(['Jajko' => '30 g + 2 szt + na zapas']);
});

test('a single unit with a known weight shows the approximate grams', function () {
    $egg = slIngredient('Jajko', extra: ['unit_weights' => ['pcs' => 50]]);
    slItem(['name' => 'Jajko', 'ingredient_id' => $egg->id, 'amount' => 3, 'unit' => 'pcs', 'needed_on' => SL_WED]);

    expect(slRows(slDay(slBuild(), SL_WED)['categories']))->toBe(['Jajko' => '3 szt ≈ 150 g']);
});

test('manual items without an ingredient are never merged, and without a date go to the weekly section', function () {
    slItem(['name' => 'Papier', 'amount' => 1, 'unit' => 'pack']);
    slItem(['name' => 'Papier', 'amount' => 1, 'unit' => 'pack']);
    slItem(['name' => 'Kwiaty', 'needed_on' => SL_THU]);

    $list = slBuild();

    expect($list['weekly']['count'])->toBe(2)
        ->and(slDay($list, SL_THU)['count'])->toBe(1);
});

test('a day reports how many of its products are covered by the weekly section', function () {
    $oats = slIngredient('Płatki', Ingredient::PURCHASE_TIMING_ADVANCE);
    $salt = slIngredient('Sól', Ingredient::PURCHASE_TIMING_ADVANCE);
    $banana = slIngredient('Banan');
    slItem(['name' => 'Płatki', 'ingredient_id' => $oats->id, 'amount' => 50, 'unit' => 'g', 'needed_on' => SL_THU]);
    slItem(['name' => 'Sól', 'ingredient_id' => $salt->id, 'amount' => 2, 'unit' => 'g', 'needed_on' => SL_THU]);
    slItem(['name' => 'Banan', 'ingredient_id' => $banana->id, 'amount' => 100, 'unit' => 'g', 'needed_on' => SL_THU]);

    expect(slDay(slBuild(), SL_THU)['coveredCount'])->toBe(2);
});

test('bought rows are summed in their own section, only for the last 7 days, and drive the progress', function () {
    $banana = slIngredient('Banan');
    slItem(['name' => 'Banan', 'ingredient_id' => $banana->id, 'amount' => 100, 'unit' => 'g', 'needed_on' => SL_WED, 'is_checked' => true, 'checked_at' => now()]);
    slItem(['name' => 'Banan', 'ingredient_id' => $banana->id, 'amount' => 50, 'unit' => 'g', 'needed_on' => SL_THU, 'is_checked' => true, 'checked_at' => now()]);
    slItem(['name' => 'Stare', 'is_checked' => true, 'checked_at' => now()->subDays(10)]);
    slItem(['name' => 'Chleb', 'needed_on' => SL_THU]);

    $list = slBuild();

    expect($list['bought']['count'])->toBe(1)
        ->and($list['bought']['groups'][0]['quantity'])->toBe('150 g')
        ->and($list['progress'])->toBe(['bought' => 1, 'total' => 2]);
});

// --- Page actions ---------------------------------------------------------

test('ticking rows records when they were bought and unticking clears it', function () {
    $item = slItem(['name' => 'Chleb', 'needed_on' => SL_THU]);
    $page = Livewire::actingAs(User::factory()->create())->test('pages::shopping-list.index');

    $page->call('toggle', [$item->id], true);
    expect($item->fresh()->is_checked)->toBeTrue()
        ->and($item->fresh()->checked_at)->not->toBeNull();

    // A second person ticking the same (already ticked) row doesn't untick it.
    $page->call('toggle', [$item->id], true);
    expect($item->fresh()->is_checked)->toBeTrue();

    $page->call('toggle', [$item->id], false);
    expect($item->fresh()->is_checked)->toBeFalse()
        ->and($item->fresh()->checked_at)->toBeNull();
});

test('moving rows a day earlier shifts their date', function () {
    $item = slItem(['name' => 'Chleb', 'needed_on' => SL_THU]);

    Livewire::actingAs(User::factory()->create())
        ->test('pages::shopping-list.index')
        ->call('moveEarlier', [$item->id]);

    expect($item->fresh()->needed_on->toDateString())->toBe(SL_WED);
});

test('editing a quantity parses it so it keeps summing with other rows', function () {
    $flour = slIngredient('Mąka');
    $item = slItem(['name' => 'Mąka', 'ingredient_id' => $flour->id, 'amount' => 100, 'unit' => 'g', 'needed_on' => SL_THU]);

    Livewire::actingAs(User::factory()->create())
        ->test('pages::shopping-list.index')
        ->call('openGroup', [$item->id])
        ->set('editQuantity', '1,5 kg')
        ->call('saveItem')
        ->assertHasNoErrors();

    expect($item->fresh()->only(['amount', 'unit', 'quantity']))->toBe(['amount' => 1500.0, 'unit' => 'g', 'quantity' => null]);
});

test('a manually added item matching an ingredient name is linked to it', function () {
    $milk = slIngredient('Mleko', extra: ['category' => Ingredient::CATEGORY_DAIRY]);

    Livewire::actingAs(User::factory()->create())
        ->test('pages::shopping-list.index')
        ->set('newItemName', 'mleko')
        ->set('newItemQuantity', '2 l')
        ->call('addItem')
        ->assertHasNoErrors();

    $item = ShoppingListItem::query()->sole();
    expect($item->ingredient_id)->toBe($milk->id)
        ->and($item->name)->toBe('Mleko')
        ->and($item->only(['amount', 'unit']))->toBe(['amount' => 2000.0, 'unit' => 'ml'])
        ->and($item->needed_on)->toBeNull();
});

test('opening the page purges long-bought and stale rows', function () {
    slItem(['name' => 'Stare kupione', 'is_checked' => true, 'checked_at' => now()->subDays(20)]);
    slItem(['name' => 'Zaległe', 'needed_on' => now()->subDays(10)->toDateString()]);
    slItem(['name' => 'Aktualne', 'needed_on' => SL_THU]);

    Livewire::actingAs(User::factory()->create())->test('pages::shopping-list.index')->assertOk();

    expect(ShoppingListItem::query()->pluck('name')->all())->toBe(['Aktualne']);
});

test('the shopping list page renders in Polish', function () {
    $banana = slIngredient('Banan', extra: ['category' => Ingredient::CATEGORY_PRODUCE]);
    slItem(['name' => 'Banan', 'ingredient_id' => $banana->id, 'amount' => 120, 'unit' => 'g', 'needed_on' => SL_THU]);

    $this->actingAs(User::factory()->create())
        ->get(route('shopping-list.index'))
        ->assertOk()
        ->assertSee('Zakupy')
        ->assertSee('Czwartek')
        ->assertSee('Owoce i warzywa')
        ->assertSee('120 g');
});
