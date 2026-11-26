<?php

namespace App\Support\Ingredients;

/**
 * The one place that decides when two ingredient names mean the same thing.
 * Used by the recipe form suggestions, the duplicate filter and merging, the
 * link import and the pantry links.
 *
 *  - fold(): lower case, no Polish diacritics, single spaces — two names with
 *    the same fold are the same ingredient (saving the second is refused).
 *  - key(): fold + Polish inflection endings stripped from every word
 *    ("pomidory" → "pomidor") — the same key means "probably the same".
 */
class IngredientName
{
    private const DIACRITICS = [
        'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n', 'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z',
    ];

    /** Longest first; only stripped when at least 3 letters stay. */
    private const ENDINGS = ['ami', 'ach', 'owie', 'owi', 'ow', 'om', 'em', 'ie', 'y', 'i', 'a', 'e', 'u', 'o'];

    public static function fold(string $name): string
    {
        $name = strtr(mb_strtolower(trim($name)), self::DIACRITICS);
        $name = preg_replace('/[^a-z0-9%,.]+/u', ' ', $name);

        return trim(preg_replace('/\s+/', ' ', $name));
    }

    public static function key(string $name): string
    {
        return collect(explode(' ', self::fold($name)))
            ->filter()
            ->map(fn (string $word) => self::stem($word))
            ->implode(' ');
    }

    public static function stem(string $word): string
    {
        foreach (self::ENDINGS as $ending) {
            if (str_ends_with($word, $ending) && strlen($word) - strlen($ending) >= 3) {
                return substr($word, 0, -strlen($ending));
            }
        }

        return $word;
    }

    /**
     * Similar enough to ask "did you mean": the same key, one key starting
     * with the other (Pomidor / Pomidory suszone), or a small typo.
     */
    public static function similar(string $a, string $b): bool
    {
        $ka = self::key($a);
        $kb = self::key($b);

        if ($ka === '' || $kb === '') {
            return false;
        }

        // "Śmietana 12%" and "Śmietana 18%" are different products.
        if (preg_replace('/\D+/', ' ', $ka) !== preg_replace('/\D+/', ' ', $kb)) {
            return false;
        }

        if ($ka === $kb || str_starts_with($kb, $ka.' ') || str_starts_with($ka, $kb.' ')) {
            return true;
        }

        $shorter = min(strlen($ka), strlen($kb));

        return $shorter >= 5 && levenshtein($ka, $kb) <= ($shorter >= 9 ? 2 : 1);
    }

    /** Lower = closer. 0 same key, 1 prefix / extra word, 2 typo, null not similar. */
    public static function distance(string $a, string $b): ?int
    {
        $ka = self::key($a);
        $kb = self::key($b);

        if ($ka === $kb) {
            return 0;
        }

        if (! self::similar($a, $b)) {
            return null;
        }

        return str_starts_with($kb, $ka) || str_starts_with($ka, $kb) ? 1 : 2;
    }
}
