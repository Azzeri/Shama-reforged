<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data clean-up based on the production ingredient list (dump 2026-11-18):
 *
 *  - moves ingredients into the new aisles (spices / flour & grains /
 *    sweet & baking) and gives the ones added later a real category,
 *  - fixes rows from a bulk insert on 2026-08-31 that lost Polish letters
 *    and used values the app doesn't know (purchase_timing "pantry",
 *    categories "Spices", "Oils", ...),
 *  - removes the duplicates from that insert (only if nothing uses them).
 *
 * Everything is matched by exact name, so ingredients that don't exist in a
 * given database are simply skipped. Anything left with an unknown
 * category/timing afterwards falls back to uncategorized/fresh.
 */
return new class extends Migration
{
    /** broken name => correct name */
    private const RENAMES = [
        'Groszek zielony mroony' => 'Groszek zielony mrożony',
        'Patki chilli' => 'Płatki chilli',
        'Komosa ryowa (Quinoa)' => 'Komosa ryżowa (Quinoa)',
    ];

    /** Duplicates from the broken bulk insert => the original they duplicate. */
    private const DUPLICATES = [
        'Ogrek wiey' => 'Ogórek świeży',
        'Sos sojowy' => 'Sos sojowy',
        'Czosnek granulowany' => 'Czosnek granulowany',
        'Olej rzepakowy' => 'Olej rzepakowy',
    ];

    /** name => [category, purchase_timing] */
    private const INGREDIENTS = [
        // Spices
        'Sól' => ['spices', 'advance'],
        'Pieprz czarny' => ['spices', 'advance'],
        'Bazylia suszona' => ['spices', 'advance'],
        'Oregano suszone' => ['spices', 'advance'],
        'Tymianek suszony' => ['spices', 'advance'],
        'Majeranek' => ['spices', 'advance'],
        'Kumin' => ['spices', 'advance'],
        'Zioła prowansalskie' => ['spices', 'advance'],
        'Czosnek granulowany' => ['spices', 'advance'],
        'Imbir mielony' => ['spices', 'advance'],
        'Płatki chilli' => ['spices', 'advance'],
        'Garam masala' => ['spices', 'advance'],

        // Flour, groats & grains
        'Płatki owsiane górskie' => ['grains', 'advance'],
        'Mąka pszenna typ 500' => ['grains', 'advance'],
        'Mąka kokosowa' => ['grains', 'advance'],
        'Kasza gryczana niepalona (biała)' => ['grains', 'advance'],
        'Kasza jęczmienna perłowa' => ['grains', 'advance'],
        'Kasza jęczmienna pęczak' => ['grains', 'advance'],
        'Ryż basmati' => ['grains', 'advance'],
        'Ryż do sushi' => ['grains', 'advance'],
        'Komosa ryżowa (Quinoa)' => ['grains', 'advance'],
        'Makaron pełnoziarnisty' => ['grains', 'advance'],
        'Bułka tarta' => ['grains', 'advance'],
        'Nasiona chia' => ['grains', 'advance'],
        'Sezam' => ['grains', 'advance'],

        // Sweet & baking
        'Cukier' => ['baking', 'advance'],
        'Erytrol' => ['baking', 'advance'],
        'Puder z erytrolu' => ['baking', 'advance'],
        'Ksylitol' => ['baking', 'advance'],
        'Aromat waniliowy' => ['baking', 'advance'],
        'Wanilia ekstrakt' => ['baking', 'advance'],
        'Kakao gorzkie' => ['baking', 'advance'],
        'Czekolada gorzka 70%' => ['baking', 'advance'],
        'Soda oczyszczona' => ['baking', 'advance'],
        'Skrobia ziemniaczana' => ['baking', 'advance'],
        'Wiórki kokosowe' => ['baking', 'advance'],
        'Drożdże świeże' => ['baking', 'advance'],
        'Syrop klonowy' => ['baking', 'advance'],
        'Miód' => ['baking', 'advance'],
        'Krem crispy hazelnut Nutlove' => ['baking', 'advance'],

        // Pantry: added later without a category / from the broken insert
        'Tuńczyk' => ['pantry', 'advance'],
        'Nori' => ['pantry', 'advance'],
        'Ocet ryżowy' => ['pantry', 'advance'],
        'Soczewica czerwona' => ['pantry', 'advance'],
        'Mleczko kokosowe' => ['pantry', 'advance'],
        'Pasta curry żółta/czerwona' => ['pantry', 'advance'],
        'Sos sojowy' => ['pantry', 'advance'],
        'Olej rzepakowy' => ['pantry', 'advance'],

        // Fridge / other
        'Masło klarowane' => ['dairy', 'advance'],
        'Tofu naturalne' => ['dairy', 'advance'],
        'Imbir świeży' => ['produce', 'fresh'],
        'Groszek zielony mrożony' => ['frozen', 'advance'],
    ];

    private const CATEGORIES = ['spices', 'grains', 'baking', 'pantry', 'bread', 'meat_fish', 'dairy', 'produce', 'frozen', 'uncategorized'];

    private const TIMINGS = ['fresh', 'advance'];

    public function up(): void
    {
        foreach (self::DUPLICATES as $duplicate => $original) {
            $this->removeDuplicate($duplicate, $original);
        }

        foreach (self::RENAMES as $from => $to) {
            if (! DB::table('ingredients')->where('name', $to)->exists()) {
                DB::table('ingredients')->where('name', $from)->update(['name' => $to]);
            }
        }

        foreach (self::INGREDIENTS as $name => [$category, $timing]) {
            DB::table('ingredients')->where('name', $name)->update([
                'category' => $category,
                'purchase_timing' => $timing,
            ]);
        }

        DB::table('ingredients')->whereNotIn('category', self::CATEGORIES)->update(['category' => 'uncategorized']);
        DB::table('ingredients')->whereNotIn('purchase_timing', self::TIMINGS)->update(['purchase_timing' => 'fresh']);
    }

    /**
     * Deletes the newest row named $duplicate when an older row named
     * $original exists and nothing references the duplicate.
     */
    private function removeDuplicate(string $duplicate, string $original): void
    {
        $originalId = DB::table('ingredients')->where('name', $original)->orderBy('id')->value('id');

        if (! $originalId) {
            return;
        }

        $duplicateIds = DB::table('ingredients')
            ->where('name', $duplicate)
            ->where('id', '!=', $originalId)
            ->pluck('id');

        foreach ($duplicateIds as $id) {
            $used = DB::table('recipe_ingredient_assignments')->where('ingredient_id', $id)->exists()
                || DB::table('shopping_list_items')->where('ingredient_id', $id)->exists();

            if (! $used) {
                DB::table('ingredients')->where('id', $id)->delete();
            }
        }
    }

    public function down(): void
    {
        // Data clean-up; nothing sensible to restore.
    }
};
