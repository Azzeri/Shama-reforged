<?php

namespace App\Support\Ingredients;

use App\Models\Ingredient;
use Illuminate\Support\Collection;

/** Looks up existing ingredients by name (see IngredientName for the rules). */
class IngredientMatcher
{
    /** @var Collection<int, Ingredient>|null */
    private ?Collection $all = null;

    private function all(): Collection
    {
        return $this->all ??= Ingredient::query()->orderBy('name')->get(['id', 'name', 'name_key', 'category', 'purchase_timing']);
    }

    /** The ingredient that IS this name (same fold), if any. */
    public function same(string $name, ?int $exceptId = null): ?Ingredient
    {
        $fold = IngredientName::fold($name);

        return $fold === '' ? null : $this->all()->first(fn (Ingredient $i) => $i->id !== $exceptId && $i->name_key === $fold);
    }

    /** The ingredient with the same key (inflection aside): "pomidory" → Pomidor. */
    public function sameKey(string $name): ?Ingredient
    {
        $key = IngredientName::key($name);

        return $key === '' ? null : $this->all()->first(fn (Ingredient $i) => IngredientName::key($i->name) === $key);
    }

    /**
     * Suggestions while typing: names containing what was typed, plus similar
     * ones ("pomidory" → Pomidor), the closest first.
     *
     * @return Collection<int, array{ingredient: Ingredient, similar: bool}>
     */
    public function suggest(string $query, int $limit = 6, ?int $exceptId = null): Collection
    {
        $fold = IngredientName::fold($query);

        if (mb_strlen($fold) < 2) {
            return collect();
        }

        $key = IngredientName::key($query);

        return $this->all()
            ->reject(fn (Ingredient $i) => $i->id === $exceptId)
            ->map(function (Ingredient $i) use ($fold, $key, $query) {
                $distance = IngredientName::distance($query, $i->name);
                $contains = str_contains($i->name_key ?? IngredientName::fold($i->name), $fold)
                    || ($key !== '' && str_contains(IngredientName::key($i->name), $key));

                if ($distance === null && ! $contains) {
                    return null;
                }

                $starts = str_starts_with($i->name_key ?? '', $fold);

                return ['ingredient' => $i, 'similar' => $distance !== null, 'rank' => [$distance ?? 3, $starts ? 0 : 1, mb_strlen($i->name)]];
            })
            ->filter()
            ->sortBy('rank')
            ->take($limit)
            ->map(fn (array $row) => ['ingredient' => $row['ingredient'], 'similar' => $row['similar']])
            ->values();
    }

    /**
     * Pairs of existing ingredients that are almost surely the same thing
     * (same key: "Banan" / "Banany").
     *
     * @return Collection<int, array{0: Ingredient, 1: Ingredient}>
     */
    public function duplicatePairs(): Collection
    {
        $all = $this->all()->values();
        $pairs = collect();

        foreach ($all as $i => $a) {
            foreach ($all->slice($i + 1) as $b) {
                // Only names that differ by inflection / case / diacritics: typo
                // tolerance here flagged different products (Śmietana vs Śmietanka).
                if (IngredientName::key($a->name) === IngredientName::key($b->name)) {
                    $pairs->push([$a, $b]);
                }
            }
        }

        return $pairs;
    }
}
