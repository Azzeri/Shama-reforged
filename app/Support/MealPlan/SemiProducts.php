<?php

namespace App\Support\MealPlan;

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Support\ShoppingList\Quantity;
use Illuminate\Support\Collection;

/**
 * Semi-products: ingredients linked to a recipe that makes them. For a dish
 * using one there are three ways to get it:
 *  - buy:  the ready product goes on the shopping list (as any ingredient);
 *  - make: the linked recipe's ingredients go on the list instead;
 *  - have: nothing goes on the list (e.g. frozen stock).
 *
 * Which one applies, the later wins: the ingredient's default → the choice
 * remembered for the recipe → the choice made when planning the recipe.
 * "have" exists only on the last level.
 *
 * One level only: a semi-product inside a recipe for a semi-product is bought.
 */
class SemiProducts
{
    public const BUY = 'buy';

    public const MAKE = 'make';

    public const HAVE = 'have';

    /** Choices that can be remembered for a recipe. */
    public const SOURCES = [self::BUY, self::MAKE];

    /** Choices for one planned meal. */
    public const CHOICES = [self::BUY, self::MAKE, self::HAVE];

    /**
     * The recipe's ingredients that are semi-products (ingredients must be
     * loaded). An ingredient linked to the very recipe it is used in is not one.
     *
     * @return Collection<int, Ingredient>
     */
    public static function of(Recipe $recipe): Collection
    {
        return $recipe->ingredients
            ->filter(fn (Ingredient $ingredient) => $ingredient->isSemiProduct() && $ingredient->recipe_id !== $recipe->id)
            ->values();
    }

    /** buy | make for an ingredient of a recipe (pivot loaded): remembered for the recipe, else the ingredient's default. */
    public static function sourceFor(Ingredient $ingredient): string
    {
        $remembered = $ingredient->pivot?->semi_source;

        return in_array($remembered, self::SOURCES, true)
            ? $remembered
            : ($ingredient->homemade_default ? self::MAKE : self::BUY);
    }

    /**
     * Choices in effect for a planned recipe (pivot + ingredients loaded):
     * ingredient id => buy | make | have.
     *
     * @return array<int, string>
     */
    public static function choicesFor(Recipe $planned): array
    {
        $stored = (array) ($planned->pivot?->semi_choices ?? []);

        return self::of($planned)->mapWithKeys(fn (Ingredient $ingredient) => [
            $ingredient->id => in_array($stored[$ingredient->id] ?? null, self::CHOICES, true)
                ? $stored[$ingredient->id]
                : self::sourceFor($ingredient),
        ])->all();
    }

    /** "bulion warzywny: robię sam · pesto: mam już" — the choices other than buying, or null. */
    public static function caption(Recipe $planned): ?string
    {
        $choices = self::choicesFor($planned);

        $parts = self::of($planned)
            ->filter(fn (Ingredient $ingredient) => $choices[$ingredient->id] !== self::BUY)
            ->map(fn (Ingredient $ingredient) => mb_strtolower($ingredient->name).': '.($choices[$ingredient->id] === self::MAKE ? __('making it myself') : __('already have it')));

        return $parts->isEmpty() ? null : $parts->implode(' · ');
    }

    /**
     * Share of the linked recipe needed to make $amount $unit of the
     * semi-product: needed / what the recipe yields. 1.0 (the whole recipe)
     * when the yield is unknown or the units cannot be compared — amounts in
     * different units are compared by weight when the ingredient has unit
     * weights for them.
     */
    public static function ratio(Ingredient $semi, ?float $amount, ?string $unit): float
    {
        $yield = $semi->recipe_yield_amount !== null ? (float) $semi->recipe_yield_amount : null;

        if ($amount === null || $unit === null || $yield === null || $yield <= 0 || $semi->recipe_yield_unit === null) {
            return 1.0;
        }

        [$needed, $neededUnit] = Quantity::normalize($amount, $unit);
        [$made, $madeUnit] = Quantity::normalize($yield, $semi->recipe_yield_unit);

        if ($neededUnit === $madeUnit) {
            return $needed / $made;
        }

        $neededGrams = $neededUnit === 'g' ? $needed : $semi->gramsFor($neededUnit, $needed);
        $madeGrams = $madeUnit === 'g' ? $made : $semi->gramsFor($madeUnit, $made);

        return $neededGrams !== null && $madeGrams !== null && $madeGrams > 0 ? $neededGrams / $madeGrams : 1.0;
    }
}
