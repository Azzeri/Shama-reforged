<?php

use App\Http\Controllers\MealController;
use App\Http\Controllers\IngredientController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\ShoppingListController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('meals.index');
    }

    return redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('ingredients', IngredientController::class)->except(['show']);
    Route::resource('recipes', RecipeController::class);
    Route::resource('meals', MealController::class);
    Route::get('meals/day/{date}', [MealController::class, 'day'])->name('meals.day');

    Route::get('shopping-list', [ShoppingListController::class, 'index'])->name('shopping-list.index');
    Route::post('shopping-list/items', [ShoppingListController::class, 'store'])->name('shopping-list.items.store');
    Route::patch('shopping-list/items/{shoppingListItem}/toggle', [ShoppingListController::class, 'toggle'])->name('shopping-list.items.toggle');
    Route::post('shopping-list/generate', [ShoppingListController::class, 'generate'])->name('shopping-list.generate');
});

require __DIR__.'/settings.php';
