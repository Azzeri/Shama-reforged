<?php

use App\Models\Ingredient;
use App\Models\PantryItem;
use App\Models\Recipe;

function runIngredientCleanup(): void
{
    (require database_path('migrations/2026_11_26_000011_clean_up_ingredients_and_seed_pantry.php'))->up();
}

test('the clean-up merges, renames, deletes only unused ingredients and links household supplies', function () {
    $lemon = Ingredient::query()->create(['name' => 'Cytryna']);
    $juice = Ingredient::query()->create(['name' => 'Sok z cytryny']);
    $lime = Ingredient::query()->create(['name' => 'Sok z limonki']);
    Ingredient::query()->create(['name' => 'Lody waniliowe']);
    $quinoa = Ingredient::query()->create(['name' => 'Komosa ryżowa (Quinoa)']);
    Ingredient::query()->create(['name' => 'Mąka pszenna typ 500']);

    $sorbet = Recipe::query()->create(['name' => 'Sorbet']);
    $sorbet->ingredients()->attach($lemon->id, ['amount' => 100, 'unit' => 'g']);
    $sorbet->ingredients()->attach($juice->id, ['amount' => 30, 'unit' => 'g']);
    $salad = Recipe::query()->create(['name' => 'Sałatka']);
    $salad->ingredients()->attach($juice->id, ['amount' => 12, 'unit' => 'g']);
    $salad->ingredients()->attach($lime->id, ['amount' => 10, 'unit' => 'g']);
    $salad->ingredients()->attach($quinoa->id, ['amount' => 50, 'unit' => 'g']);

    runIngredientCleanup();
    runIngredientCleanup(); // safe to run again

    $lemon->refresh();

    expect(Ingredient::findByName('Sok z cytryny'))->toBeNull()
        ->and($sorbet->fresh()->ingredients->sole()->pivot->amount)->toEqual(130.0)
        ->and($salad->fresh()->ingredients->pluck('name')->sort()->values()->all())->toBe(['Cytryna', 'Komosa ryżowa (Quinoa)', 'Limonka'])
        ->and(Ingredient::findByName('Lody waniliowe'))->toBeNull()
        ->and(Ingredient::findByName('Komosa ryżowa (Quinoa)'))->not->toBeNull() // used in a recipe → kept
        ->and($lemon->category)->toBe(Ingredient::CATEGORY_PRODUCE)
        ->and($lemon->unit_weights)->toBe(['pcs' => 100])
        ->and(Ingredient::findByName('Papryka czerwona'))->not->toBeNull()
        ->and(PantryItem::query()->where('name', 'Mąka pszenna')->sole()->ingredient?->name)->toBe('Mąka pszenna typ 500')
        ->and(PantryItem::query()->where('name', 'Papier toaletowy')->count())->toBe(1);
});

test('the clean-up does nothing on a database without recipes', function () {
    Ingredient::query()->create(['name' => 'Sok z cytryny']);

    runIngredientCleanup();

    expect(Ingredient::query()->pluck('name')->all())->toBe(['Sok z cytryny'])
        ->and(PantryItem::query()->count())->toBe(0);
});
