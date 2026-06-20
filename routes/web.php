<?php

use App\Http\Controllers\MealController;
use App\Http\Controllers\RecipeController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::resource('recipes', RecipeController::class);
    Route::resource('meals', MealController::class);
    Route::get('meals/day/{date}', [MealController::class, 'day'])->name('meals.day');
});

require __DIR__.'/settings.php';
