<?php

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
    Route::livewire('/meals/day/{date}', 'pages::meal.day')->name('meals.day');

    Route::livewire('/shopping-list', 'pages::shopping-list.index')->name('shopping-list.index');
});

require __DIR__ . '/settings.php';
