<?php

namespace Database\Seeders;

use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\Recipe;
use App\Models\Tag;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Seeds the Respo 7-day diet plan (2026-08-10 .. 2026-08-16) for both
 * profiles. Recipes are shared by name across the two plans (both people
 * eat the same dish each slot), but portion sizes differ — Mariusz's
 * (larger) gram amounts are used as the actual recipe/shopping-list
 * quantities, while both people's per-serving macros are recorded via the
 * existing dual nutrition profile columns.
 */
class DietPlanSeeder extends Seeder
{
    private const WEEK_START = '2026-08-10';

    private const MEAL_SLOTS = ['breakfast', 'lunch', 'dinner', 'supper', 'dessert'];

    /**
     * Ingredients bought fresh the day before use. Everything not listed
     * here defaults to "advance" (pantry / long shelf life) — see
     * purchaseTimingFor().
     */
    private const FRESH_INGREDIENTS = [
        'Mleko 1,5%', 'Banan', 'Rukola', 'Schiacciata', 'Prosciutto Cotto', 'Burrata',
        'Schab wieprzowy', 'Jajko kurze całe', 'Ogórek świeży', 'Śmietana 12%',
        'Sok z cytryny', 'Szczypiorek siekany', 'Zsiadłe mleko', 'Szpinak',
        'Chleb żytni razowy', 'Borówki amerykańskie', 'Jogurt skyr', 'Ser typu Feta',
        'Pomidor', 'Pomidorki koktajlowe', 'Miks sałat', 'Pita', 'Łosoś świeży (bez skóry)',
        'Cukinia', 'Ser gouda', 'Szynka z kurczaka', 'Maliny świeże lub mrożone',
        'Jogurt grecki', 'Tortilla pełnoziarnista', 'Jogurt naturalny 2%', 'Awokado',
        'Roszponka', 'Mięso z piersi kurczaka', 'Seler naciowy', 'Por', 'Jagody świeże lub mrożone',
        'Arbuz', 'Mięta', 'Kalarepa', 'Hummus', 'Serek śmietankowy', 'Serek wiejski naturalny',
        'Śmietanka 12%', 'Natka pietruszki posiekana', 'Brzoskwinia', 'Sok z limonki',
        'Bajgiel z sezamem', 'Chorizo', 'Jogurt naturalny gęsty', 'Ser twarogowy półtłusty',
        'Biała rzodkiew', 'Cebula dymka', 'Boczek parzony', 'Truskawki, świeże lub mrożone',
        'Cytryna', 'Bułka grahamka', 'Łosoś wędzony na zimno', 'Kiełki rzodkiewki',
        'Kluski leniwe Proste Historie', 'Masło extra', 'Drożdże świeże', 'Bób świeży',
        'Skórka z cytryny', 'Koper posiekany', 'Mięso z piersi indyka', 'Sałatka arbuzowa',
        'Półbagietka', 'Proteinowy ser żółty Go Active',
    ];

    public function run(): void
    {
        $tags = collect(self::MEAL_SLOTS)->mapWithKeys(fn (string $type) => [
            $type => Tag::query()->firstOrCreate(
                ['name' => Meal::typeLabel($type)],
                ['category' => Tag::MEAL_TYPE]
            ),
        ]);

        $weekStart = Carbon::parse(self::WEEK_START);

        foreach ($this->days() as $dayIndex => $meals) {
            $date = $weekStart->copy()->addDays($dayIndex - 1);

            foreach ($meals as $mealType => $definition) {
                $recipe = $this->createRecipe($definition);
                $recipe->tags()->attach($tags[$mealType]->id);

                $meal = Meal::create([
                    'type' => $mealType,
                    'date' => $date,
                ]);
                $meal->recipes()->attach($recipe->id);
            }
        }
    }

    private function createRecipe(array $definition): Recipe
    {
        [$caloriesMe, $proteinMe, $fatMe, $carbsMe] = $definition['macros']['me'];
        [$caloriesWife, $proteinWife, $fatWife, $carbsWife] = $definition['macros']['wife'];

        $recipe = Recipe::create([
            'name' => $definition['name'],
            'calories_me' => $caloriesMe,
            'protein_me' => $proteinMe,
            'fat_me' => $fatMe,
            'carbs_me' => $carbsMe,
            'calories_wife' => $caloriesWife,
            'protein_wife' => $proteinWife,
            'fat_wife' => $fatWife,
            'carbs_wife' => $carbsWife,
        ]);

        foreach ($definition['ingredients'] as $name => $grams) {
            $ingredient = Ingredient::query()->firstOrCreate(
                ['name' => $name],
                ['purchase_timing' => $this->purchaseTimingFor($name)]
            );

            $recipe->ingredients()->attach($ingredient->id, [
                'amount' => $grams,
                'unit' => 'g',
            ]);
        }

        return $recipe;
    }

    private function purchaseTimingFor(string $name): string
    {
        return in_array($name, self::FRESH_INGREDIENTS, true)
            ? Ingredient::PURCHASE_TIMING_FRESH
            : Ingredient::PURCHASE_TIMING_ADVANCE;
    }

    private function days(): array
    {
        return [
            1 => [
                'breakfast' => [
                    'name' => 'Orzechowa nocna owsianka z kremem orzechowym',
                    'ingredients' => [
                        'Płatki owsiane górskie' => 50, 'Nasiona chia' => 10, 'Mleko 1,5%' => 200,
                        'Krem crispy hazelnut Nutlove' => 25, 'Banan' => 60, 'Czekolada gorzka 70%' => 10,
                        'Orzechy włoskie' => 5, 'Erytrol' => 5,
                    ],
                    'macros' => ['me' => [619, 18, 28, 80], 'wife' => [409, 11, 17, 56]],
                ],
                'lunch' => [
                    'name' => 'Schiacciata w stylu włoskim',
                    'ingredients' => [
                        'Schiacciata' => 80, 'Prosciutto Cotto' => 50, 'Burrata' => 60,
                        'Pomidory suszone w oleju (odsączone)' => 30, 'Rukola' => 15, 'Oliwa z oliwek' => 10,
                    ],
                    'macros' => ['me' => [598, 25, 36, 45], 'wife' => [430, 18, 21, 42]],
                ],
                'dinner' => [
                    'name' => 'Młode ziemniaki ze schabowym, mizerią i zsiadłym mlekiem',
                    'ingredients' => [
                        'Ziemniaki' => 280, 'Sól' => 0.5, 'Koper posiekany' => 8, 'Schab wieprzowy' => 250,
                        'Mleko 1,5%' => 30, 'Czosnek świeży' => 9, 'Majeranek' => 2, 'Pieprz czarny' => 0.25,
                        'Jajko kurze całe' => 56, 'Mąka pszenna typ 500' => 24, 'Bułka tarta' => 30,
                        'Olej rzepakowy' => 10, 'Ogórek świeży' => 150, 'Śmietana 12%' => 36,
                        'Sok z cytryny' => 3, 'Erytrol' => 5, 'Szczypiorek siekany' => 10, 'Zsiadłe mleko' => 300,
                    ],
                    'macros' => ['me' => [589, 44, 21, 59], 'wife' => [313, 26, 11, 29]],
                ],
                'supper' => [
                    'name' => 'Jajecznica ze szpinakiem oraz suszonymi pomidorami',
                    'ingredients' => [
                        'Pomidory suszone w oleju (odsączone)' => 20, 'Oliwa z oliwek' => 5, 'Szpinak' => 25,
                        'Jajko kurze całe' => 168, 'Chleb żytni razowy' => 120, 'Sól' => 0.25, 'Pieprz czarny' => 0.25,
                    ],
                    'macros' => ['me' => [601, 30, 26, 68], 'wife' => [399, 26, 19, 35]],
                ],
                'dessert' => [
                    'name' => 'Big milk + owoce',
                    'ingredients' => ['Big milk' => 57, 'Borówki amerykańskie' => 50],
                    'macros' => ['me' => [110, 2, 3, 20], 'wife' => [110, 2, 3, 20]],
                ],
            ],
            2 => [
                'breakfast' => [
                    'name' => 'Kakaowa kasza gryczana z owocami',
                    'ingredients' => [
                        'Kasza gryczana niepalona (biała)' => 50, 'Mleko 1,5%' => 105, 'Sól' => 0.25,
                        'Kakao gorzkie' => 5, 'Syrop klonowy' => 5, 'Borówki amerykańskie' => 150,
                        'Jogurt skyr' => 150, 'Orzechy włoskie' => 25,
                    ],
                    'macros' => ['me' => [601, 34, 20, 79], 'wife' => [413, 29, 10, 56]],
                ],
                'lunch' => [
                    'name' => 'Sałatka grecka z serem typu feta oraz pitą',
                    'ingredients' => [
                        'Ser typu Feta' => 100, 'Cebula czerwona' => 40, 'Pomidor' => 80, 'Ogórek świeży' => 75,
                        'Pomidorki koktajlowe' => 150, 'Miks sałat' => 30, 'Oliwki zielone' => 36,
                        'Oregano suszone' => 1.5, 'Bazylia suszona' => 2, 'Oliwa z oliwek' => 5,
                        'Sok z cytryny' => 6, 'Pita' => 81,
                    ],
                    'macros' => ['me' => [628, 28, 27, 67], 'wife' => [409, 19, 22, 34]],
                ],
                'dinner' => [
                    'name' => 'Szybka pieczona ryba',
                    'ingredients' => [
                        'Łosoś świeży (bez skóry)' => 225, 'Ryż basmati' => 25, 'Pomidorki koktajlowe' => 80,
                        'Cukinia' => 100, 'Sól' => 1, 'Pieprz czarny' => 0.25, 'Miód' => 6, 'Sos sojowy' => 10,
                        'Sok z cytryny' => 6, 'Zioła prowansalskie' => 3,
                    ],
                    'macros' => ['me' => [612, 49, 31, 32], 'wife' => [284, 25, 15, 12]],
                ],
                'supper' => [
                    'name' => 'Tosty francuskie z serem gouda oraz szynką z kurczaka',
                    'ingredients' => [
                        'Chleb żytni razowy' => 120, 'Jajko kurze całe' => 56, 'Sól' => 0.25, 'Bazylia suszona' => 2,
                        'Ser gouda' => 45, 'Szynka z kurczaka' => 40, 'Ogórek świeży' => 150, 'Oliwa z oliwek' => 5,
                    ],
                    'macros' => ['me' => [603, 37, 23, 67], 'wife' => [419, 29, 19, 36]],
                ],
                'dessert' => [
                    'name' => 'Smoothie malinowe',
                    'ingredients' => ['Maliny świeże lub mrożone' => 80, 'Mleko 1,5%' => 150],
                    'macros' => ['me' => [105, 6, 2, 17], 'wife' => [105, 6, 2, 17]],
                ],
            ],
            3 => [
                'breakfast' => [
                    'name' => 'Jogurt grecki z orzechami włoskimi oraz bananem',
                    'ingredients' => [
                        'Jogurt grecki' => 160, 'Dżem z czarnych porzeczek' => 30, 'Orzechy włoskie' => 10,
                        'Banan' => 240, 'Odżywka białkowa' => 25,
                    ],
                    'macros' => ['me' => [607, 29, 21, 77], 'wife' => [405, 31, 19, 29]],
                ],
                'lunch' => [
                    'name' => 'Wrap z pastą jajeczną, awokado oraz roszponką',
                    'ingredients' => [
                        'Tortilla pełnoziarnista' => 60, 'Jajko kurze całe' => 112, 'Jogurt naturalny 2%' => 20,
                        'Majonez light' => 30, 'Awokado' => 70, 'Sok z cytryny' => 6, 'Sól' => 0.25,
                        'Pieprz czarny' => 0.25, 'Roszponka' => 25,
                    ],
                    'macros' => ['me' => [582, 21, 38, 39], 'wife' => [402, 13, 23, 36]],
                ],
                'dinner' => [
                    'name' => 'Kurczak pieczony z warzywami i ziemniakami',
                    'ingredients' => [
                        'Mięso z piersi kurczaka' => 175, 'Ziemniaki' => 210, 'Seler naciowy' => 45,
                        'Marchew' => 80, 'Por' => 70, 'Czosnek świeży' => 3, 'Cebula' => 20,
                        'Oliwa z oliwek' => 20, 'Tymianek suszony' => 1.5, 'Sól' => 0.25, 'Pieprz czarny' => 0.25,
                        'Sos sojowy' => 5, 'Zioła prowansalskie' => 3,
                    ],
                    'macros' => ['me' => [598, 46, 23, 55], 'wife' => [320, 31, 12, 24]],
                ],
                'supper' => [
                    'name' => 'Lodowy deser ze skyrem i owocami',
                    'ingredients' => [
                        'Jogurt skyr' => 150, 'Lody waniliowe' => 100, 'Jagody świeże lub mrożone' => 150,
                        'Wiórki kokosowe' => 15, 'Płatki owsiane górskie' => 40,
                    ],
                    'macros' => ['me' => [602, 27, 22, 81], 'wife' => [413, 30, 13, 48]],
                ],
                'dessert' => [
                    'name' => 'Sałatka arbuzowa',
                    'ingredients' => [
                        'Arbuz' => 75, 'Ser typu Feta' => 25, 'Mięta' => 2, 'Oliwa z oliwek' => 2.5,
                        'Pieprz czarny' => 0.25,
                    ],
                    'macros' => ['me' => [105, 5, 7, 7], 'wife' => [105, 5, 7, 7]],
                ],
            ],
            4 => [
                'breakfast' => [
                    'name' => 'Bananowa owsianka z malinami',
                    'ingredients' => [
                        'Płatki owsiane górskie' => 50, 'Maliny świeże lub mrożone' => 50, 'Mleko 1,5%' => 200,
                        'Banan' => 120, 'Orzechy włoskie' => 20, 'Jogurt skyr' => 80,
                    ],
                    'macros' => ['me' => [602, 27, 19, 86], 'wife' => [409, 29, 10, 56]],
                ],
                'lunch' => [
                    'name' => 'Bagietka z serem i rukolą',
                    'ingredients' => [
                        'Półbagietka' => 110, 'Serek śmietankowy' => 75, 'Proteinowy ser żółty Go Active' => 45,
                        'Rukola' => 15, 'Pomidor' => 80, 'Sól' => 0.25, 'Pieprz czarny' => 0.26,
                    ],
                    'macros' => ['me' => [607, 30, 23, 71], 'wife' => [390, 28, 15, 36]],
                ],
                'dinner' => [
                    'name' => 'Jednogarnkowe danie z indyka i warzyw',
                    'ingredients' => [
                        'Mięso z piersi indyka' => 150, 'Marchew' => 80, 'Kapusta biała młoda' => 100,
                        'Groszek cukrowy' => 75, 'Kasza jęczmienna pęczak' => 50, 'Bulion warzywny' => 375,
                        'Cebula' => 40, 'Natka pietruszki posiekana' => 10, 'Olej rzepakowy' => 15, 'Sól' => 0.25,
                        'Pieprz czarny' => 0.25, 'Tymianek suszony' => 1.5,
                    ],
                    'macros' => ['me' => [596, 44, 19, 69], 'wife' => [296, 27, 7, 34]],
                ],
                'supper' => [
                    'name' => 'Letnie ciasto z owocami',
                    'ingredients' => [
                        'Jajko kurze całe' => 336, 'Masło extra' => 70, 'Mąka kokosowa' => 84,
                        'Soda oczyszczona' => 5, 'Ocet jabłkowy' => 10, 'Jogurt grecki' => 300, 'Erytrol' => 60,
                        'Borówki amerykańskie' => 300, 'Sól' => 0.25, 'Sok z cytryny' => 12,
                        'Skórka z cytryny' => 15, 'Wanilia ekstrakt' => 3,
                    ],
                    'macros' => ['me' => [585, 24, 42, 36], 'wife' => [412, 18, 29, 25]],
                ],
                'dessert' => [
                    'name' => 'Mieszanka orzechów',
                    'ingredients' => ['Mieszanka orzechów' => 15],
                    'macros' => ['me' => [91, 3, 8, 3], 'wife' => [91, 3, 8, 3]],
                ],
            ],
            5 => [
                'breakfast' => [
                    'name' => 'Kanapki z hummusem, wędliną i warzywami',
                    'ingredients' => [
                        'Chleb żytni razowy' => 120, 'Szynka z kurczaka' => 80, 'Pomidor' => 80,
                        'Kalarepa' => 85, 'Ogórek świeży' => 75, 'Szczypiorek siekany' => 10, 'Hummus' => 80,
                    ],
                    'macros' => ['me' => [596, 34, 18, 85], 'wife' => [403, 27, 13, 51]],
                ],
                'lunch' => [
                    'name' => 'Kanapki z serkiem śmietankowym, pomidorem oraz szczypiorkiem',
                    'ingredients' => [
                        'Chleb żytni razowy' => 90, 'Serek śmietankowy' => 75, 'Pomidor' => 80,
                        'Szczypiorek siekany' => 10, 'Serek wiejski naturalny' => 200,
                    ],
                    'macros' => ['me' => [607, 36, 28, 59], 'wife' => [398, 24, 17, 40]],
                ],
                'dinner' => [
                    'name' => 'Makaron z kurczakiem w sosie śmietanowo-ziołowym',
                    'ingredients' => [
                        'Makaron pełnoziarnisty' => 70, 'Oliwa z oliwek' => 10, 'Mięso z piersi kurczaka' => 150,
                        'Cebula' => 40, 'Cukinia' => 100, 'Śmietanka 12%' => 72, 'Zioła prowansalskie' => 1.5,
                        'Sól' => 0.25, 'Pieprz czarny' => 0.25, 'Natka pietruszki posiekana' => 2.5,
                    ],
                    'macros' => ['me' => [623, 46, 23, 54], 'wife' => [317, 23, 14, 25]],
                ],
                'supper' => [
                    'name' => 'Sałatka z arbuzem, serem typu feta oraz brzoskwinią',
                    'ingredients' => [
                        'Szpinak' => 75, 'Brzoskwinia' => 90, 'Arbuz' => 100, 'Ser typu Feta' => 100,
                        'Orzechy włoskie' => 20, 'Chleb żytni razowy' => 30, 'Oliwa z oliwek' => 5,
                        'Sok z limonki' => 6, 'Miód' => 6,
                    ],
                    'macros' => ['me' => [580, 25, 34, 47], 'wife' => [406, 22, 22, 33]],
                ],
                'dessert' => [
                    'name' => 'Borówki',
                    'ingredients' => ['Borówki amerykańskie' => 200],
                    'macros' => ['me' => [114, 1, 1, 29], 'wife' => [114, 1, 1, 29]],
                ],
            ],
            6 => [
                'breakfast' => [
                    'name' => 'Bajgiel z serem i chorizo',
                    'ingredients' => [
                        'Bajgiel z sezamem' => 75, 'Miks sałat' => 25, 'Ser gouda' => 30, 'Chorizo' => 42,
                        'Ogórek świeży' => 75, 'Jogurt naturalny 2%' => 40, 'Majonez' => 10, 'Sos sriracha' => 5,
                    ],
                    'macros' => ['me' => [601, 29, 34, 46], 'wife' => [414, 17, 19, 44]],
                ],
                'lunch' => [
                    'name' => 'Twarożek na chrupiącym chlebie z warzywami',
                    'ingredients' => [
                        'Ser twarogowy półtłusty' => 120, 'Jogurt naturalny gęsty' => 40, 'Chleb żytni razowy' => 120,
                        'Biała rzodkiew' => 75, 'Kalarepa' => 75, 'Oliwa z oliwek' => 10, 'Koper posiekany' => 4,
                        'Sól' => 0.25, 'Pieprz czarny' => 0.25,
                    ],
                    'macros' => ['me' => [585, 33, 19, 76], 'wife' => [395, 29, 13, 43]],
                ],
                'dinner' => [
                    'name' => 'Zupa z młodych ziemniaków',
                    'ingredients' => [
                        'Ziemniaki' => 280, 'Mięso z piersi kurczaka' => 125, 'Bulion warzywny' => 500,
                        'Cebula dymka' => 40, 'Koper posiekany' => 16, 'Śmietanka 12%' => 72, 'Oliwa z oliwek' => 5,
                        'Sól' => 1.5, 'Pieprz czarny' => 1.5, 'Boczek parzony' => 30,
                    ],
                    'macros' => ['me' => [615, 43, 23, 61], 'wife' => [307, 22, 12, 31]],
                ],
                'supper' => [
                    'name' => 'Orzeźwiające smoothie z truskawkami oraz jogurtem skyr',
                    'ingredients' => [
                        'Truskawki, świeże lub mrożone' => 250, 'Jogurt skyr' => 150, 'Płatki owsiane górskie' => 40,
                        'Miód' => 24, 'Masło orzechowe' => 30,
                    ],
                    'macros' => ['me' => [577, 31, 19, 79], 'wife' => [416, 34, 12, 51]],
                ],
                'dessert' => [
                    'name' => 'Sorbet cytrynowy',
                    'ingredients' => ['Cytryna' => 160, 'Banan' => 120, 'Woda' => 75, 'Ksylitol' => 75],
                    'macros' => ['me' => [90, 1, 0, 30], 'wife' => [90, 1, 0, 30]],
                ],
            ],
            7 => [
                'breakfast' => [
                    'name' => 'Szybka szakszuka',
                    'ingredients' => [
                        'Pomidory z puszki' => 300, 'Jajko kurze całe' => 224, 'Cebula' => 80,
                        'Czosnek świeży' => 12, 'Kumin' => 1, 'Sól' => 0.25, 'Pieprz czarny' => 0.25,
                        'Oliwa z oliwek' => 5, 'Chleb żytni razowy' => 60, 'Natka pietruszki posiekana' => 5,
                    ],
                    'macros' => ['me' => [612, 37, 30, 53], 'wife' => [399, 26, 21, 29]],
                ],
                'lunch' => [
                    'name' => 'Kanapka z łososiem',
                    'ingredients' => [
                        'Bułka grahamka' => 140, 'Łosoś wędzony na zimno' => 60, 'Serek śmietankowy' => 40,
                        'Chrzan (tarty)' => 20, 'Koper posiekany' => 12, 'Kiełki rzodkiewki' => 10,
                        'Ogórek świeży' => 150,
                    ],
                    'macros' => ['me' => [607, 31, 17, 89], 'wife' => [413, 26, 15, 47]],
                ],
                'dinner' => [
                    'name' => 'Leniwe odsmażone na maśle z jagodami i śmietaną',
                    'ingredients' => [
                        'Kluski leniwe Proste Historie' => 225, 'Śmietana 12%' => 40, 'Jogurt skyr' => 150,
                        'Sól' => 0.25, 'Erytrol' => 15, 'Masło extra' => 5, 'Jagody świeże lub mrożone' => 100,
                    ],
                    'macros' => ['me' => [612, 41, 14, 82], 'wife' => [306, 20, 7, 42]],
                ],
                'supper' => [
                    'name' => 'Racuchy podawane z owocami',
                    'ingredients' => [
                        'Mleko 1,5%' => 125, 'Drożdże świeże' => 15, 'Cukier' => 5, 'Mąka pszenna typ 500' => 160,
                        'Jogurt skyr' => 150, 'Jajko kurze całe' => 56, 'Wanilia ekstrakt' => 3, 'Sól' => 0.25,
                        'Erytrol' => 20, 'Olej rzepakowy' => 25, 'Borówki amerykańskie' => 175,
                        'Truskawki, świeże lub mrożone' => 175, 'Puder z erytrolu' => 10,
                    ],
                    'macros' => ['me' => [602, 25, 18, 89], 'wife' => [401, 17, 12, 59]],
                ],
                'dessert' => [
                    'name' => 'Bób',
                    'ingredients' => ['Bób świeży' => 150, 'Sól' => 0.25],
                    'macros' => ['me' => [114, 11, 1, 21], 'wife' => [114, 11, 1, 21]],
                ],
            ],
        ];
    }
}
