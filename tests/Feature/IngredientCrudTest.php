<?php

use App\Models\Ingredient;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    app()->setLocale('pl');
    $this->actingAs(User::factory()->create());
});

test('a new ingredient is added from the sheet with its section, timing and unit weights', function () {
    Livewire::test('pages::ingredient.index')
        ->call('openNew')
        ->set('name', '   papryka czerwona   ')
        ->set('purchaseTiming', Ingredient::PURCHASE_TIMING_ADVANCE)
        ->set('category', Ingredient::CATEGORY_PRODUCE)
        ->set('unitWeights.pcs', '150,5')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Papryka czerwona');

    $ingredient = Ingredient::query()->sole();

    expect($ingredient->name)->toBe('Papryka czerwona')
        ->and($ingredient->purchase_timing)->toBe(Ingredient::PURCHASE_TIMING_ADVANCE)
        ->and($ingredient->category)->toBe(Ingredient::CATEGORY_PRODUCE)
        ->and($ingredient->unit_weights)->toBe(['pcs' => 150.5]);
});

test('a new ingredient defaults to fresh and uncategorized', function () {
    Livewire::test('pages::ingredient.index')
        ->call('openNew')
        ->set('name', 'Koperek')
        ->call('save');

    $ingredient = Ingredient::query()->sole();

    expect($ingredient->category)->toBe(Ingredient::CATEGORY_UNCATEGORIZED)
        ->and($ingredient->purchase_timing)->toBe(Ingredient::PURCHASE_TIMING_FRESH)
        ->and($ingredient->unit_weights)->toBeNull();
});

test('editing pre-fills the sheet and saves the changes', function () {
    $ingredient = Ingredient::query()->create(['name' => 'Cukier', 'category' => Ingredient::CATEGORY_BAKING, 'unit_weights' => ['tsp' => 5]]);

    Livewire::test('pages::ingredient.index')
        ->call('openEdit', $ingredient->id)
        ->assertSet('name', 'Cukier')
        ->assertSet('category', Ingredient::CATEGORY_BAKING)
        ->assertSet('unitWeights.tsp', '5')
        ->set('name', 'Cukier trzcinowy')
        ->set('category', Ingredient::CATEGORY_PANTRY)
        ->set('skipShopping', true)
        ->call('save')
        ->assertHasNoErrors();

    $ingredient->refresh();

    expect($ingredient->name)->toBe('Cukier trzcinowy')
        ->and($ingredient->category)->toBe(Ingredient::CATEGORY_PANTRY)
        ->and($ingredient->skip_shopping)->toBeTrue();
});

test('the name is required, unique and the section must be known', function () {
    Ingredient::query()->create(['name' => 'Sól']);

    Livewire::test('pages::ingredient.index')
        ->call('openNew')
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name' => 'required'])
        ->set('name', 'sól')
        ->call('save')
        ->assertHasErrors(['name' => 'unique'])
        ->set('name', 'Pieprz')
        ->set('category', 'not-a-category')
        ->call('save')
        ->assertHasErrors(['category']);

    expect(Ingredient::query()->count())->toBe(1);
});

test('an ingredient is deleted from its sheet', function () {
    $ingredient = Ingredient::query()->create(['name' => 'Cukier']);

    Livewire::test('pages::ingredient.index')
        ->call('openEdit', $ingredient->id)
        ->call('delete')
        ->assertDontSee('Cukier');

    expect(Ingredient::query()->count())->toBe(0);
});

test('the list is searched and filtered by "for the whole week" and by section', function () {
    Ingredient::query()->create(['name' => 'Ryż', 'category' => Ingredient::CATEGORY_GRAINS, 'purchase_timing' => Ingredient::PURCHASE_TIMING_ADVANCE]);
    Ingredient::query()->create(['name' => 'Rukola', 'category' => Ingredient::CATEGORY_PRODUCE]);
    Ingredient::query()->create(['name' => 'Mleko', 'category' => Ingredient::CATEGORY_DAIRY]);

    Livewire::test('pages::ingredient.index')
        ->assertSee('na cały tydzień')
        ->set('search', 'r')
        ->assertSee('Ryż')->assertSee('Rukola')->assertDontSee('Mleko')
        ->call('setFilter', 'advance')
        ->assertSee('Ryż')->assertDontSee('Rukola')
        ->set('search', '')
        ->call('setFilter', Ingredient::CATEGORY_DAIRY)
        ->assertSee('Mleko')->assertDontSee('Ryż');
});
