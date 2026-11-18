<?php

namespace App\Http\Controllers\Api;

use App\Actions\MealPlan\ImportMealPlan;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MealPlanController extends Controller
{
    public function store(Request $request, ImportMealPlan $import): JsonResponse
    {
        return response()->json($import($request->all()));
    }
}
