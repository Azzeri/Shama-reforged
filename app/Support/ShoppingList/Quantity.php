<?php

namespace App\Support\ShoppingList;

/**
 * Amount + unit helpers for the shopping list: normalising to base units so
 * equal ingredients sum up, parsing what the user types and formatting for
 * display (Polish decimal comma, kg/l once amounts get large).
 */
class Quantity
{
    /** unit => [base unit, factor] */
    private const BASE_UNITS = [
        'kg' => ['g', 1000],
        'l' => ['ml', 1000],
    ];

    /** Unit spellings people type, mapped to the app's unit codes. */
    private const ALIASES = [
        'gr' => 'g', 'gram' => 'g', 'gramy' => 'g', 'gramów' => 'g',
        'kilo' => 'kg',
        'szt' => 'pcs', 'szt.' => 'pcs', 'sztuka' => 'pcs', 'sztuki' => 'pcs', 'sztuk' => 'pcs', 'pc' => 'pcs',
        'opak' => 'pack', 'opak.' => 'pack', 'op' => 'pack', 'op.' => 'pack', 'opakowanie' => 'pack', 'opakowania' => 'pack',
        'łyżka' => 'tbsp', 'łyżki' => 'tbsp', 'łyżek' => 'tbsp',
        'łyżeczka' => 'tsp', 'łyżeczki' => 'tsp', 'łyżeczek' => 'tsp',
        'szklanka' => 'cup', 'szklanki' => 'cup', 'szklanek' => 'cup',
        'puszka' => 'can', 'puszki' => 'can', 'puszek' => 'can',
        'pęczek' => 'bunch', 'pęczki' => 'bunch', 'pęczków' => 'bunch',
        'litr' => 'l', 'litry' => 'l', 'litrów' => 'l',
    ];

    /**
     * @return array{0: ?float, 1: ?string}
     */
    public static function normalize(?float $amount, ?string $unit): array
    {
        $unit = $unit !== null ? self::canonicalUnit($unit) : null;

        if ($amount !== null && $unit !== null && isset(self::BASE_UNITS[$unit])) {
            [$base, $factor] = self::BASE_UNITS[$unit];

            return [$amount * $factor, $base];
        }

        return [$amount, $unit];
    }

    public static function canonicalUnit(string $unit): string
    {
        $unit = mb_strtolower(trim($unit));

        return self::ALIASES[$unit] ?? $unit;
    }

    /**
     * "1,5 kg", "2 szt", "300 g". Grams/millilitres switch to kg/l from 1000.
     */
    public static function format(?float $amount, ?string $unit): ?string
    {
        if ($amount === null) {
            return null;
        }

        if ($unit === 'g' && $amount >= 1000) {
            [$amount, $unit] = [$amount / 1000, 'kg'];
        } elseif ($unit === 'ml' && $amount >= 1000) {
            [$amount, $unit] = [$amount / 1000, 'l'];
        }

        // Whole grams/millilitres are precise enough — except for tiny
        // amounts (spices), which would otherwise round down to "0 g".
        $number = match (true) {
            ! in_array($unit, ['g', 'ml'], true) => self::number($amount),
            $amount >= 10 => (string) round($amount),
            default => self::number(max(round($amount, 1), 0.1)),
        };

        return $unit ? "{$number} ".self::unitLabel($unit) : $number;
    }

    public static function number(float $value): string
    {
        return str_replace('.', ',', rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.'));
    }

    public static function unitLabel(string $unit): string
    {
        return match ($unit) {
            'pcs' => __('pcs'),
            'pack' => __('pack'),
            'tbsp' => __('tbsp'),
            'tsp' => __('tsp'),
            'cup' => __('cup'),
            'can' => __('can'),
            'bunch' => __('bunch'),
            default => $unit,
        };
    }

    /**
     * Parses "300 g", "1,5kg", "2 szt" into a normalised [amount, unit].
     * Returns null for free text that doesn't start with a number
     * ("a pinch", "small jar").
     *
     * @return array{0: float, 1: ?string}|null
     */
    public static function parse(string $text): ?array
    {
        if (! preg_match('/^\s*(\d+(?:[.,]\d+)?)\s*(\S*)\s*$/u', $text, $m)) {
            return null;
        }

        $amount = (float) str_replace(',', '.', $m[1]);
        $unit = $m[2] !== '' ? self::canonicalUnit($m[2]) : null;

        return self::normalize($amount, $unit);
    }
}
