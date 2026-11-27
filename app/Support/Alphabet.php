<?php

namespace App\Support;

use Collator;
use Illuminate\Support\Collection;

/**
 * Long lists (recipes, ingredients) in Polish alphabetical order, split by
 * first letter: Ł comes after L and is its own group, like Ś and Ż.
 */
final class Alphabet
{
    private static ?Collator $collator = null;

    private static function collator(): Collator
    {
        return self::$collator ??= new Collator('pl_PL');
    }

    /**
     * @template T
     *
     * @param  Collection<int, T>  $items
     * @param  callable(T): string  $name
     * @return Collection<int, T>
     */
    public static function sort(Collection $items, callable $name): Collection
    {
        $collator = self::collator();

        return $items->sort(fn ($a, $b) => $collator->compare($name($a), $name($b)))->values();
    }

    /** "Ł" for "Łosoś"; "#" for a name that doesn't start with a letter. */
    public static function letter(string $name): string
    {
        $first = mb_substr(ltrim($name), 0, 1);

        return $first !== '' && preg_match('/\p{L}/u', $first) ? mb_strtoupper($first) : '#';
    }

    /**
     * Letters that have items, in list order (the items must already be sorted).
     *
     * @return list<string>
     */
    public static function letters(Collection $items, callable $name): array
    {
        return $items->map(fn ($item) => self::letter($name($item)))->unique()->values()->all();
    }

    /** Element id of a letter header: "{prefix}-{code point}". */
    public static function anchor(string $prefix, string $letter): string
    {
        return $prefix.'-'.mb_ord($letter);
    }
}
