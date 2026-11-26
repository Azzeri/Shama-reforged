<?php

use App\Http\Controllers\LiveSyncController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('meals.index');
    }

    return redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('/recipes', 'pages::recipe.index')->name('recipes.index');
    Route::livewire('/recipes/create', 'pages::recipe.create')->name('recipes.create');
    Route::livewire('/recipes/{recipe}', 'pages::recipe.show')->name('recipes.show');
    Route::livewire('/recipes/{recipe}/edit', 'pages::recipe.edit')->name('recipes.edit');

    Route::livewire('/ingredients', 'pages::ingredient.index')->name('ingredients.index');
    Route::livewire('/meals', 'pages::meal.index')->name('meals.index');
    Route::livewire('/meals/day/{date}/edit', 'pages::meal.day-edit')->name('meals.day.edit');
    // The read-only day view is gone — old links land in the day editor.
    Route::get('/meals/day/{date}', fn (string $date) => redirect()->route('meals.day.edit', $date))->name('meals.day');

    Route::livewire('/shopping-list', 'pages::shopping-list.index')->name('shopping-list.index');
    Route::livewire('/shopping-list/lists/{customList}', 'pages::shopping-list.custom-list')->name('shopping-list.custom');

    // Tiny "has anything changed?" endpoint for screens shared by both phones.
    Route::get('/sync/{scope}/{id?}', LiveSyncController::class)
        ->whereNumber('id')
        ->name('live-sync');
});

require __DIR__.'/settings.php';
