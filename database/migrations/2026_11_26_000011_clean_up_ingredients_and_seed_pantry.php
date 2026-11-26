<?php

use App\Support\Ingredients\IngredientName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ingredient clean-up agreed with the owner on 2026-11-26 (production dump
 * of that day), plus starter data:
 *
 *  1. merges (recipes move to the remaining ingredient, amounts of the same
 *     unit in one recipe are summed), renames and two deletions;
 *  2. a few common ingredients and household supplies, the food ones linked
 *     to their ingredient;
 *  3. store section, when to buy and unit weights for the ingredients.
 *
 * Everything is addressed by name, so it does nothing where a name doesn't
 * exist (other databases) and can safely run again. Unit weights already set
 * by hand win over the ones below. Skipped on a database without recipes
 * (fresh install, tests). Not reversible (data clean-up).
 */
return new class extends Migration
{
    /** source => target; a missing target means the source is just renamed. */
    private const MERGES = [
        'Sok z cytryny' => 'Cytryna',
        'Skórka z cytryny' => 'Cytryna',
        'Aromat waniliowy' => 'Wanilia ekstrakt',
        'Puder z erytrolu' => 'Erytrol',
        'Majonez light' => 'Majonez',
        'Majonez wegański' => 'Majonez',
    ];

    private const RENAMES = [
        'Sok z limonki' => 'Limonka',
        'Jogurt naturalny 2%' => 'Jogurt naturalny',
        'Koper posiekany' => 'Koper',
        'Natka pietruszki posiekana' => 'Natka pietruszki',
        'Szczypiorek siekany' => 'Szczypiorek',
        'Pasta curry żółta/czerwona' => 'Pasta curry',
        'Kluski leniwe Proste Historie' => 'Kluski leniwe',
    ];

    /** Deleted only when no recipe uses them. */
    private const DELETES = ['Lody waniliowe', 'Komosa ryżowa (Quinoa)'];

    /**
     * name => [category, purchase timing, unit weights (grams per unit)].
     * null = leave as it is.
     */
    private const SETTINGS = [
        // Owoce i warzywa
        'Awokado' => ['produce', 'fresh', ['pcs' => 200]],
        'Banan' => ['produce', 'fresh', ['pcs' => 120]],
        'Bazylia świeża' => ['produce', 'fresh', ['pack' => 20]],
        'Borówki amerykańskie' => ['produce', 'fresh', ['pack' => 125]],
        'Brzoskwinia' => ['produce', 'fresh', ['pcs' => 150]],
        'Cebula' => ['produce', 'advance', ['pcs' => 120]],
        'Cebula czerwona' => ['produce', 'advance', ['pcs' => 120]],
        'Cebula dymka' => ['produce', 'fresh', ['bunch' => 150]],
        'Cukinia' => ['produce', 'fresh', ['pcs' => 300]],
        'Cytryna' => ['produce', 'advance', ['pcs' => 100]],
        'Czosnek świeży' => ['produce', 'advance', ['pcs' => 5]],
        'Groszek cukrowy' => ['produce', 'fresh', ['pack' => 150]],
        'Imbir świeży' => ['produce', 'advance', null],
        'Kalarepa' => ['produce', 'fresh', ['pcs' => 250]],
        'Kapusta biała młoda' => ['produce', 'fresh', null],
        'Koper' => ['produce', 'fresh', ['bunch' => 50]],
        'Limonka' => ['produce', 'advance', ['pcs' => 70]],
        'Maliny świeże lub mrożone' => ['produce', 'fresh', ['pack' => 125]],
        'Marchew' => ['produce', 'advance', ['pcs' => 100]],
        'Mięta' => ['produce', 'fresh', ['pack' => 20]],
        'Miks sałat' => ['produce', 'fresh', ['pack' => 125]],
        'Natka pietruszki' => ['produce', 'fresh', ['bunch' => 40]],
        'Pomidor' => ['produce', 'fresh', ['pcs' => 150]],
        'Pomidorki koktajlowe' => ['produce', 'fresh', ['pcs' => 15, 'pack' => 250]],
        'Por' => ['produce', 'fresh', ['pcs' => 200]],
        'Roszponka' => ['produce', 'fresh', ['pack' => 100]],
        'Rukola' => ['produce', 'fresh', ['pack' => 100]],
        'Rzodkiewka' => ['produce', 'fresh', ['pcs' => 15, 'bunch' => 200]],
        'Seler naciowy' => ['produce', 'fresh', ['pcs' => 40]],
        'Szczypiorek' => ['produce', 'fresh', ['bunch' => 30]],
        'Szpinak' => ['produce', 'fresh', ['pack' => 150]],
        'Ziemniaki' => ['produce', 'advance', ['pcs' => 100]],

        // Pieczywo
        'Bajgiel z sezamem' => ['bread', 'fresh', ['pcs' => 90]],
        'Bułka grahamka' => ['bread', 'fresh', ['pcs' => 70]],
        'Chleb żytni razowy' => ['bread', 'fresh', ['pcs' => 30]],
        'Pita' => ['bread', 'advance', ['pcs' => 70]],
        'Półbagietka' => ['bread', 'fresh', ['pcs' => 165]],
        'Schiacciata' => ['bread', 'fresh', ['pcs' => 160]],
        'Tortilla pełnoziarnista' => ['bread', 'fresh', ['pcs' => 60]],

        // Mięso, ryby i wędliny
        'Chorizo' => ['meat_fish', 'advance', ['pack' => 100]],
        'Mięso mielone z indyka' => ['meat_fish', 'fresh', ['pack' => 500]],
        'Mięso z piersi kurczaka' => ['meat_fish', 'fresh', ['pcs' => 220]],
        'Prosciutto Cotto' => ['meat_fish', 'advance', ['pack' => 100]],
        'Szynka z indyka' => ['meat_fish', 'advance', ['pack' => 100]],
        'Szynka z kurczaka' => ['meat_fish', 'advance', ['pack' => 100]],
        'Łosoś wędzony na zimno' => ['meat_fish', 'advance', ['pack' => 100]],

        // Nabiał i jajka
        'Burrata' => ['dairy', 'fresh', ['pcs' => 125]],
        'Jajko kurze całe' => ['dairy', 'advance', ['pcs' => 56]],
        'Jogurt grecki' => ['dairy', 'advance', ['tbsp' => 20, 'pack' => 400]],
        'Jogurt naturalny' => ['dairy', 'advance', ['tbsp' => 20]],
        'Jogurt naturalny gęsty' => ['dairy', 'advance', ['tbsp' => 20]],
        'Jogurt skyr' => ['dairy', 'advance', ['pack' => 150]],
        'Masło extra' => ['dairy', 'advance', ['tsp' => 5, 'tbsp' => 15, 'pack' => 200]],
        'Masło klarowane' => ['dairy', 'advance', ['tbsp' => 12]],
        'Mleko 1,5%' => ['dairy', 'advance', ['ml' => 1, 'l' => 1000, 'cup' => 250]],
        'Parmezan starty' => ['dairy', 'advance', ['tbsp' => 6]],
        'Ser camembert' => ['dairy', 'advance', ['pcs' => 120]],
        'Ser gouda' => ['dairy', 'advance', ['pack' => 150]],
        'Ser Grana Padano starty' => ['dairy', 'advance', ['tbsp' => 6]],
        'Ser halloumi' => ['dairy', 'advance', ['pack' => 200]],
        'Ser mozzarella kulka light' => ['dairy', 'advance', ['pcs' => 125]],
        'Ser twarogowy półtłusty' => ['dairy', 'advance', ['pack' => 250]],
        'Ser typu Feta' => ['dairy', 'advance', ['pack' => 200]],
        'Serek homogenizowany naturalny' => ['dairy', 'advance', ['pack' => 150]],
        'Serek wiejski naturalny' => ['dairy', 'advance', ['pack' => 200]],
        'Śmietana 12%' => ['dairy', 'advance', ['tbsp' => 15]],
        'Śmietana 18%' => ['dairy', 'advance', ['tbsp' => 15]],
        'Śmietanka 12%' => ['dairy', 'advance', ['tbsp' => 15, 'ml' => 1]],
        'Tofu naturalne' => ['dairy', 'advance', ['pack' => 180]],
        'Zsiadłe mleko' => ['dairy', 'advance', ['pack' => 400]],
        'Żółtko jaja' => ['dairy', 'advance', ['pcs' => 18]],

        // Mąka, kasze i zboża
        'Bułka tarta' => ['grains', 'advance', ['tbsp' => 8]],
        'Makaron pełnoziarnisty' => ['grains', 'advance', ['pack' => 500]],
        'Mąka kokosowa' => ['grains', 'advance', ['tbsp' => 8]],
        'Mąka pszenna typ 500' => ['grains', 'advance', ['tsp' => 3, 'tbsp' => 10, 'cup' => 160]],
        'Nasiona chia' => ['grains', 'advance', ['tsp' => 4, 'tbsp' => 12]],
        'Płatki owsiane górskie' => ['grains', 'advance', ['tbsp' => 6, 'cup' => 90]],
        'Sezam' => ['grains', 'advance', ['tsp' => 3, 'tbsp' => 9]],

        // Słodkie i do pieczenia
        'Cukier' => ['baking', 'advance', ['tsp' => 5, 'tbsp' => 15, 'cup' => 220]],
        'Czekolada gorzka 70%' => ['baking', 'advance', ['pack' => 100]],
        'Drożdże świeże' => ['baking', 'advance', ['pack' => 100]],
        'Erytrol' => ['baking', 'advance', ['tsp' => 4, 'tbsp' => 12]],
        'Kakao gorzkie' => ['baking', 'advance', ['tsp' => 3, 'tbsp' => 8]],
        'Krem crispy hazelnut Nutlove' => ['baking', 'advance', ['tbsp' => 15]],
        'Ksylitol' => ['baking', 'advance', ['tsp' => 4, 'tbsp' => 12]],
        'Miód' => ['baking', 'advance', ['tsp' => 8, 'tbsp' => 20]],
        'Skrobia ziemniaczana' => ['baking', 'advance', ['tsp' => 3, 'tbsp' => 10]],
        'Soda oczyszczona' => ['baking', 'advance', ['tsp' => 5]],
        'Syrop klonowy' => ['baking', 'advance', ['tbsp' => 20]],
        'Wanilia ekstrakt' => ['baking', 'advance', ['tsp' => 5]],
        'Wiórki kokosowe' => ['baking', 'advance', ['tbsp' => 6]],

        // Spiżarnia
        'Bulion warzywny' => ['pantry', 'advance', ['ml' => 1, 'l' => 1000]],
        'Chrzan (tarty)' => ['pantry', 'advance', ['tbsp' => 15]],
        'Ciecierzyca konserwowa' => ['pantry', 'advance', ['can' => 240]],
        'Dżem z czarnych porzeczek' => ['pantry', 'advance', ['tsp' => 8, 'tbsp' => 20]],
        'Gnocchi' => ['pantry', 'advance', ['pack' => 500]],
        'Hummus' => ['pantry', 'advance', ['tbsp' => 20, 'pack' => 200]],
        'Majonez' => ['pantry', 'advance', ['tsp' => 5, 'tbsp' => 15]],
        'Masło orzechowe' => ['pantry', 'advance', ['tsp' => 5, 'tbsp' => 16]],
        'Mleczko kokosowe' => ['pantry', 'advance', ['can' => 400]],
        'Nori' => ['pantry', 'advance', ['pcs' => 3]],
        'Ocet balsamiczny' => ['pantry', 'advance', ['tsp' => 5, 'tbsp' => 15]],
        'Ocet jabłkowy' => ['pantry', 'advance', ['tbsp' => 15]],
        'Ocet ryżowy' => ['pantry', 'advance', ['tbsp' => 15]],
        'Olej rzepakowy' => ['pantry', 'advance', ['tsp' => 4, 'tbsp' => 13]],
        'Oliwa z oliwek' => ['pantry', 'advance', ['tsp' => 4, 'tbsp' => 13]],
        'Orzechy włoskie' => ['pantry', 'advance', ['tbsp' => 8, 'pack' => 200]],
        'Passata pomidorowa' => ['pantry', 'advance', ['pack' => 500]],
        'Pasta curry' => ['pantry', 'advance', ['tsp' => 5, 'tbsp' => 15]],
        'Pomidory suszone w oleju (odsączone)' => ['pantry', 'advance', ['tbsp' => 15]],
        'Pomidory z puszki' => ['pantry', 'advance', ['can' => 400]],
        'Sos sojowy' => ['pantry', 'advance', ['tsp' => 5, 'tbsp' => 15]],
        'Sos sriracha' => ['pantry', 'advance', ['tsp' => 5, 'tbsp' => 15]],
        'Tuńczyk' => ['pantry', 'advance', ['can' => 120]],
        'Żurawina do mięs (sos żurawinowy)' => ['pantry', 'advance', ['tbsp' => 20]],

        // Przyprawy
        'Bazylia suszona' => ['spices', 'advance', ['tsp' => 1]],
        'Czosnek granulowany' => ['spices', 'advance', ['tsp' => 3]],
        'Garam masala' => ['spices', 'advance', ['tsp' => 2]],
        'Imbir mielony' => ['spices', 'advance', ['tsp' => 2]],
        'Kumin' => ['spices', 'advance', ['tsp' => 2]],
        'Majeranek' => ['spices', 'advance', ['tsp' => 1]],
        'Oregano suszone' => ['spices', 'advance', ['tsp' => 1]],
        'Pieprz czarny' => ['spices', 'advance', ['tsp' => 2]],
        'Płatki chilli' => ['spices', 'advance', ['tsp' => 1]],
        'Sól' => ['spices', 'advance', ['tsp' => 6]],
        'Tymianek suszony' => ['spices', 'advance', ['tsp' => 1]],
        'Zioła prowansalskie' => ['spices', 'advance', ['tsp' => 1]],

        // Mrożonki
        'Groszek zielony mrożony' => ['frozen', 'advance', ['pack' => 450]],
        'Kluski leniwe' => ['frozen', 'advance', null],

        // Woda: always at home, never on the list
        'Woda' => ['pantry', 'advance', ['ml' => 1, 'l' => 1000, 'cup' => 250]],
    ];

    /** New common ingredients: name => [category, timing, unit weights]. */
    private const NEW_INGREDIENTS = [
        'Papryka czerwona' => ['produce', 'fresh', ['pcs' => 200]],
        'Brokuł' => ['produce', 'fresh', ['pcs' => 400]],
        'Kalafior' => ['produce', 'fresh', ['pcs' => 700]],
        'Pieczarki' => ['produce', 'fresh', ['pack' => 500]],
        'Sałata lodowa' => ['produce', 'fresh', ['pcs' => 500]],
        'Jabłko' => ['produce', 'advance', ['pcs' => 180]],
        'Pomarańcza' => ['produce', 'advance', ['pcs' => 200]],
        'Dynia hokkaido' => ['produce', 'advance', ['pcs' => 1000]],
        'Mięso mielone wołowe' => ['meat_fish', 'fresh', ['pack' => 500]],
        'Udka z kurczaka' => ['meat_fish', 'fresh', ['pcs' => 250]],
        'Ser mozzarella' => ['dairy', 'advance', ['pcs' => 125]],
        'Śmietanka 30%' => ['dairy', 'advance', ['tbsp' => 15, 'ml' => 1]],
        'Napój owsiany' => ['dairy', 'advance', ['ml' => 1, 'l' => 1000, 'cup' => 250]],
        'Makaron spaghetti' => ['grains', 'advance', ['pack' => 500]],
        'Kasza jaglana' => ['grains', 'advance', ['cup' => 190]],
        'Kuskus' => ['grains', 'advance', ['cup' => 180]],
        'Proszek do pieczenia' => ['baking', 'advance', ['tsp' => 4]],
        'Koncentrat pomidorowy' => ['pantry', 'advance', ['tsp' => 6, 'tbsp' => 17]],
        'Fasola czerwona konserwowa' => ['pantry', 'advance', ['can' => 240]],
        'Kukurydza konserwowa' => ['pantry', 'advance', ['can' => 285]],
        'Musztarda' => ['pantry', 'advance', ['tsp' => 5, 'tbsp' => 15]],
        'Ketchup' => ['pantry', 'advance', ['tbsp' => 17]],
        'Papryka słodka mielona' => ['spices', 'advance', ['tsp' => 3]],
        'Papryka wędzona' => ['spices', 'advance', ['tsp' => 3]],
        'Cynamon' => ['spices', 'advance', ['tsp' => 3]],
        'Kurkuma' => ['spices', 'advance', ['tsp' => 3]],
        'Curry' => ['spices', 'advance', ['tsp' => 2]],
        'Liść laurowy' => ['spices', 'advance', null],
        'Ziele angielskie' => ['spices', 'advance', null],
        'Mieszanka warzyw na patelnię' => ['frozen', 'advance', ['pack' => 450]],
        'Szpinak mrożony' => ['frozen', 'advance', ['pack' => 450]],
    ];

    /** group => [product => linked ingredient (null = not a recipe ingredient)]. All start as "Jest". */
    private const PANTRY = [
        'Spiżarnia' => [
            'Mąka pszenna' => 'Mąka pszenna typ 500',
            'Cukier' => 'Cukier',
            'Erytrol' => 'Erytrol',
            'Płatki owsiane' => 'Płatki owsiane górskie',
            'Ryż basmati' => 'Ryż basmati',
            'Makaron' => 'Makaron pełnoziarnisty',
            'Kasza jęczmienna' => 'Kasza jęczmienna pęczak',
            'Olej rzepakowy' => 'Olej rzepakowy',
            'Oliwa z oliwek' => 'Oliwa z oliwek',
            'Ocet jabłkowy' => 'Ocet jabłkowy',
            'Ocet balsamiczny' => 'Ocet balsamiczny',
            'Sos sojowy' => 'Sos sojowy',
            'Miód' => 'Miód',
            'Bulion warzywny' => 'Bulion warzywny',
            'Pomidory z puszki' => 'Pomidory z puszki',
            'Passata pomidorowa' => 'Passata pomidorowa',
            'Koncentrat pomidorowy' => 'Koncentrat pomidorowy',
            'Bułka tarta' => 'Bułka tarta',
            'Kawa' => null,
            'Herbata' => null,
        ],
        'Przyprawy' => [
            'Oregano' => 'Oregano suszone',
            'Bazylia suszona' => 'Bazylia suszona',
            'Zioła prowansalskie' => 'Zioła prowansalskie',
            'Majeranek' => 'Majeranek',
            'Tymianek' => 'Tymianek suszony',
            'Kumin' => 'Kumin',
            'Czosnek granulowany' => 'Czosnek granulowany',
            'Papryka słodka' => 'Papryka słodka mielona',
            'Papryka wędzona' => 'Papryka wędzona',
            'Cynamon' => 'Cynamon',
            'Curry' => 'Curry',
            'Liść laurowy' => 'Liść laurowy',
            'Ziele angielskie' => 'Ziele angielskie',
        ],
        'Kuchnia' => [
            'Folia aluminiowa' => null,
            'Papier do pieczenia' => null,
            'Folia spożywcza' => null,
            'Woreczki strunowe' => null,
            'Ręczniki papierowe' => null,
            'Worki na śmieci' => null,
            'Płyn do naczyń' => null,
            'Tabletki do zmywarki' => null,
            'Gąbki do naczyń' => null,
        ],
        'Łazienka' => [
            'Papier toaletowy' => null,
            'Mydło w płynie' => null,
            'Pasta do zębów' => null,
            'Szampon' => null,
            'Żel pod prysznic' => null,
            'Dezodorant' => null,
        ],
        'Pranie i sprzątanie' => [
            'Kapsułki do prania' => null,
            'Płyn do płukania' => null,
            'Płyn do podłóg' => null,
            'Środek do łazienki' => null,
            'Płyn do szyb' => null,
        ],
    ];

    public function up(): void
    {
        // Only for a household's database: a fresh install (or the test
        // database) has no recipes and starts empty.
        if (! DB::table('recipes')->exists()) {
            return;
        }

        DB::transaction(function () {
            foreach (self::MERGES as $source => $target) {
                $this->merge($source, $target);
            }

            foreach (self::RENAMES as $from => $to) {
                $this->merge($from, $to);
            }

            foreach (self::DELETES as $name) {
                $ingredient = $this->find($name);

                if ($ingredient && ! DB::table('recipe_ingredient_assignments')->where('ingredient_id', $ingredient->id)->exists()) {
                    DB::table('ingredients')->where('id', $ingredient->id)->delete();
                }
            }

            foreach (self::NEW_INGREDIENTS as $name => [$category, $timing, $weights]) {
                if (! $this->find($name)) {
                    DB::table('ingredients')->insert([
                        'name' => $name,
                        'name_key' => IngredientName::fold($name),
                        'category' => $category,
                        'purchase_timing' => $timing,
                        'skip_shopping' => false,
                        'unit_weights' => $weights ? json_encode($weights) : null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            foreach (self::SETTINGS as $name => [$category, $timing, $weights]) {
                $this->configure($name, $category, $timing, $weights);
            }

            $this->seedPantry();
        });
    }

    private function find(string $name): ?object
    {
        return DB::table('ingredients')->where('name_key', IngredientName::fold($name))->first();
    }

    /**
     * Moves everything from $source to $target and deletes $source; when
     * $target doesn't exist yet, $source simply gets $target's name.
     */
    private function merge(string $sourceName, string $targetName): void
    {
        $source = $this->find($sourceName);

        if (! $source) {
            return;
        }

        $target = $this->find($targetName);

        if (! $target || $target->id === $source->id) {
            DB::table('ingredients')->where('id', $source->id)->update([
                'name' => $targetName,
                'name_key' => IngredientName::fold($targetName),
                'updated_at' => now(),
            ]);

            return;
        }

        foreach (DB::table('recipe_ingredient_assignments')->where('ingredient_id', $source->id)->get() as $row) {
            $existing = DB::table('recipe_ingredient_assignments')
                ->where('recipe_id', $row->recipe_id)->where('ingredient_id', $target->id)->first();

            if (! $existing) {
                DB::table('recipe_ingredient_assignments')->where('id', $row->id)->update(['ingredient_id' => $target->id]);

                continue;
            }

            if ($existing->unit === $row->unit && $row->amount !== null) {
                DB::table('recipe_ingredient_assignments')->where('id', $existing->id)
                    ->update(['amount' => (float) ($existing->amount ?? 0) + (float) $row->amount]);
            }

            DB::table('recipe_ingredient_assignments')->where('id', $row->id)->delete();
        }

        DB::table('shopping_list_items')->where('ingredient_id', $source->id)
            ->update(['ingredient_id' => $target->id, 'name' => $target->name]);
        DB::table('pantry_items')->where('ingredient_id', $source->id)->update(['ingredient_id' => $target->id]);
        DB::table('ingredients')->where('id', $source->id)->delete();
    }

    private function configure(string $name, ?string $category, ?string $timing, ?array $weights): void
    {
        $ingredient = $this->find($name);

        if (! $ingredient) {
            return;
        }

        $current = json_decode((string) $ingredient->unit_weights, true) ?: [];

        DB::table('ingredients')->where('id', $ingredient->id)->update(array_filter([
            'category' => $category,
            'purchase_timing' => $timing,
            // Weights someone already set by hand win.
            'unit_weights' => $weights ? json_encode($current + $weights) : null,
            'updated_at' => now(),
        ], fn ($value) => $value !== null));
    }

    private function seedPantry(): void
    {
        foreach (self::PANTRY as $groupName => $products) {
            $groupId = DB::table('pantry_groups')->where('name', $groupName)->value('id')
                ?? DB::table('pantry_groups')->insertGetId(['name' => $groupName, 'created_at' => now(), 'updated_at' => now()]);

            foreach ($products as $product => $ingredientName) {
                if (DB::table('pantry_items')->where('name', $product)->exists()) {
                    continue;
                }

                DB::table('pantry_items')->insert([
                    'pantry_group_id' => $groupId,
                    'name' => $product,
                    'ingredient_id' => $ingredientName ? $this->find($ingredientName)?->id : null,
                    'status' => 'ok',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Data clean-up: not reversible. Restore from a backup if needed.
    }
};
