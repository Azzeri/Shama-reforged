<?php

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    app()->setLocale('pl');
    Carbon::setTestNow(Carbon::parse('2026-11-18 10:00'));
    $this->actingAs(User::factory()->create());
});

afterEach(fn () => Carbon::setTestNow());

test('every app page has the side menu for desktops next to the phone bottom nav', function () {
    $this->get(route('meals.index'))
        ->assertOk()
        ->assertSee('Shama')
        ->assertSeeInOrder(['Plan', 'Przepisy', 'Zakupy', 'Składniki', 'Konto']);

    $recipe = Recipe::query()->create(['name' => 'Owsianka']);
    $this->get(route('recipes.edit', $recipe))->assertOk()->assertSee('aria-label="Główna nawigacja"', false);
});

test('the week grid opens the day editor in a side panel and refreshes after its changes', function () {
    $recipe = Recipe::query()->create(['name' => 'Owsianka']);

    $calendar = Livewire::test('pages::meal.index')
        ->assertSee('Kliknij dzień, żeby go edytować.')
        ->call('openDay', '2026-11-19')
        ->assertSet('editingDay', '2026-11-19')
        ->assertSee('Zmiany zapisują się automatycznie.');

    Livewire::test('pages::meal.day-edit', ['date' => '2026-11-19', 'inPanel' => true])
        ->assertSee('Edycja dnia')
        ->assertDontSee('Tydzień')
        ->call('openRecipePicker', 'breakfast')
        ->call('chooseRecipe', $recipe->id)
        ->assertDispatched('saved');

    $calendar->dispatch('saved')->assertSee('Owsianka');

    $calendar->dispatch('close-day-panel')->assertSet('editingDay', null);
});

test('the recipe list shows the selected recipe next to it, archiving there removes it from the list', function () {
    $soup = Recipe::query()->create(['name' => 'Zupa pomidorowa']);
    Recipe::query()->create(['name' => 'Owsianka']);

    $list = Livewire::test('pages::recipe.index')
        ->call('select', $soup->id)
        ->assertSet('selectedId', $soup->id)
        ->assertSee('Do archiwum');

    Livewire::test('pages::recipe.show', ['recipe' => $soup, 'embedded' => true])
        ->assertDontSee('Przenieś do archiwum')
        ->call('archive')
        ->assertDispatched('recipe-archived');

    $list->dispatch('recipe-archived')->assertSet('selectedId', null)->assertSee('Archiwum (1)');
});

test('the ingredient form opens in the desktop panel and closes after saving', function () {
    $ingredient = Ingredient::query()->create(['name' => 'Arbuz']);

    Livewire::test('pages::ingredient.index')
        ->call('openEdit', $ingredient->id)
        ->assertSet('panelOpen', true)
        ->assertDispatched('ingredient-form-opened')
        ->assertSee('Edytuj składnik')
        ->set('name', 'Arbuz bezpestkowy')
        ->call('save')
        ->assertSet('panelOpen', false);

    expect($ingredient->fresh()->name)->toBe('Arbuz bezpestkowy');
});
