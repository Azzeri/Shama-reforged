<?php

use App\Actions\ShoppingList\GenerateShoppingList;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\Recipe;
use App\Models\RecipeMealAssignment;
use App\Models\ShoppingListItem;
use App\Models\User;
use App\Support\MealPlan\SemiProducts;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

// Tomato soup for 2 needs 700 ml of vegetable stock; the stock recipe makes 1500 ml.
beforeEach(function () {
    app()->setLocale('pl');
    Carbon::setTestNow(Carbon::parse('2026-11-18 10:00'));
    $this->actingAs(User::factory()->create());

    $this->carrot = Ingredient::query()->create(['name' => 'Marchew']);
    $this->celery = Ingredient::query()->create(['name' => 'Seler']);
    $this->tomatoes = Ingredient::query()->create(['name' => 'Pomidory']);

    $this->stockRecipe = Recipe::query()->create(['name' => 'Domowy bulion warzywny', 'servings' => 6]);
    $this->stockRecipe->ingredients()->attach($this->carrot->id, ['amount' => 300, 'unit' => 'g']);
    $this->stockRecipe->ingredients()->attach($this->celery->id, ['amount' => 150, 'unit' => 'g']);

    $this->stock = Ingredient::query()->create([
        'name' => 'Bulion warzywny', 'recipe_id' => $this->stockRecipe->id,
        'recipe_yield_amount' => 1500, 'recipe_yield_unit' => 'ml',
    ]);

    $this->soup = Recipe::query()->create(['name' => 'Zupa pomidorowa', 'servings' => 2]);
    $this->soup->ingredients()->attach($this->stock->id, ['amount' => 700, 'unit' => 'ml']);
    $this->soup->ingredients()->attach($this->tomatoes->id, ['amount' => 400, 'unit' => 'g']);
});

afterEach(fn () => Carbon::setTestNow());

function spPlanSoup(?string $choice = null): Testable
{
    $page = Livewire::test('pages::meal.day-edit', ['date' => '2026-11-19'])
        ->call('openRecipePicker', 'dinner')
        ->call('chooseRecipe', test()->soup->id);

    if ($choice !== null) {
        $page->call('setSemiChoice', test()->stock->id, $choice);
    }

    return $page->call('addChosenRecipe');
}

function spList(): array
{
    app(GenerateShoppingList::class)(['2026-11-19']);

    return ShoppingListItem::query()->orderBy('name')->get()->mapWithKeys(fn ($item) => [$item->name => $item->amount])->all();
}

test('the ingredient form links an ingredient to its recipe and marks it as a semi-product', function () {
    $pesto = Ingredient::query()->create(['name' => 'Pesto']);
    $pestoRecipe = Recipe::query()->create(['name' => 'Domowe pesto']);

    Livewire::test('pages::ingredient.index')
        ->assertSee('półprodukt')
        ->call('openEdit', $pesto->id)
        ->set('semiRecipeId', $pestoRecipe->id)
        ->assertSee('Zwykle robię sam')
        ->set('homemadeDefault', true)
        ->set('yieldAmount', '0,2')
        ->set('yieldUnit', 'kg')
        ->call('save')
        ->assertHasNoErrors();

    expect($pesto->fresh())->recipe_id->toBe($pestoRecipe->id)
        ->homemade_default->toBeTrue()
        ->recipe_yield_amount->toBe(0.2)
        ->recipe_yield_unit->toBe('kg');

    // Unlinking the recipe clears the rest.
    Livewire::test('pages::ingredient.index')
        ->call('openEdit', $pesto->id)
        ->assertSet('yieldAmount', '0,2')
        ->set('semiRecipeId', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($pesto->fresh())->recipe_id->toBeNull()->homemade_default->toBeFalse()->recipe_yield_amount->toBeNull();
});

test('a recipe with a semi-product asks how to get it even when it is a single meal; buying ready is the default', function () {
    Livewire::test('pages::meal.day-edit', ['date' => '2026-11-19'])
        ->call('openRecipePicker', 'dinner')
        ->call('chooseRecipe', $this->soup->id)
        ->assertSee('Półprodukty')
        ->assertSee('Na listę zakupów trafi gotowy produkt.')
        ->assertSet('semiChoices', [$this->stock->id => SemiProducts::BUY])
        ->call('addChosenRecipe')
        ->assertDontSee('bulion warzywny:');

    expect(RecipeMealAssignment::query()->sole()->semi_choices)->toBe([$this->stock->id => 'buy'])
        ->and(spList())->toBe(['Bulion warzywny' => 700.0, 'Pomidory' => 400.0]);
});

test('"making it myself" puts the stock recipe on the list, scaled to the amount the dish needs', function () {
    spPlanSoup(SemiProducts::MAKE)->assertSee('bulion warzywny: robię sam');

    // 700 of 1500 ml: 300 g carrot → 140 g, 150 g celery → 70 g.
    expect(spList())->toBe(['Marchew' => 140.0, 'Pomidory' => 400.0, 'Seler' => 70.0])
        ->and(ShoppingListItem::query()->where('name', 'Marchew')->value('semi_ingredient_id'))->toBe($this->stock->id);

    Livewire::test('pages::meal.index', ['week' => '2026-11-18'])->assertSee('bulion warzywny: robię sam');
});

test('"already have it" leaves only the semi-product off the list', function () {
    spPlanSoup(SemiProducts::HAVE)->assertSee('bulion warzywny: mam już');

    expect(spList())->toBe(['Pomidory' => 400.0]);
});

test('without a known yield, or with units that cannot be compared, the whole stock recipe goes on the list', function () {
    $this->stock->update(['recipe_yield_amount' => null, 'recipe_yield_unit' => null]);
    spPlanSoup(SemiProducts::MAKE);

    expect(spList())->toBe(['Marchew' => 300.0, 'Pomidory' => 400.0, 'Seler' => 150.0]);

    $this->stock->update(['recipe_yield_amount' => 6, 'recipe_yield_unit' => 'pcs']);
    ShoppingListItem::query()->delete();

    expect(spList())->toBe(['Marchew' => 300.0, 'Pomidory' => 400.0, 'Seler' => 150.0]);
});

test('the choice made when planning beats the one remembered for the recipe, which beats the ingredient default', function () {
    // Default on the ingredient: home-made. A meal planned without asking (e.g. inserted from the week review) follows it.
    $this->stock->update(['homemade_default' => true]);
    $meal = Meal::query()->create(['date' => '2026-11-19 12:00:00', 'type' => 'supper']);
    $entry = RecipeMealAssignment::query()->create(['meal_id' => $meal->id, 'recipe_id' => $this->soup->id, 'mode' => 'single']);

    expect(array_keys(spList()))->toBe(['Marchew', 'Pomidory', 'Seler']);

    // Remembered for the recipe on its page: ready.
    Livewire::test('pages::recipe.show', ['recipe' => $this->soup])
        ->assertSee('półprodukt')
        ->assertSee('Przepis: Domowy bulion warzywny')
        ->call('setSemiSource', $this->stock->id, SemiProducts::BUY)
        ->assertDontSee('Przepis: Domowy bulion warzywny');

    expect($this->soup->ingredients()->find($this->stock->id)->pivot->semi_source)->toBe('buy')
        ->and(array_keys(spList()))->toBe(['Bulion warzywny', 'Pomidory']);

    // Chosen for this meal: already have it.
    $entry->update(['semi_choices' => [$this->stock->id => SemiProducts::HAVE]]);

    expect(array_keys(spList()))->toBe(['Pomidory']);
});

test('only one level is made at home: a semi-product inside the stock recipe is bought ready', function () {
    $saltRecipe = Recipe::query()->create(['name' => 'Sól ziołowa']);
    $saltRecipe->ingredients()->attach($this->celery->id, ['amount' => 999, 'unit' => 'g']);
    $salt = Ingredient::query()->create(['name' => 'Sól ziołowa', 'recipe_id' => $saltRecipe->id, 'homemade_default' => true]);
    $this->stockRecipe->ingredients()->attach($salt->id, ['amount' => 15, 'unit' => 'g']);

    spPlanSoup(SemiProducts::MAKE);

    expect(spList())->toBe(['Marchew' => 140.0, 'Pomidory' => 400.0, 'Seler' => 70.0, 'Sól ziołowa' => 7.0]);
});

test('"have it ready" for the whole meal skips the semi-product question and the shopping', function () {
    Livewire::test('pages::meal.day-edit', ['date' => '2026-11-19'])
        ->call('openRecipePicker', 'dinner')
        ->call('chooseRecipe', $this->soup->id)
        ->call('setSemiChoice', $this->stock->id, SemiProducts::MAKE)
        ->call('setMode', 'ready')
        ->assertDontSee('Półprodukty')
        ->call('addChosenRecipe')
        ->assertSee('mam gotowe · bez zakupów');

    expect(RecipeMealAssignment::query()->sole()->semi_choices)->toBeNull()
        ->and(spList())->toBe([]);
});

test('a bought row of the home-made stock stays bought when the list is generated again', function () {
    // The soup itself also uses carrot, so the same ingredient comes from two places.
    $this->soup->ingredients()->attach($this->carrot->id, ['amount' => 100, 'unit' => 'g']);
    spPlanSoup(SemiProducts::MAKE);
    spList();

    ShoppingListItem::query()->where('name', 'Marchew')->whereNotNull('semi_ingredient_id')->update(['is_checked' => true]);
    app(GenerateShoppingList::class)(['2026-11-19']);

    expect(ShoppingListItem::query()->where('name', 'Marchew')->orderBy('amount')->get()->map(fn ($item) => [$item->amount, $item->is_checked])->all())
        ->toBe([[100.0, false], [140.0, true]]);
});

it('marks a recipe as a semi-product in the recipe form', function () {
    Livewire::test('recipe.form', ['recipe' => $this->stockRecipe])
        ->assertSee('Posiłek')
        ->set('semiProduct', true)
        ->assertSee('Nie da się go dodać do planu posiłków')
        ->assertDontSee('Na wynos')
        ->call('save');

    expect($this->stockRecipe->fresh()->is_semi_product)->toBeTrue();
});

it('keeps a recipe marked as a semi-product out of planning and of the "to fill in" list', function () {
    $this->stockRecipe->update(['is_semi_product' => true]);

    Livewire::test('pages::meal.day-edit', ['date' => '2026-11-18'])
        ->call('openRecipePicker', 'dinner')
        ->set('recipeSearch', 'bulion')
        ->assertDontSee('Domowy bulion warzywny')
        ->call('chooseRecipe', $this->stockRecipe->id)
        ->assertSet('chosenRecipeId', null);

    expect(RecipeMealAssignment::query()->count())->toBe(0)
        ->and(Recipe::query()->incomplete()->pluck('name')->all())->toBe(['Zupa pomidorowa'])
        ->and($this->stockRecipe->fresh()->load('tags')->missingLabels())->toBe([]);

    // It still works as the home-made source of the ingredient.
    $this->get(route('recipes.index'))->assertOk()->assertSee('półprodukt');
});
