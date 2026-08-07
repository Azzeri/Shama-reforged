<?php

use App\Models\Meal;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

test('authenticated user can see weekly meal calendar', function () {
    $user = User::factory()->create();
    $meal = Meal::query()->create([
        'type' => 'breakfast',
        'date' => Carbon::parse('2026-06-22 08:00:00'),
    ]);

    $meal->recipes()->attach(
        Recipe::query()->create([
            'name' => 'Owsianka',
            'content' => 'Zalej płatki mlekiem.',
        ])->id
    );

    $this->actingAs($user);

    Livewire::test('pages::meal.index')
        ->set('week', '2026-06-22')
        ->assertOk()
        ->assertSee('Meal calendar')
        ->assertSee('Owsianka')
        ->assertSee('22');
});

test('week navigation moves the calendar to the previous and next week', function () {
    $user = User::factory()->create();

    Meal::query()->create([
        'type' => 'lunch',
        'date' => Carbon::parse('2026-06-15 12:00:00'),
    ])->recipes()->attach(
        Recipe::query()->create(['name' => 'Zupa pomidorowa', 'content' => 'Opis.'])->id
    );

    Meal::query()->create([
        'type' => 'dinner',
        'date' => Carbon::parse('2026-06-29 19:00:00'),
    ])->recipes()->attach(
        Recipe::query()->create(['name' => 'Risotto', 'content' => 'Opis.'])->id
    );

    $this->actingAs($user);

    Livewire::test('pages::meal.index')
        ->set('week', '2026-06-22')
        ->call('goToPreviousWeek')
        ->assertSee('Zupa pomidorowa')
        ->call('goToNextWeek')
        ->call('goToNextWeek')
        ->assertSee('Risotto');
});
