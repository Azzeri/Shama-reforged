<?php

namespace App\Support;

use Collator;
use Illuminate\Support\Collection;

/**
 * Long lists (recipes, ingredients) in Polish alphabetical order, split by
 * first letter: Ł comes after L and is its own group, like Ś and Ż.
 *
 * Uses intl's Collator; without the extension a simple Polish ordering
 * takes over, so a server missing intl never breaks the lists.
 */
final class Alphabet
{
    private const POLISH = 'aąbcćdeęfghijklłmnńoópqrsśtuvwxyzźż';

    private static ?Collator $collator = null;

    private static function collator(): ?Collator
    {
        if (! class_exists(Collator::class)) {
            return null;
        }

        return self::$collator ??= new Collator('pl_PL');
    }

    /** Fallback comparison: Polish letter order, case-insensitive. */
    private static function compare(string $a, string $b): int
    {
        $ka = self::key($a);
        $kb = self::key($b);

        for ($i = 0, $n = min(count($ka), count($kb)); $i < $n; $i++) {
            if ($ka[$i] !== $kb[$i]) {
                return $ka[$i] <=> $kb[$i];
            }
        }

        return count($ka) <=> count($kb) ?: strcmp($a, $b);
    }

    /** @return list<int> Spaces, digits and punctuation first, then Polish letters, then any other letter. */
    private static function key(string $name): array
    {
        static $order = null;
        $order ??= array_flip(mb_str_split(self::POLISH));

        return array_map(
            fn (string $char) => isset($order[$char]) ? 0x1000 + $order[$char] : (mb_ord($char) < 0x80 ? mb_ord($char) : 0x2000 + mb_ord($char)),
            mb_str_split(mb_strtolower($name)),
        );
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

        return $items->sort(fn ($a, $b) => $collator
            ? $collator->compare($name($a), $name($b))
            : self::compare($name($a), $name($b)))->values();
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
