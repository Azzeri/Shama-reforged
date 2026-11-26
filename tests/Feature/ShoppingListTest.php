<?php

use App\Actions\ShoppingList\GenerateShoppingList;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\PantryGroup;
use App\Models\PantryItem;
use App\Models\RecipeMealAssignment;
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
        ->and(Quantity::format(2.5, 'pcs'))->toBe('2,5 szt.')
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

test('ingredients marked as skipped on the shopping list are not generated', function () {
    $salt = slIngredient('Sól', extra: ['skip_shopping' => true]);
    $egg = slIngredient('Jajko');
    slMeal(SL_THU, 'breakfast', slRecipe('Jajecznica', [[$salt, 1, 'g'], [$egg, 3, 'pcs']]));

    $result = app(GenerateShoppingList::class)([SL_THU]);

    expect($result['added'])->toBe(1)
        ->and(ShoppingListItem::query()->pluck('name')->all())->toBe(['Jajko']);
});

// --- Building the list: summing & merging ----------------------------------

test('"can buy for the whole week" ingredients are summed across all days into the upcoming section', function () {
    $oats = slIngredient('Płatki owsiane', Ingredient::PURCHASE_TIMING_ADVANCE, ['category' => Ingredient::CATEGORY_GRAINS]);
    slItem(['name' => 'Płatki owsiane', 'ingredient_id' => $oats->id, 'amount' => 60, 'unit' => 'g', 'needed_on' => SL_WED, 'recipe_id' => Recipe::query()->create(['name' => 'Owsianka'])->id]);
    slItem(['name' => 'Płatki owsiane', 'ingredient_id' => $oats->id, 'amount' => 105, 'unit' => 'g', 'needed_on' => SL_THU, 'recipe_id' => Recipe::query()->create(['name' => 'Ciastka'])->id]);

    $list = slBuild();
    $group = $list['upcoming']['categories'][0]['groups'][0];

    expect($list['upcoming']['count'])->toBe(1)
        ->and($list['days'])->toBeEmpty()
        ->and($group['when'])->toBeNull()
        ->and($list['upcoming']['categories'][0]['label'])->toBe('Mąka, kasze i zboża')
        ->and($group['quantity'])->toBe('165 g')
        ->and($group['subtitle'])->toBe('2 przepisy · śr, czw')
        ->and($group['ids'])->toHaveCount(2);
});

test('fresh ingredients for today and tomorrow are merged into the upcoming section and labelled', function () {
    $banana = slIngredient('Banan', extra: ['category' => Ingredient::CATEGORY_PRODUCE]);
    $rocket = slIngredient('Rukola', extra: ['category' => Ingredient::CATEGORY_PRODUCE]);
    slItem(['name' => 'Banan', 'ingredient_id' => $banana->id, 'amount' => 120, 'unit' => 'g', 'needed_on' => SL_WED]);
    slItem(['name' => 'Banan', 'ingredient_id' => $banana->id, 'amount' => 180, 'unit' => 'g', 'needed_on' => SL_THU]);
    slItem(['name' => 'Rukola', 'ingredient_id' => $rocket->id, 'amount' => 30, 'unit' => 'g', 'needed_on' => SL_THU]);

    $list = slBuild();
    $groups = collect($list['upcoming']['categories'])->flatMap(fn ($category) => $category['groups'])->keyBy('name');

    expect($list['days'])->toBeEmpty()
        ->and(slRows($list['upcoming']['categories']))->toBe(['Banan' => '300 g', 'Rukola' => '30 g'])
        ->and($groups['Banan']['when'])->toBe('today')
        ->and($groups['Rukola']['when'])->toBe('tomorrow');
});

test('overdue fresh rows are bought today', function () {
    $banana = slIngredient('Banan');
    slItem(['name' => 'Banan', 'ingredient_id' => $banana->id, 'amount' => 100, 'unit' => 'g', 'needed_on' => '2026-11-16']);

    $group = slBuild()['upcoming']['categories'][0]['groups'][0];

    expect($group['when'])->toBe('today');
});

test('days after tomorrow get their own sections and are not merged with the upcoming section', function () {
    $banana = slIngredient('Banan');
    slItem(['name' => 'Banan', 'ingredient_id' => $banana->id, 'amount' => 120, 'unit' => 'g', 'needed_on' => SL_WED]);
    slItem(['name' => 'Banan', 'ingredient_id' => $banana->id, 'amount' => 180, 'unit' => 'g', 'needed_on' => SL_FRI]);

    $list = slBuild();

    expect(collect($list['days'])->map(fn ($day) => $day['date']->toDateString())->all())->toBe([SL_FRI])
        ->and(slRows($list['upcoming']['categories']))->toBe(['Banan' => '120 g'])
        ->and(slRows(slDay($list, SL_FRI)['categories']))->toBe(['Banan' => '180 g']);
});

test('the same ingredient from several recipes on one day is one row', function () {
    $onion = slIngredient('Cebula');
    $pierogi = Recipe::query()->create(['name' => 'Pierogi']);
    $soup = Recipe::query()->create(['name' => 'Zupa']);
    slItem(['name' => 'Cebula', 'ingredient_id' => $onion->id, 'amount' => 1, 'unit' => 'pcs', 'needed_on' => SL_FRI, 'recipe_id' => $pierogi->id]);
    slItem(['name' => 'Cebula', 'ingredient_id' => $onion->id, 'amount' => 2, 'unit' => 'pcs', 'needed_on' => SL_FRI, 'recipe_id' => $soup->id]);

    $day = slDay(slBuild(), SL_FRI);

    expect(slRows($day['categories']))->toBe(['Cebula' => '3 szt.'])
        ->and($day['count'])->toBe(1)
        ->and($day['recipes']->pluck('name')->sort()->values()->all())->toBe(['Pierogi', 'Zupa']);
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
    slItem(['name' => 'Mąka', 'ingredient_id' => $flour->id, 'amount' => 1, 'unit' => 'kg', 'needed_on' => SL_FRI]);
    slItem(['name' => 'Mąka', 'ingredient_id' => $flour->id, 'amount' => 250, 'unit' => 'g', 'needed_on' => SL_FRI]);

    expect(slRows(slDay(slBuild(), SL_FRI)['categories']))->toBe(['Mąka' => '1,25 kg']);
});

test('pieces and grams are summed in grams when the ingredient has a unit weight', function () {
    $egg = slIngredient('Jajko', extra: ['unit_weights' => ['pcs' => 50]]);
    slItem(['name' => 'Jajko', 'ingredient_id' => $egg->id, 'amount' => 2, 'unit' => 'pcs', 'needed_on' => SL_FRI]);
    slItem(['name' => 'Jajko', 'ingredient_id' => $egg->id, 'amount' => 30, 'unit' => 'g', 'needed_on' => SL_FRI]);

    expect(slRows(slDay(slBuild(), SL_FRI)['categories']))->toBe(['Jajko' => '130 g']);
});

test('units that cannot be converted are listed side by side, with free text appended', function () {
    $egg = slIngredient('Jajko');
    slItem(['name' => 'Jajko', 'ingredient_id' => $egg->id, 'amount' => 2, 'unit' => 'pcs', 'needed_on' => SL_FRI]);
    slItem(['name' => 'Jajko', 'ingredient_id' => $egg->id, 'amount' => 30, 'unit' => 'g', 'needed_on' => SL_FRI]);
    slItem(['name' => 'Jajko', 'ingredient_id' => $egg->id, 'quantity' => 'na zapas', 'needed_on' => SL_FRI]);

    expect(slRows(slDay(slBuild(), SL_FRI)['categories']))->toBe(['Jajko' => '30 g + 2 szt. + na zapas']);
});

test('a single unit with a known weight shows the approximate grams', function () {
    $egg = slIngredient('Jajko', extra: ['unit_weights' => ['pcs' => 50]]);
    slItem(['name' => 'Jajko', 'ingredient_id' => $egg->id, 'amount' => 3, 'unit' => 'pcs', 'needed_on' => SL_FRI]);

    expect(slRows(slDay(slBuild(), SL_FRI)['categories']))->toBe(['Jajko' => '3 szt. ≈ 150 g']);
});

test('manual items without an ingredient are never merged, and without a date go to the upcoming section', function () {
    slItem(['name' => 'Papier', 'amount' => 1, 'unit' => 'pack']);
    slItem(['name' => 'Papier', 'amount' => 1, 'unit' => 'pack']);
    slItem(['name' => 'Kwiaty', 'needed_on' => SL_FRI]);

    $list = slBuild();

    expect($list['upcoming']['count'])->toBe(2)
        ->and(slDay($list, SL_FRI)['count'])->toBe(1);
});

test('rows generated before an ingredient was marked as skipped disappear, manual ones stay', function () {
    $water = slIngredient('Woda', extra: ['skip_shopping' => true]);
    $meal = slMeal(SL_THU, 'dinner');
    slItem(['name' => 'Woda', 'ingredient_id' => $water->id, 'amount' => 500, 'unit' => 'ml', 'needed_on' => SL_THU, 'meal_id' => $meal->id]);
    slItem(['name' => 'Woda', 'ingredient_id' => $water->id, 'amount' => 6, 'unit' => 'l', 'needed_on' => null]);

    $list = slBuild();

    expect($list['days'])->toBeEmpty()
        ->and($list['upcoming']['count'])->toBe(1)
        ->and(slRows($list['upcoming']['categories']))->toBe(['Woda' => '6 l']);
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

    $this->withoutVite()
        ->actingAs(User::factory()->create())
        ->get(route('shopping-list.index'))
        ->assertOk()
        ->assertSee('Zakupy')
        ->assertSee('Najbliższe zakupy')
        ->assertSee('jutro')
        ->assertSee('Owoce i warzywa')
        ->assertSee('120 g');
});

// --- Servings & cooking a recipe whole -------------------------------------

function slServingsRecipe(string $name, int $servings, array $ingredients): Recipe
{
    $recipe = Recipe::query()->create(['name' => $name, 'servings' => $servings]);

    foreach ($ingredients as [$ingredient, $amount, $unit, $extra]) {
        $recipe->ingredients()->attach($ingredient->id, ['amount' => $amount, 'unit' => $unit, ...($extra ?? [])]);
    }

    return $recipe;
}

function slPlan(string $date, string $type, Recipe $recipe, string $mode = 'single', ?int $cookedFromId = null): RecipeMealAssignment
{
    $meal = Meal::query()->firstOrCreate(['date' => "{$date} 12:00:00", 'type' => $type]);

    return RecipeMealAssignment::query()->create([
        'meal_id' => $meal->id,
        'recipe_id' => $recipe->id,
        'mode' => $mode,
        'cooked_from_id' => $cookedFromId,
    ]);
}

test('one planned meal puts two servings worth of the recipe on the list', function () {
    $potatoes = slIngredient('Ziemniaki');
    $pierogi = slServingsRecipe('Pierogi', 6, [[$potatoes, 1200, 'g', null]]);
    slPlan(SL_FRI, 'dinner', $pierogi);

    app(GenerateShoppingList::class)([SL_FRI]);

    expect(ShoppingListItem::query()->sole()->only(['amount', 'unit']))->toBe(['amount' => 400.0, 'unit' => 'g']);
});

test('a recipe for two is bought in full for one meal', function () {
    $oats = slIngredient('Płatki');
    $porridge = slServingsRecipe('Owsianka', 2, [[$oats, 100, 'g', null]]);
    slPlan(SL_FRI, 'breakfast', $porridge);

    app(GenerateShoppingList::class)([SL_FRI]);

    expect(ShoppingListItem::query()->sole()->amount)->toBe(100.0);
});

test('a recipe cooked whole is bought in full on the cooking day and leftover days need no shopping', function () {
    $potatoes = slIngredient('Ziemniaki');
    $pierogi = slServingsRecipe('Pierogi', 6, [[$potatoes, 1200, 'g', null]]);
    $cooking = slPlan(SL_FRI, 'dinner', $pierogi, 'full');
    slPlan('2026-11-21', 'dinner', $pierogi, 'leftover', $cooking->id);
    slPlan('2026-11-22', 'lunch', $pierogi, 'leftover', $cooking->id);

    $result = app(GenerateShoppingList::class)([SL_FRI, '2026-11-21', '2026-11-22']);

    $row = ShoppingListItem::query()->sole();
    expect($result['added'])->toBe(1)
        ->and($row->amount)->toBe(1200.0)
        ->and($row->needed_on->toDateString())->toBe(SL_FRI);
});

test('leftovers are removed together with the cooking day entry', function () {
    $pierogi = slServingsRecipe('Pierogi', 6, []);
    $cooking = slPlan(SL_FRI, 'dinner', $pierogi, 'full');
    slPlan('2026-11-21', 'dinner', $pierogi, 'leftover', $cooking->id);

    $cooking->delete();

    expect(RecipeMealAssignment::query()->count())->toBe(0);
});

test('optional ingredients and substitutes are carried to the list and shown', function () {
    $chia = slIngredient('Chia');
    $sugar = slIngredient('Erytrol');
    $porridge = slServingsRecipe('Owsianka', 2, [
        [$chia, 15, 'g', ['is_optional' => true]],
        [$sugar, 50, 'g', ['substitute' => 'cukier']],
    ]);
    slPlan(SL_FRI, 'breakfast', $porridge);

    app(GenerateShoppingList::class)([SL_FRI]);

    $groups = collect(slDay(slBuild(), SL_FRI)['categories'])->flatMap(fn ($category) => $category['groups'])->keyBy('name');

    expect($groups['Chia']['optional'])->toBeTrue()
        ->and($groups['Chia']['substitute'])->toBeNull()
        ->and($groups['Erytrol']['optional'])->toBeFalse()
        ->and($groups['Erytrol']['substitute'])->toBe('cukier');
});

test('an ingredient optional in one recipe but required in another is not optional', function () {
    $chia = slIngredient('Chia');
    slItem(['name' => 'Chia', 'ingredient_id' => $chia->id, 'amount' => 10, 'unit' => 'g', 'needed_on' => SL_FRI, 'is_optional' => true]);
    slItem(['name' => 'Chia', 'ingredient_id' => $chia->id, 'amount' => 5, 'unit' => 'g', 'needed_on' => SL_FRI, 'is_optional' => false]);

    $group = slDay(slBuild(), SL_FRI)['categories'][0]['groups'][0];

    expect($group['optional'])->toBeFalse()->and($group['quantity'])->toBe('15 g');
});

// --- Household supplies (pantry) ---------------------------------------------

test('low and missing household supplies show up on the list, ticking one off restocks it', function () {
    $group = PantryGroup::query()->create(['name' => 'Kuchnia']);
    $foil = PantryItem::query()->create(['name' => 'Folia aluminiowa', 'pantry_group_id' => $group->id, 'status' => PantryItem::STATUS_LOW]);
    PantryItem::query()->create(['name' => 'Papier toaletowy', 'pantry_group_id' => $group->id, 'status' => PantryItem::STATUS_OUT]);
    PantryItem::query()->create(['name' => 'Mąka', 'pantry_group_id' => $group->id, 'status' => PantryItem::STATUS_OK]);

    $page = Livewire::actingAs(User::factory()->create())->test('pages::shopping-list.index')
        ->assertSee('Zapasy domowe')
        ->assertSee('Folia aluminiowa')
        ->assertSee('kończy się')
        ->assertSee('brak w domu')
        ->assertDontSee('Mąka');

    $page->call('restock', $foil->id)->assertDontSee('Folia aluminiowa');

    expect($foil->fresh()->status)->toBe(PantryItem::STATUS_OK);
});

test('changing a product state in the pantry tab puts it on the shopping list', function () {
    $group = PantryGroup::query()->create(['name' => 'Łazienka']);
    $soap = PantryItem::query()->create(['name' => 'Mydło', 'pantry_group_id' => $group->id]);

    Livewire::actingAs(User::factory()->create())->test('shopping-list.pantry')
        ->assertDontSee('na liście zakupów')
        ->call('setStatus', $soap->id, PantryItem::STATUS_OUT)
        ->assertSee('na liście zakupów')
        ->assertDispatched('pantry-changed');

    expect($soap->fresh()->status)->toBe(PantryItem::STATUS_OUT);
});

test('products are added with a new group and empty groups disappear', function () {
    $old = PantryGroup::query()->create(['name' => 'Garaż']);
    $oil = PantryItem::query()->create(['name' => 'Olej silnikowy', 'pantry_group_id' => $old->id]);

    Livewire::actingAs(User::factory()->create())->test('shopping-list.pantry')
        ->call('openEdit', $oil->id)
        ->call('startNewGroup')
        ->set('newGroupName', 'Piwnica')
        ->call('save')
        ->assertHasNoErrors();

    expect($oil->fresh()->group->name)->toBe('Piwnica')
        ->and(PantryGroup::query()->pluck('name')->all())->toBe(['Piwnica']);
});

