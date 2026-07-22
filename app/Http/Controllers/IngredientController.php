<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IngredientController extends Controller
{
    public function index(): View//out I guess
    {
        $ingredients = Ingredient::query()
            ->orderBy(Ingredient::NAME_COLUMN)
            ->paginate(20);

        return view('ingredients.index', [
            'ingredients' => $ingredients,
        ]);
    }

    public function create(): View
    {
        return view('ingredients.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            Ingredient::NAME_COLUMN => ['required', 'string', 'max:255', 'unique:ingredients,name'],
        ]);

        Ingredient::query()->create($validated);

        return redirect()
            ->route('ingredients.index')
            ->with('status', 'Składnik został dodany.');
    }

    public function edit(Ingredient $ingredient): View
    {
        return view('ingredients.edit', [
            'ingredient' => $ingredient,
        ]);
    }

    public function update(Request $request, Ingredient $ingredient): RedirectResponse
    {
        $validated = $request->validate([
            Ingredient::NAME_COLUMN => ['required', 'string', 'max:255', 'unique:ingredients,name,'.$ingredient->id],
        ]);

        $ingredient->update($validated);

        return redirect()
            ->route('ingredients.index')
            ->with('status', 'Składnik został zaktualizowany.');
    }

    public function destroy(Ingredient $ingredient): RedirectResponse
    {
        $ingredient->delete();

        return redirect()
            ->route('ingredients.index')
            ->with('status', 'Składnik został usunięty.');
    }
}
