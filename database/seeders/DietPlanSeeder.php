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
 * eat the same dish each slot), but portion sizes differ per person — each
 * ingredient records amount_me and amount_wife separately (either can be
 * null when that person's version of the recipe doesn't use it at all).
 * The shopping list sums both at generation time.
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
        'Skórka z cytryny', 'Koper posiekany', 'Mięso z piersi indyka',
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

        foreach ($definition['ingredients'] as $name => $amounts) {
            $ingredient = Ingredient::query()->firstOrCreate(
                ['name' => $name],
                ['purchase_timing' => $this->purchaseTimingFor($name)]
            );

            $recipe->ingredients()->attach($ingredient->id, [
                'amount_me' => $amounts['me'] ?? null,
                'amount_wife' => $amounts['wife'] ?? null,
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
                        'Płatki owsiane górskie' => ['me' => 50, 'wife' => 35],
                        'Nasiona chia' => ['me' => 10, 'wife' => 5],
                        'Mleko 1,5%' => ['me' => 200, 'wife' => 100],
                        'Krem crispy hazelnut Nutlove' => ['me' => 25, 'wife' => 10],
                        'Banan' => ['me' => 60, 'wife' => 60],
                        'Czekolada gorzka 70%' => ['me' => 10, 'wife' => 10],
                        'Orzechy włoskie' => ['me' => 5, 'wife' => 5],
                        'Erytrol' => ['me' => 5, 'wife' => 5],
                    ],
                    'macros' => ['me' => [619, 18, 28, 80], 'wife' => [409, 11, 17, 56]],
                ],
                'lunch' => [
                    'name' => 'Schiacciata w stylu włoskim',
                    'ingredients' => [
                        'Schiacciata' => ['me' => 80, 'wife' => 80],
                        'Prosciutto Cotto' => ['me' => 50, 'wife' => 25],
                        'Burrata' => ['me' => 60, 'wife' => 40],
                        'Pomidory suszone w oleju (odsączone)' => ['me' => 30, 'wife' => 20],
                        'Rukola' => ['me' => 15, 'wife' => 15],
                        'Oliwa z oliwek' => ['me' => 10, 'wife' => 3],
                    ],
                    'macros' => ['me' => [598, 25, 36, 45], 'wife' => [430, 18, 21, 42]],
                ],
                'dinner' => [
                    'name' => 'Młode ziemniaki ze schabowym, mizerią i zsiadłym mlekiem',
                    'ingredients' => [
                        'Ziemniaki' => ['me' => 280, 'wife' => 210],
                        'Sól' => ['me' => 0.5, 'wife' => 0.5],
                        'Koper posiekany' => ['me' => 8, 'wife' => 8],
                        'Schab wieprzowy' => ['me' => 250, 'wife' => 225],
                        'Mleko 1,5%' => ['me' => 30, 'wife' => 30],
                        'Czosnek świeży' => ['me' => 9, 'wife' => 6],
                        'Majeranek' => ['me' => 2, 'wife' => 2],
                        'Pieprz czarny' => ['me' => 0.25, 'wife' => 0.25],
                        'Jajko kurze całe' => ['me' => 56, 'wife' => 56],
                        'Mąka pszenna typ 500' => ['me' => 24, 'wife' => 12],
                        'Bułka tarta' => ['me' => 30, 'wife' => 20],
                        'Olej rzepakowy' => ['me' => 10, 'wife' => 5],
                        'Ogórek świeży' => ['me' => 150, 'wife' => 150],
                        'Śmietana 12%' => ['me' => 36, 'wife' => 18],
                        'Sok z cytryny' => ['me' => 3, 'wife' => 3],
                        'Erytrol' => ['me' => 5, 'wife' => 5],
                        'Szczypiorek siekany' => ['me' => 10, 'wife' => 5],
                        'Zsiadłe mleko' => ['me' => 300, 'wife' => 300],
                    ],
                    'macros' => ['me' => [589, 44, 21, 59], 'wife' => [313, 26, 11, 29]],
                ],
                'supper' => [
                    'name' => 'Jajecznica ze szpinakiem oraz suszonymi pomidorami',
                    'ingredients' => [
                        'Pomidory suszone w oleju (odsączone)' => ['me' => 20, 'wife' => 10],
                        'Oliwa z oliwek' => ['me' => 5, 'wife' => null],
                        'Szpinak' => ['me' => 25, 'wife' => 25],
                        'Jajko kurze całe' => ['me' => 168, 'wife' => 168],
                        'Chleb żytni razowy' => ['me' => 120, 'wife' => 60],
                        'Sól' => ['me' => 0.25, 'wife' => 0.25],
                        'Pieprz czarny' => ['me' => 0.25, 'wife' => 0.25],
                    ],
                    'macros' => ['me' => [601, 30, 26, 68], 'wife' => [399, 26, 19, 35]],
                ],
                'dessert' => [
                    'name' => 'Big milk + owoce',
                    'ingredients' => [
                        'Big milk' => ['me' => 57, 'wife' => 57],
                        'Borówki amerykańskie' => ['me' => 50, 'wife' => 50],
                    ],
                    'macros' => ['me' => [110, 2, 3, 20], 'wife' => [110, 2, 3, 20]],
                ],
            ],
            2 => [
                'breakfast' => [
                    'name' => 'Kakaowa kasza gryczana z owocami',
                    'ingredients' => [
                        'Kasza gryczana niepalona (biała)' => ['me' => 50, 'wife' => 30],
                        'Mleko 1,5%' => ['me' => 105, 'wife' => 125],
                        'Sól' => ['me' => 0.25, 'wife' => 0.25],
                        'Kakao gorzkie' => ['me' => 5, 'wife' => 5],
                        'Syrop klonowy' => ['me' => 5, 'wife' => 5],
                        'Borówki amerykańskie' => ['me' => 150, 'wife' => 100],
                        'Jogurt skyr' => ['me' => 150, 'wife' => 150],
                        'Orzechy włoskie' => ['me' => 25, 'wife' => 10],
                    ],
                    'macros' => ['me' => [601, 34, 20, 79], 'wife' => [413, 29, 10, 56]],
                ],
                'lunch' => [
                    'name' => 'Sałatka grecka z serem typu feta oraz pitą',
                    'ingredients' => [
                        'Ser typu Feta' => ['me' => 100, 'wife' => 75],
                        'Cebula czerwona' => ['me' => 40, 'wife' => 40],
                        'Pomidor' => ['me' => 80, 'wife' => 80],
                        'Ogórek świeży' => ['me' => 75, 'wife' => 75],
                        'Pomidorki koktajlowe' => ['me' => 150, 'wife' => 150],
                        'Miks sałat' => ['me' => 30, 'wife' => 30],
                        'Oliwki zielone' => ['me' => 36, 'wife' => 30],
                        'Oregano suszone' => ['me' => 1.5, 'wife' => 1.5],
                        'Bazylia suszona' => ['me' => 2, 'wife' => 2],
                        'Oliwa z oliwek' => ['me' => 5, 'wife' => 5],
                        'Sok z cytryny' => ['me' => 6, 'wife' => 6],
                        'Pita' => ['me' => 81, 'wife' => 27],
                    ],
                    'macros' => ['me' => [628, 28, 27, 67], 'wife' => [409, 19, 22, 34]],
                ],
                'dinner' => [
                    'name' => 'Szybka pieczona ryba',
                    'ingredients' => [
                        'Łosoś świeży (bez skóry)' => ['me' => 225, 'wife' => 110],
                        'Ryż basmati' => ['me' => 25, 'wife' => null],
                        'Pomidorki koktajlowe' => ['me' => 80, 'wife' => 100],
                        'Cukinia' => ['me' => 100, 'wife' => 100],
                        'Sól' => ['me' => 1, 'wife' => 1],
                        'Pieprz czarny' => ['me' => 0.25, 'wife' => 0.25],
                        'Miód' => ['me' => 6, 'wife' => 6],
                        'Sos sojowy' => ['me' => 10, 'wife' => 10],
                        'Sok z cytryny' => ['me' => 6, 'wife' => 6],
                        'Zioła prowansalskie' => ['me' => 3, 'wife' => null],
                    ],
                    'macros' => ['me' => [612, 49, 31, 32], 'wife' => [284, 25, 15, 12]],
                ],
                'supper' => [
                    'name' => 'Tosty francuskie z serem gouda oraz szynką z kurczaka',
                    'ingredients' => [
                        'Chleb żytni razowy' => ['me' => 120, 'wife' => 60],
                        'Jajko kurze całe' => ['me' => 56, 'wife' => 56],
                        'Sól' => ['me' => 0.25, 'wife' => 0.25],
                        'Bazylia suszona' => ['me' => 2, 'wife' => 2],
                        'Ser gouda' => ['me' => 45, 'wife' => 30],
                        'Szynka z kurczaka' => ['me' => 40, 'wife' => 40],
                        'Ogórek świeży' => ['me' => 150, 'wife' => 150],
                        'Oliwa z oliwek' => ['me' => 5, 'wife' => 5],
                    ],
                    'macros' => ['me' => [603, 37, 23, 67], 'wife' => [419, 29, 19, 36]],
                ],
                'dessert' => [
                    'name' => 'Smoothie malinowe',
                    'ingredients' => [
                        'Maliny świeże lub mrożone' => ['me' => 80, 'wife' => 80],
                        'Mleko 1,5%' => ['me' => 150, 'wife' => 150],
                    ],
                    'macros' => ['me' => [105, 6, 2, 17], 'wife' => [105, 6, 2, 17]],
                ],
            ],
            3 => [
                'breakfast' => [
                    'name' => 'Jogurt grecki z orzechami włoskimi oraz bananem',
                    'ingredients' => [
                        'Jogurt grecki' => ['me' => 160, 'wife' => 170],
                        'Dżem z czarnych porzeczek' => ['me' => 30, 'wife' => 15],
                        'Orzechy włoskie' => ['me' => 10, 'wife' => 5],
                        'Banan' => ['me' => 240, 'wife' => 60],
                        'Odżywka białkowa' => ['me' => 25, 'wife' => 30],
                    ],
                    'macros' => ['me' => [607, 29, 21, 77], 'wife' => [405, 31, 19, 29]],
                ],
                'lunch' => [
                    'name' => 'Wrap z pastą jajeczną, awokado oraz roszponką',
                    'ingredients' => [
                        'Tortilla pełnoziarnista' => ['me' => 60, 'wife' => 60],
                        'Jajko kurze całe' => ['me' => 112, 'wife' => 56],
                        'Jogurt naturalny 2%' => ['me' => 20, 'wife' => 20],
                        'Majonez light' => ['me' => 30, 'wife' => 10],
                        'Awokado' => ['me' => 70, 'wife' => 50],
                        'Sok z cytryny' => ['me' => 6, 'wife' => 6],
                        'Sól' => ['me' => 0.25, 'wife' => 0.25],
                        'Pieprz czarny' => ['me' => 0.25, 'wife' => 0.25],
                        'Roszponka' => ['me' => 25, 'wife' => 25],
                    ],
                    'macros' => ['me' => [582, 21, 38, 39], 'wife' => [402, 13, 23, 36]],
                ],
                'dinner' => [
                    'name' => 'Kurczak pieczony z warzywami i ziemniakami',
                    'ingredients' => [
                        'Mięso z piersi kurczaka' => ['me' => 175, 'wife' => 125],
                        'Ziemniaki' => ['me' => 210, 'wife' => 70],
                        'Seler naciowy' => ['me' => 45, 'wife' => 45],
                        'Marchew' => ['me' => 80, 'wife' => 40],
                        'Por' => ['me' => 70, 'wife' => 70],
                        'Czosnek świeży' => ['me' => 3, 'wife' => null],
                        'Cebula' => ['me' => 20, 'wife' => null],
                        'Oliwa z oliwek' => ['me' => 20, 'wife' => 10],
                        'Tymianek suszony' => ['me' => 1.5, 'wife' => 1.5],
                        'Sól' => ['me' => 0.25, 'wife' => 0.25],
                        'Pieprz czarny' => ['me' => 0.25, 'wife' => 0.25],
                        'Sos sojowy' => ['me' => 5, 'wife' => 5],
                        'Zioła prowansalskie' => ['me' => 3, 'wife' => 1.5],
                    ],
                    'macros' => ['me' => [598, 46, 23, 55], 'wife' => [320, 31, 12, 24]],
                ],
                'supper' => [
                    'name' => 'Lodowy deser ze skyrem i owocami',
                    'ingredients' => [
                        'Jogurt skyr' => ['me' => 150, 'wife' => 225],
                        'Lody waniliowe' => ['me' => 100, 'wife' => 100],
                        'Jagody świeże lub mrożone' => ['me' => 150, 'wife' => 100],
                        'Wiórki kokosowe' => ['me' => 15, 'wife' => 6],
                        'Płatki owsiane górskie' => ['me' => 40, 'wife' => null],
                    ],
                    'macros' => ['me' => [602, 27, 22, 81], 'wife' => [413, 30, 13, 48]],
                ],
                'dessert' => [
                    'name' => 'Sałatka arbuzowa',
                    'ingredients' => [
                        'Arbuz' => ['me' => 75, 'wife' => 75],
                        'Ser typu Feta' => ['me' => 25, 'wife' => 25],
                        'Mięta' => ['me' => 2, 'wife' => 2],
                        'Oliwa z oliwek' => ['me' => 2.5, 'wife' => 2.5],
                        'Pieprz czarny' => ['me' => 0.25, 'wife' => 0.25],
                    ],
                    'macros' => ['me' => [105, 5, 7, 7], 'wife' => [105, 5, 7, 7]],
                ],
            ],
            4 => [
                'breakfast' => [
                    'name' => 'Bananowa owsianka z malinami',
                    'ingredients' => [
                        'Płatki owsiane górskie' => ['me' => 50, 'wife' => 30],
                        'Maliny świeże lub mrożone' => ['me' => 50, 'wife' => 50],
                        'Mleko 1,5%' => ['me' => 200, 'wife' => 125],
                        'Banan' => ['me' => 120, 'wife' => 60],
                        'Orzechy włoskie' => ['me' => 20, 'wife' => 10],
                        'Jogurt skyr' => ['me' => 80, 'wife' => 150],
                    ],
                    'macros' => ['me' => [602, 27, 19, 86], 'wife' => [409, 29, 10, 56]],
                ],
                'lunch' => [
                    'name' => 'Bagietka z serem i rukolą',
                    'ingredients' => [
                        'Półbagietka' => ['me' => 110, 'wife' => 55],
                        'Serek śmietankowy' => ['me' => 75, 'wife' => 40],
                        'Proteinowy ser żółty Go Active' => ['me' => 45, 'wife' => 60],
                        'Rukola' => ['me' => 15, 'wife' => 15],
                        'Pomidor' => ['me' => 80, 'wife' => 40],
                        'Sól' => ['me' => 0.25, 'wife' => 0.25],
                        'Pieprz czarny' => ['me' => 0.26, 'wife' => 0.25],
                    ],
                    'macros' => ['me' => [607, 30, 23, 71], 'wife' => [390, 28, 15, 36]],
                ],
                'dinner' => [
                    'name' => 'Jednogarnkowe danie z indyka i warzyw',
                    'ingredients' => [
                        'Mięso z piersi indyka' => ['me' => 150, 'wife' => 200],
                        'Marchew' => ['me' => 80, 'wife' => 80],
                        'Kapusta biała młoda' => ['me' => 100, 'wife' => 100],
                        'Groszek cukrowy' => ['me' => 75, 'wife' => 75],
                        'Kasza jęczmienna pęczak' => ['me' => 50, 'wife' => 50],
                        'Bulion warzywny' => ['me' => 375, 'wife' => 375],
                        'Cebula' => ['me' => 40, 'wife' => 40],
                        'Natka pietruszki posiekana' => ['me' => 10, 'wife' => 10],
                        'Olej rzepakowy' => ['me' => 15, 'wife' => 10],
                        'Sól' => ['me' => 0.25, 'wife' => 0.25],
                        'Pieprz czarny' => ['me' => 0.25, 'wife' => 0.25],
                        'Tymianek suszony' => ['me' => 1.5, 'wife' => 1.5],
                    ],
                    'macros' => ['me' => [596, 44, 19, 69], 'wife' => [296, 27, 7, 34]],
                ],
                'supper' => [
                    'name' => 'Letnie ciasto z owocami',
                    'ingredients' => [
                        'Jajko kurze całe' => ['me' => 336, 'wife' => 336],
                        'Masło extra' => ['me' => 70, 'wife' => 60],
                        'Mąka kokosowa' => ['me' => 84, 'wife' => 84],
                        'Soda oczyszczona' => ['me' => 5, 'wife' => 5],
                        'Ocet jabłkowy' => ['me' => 10, 'wife' => 5],
                        'Jogurt grecki' => ['me' => 300, 'wife' => 300],
                        'Erytrol' => ['me' => 60, 'wife' => 60],
                        'Borówki amerykańskie' => ['me' => 300, 'wife' => 250],
                        'Sól' => ['me' => 0.25, 'wife' => 0.25],
                        'Sok z cytryny' => ['me' => 12, 'wife' => 12],
                        'Skórka z cytryny' => ['me' => 15, 'wife' => 12],
                        'Wanilia ekstrakt' => ['me' => 3, 'wife' => 3],
                    ],
                    'macros' => ['me' => [585, 24, 42, 36], 'wife' => [412, 18, 29, 25]],
                ],
                'dessert' => [
                    'name' => 'Mieszanka orzechów',
                    'ingredients' => [
                        'Mieszanka orzechów' => ['me' => 15, 'wife' => 15],
                    ],
                    'macros' => ['me' => [91, 3, 8, 3], 'wife' => [91, 3, 8, 3]],
                ],
            ],
            5 => [
                'breakfast' => [
                    'name' => 'Kanapki z hummusem, wędliną i warzywami',
                    'ingredients' => [
                        'Chleb żytni razowy' => ['me' => 120, 'wife' => 60],
                        'Szynka z kurczaka' => ['me' => 80, 'wife' => 70],
                        'Pomidor' => ['me' => 80, 'wife' => 80],
                        'Kalarepa' => ['me' => 85, 'wife' => 85],
                        'Ogórek świeży' => ['me' => 75, 'wife' => 75],
                        'Szczypiorek siekany' => ['me' => 10, 'wife' => 10],
                        'Hummus' => ['me' => 80, 'wife' => 60],
                    ],
                    'macros' => ['me' => [596, 34, 18, 85], 'wife' => [403, 27, 13, 51]],
                ],
                'lunch' => [
                    'name' => 'Kanapki z serkiem śmietankowym, pomidorem oraz szczypiorkiem',
                    'ingredients' => [
                        'Chleb żytni razowy' => ['me' => 90, 'wife' => 60],
                        'Serek śmietankowy' => ['me' => 75, 'wife' => 50],
                        'Pomidor' => ['me' => 80, 'wife' => 80],
                        'Szczypiorek siekany' => ['me' => 10, 'wife' => 10],
                        'Serek wiejski naturalny' => ['me' => 200, 'wife' => 150],
                    ],
                    'macros' => ['me' => [607, 36, 28, 59], 'wife' => [398, 24, 17, 40]],
                ],
                'dinner' => [
                    'name' => 'Makaron z kurczakiem w sosie śmietanowo-ziołowym',
                    'ingredients' => [
                        'Makaron pełnoziarnisty' => ['me' => 70, 'wife' => 25],
                        'Oliwa z oliwek' => ['me' => 10, 'wife' => 5],
                        'Mięso z piersi kurczaka' => ['me' => 150, 'wife' => 75],
                        'Cebula' => ['me' => 40, 'wife' => 40],
                        'Cukinia' => ['me' => 100, 'wife' => 100],
                        'Śmietanka 12%' => ['me' => 72, 'wife' => 54],
                        'Zioła prowansalskie' => ['me' => 1.5, 'wife' => 1.5],
                        'Sól' => ['me' => 0.25, 'wife' => 0.25],
                        'Pieprz czarny' => ['me' => 0.25, 'wife' => 0.25],
                        'Natka pietruszki posiekana' => ['me' => 2.5, 'wife' => 2.5],
                    ],
                    'macros' => ['me' => [623, 46, 23, 54], 'wife' => [317, 23, 14, 25]],
                ],
                'supper' => [
                    'name' => 'Sałatka z arbuzem, serem typu feta oraz brzoskwinią',
                    'ingredients' => [
                        'Szpinak' => ['me' => 75, 'wife' => 75],
                        'Brzoskwinia' => ['me' => 90, 'wife' => 45],
                        'Arbuz' => ['me' => 100, 'wife' => 50],
                        'Ser typu Feta' => ['me' => 100, 'wife' => 100],
                        'Orzechy włoskie' => ['me' => 20, 'wife' => null],
                        'Chleb żytni razowy' => ['me' => 30, 'wife' => 30],
                        'Oliwa z oliwek' => ['me' => 5, 'wife' => 5],
                        'Sok z limonki' => ['me' => 6, 'wife' => 6],
                        'Miód' => ['me' => 6, 'wife' => 6],
                    ],
                    'macros' => ['me' => [580, 25, 34, 47], 'wife' => [406, 22, 22, 33]],
                ],
                'dessert' => [
                    'name' => 'Borówki',
                    'ingredients' => [
                        'Borówki amerykańskie' => ['me' => 200, 'wife' => 200],
                    ],
                    'macros' => ['me' => [114, 1, 1, 29], 'wife' => [114, 1, 1, 29]],
                ],
            ],
            6 => [
                'breakfast' => [
                    'name' => 'Bajgiel z serem i chorizo',
                    'ingredients' => [
                        'Bajgiel z sezamem' => ['me' => 75, 'wife' => 75],
                        'Miks sałat' => ['me' => 25, 'wife' => 25],
                        'Ser gouda' => ['me' => 30, 'wife' => 15],
                        'Chorizo' => ['me' => 42, 'wife' => 14],
                        'Ogórek świeży' => ['me' => 75, 'wife' => 75],
                        'Jogurt naturalny 2%' => ['me' => 40, 'wife' => 20],
                        'Majonez' => ['me' => 10, 'wife' => 10],
                        'Sos sriracha' => ['me' => 5, 'wife' => 5],
                    ],
                    'macros' => ['me' => [601, 29, 34, 46], 'wife' => [414, 17, 19, 44]],
                ],
                'lunch' => [
                    'name' => 'Twarożek na chrupiącym chlebie z warzywami',
                    'ingredients' => [
                        'Ser twarogowy półtłusty' => ['me' => 120, 'wife' => 120],
                        'Jogurt naturalny gęsty' => ['me' => 40, 'wife' => 40],
                        'Chleb żytni razowy' => ['me' => 120, 'wife' => 60],
                        'Biała rzodkiew' => ['me' => 75, 'wife' => 50],
                        'Kalarepa' => ['me' => 75, 'wife' => 60],
                        'Oliwa z oliwek' => ['me' => 10, 'wife' => 5],
                        'Koper posiekany' => ['me' => 4, 'wife' => 4],
                        'Sól' => ['me' => 0.25, 'wife' => 0.25],
                        'Pieprz czarny' => ['me' => 0.25, 'wife' => 0.25],
                    ],
                    'macros' => ['me' => [585, 33, 19, 76], 'wife' => [395, 29, 13, 43]],
                ],
                'dinner' => [
                    'name' => 'Zupa z młodych ziemniaków',
                    'ingredients' => [
                        'Ziemniaki' => ['me' => 280, 'wife' => 280],
                        'Mięso z piersi kurczaka' => ['me' => 125, 'wife' => 125],
                        'Bulion warzywny' => ['me' => 500, 'wife' => 500],
                        'Cebula dymka' => ['me' => 40, 'wife' => 40],
                        'Koper posiekany' => ['me' => 16, 'wife' => 16],
                        'Śmietanka 12%' => ['me' => 72, 'wife' => 72],
                        'Oliwa z oliwek' => ['me' => 5, 'wife' => 5],
                        'Sól' => ['me' => 1.5, 'wife' => 1.5],
                        'Pieprz czarny' => ['me' => 1.5, 'wife' => 1.5],
                        'Boczek parzony' => ['me' => 30, 'wife' => 30],
                    ],
                    'macros' => ['me' => [615, 43, 23, 61], 'wife' => [307, 22, 12, 31]],
                ],
                'supper' => [
                    'name' => 'Orzeźwiające smoothie z truskawkami oraz jogurtem skyr',
                    'ingredients' => [
                        'Truskawki, świeże lub mrożone' => ['me' => 250, 'wife' => 200],
                        'Jogurt skyr' => ['me' => 150, 'wife' => 225],
                        'Płatki owsiane górskie' => ['me' => 40, 'wife' => 10],
                        'Miód' => ['me' => 24, 'wife' => 18],
                        'Masło orzechowe' => ['me' => 30, 'wife' => 20],
                    ],
                    'macros' => ['me' => [577, 31, 19, 79], 'wife' => [416, 34, 12, 51]],
                ],
                'dessert' => [
                    'name' => 'Sorbet cytrynowy',
                    'ingredients' => [
                        'Cytryna' => ['me' => 160, 'wife' => 160],
                        'Banan' => ['me' => 120, 'wife' => 120],
                        'Woda' => ['me' => 75, 'wife' => 75],
                        'Ksylitol' => ['me' => 75, 'wife' => 75],
                    ],
                    'macros' => ['me' => [90, 1, 0, 30], 'wife' => [90, 1, 0, 30]],
                ],
            ],
            7 => [
                'breakfast' => [
                    'name' => 'Szybka szakszuka',
                    'ingredients' => [
                        'Pomidory z puszki' => ['me' => 300, 'wife' => 200],
                        'Jajko kurze całe' => ['me' => 224, 'wife' => 168],
                        'Cebula' => ['me' => 80, 'wife' => 40],
                        'Czosnek świeży' => ['me' => 12, 'wife' => 6],
                        'Kumin' => ['me' => 1, 'wife' => 1],
                        'Sól' => ['me' => 0.25, 'wife' => 0.25],
                        'Pieprz czarny' => ['me' => 0.25, 'wife' => 0.25],
                        'Oliwa z oliwek' => ['me' => 5, 'wife' => 2.5],
                        'Chleb żytni razowy' => ['me' => 60, 'wife' => 30],
                        'Natka pietruszki posiekana' => ['me' => 5, 'wife' => 5],
                    ],
                    'macros' => ['me' => [612, 37, 30, 53], 'wife' => [399, 26, 21, 29]],
                ],
                'lunch' => [
                    'name' => 'Kanapka z łososiem',
                    'ingredients' => [
                        'Bułka grahamka' => ['me' => 140, 'wife' => 70],
                        'Łosoś wędzony na zimno' => ['me' => 60, 'wife' => 75],
                        'Serek śmietankowy' => ['me' => 40, 'wife' => 30],
                        'Chrzan (tarty)' => ['me' => 20, 'wife' => 20],
                        'Koper posiekany' => ['me' => 12, 'wife' => 12],
                        'Kiełki rzodkiewki' => ['me' => 10, 'wife' => 10],
                        'Ogórek świeży' => ['me' => 150, 'wife' => 75],
                    ],
                    'macros' => ['me' => [607, 31, 17, 89], 'wife' => [413, 26, 15, 47]],
                ],
                'dinner' => [
                    'name' => 'Leniwe odsmażone na maśle z jagodami i śmietaną',
                    'ingredients' => [
                        'Kluski leniwe Proste Historie' => ['me' => 225, 'wife' => 110],
                        'Śmietana 12%' => ['me' => 40, 'wife' => null],
                        'Jogurt skyr' => ['me' => 150, 'wife' => 75],
                        'Sól' => ['me' => 0.25, 'wife' => 0.25],
                        'Erytrol' => ['me' => 15, 'wife' => 10],
                        'Masło extra' => ['me' => 5, 'wife' => 5],
                        'Jagody świeże lub mrożone' => ['me' => 100, 'wife' => 75],
                    ],
                    'macros' => ['me' => [612, 41, 14, 82], 'wife' => [306, 20, 7, 42]],
                ],
                'supper' => [
                    'name' => 'Racuchy podawane z owocami',
                    'ingredients' => [
                        'Mleko 1,5%' => ['me' => 125, 'wife' => 125],
                        'Drożdże świeże' => ['me' => 15, 'wife' => 15],
                        'Cukier' => ['me' => 5, 'wife' => 5],
                        'Mąka pszenna typ 500' => ['me' => 160, 'wife' => 160],
                        'Jogurt skyr' => ['me' => 150, 'wife' => 150],
                        'Jajko kurze całe' => ['me' => 56, 'wife' => 56],
                        'Wanilia ekstrakt' => ['me' => 3, 'wife' => 3],
                        'Sól' => ['me' => 0.25, 'wife' => 0.25],
                        'Erytrol' => ['me' => 20, 'wife' => 20],
                        'Olej rzepakowy' => ['me' => 25, 'wife' => 25],
                        'Borówki amerykańskie' => ['me' => 175, 'wife' => 175],
                        'Truskawki, świeże lub mrożone' => ['me' => 175, 'wife' => 175],
                        'Puder z erytrolu' => ['me' => 10, 'wife' => 10],
                    ],
                    'macros' => ['me' => [602, 25, 18, 89], 'wife' => [401, 17, 12, 59]],
                ],
                'dessert' => [
                    'name' => 'Bób',
                    'ingredients' => [
                        'Bób świeży' => ['me' => 150, 'wife' => 150],
                        'Sól' => ['me' => 0.25, 'wife' => 0.25],
                    ],
                    'macros' => ['me' => [114, 11, 1, 21], 'wife' => [114, 11, 1, 21]],
                ],
            ],
        ];
    }
}
