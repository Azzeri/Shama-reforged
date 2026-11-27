<?php

use App\Actions\ShoppingList\GenerateShoppingList;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\Recipe;
use App\Models\RecipeMealAssignment;
use App\Models\ShoppingListItem;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

// 2026-11-18 is a Wednesday.
beforeEach(function () {
    app()->setLocale('pl');
    Carbon::setTestNow(Carbon::parse('2026-11-18 10:00'));
    $this->actingAs(User::factory()->create());
});

afterEach(fn () => Carbon::setTestNow());

function mpEntries(string $date): array
{
    return RecipeMealAssignment::query()
        ->with(['meal', 'recipe'])
        ->get()
        ->filter(fn ($assignment) => $assignment->meal->date->toDateString() === $date)
        ->map(fn ($assignment) => [$assignment->meal->type, $assignment->recipe->name, $assignment->mode])
        ->values()
        ->all();
}

test('a recipe for two is added to the meal right away as one meal', function () {
    $recipe = Recipe::query()->create(['name' => 'Owsianka', 'servings' => 2]);

    Livewire::test('pages::meal.day-edit', ['date' => '2026-11-18'])
        ->call('openRecipePicker', 'breakfast')
        ->call('chooseRecipe', $recipe->id)
        ->assertSet('pickingType', null)
        ->assertDispatched('saved')
        ->assertSee('Owsianka');

    expect(mpEntries('2026-11-18'))->toBe([['breakfast', 'Owsianka', 'single']]);
});

test('cooking a recipe whole plans leftovers on the chosen days, limited by its servings', function () {
    $recipe = Recipe::query()->create(['name' => 'Pierogi ruskie', 'servings' => 6]);

    Livewire::test('pages::meal.day-edit', ['date' => '2026-11-21'])
        ->call('openRecipePicker', 'dinner')
        ->call('chooseRecipe', $recipe->id)
        ->assertSee('Przepis na 6 porcji')
        ->call('setMode', 'full')
        ->call('toggleLeftoverDate', '2026-11-22')
        ->call('toggleLeftoverDate', '2026-11-23')
        ->call('toggleLeftoverDate', '2026-11-24') // 6 servings = 3 meals: no room for a third leftover day
        ->assertSet('leftoverDates', ['2026-11-22', '2026-11-23'])
        ->call('addChosenRecipe')
        ->assertSee('gotowanie · 6 porcji');

    expect(mpEntries('2026-11-21'))->toBe([['dinner', 'Pierogi ruskie', 'full']])
        ->and(mpEntries('2026-11-22'))->toBe([['dinner', 'Pierogi ruskie', 'leftover']])
        ->and(mpEntries('2026-11-23'))->toBe([['dinner', 'Pierogi ruskie', 'leftover']]);

    Livewire::test('pages::meal.index', ['week' => '2026-11-18'])
        ->assertSee('gotowanie · 6 porcji')
        ->assertSee('z soboty · bez zakupów');
});

test('removing the cooking day entry removes its leftover days', function () {
    $recipe = Recipe::query()->create(['name' => 'Pierogi ruskie', 'servings' => 6]);

    $page = Livewire::test('pages::meal.day-edit', ['date' => '2026-11-21'])
        ->call('openRecipePicker', 'dinner')
        ->call('chooseRecipe', $recipe->id)
        ->call('setMode', 'full')
        ->call('toggleLeftoverDate', '2026-11-22')
        ->call('addChosenRecipe');

    $page->call('removeEntry', RecipeMealAssignment::query()->where('mode', 'full')->value('id'));

    expect(RecipeMealAssignment::query()->count())->toBe(0)
        ->and(Meal::query()->count())->toBe(0);
});

test('archived recipes are not offered when planning', function () {
    Recipe::query()->create(['name' => 'Stara zupa', 'archived_at' => now()]);
    Recipe::query()->create(['name' => 'Nowa zupa']);

    Livewire::test('pages::meal.day-edit', ['date' => '2026-11-18'])
        ->call('openRecipePicker', 'lunch')
        ->set('recipeSearch', 'zupa')
        ->assertSee('Nowa zupa')
        ->assertDontSee('Stara zupa');
});

test('an archived recipe already in the plan stays there', function () {
    $recipe = Recipe::query()->create(['name' => 'Stara zupa']);
    $meal = Meal::query()->create(['date' => '2026-11-18 12:00:00', 'type' => 'lunch']);
    $meal->recipes()->attach($recipe->id);
    $recipe->update(['archived_at' => now()]);

    Livewire::test('pages::meal.index')->assertSee('Stara zupa');
});

test('notes are saved without a save button and removed again', function () {
    $page = Livewire::test('pages::meal.day-edit', ['date' => '2026-11-18'])
        ->call('startEditingNote', 'lunch')
        ->set('noteDraft', 'Jemy na mieście')
        ->call('saveNote')
        ->assertDispatched('saved')
        ->assertSee('Utwórz przepis z tej notatki');

    expect(Meal::query()->sole()->note)->toBe('Jemy na mieście');

    $page->call('clearNote', 'lunch');

    expect(Meal::query()->count())->toBe(0);
});

test('the calendar re-renders with fresh data when restored from history (regression)', function () {
    $calendar = Livewire::test('pages::meal.index')->assertDontSee('Owsianka');

    // Meanwhile the day is edited (autosave) in the day editor...
    $recipe = Recipe::query()->create(['name' => 'Owsianka']);
    Livewire::test('pages::meal.day-edit', ['date' => '2026-11-18'])
        ->call('openRecipePicker', 'breakfast')
        ->call('chooseRecipe', $recipe->id);

    // ...and going back restores the calendar from Livewire's history
    // snapshot; it is marked to refresh itself, which must show the change.
    expect($calendar->html())->toContain('data-refresh-on-restore');
    $calendar->call('$refresh')->assertSee('Owsianka');
});

test('the old day view redirects to the day editor', function () {
    $this->get('/meals/day/2026-11-18')->assertRedirect(route('meals.day.edit', '2026-11-18'));
});

test('a planned meal of a recipe for 4 puts half of it on the shopping list', function () {
    $tomatoes = Ingredient::query()->create(['name' => 'Pomidory']);
    $recipe = Recipe::query()->create(['name' => 'Zupa pomidorowa', 'servings' => 4]);
    $recipe->ingredients()->attach($tomatoes->id, ['amount' => 800, 'unit' => 'g']);

    Livewire::test('pages::meal.day-edit', ['date' => '2026-11-19'])
        ->call('openRecipePicker', 'lunch')
        ->call('chooseRecipe', $recipe->id)
        ->call('addChosenRecipe');

    app(GenerateShoppingList::class)(['2026-11-19']);

    expect(ShoppingListItem::query()->sole()->amount)->toBe(400.0);
});

test('a recipe can be previewed from the picker and chosen from the preview', function () {
    $recipe = Recipe::query()->create(['name' => 'Zupa pomidorowa', 'servings' => 2, 'content' => "Pokrój pomidory.\nGotuj 20 minut."]);

    Livewire::test('pages::meal.day-edit', ['date' => '2026-11-18'])
        ->call('openRecipePicker', 'lunch')
        ->call('previewRecipe', $recipe->id)
        ->assertSee('Wróć do listy')
        ->assertSee('Gotuj 20 minut.')
        ->assertSee('Wybierz ten przepis')
        ->call('closePreview')
        ->assertSet('previewRecipeId', null)
        ->call('previewRecipe', $recipe->id)
        ->call('chooseRecipe', $recipe->id)
        ->assertSet('pickingType', null)
        ->assertSet('previewRecipeId', null);

    expect(mpEntries('2026-11-18'))->toBe([['lunch', 'Zupa pomidorowa', 'single']]);
});

test('"I already have it ready" plans the recipe without putting anything on the shopping list', function () {
    $beef = Ingredient::query()->create(['name' => 'Wołowina']);
    $recipe = Recipe::query()->create(['name' => 'Gulasz wołowy', 'servings' => 6]);
    $recipe->ingredients()->attach($beef->id, ['amount' => 900, 'unit' => 'g']);

    Livewire::test('pages::meal.day-edit', ['date' => '2026-11-19'])
        ->call('openRecipePicker', 'supper')
        ->call('chooseRecipe', $recipe->id)
        ->call('setMode', 'ready')
        ->call('addChosenRecipe')
        ->assertSee('mam gotowe · bez zakupów');

    $assignment = RecipeMealAssignment::query()->sole();

    expect($assignment->mode)->toBe(RecipeMealAssignment::MODE_SINGLE)
        ->and($assignment->skip_shopping)->toBeTrue()
        ->and(app(GenerateShoppingList::class)(['2026-11-19'])['added'])->toBe(0)
        ->and(ShoppingListItem::query()->count())->toBe(0);

    Livewire::test('pages::meal.index', ['week' => '2026-11-18'])->assertSee('mam gotowe · bez zakupów');
});

test('the cart button switches "have it ready": on removes unbought ingredients from the list, off counts the meal again', function () {
    $beef = Ingredient::query()->create(['name' => 'Wołowina']);
    $onion = Ingredient::query()->create(['name' => 'Cebula']);
    $recipe = Recipe::query()->create(['name' => 'Gulasz wołowy', 'servings' => 2]);
    $recipe->ingredients()->attach($beef->id, ['amount' => 300, 'unit' => 'g']);
    $recipe->ingredients()->attach($onion->id, ['amount' => 1, 'unit' => 'szt']);

    $page = Livewire::test('pages::meal.day-edit', ['date' => '2026-11-19'])
        ->call('openRecipePicker', 'dinner')
        ->call('chooseRecipe', $recipe->id);

    app(GenerateShoppingList::class)(['2026-11-19']);
    ShoppingListItem::query()->where('name', 'Cebula')->update(['is_checked' => true]);

    $assignment = RecipeMealAssignment::query()->sole();
    $page->call('toggleSkipShopping', $assignment->id)->assertSee('mam gotowe · bez zakupów');

    // The onion was already bought, so it stays; the beef is gone and regenerating does not bring it back.
    app(GenerateShoppingList::class)(['2026-11-19']);

    expect($assignment->fresh()->skip_shopping)->toBeTrue()
        ->and(ShoppingListItem::query()->pluck('name')->all())->toBe(['Cebula']);

    $page->call('toggleSkipShopping', $assignment->id)->assertDontSee('mam gotowe · bez zakupów');
    app(GenerateShoppingList::class)(['2026-11-19']);

    expect($assignment->fresh()->skip_shopping)->toBeFalse()
        ->and(ShoppingListItem::query()->orderBy('name')->pluck('name')->all())->toBe(['Cebula', 'Wołowina']);
});

test('leftover days cannot be switched to "have it ready"', function () {
    $recipe = Recipe::query()->create(['name' => 'Pierogi ruskie', 'servings' => 6]);

    $page = Livewire::test('pages::meal.day-edit', ['date' => '2026-11-21'])
        ->call('openRecipePicker', 'dinner')
        ->call('chooseRecipe', $recipe->id)
        ->call('setMode', RecipeMealAssignment::MODE_FULL)
        ->call('toggleLeftoverDate', '2026-11-22')
        ->call('addChosenRecipe');

    $leftover = RecipeMealAssignment::query()->where('mode', RecipeMealAssignment::MODE_LEFTOVER)->sole();
    $page->call('toggleSkipShopping', $leftover->id);

    expect($leftover->fresh()->skip_shopping)->toBeFalse();
});
