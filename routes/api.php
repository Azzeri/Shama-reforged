<?php

use App\Http\Controllers\Api\MealPlanController;
use App\Http\Middleware\EnsureMealPlanApiToken;
use Illuminate\Support\Facades\Route;

Route::middleware([EnsureMealPlanApiToken::class, 'throttle:30,1'])->group(function () {
    Route::post('/meal-plan', [MealPlanController::class, 'store'])->name('api.meal-plan.store');
});
