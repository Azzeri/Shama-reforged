<?php

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

test('recipes are listed in Polish alphabetical order under letter headers', function () {
    foreach (['Żurek', 'Łosoś z piekarnika', 'Leczo', 'Zupa pomidorowa', 'Śledź', 'Sałatka'] as $name) {
        Recipe::query()->create(['name' => $name]);
    }

    Livewire::test('pages::recipe.index')
        ->assertSeeInOrder(['Leczo', 'Łosoś z piekarnika', 'Sałatka', 'Śledź', 'Zupa pomidorowa', 'Żurek'])
        ->assertSeeHtml('id="rl-'.mb_ord('Ł').'"')
        ->assertSee('Skocz do litery Ż');
});

test('ingredients are listed in Polish alphabetical order under letter headers', function () {
    foreach (['Żurawina', 'Łosoś', 'Masło', 'Cebula'] as $name) {
        Ingredient::query()->create(['name' => $name]);
    }

    Livewire::test('pages::ingredient.index')
        ->assertSeeInOrder(['Cebula', 'Łosoś', 'Masło', 'Żurawina'])
        ->assertSeeHtml('id="dil-'.mb_ord('Ł').'"')
        ->assertSeeHtml('id="il-'.mb_ord('Ż').'"');
});

test('the archive and "to fill in" links sit above the recipe list', function () {
    Recipe::query()->create(['name' => 'Archiwalny', 'archived_at' => now()]);
    Recipe::query()->create(['name' => 'Bez oznaczeń']);

    Livewire::test('pages::recipe.index')
        ->assertSeeInOrder(['Archiwum (1)', 'Do uzupełnienia (1)', 'Bez oznaczeń']);
});
