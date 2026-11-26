<?php

use App\Models\CustomList;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    app()->setLocale('pl');
    $this->actingAs(User::factory()->create());
});

test('a new list opens right away and items can be added, ticked and removed', function () {
    $overview = Livewire::test('shopping-list.custom-lists')
        ->call('openNew')
        ->set('name', 'Budowlany')
        ->call('create');

    $list = CustomList::query()->sole();

    // The phone navigates to the list's page, the desktop selects it next to the overview.
    $overview->assertDispatched('custom-list-created', id: $list->id, url: route('shopping-list.custom', $list))
        ->call('select', $list->id)
        ->assertSet('selectedId', $list->id);

    $page = Livewire::test('pages::shopping-list.custom-list', ['customList' => $list])
        ->set('newItem', 'Taśma malarska')->call('addItem')
        ->set('newItem', 'Silikon')->call('addItem')
        ->assertSee('0 z 2');

    $tape = $list->items()->where('name', 'Taśma malarska')->sole();
    $page->call('setChecked', $tape->id, true)->assertSee('1 z 2');

    $page->call('removeItem', $tape->id);

    expect($list->items()->pluck('name')->all())->toBe(['Silikon']);
});

test('ticked items move to the bottom of the list', function () {
    $list = CustomList::query()->create(['name' => 'Apteka']);
    $vitamin = $list->items()->create(['name' => 'Witamina D']);
    $list->items()->create(['name' => 'Plastry']);
    $list->items()->create(['name' => 'Krople do nosa']);

    Livewire::test('pages::shopping-list.custom-list', ['customList' => $list])
        ->call('setChecked', $vitamin->id, true)
        ->assertSeeInOrder(['Plastry', 'Krople do nosa', 'Witamina D']);

    expect($list->items()->pluck('name')->all())->toBe(['Plastry', 'Krople do nosa', 'Witamina D']);
});

test('the overview shows progress and a preview of what is left; deleting a list removes it', function () {
    $list = CustomList::query()->create(['name' => 'Prezenty']);
    $list->items()->create(['name' => 'Skarpety dla taty', 'is_checked' => true]);
    $list->items()->create(['name' => 'Gra planszowa']);

    Livewire::test('shopping-list.custom-lists')
        ->assertSee('Prezenty')
        ->assertSee('1 z 2')
        ->assertSee('Gra planszowa')
        ->assertDontSee('Skarpety dla taty');

    Livewire::test('pages::shopping-list.custom-list', ['customList' => $list])
        ->call('deleteList')
        ->assertRedirect(route('shopping-list.index', ['tab' => 'lists']));

    expect(CustomList::query()->count())->toBe(0);
});

test('on the desktop a list deleted from its pane leaves the overview without a redirect', function () {
    $list = CustomList::query()->create(['name' => 'Apteka']);

    Livewire::test('pages::shopping-list.custom-list', ['customList' => $list, 'embedded' => true])
        ->assertDontSee('Własne listy')
        ->call('deleteList')
        ->assertNoRedirect()
        ->assertDispatched('custom-list-deleted');

    expect(CustomList::query()->count())->toBe(0);
});
