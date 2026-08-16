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
        'Ser camembert', 'Szynka z indyka', 'Rzodkiewka', 'Ser halloumi', 'Bazylia świeża',
        'Wiśnie, świeże lub mrożone', 'Żółtko jaja', 'Pstrąg strumieniowy',
        'Serek homogenizowany naturalny', 'Śmietana 18%', 'Mięso mielone z indyka',
        'Ser mozzarella kulka light',
    ];

    /**
     * Grocery-aisle category per ingredient, used to seed Ingredient::category.
     * Anything not listed here falls back to "uncategorized" — see categoryFor().
     */
    private const CATEGORY_INGREDIENTS = [
        Ingredient::CATEGORY_DAIRY => [
            'Burrata', 'Jajko kurze całe', 'Jogurt grecki', 'Jogurt naturalny 2%',
            'Jogurt naturalny gęsty', 'Jogurt skyr', 'Kluski leniwe Proste Historie',
            'Masło extra', 'Mleko 1,5%', 'Proteinowy ser żółty Go Active', 'Ser gouda',
            'Ser camembert', 'Ser Grana Padano starty', 'Ser halloumi', 'Ser mozzarella kulka light',
            'Ser twarogowy półtłusty', 'Ser typu Feta', 'Serek homogenizowany naturalny',
            'Serek wiejski naturalny', 'Serek śmietankowy', 'Zsiadłe mleko', 'Śmietana 12%',
            'Śmietana 18%', 'Śmietanka 12%', 'Żółtko jaja', 'Parmezan starty',
        ],
        Ingredient::CATEGORY_BREAD => [
            'Bajgiel z sezamem', 'Bułka grahamka', 'Chleb żytni razowy', 'Pita',
            'Półbagietka', 'Schiacciata', 'Tortilla pełnoziarnista',
        ],
        Ingredient::CATEGORY_MEAT_FISH => [
            'Boczek parzony', 'Chorizo', 'Łosoś wędzony na zimno', 'Łosoś świeży (bez skóry)',
            'Mięso mielone z indyka', 'Mięso z piersi indyka', 'Mięso z piersi kurczaka',
            'Prosciutto Cotto', 'Pstrąg strumieniowy', 'Schab wieprzowy', 'Szynka z indyka',
            'Szynka z kurczaka',
        ],
        Ingredient::CATEGORY_PRODUCE => [
            'Arbuz', 'Awokado', 'Banan', 'Bazylia świeża', 'Biała rzodkiew', 'Borówki amerykańskie',
            'Brzoskwinia', 'Bób świeży', 'Cebula', 'Cebula czerwona', 'Cebula dymka',
            'Cukinia', 'Cytryna', 'Czosnek świeży', 'Groszek cukrowy', 'Jagody świeże lub mrożone',
            'Kalarepa', 'Kapusta biała młoda', 'Kiełki rzodkiewki', 'Koper posiekany',
            'Maliny świeże lub mrożone', 'Marchew', 'Mięta', 'Miks sałat', 'Natka pietruszki posiekana',
            'Ogórek świeży', 'Pomidor', 'Pomidorki koktajlowe', 'Por', 'Roszponka', 'Rukola',
            'Rzodkiewka', 'Seler naciowy', 'Skórka z cytryny', 'Sok z cytryny', 'Sok z limonki',
            'Szczypiorek siekany', 'Szpinak', 'Truskawki, świeże lub mrożone', 'Wiśnie, świeże lub mrożone',
            'Ziemniaki',
        ],
        Ingredient::CATEGORY_FROZEN => [
            'Big milk', 'Lody waniliowe',
        ],
        Ingredient::CATEGORY_PANTRY => [
            'Aromat waniliowy', 'Bazylia suszona', 'Bulion warzywny', 'Bułka tarta', 'Chrzan (tarty)',
            'Ciecierzyca konserwowa', 'Cukier', 'Czekolada gorzka 70%', 'Czosnek granulowany',
            'Drożdże świeże', 'Dżem z czarnych porzeczek', 'Erytrol', 'Gnocchi',
            'Hummus', 'Kakao gorzkie', 'Kasza gryczana niepalona (biała)', 'Kasza jęczmienna pęczak',
            'Kasza jęczmienna perłowa', 'Krem crispy hazelnut Nutlove', 'Ksylitol', 'Kumin', 'Majeranek',
            'Majonez', 'Majonez light', 'Majonez wegański', 'Makaron pełnoziarnisty', 'Masło orzechowe',
            'Mieszanka orzechów', 'Miód', 'Mąka kokosowa', 'Mąka pszenna typ 500', 'Nasiona chia',
            'Ocet balsamiczny', 'Ocet jabłkowy', 'Odżywka białkowa', 'Olej rzepakowy', 'Oliwa z oliwek',
            'Oliwki zielone', 'Oregano suszone', 'Orzechy włoskie', 'Passata pomidorowa', 'Pieprz czarny',
            'Pomidory suszone w oleju (odsączone)', 'Pomidory z puszki', 'Puder z erytrolu',
            'Płatki owsiane górskie', 'Ryż basmati', 'Skrobia ziemniaczana', 'Soda oczyszczona',
            'Sos sojowy', 'Sos sriracha', 'Syrop klonowy', 'Sól', 'Tymianek suszony', 'Wanilia ekstrakt',
            'Wiórki kokosowe', 'Woda', 'Zioła prowansalskie', 'Żurawina do mięs (sos żurawinowy)',
        ],
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

        // Extra recipes kept in the cookbook without a calendar slot (not
        // part of the current week's plan).
        foreach ($this->extraRecipes() as $definition) {
            $this->createRecipe($definition);
        }
    }

    private function createRecipe(array $definition): Recipe
    {
        [$caloriesMe, $proteinMe, $fatMe, $carbsMe] = $definition['macros']['me'];
        [$caloriesWife, $proteinWife, $fatWife, $carbsWife] = $definition['macros']['wife'];

        $recipe = Recipe::create([
            'name' => $definition['name'],
            'content' => $definition['content'] ?? null,
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
                ['purchase_timing' => $this->purchaseTimingFor($name), 'category' => $this->categoryFor($name)]
            );

            $recipe->ingredients()->attach($ingredient->id, [
                'amount_me' => $amounts['me'] ?? null,
                'amount_wife' => $amounts['wife'] ?? null,
                'unit' => 'g',
            ]);
        }

        return $recipe;
    }

    private function extraRecipes(): array
    {
        return [
            [
                'name' => 'Kanapka z szynką z indyka',
                'content' => "1. Pieczywo smarujemy równomiernie żurawiną.\n2. Ser camembert kroimy w cienkie plastry.\n3. Na połowie pieczywa układamy kolejno: rukolę, szynkę z indyka oraz ser camembert.\n4. Całość przykrywamy pozostałym pieczywem i składamy kanapki.",
                'ingredients' => [
                    'Chleb żytni razowy' => ['me' => 120, 'wife' => 60],
                    'Żurawina do mięs (sos żurawinowy)' => ['me' => 30, 'wife' => 30],
                    'Rukola' => ['me' => 20, 'wife' => 20],
                    'Szynka z indyka' => ['me' => 30, 'wife' => 30],
                    'Ser camembert' => ['me' => 90, 'wife' => 60],
                ],
                'macros' => ['me' => [618, 30, 25, 75], 'wife' => [395, 21, 16, 44]],
            ],
            [
                'name' => 'Pasta z jajek z rzodkiewką, ogórkiem, szczypiorkiem, i pieczywem',
                'content' => "1. Jajka, gotujemy we wrzącej wodzie przez 7-8 minut, studzimy i obieramy.\n2. Obrane jajka, ścieramy na tarce, dodajemy szczypiorek, sól oraz majonez i całość mieszamy.\n3. Kroimy ogórka oraz rzodkiewkę na mniejsze kawałki.\n4. Pastę podajemy luzem z ogórkiem, rzodkiewką, chlebem razowym oraz rukolą. Opcjonalnie, możesz dodać rzodkiewkę do pasty i wymieszać. Smacznego!",
                'ingredients' => [
                    'Chleb żytni razowy' => ['me' => 90, 'wife' => 60],
                    'Rukola' => ['me' => 20, 'wife' => 10],
                    'Rzodkiewka' => ['me' => 60, 'wife' => 30],
                    'Majonez wegański' => ['me' => 40, 'wife' => 30],
                    'Ogórek świeży' => ['me' => 75, 'wife' => 75],
                    'Jajko kurze całe' => ['me' => 168, 'wife' => 112],
                    'Szczypiorek siekany' => ['me' => 15, 'wife' => 15],
                    'Sól' => ['me' => 0.3, 'wife' => 0.3],
                ],
                'macros' => ['me' => [596, 29, 30, 57], 'wife' => [410, 19, 21, 39]],
            ],
            [
                'name' => 'Sałatka z grillowanym serem halloumi',
                'content' => "1. Ser kroimy na plastry, grillujemy na dobrze rozgrzanym grillu lub na suchej patelni, na średnim ogniu do zarumienienia sera.\n2. Pomidora oraz ogórka kroimy w kostkę dowolnej wielkości. Cebulę kroimy w cienkie piórka.\n3. Sałatę przekładamy na talerz, dodajemy pokrojone warzywa.\n4. W małej miseczce mieszamy: ocet, wodę, sól oraz przeciśnięty przez praskę czosnek.\n5. Zgrillowany ser kładziemy na sałacie, polewamy przygotowanym sosem. Podajemy z pieczywem (z pieczywa możemy przygotować grzanki na rozgrzanej patelni lub w piekarniku - smażymy lub pieczemy do zarumienia).",
                'ingredients' => [
                    'Ser halloumi' => ['me' => 100, 'wife' => 75],
                    'Miks sałat' => ['me' => 40, 'wife' => 30],
                    'Pomidor' => ['me' => 160, 'wife' => 80],
                    'Ogórek świeży' => ['me' => 150, 'wife' => null],
                    'Cebula' => ['me' => 25, 'wife' => 20],
                    'Ocet balsamiczny' => ['me' => 10, 'wife' => 10],
                    'Woda' => ['me' => 7, 'wife' => 7],
                    'Sól' => ['me' => 0.3, 'wife' => 0.3],
                    'Czosnek świeży' => ['me' => 3, 'wife' => 3],
                    'Chleb żytni razowy' => ['me' => 90, 'wife' => 60],
                ],
                'macros' => ['me' => [596, 29, 27, 65], 'wife' => [411, 20, 20, 40]],
            ],
            [
                'name' => 'Bruschetta z pomidorem, bazylią świeżą oraz startym serem',
                'content' => "1. Pomidora, cebulę czerwoną oraz czosnek obieramy i kroimy w kostkę.\n2. Pokrojone warzywa mieszamy ze sobą. Dodajemy bazylię świeżą. Całość doprawiamy solą, pieprzem, octem oraz oliwą.\n3. Bagietkę kroimy na kromki i pieczemy w piekarniku rozgrzanym do 180 stopni przez około 5 minut, aż będą chrupkie.\n4. Na grzanki nakładamy warzywa, posypujemy startym serem.",
                'ingredients' => [
                    'Półbagietka' => ['me' => 110, 'wife' => 55],
                    'Pomidor' => ['me' => 320, 'wife' => 160],
                    'Cebula czerwona' => ['me' => 40, 'wife' => 40],
                    'Czosnek świeży' => ['me' => 6, 'wife' => 6],
                    'Bazylia świeża' => ['me' => 3, 'wife' => 1.5],
                    'Ser Grana Padano starty' => ['me' => 40, 'wife' => 35],
                    'Ocet balsamiczny' => ['me' => 6, 'wife' => 6],
                    'Oliwa z oliwek' => ['me' => 5, 'wife' => 5],
                    'Sól' => ['me' => 0.3, 'wife' => 0.3],
                    'Pieprz czarny' => ['me' => 0.3, 'wife' => 0.3],
                ],
                'macros' => ['me' => [607, 27, 19, 84], 'wife' => [401, 19, 17, 45]],
            ],
            [
                'name' => 'Dutch baby z duszonymi wiśniami oraz jogurtem skyr',
                'content' => "1. Piekarnik rozgrzewamy do 220°C góra-dół (200°C termoobieg). Do nagrzewania wkładamy pustą patelnię żeliwną lub naczynie do pieczenia o średnicy ok. 20–22 cm, ustawione na środkowej półce.\n2. Do wysokiego naczynia wbijamy jajka, dodajemy mleko, erytrol oraz sól. Ilość erytrolu możemy dopasować do własnych preferencji. Wsypujemy mąkę pszenną. Całość miksujemy mikserem lub trzepaczką przez ok. 30-40 sekund, do uzyskania gładkiego, dość rzadkiego ciasta bez grudek. Odstawiamy na 5 minut w temperaturze pokojowej.\n3. Ostrożnie wyjmujemy gorącą patelnię lub naczynie z piekarnika. Dodajemy masło i rozprowadzamy je po całym dnie, aż całkowicie się rozpuści.\n4. Do gorącego naczynia wlewamy przygotowane ciasto. Natychmiast wstawiamy do piekarnika.\n5. Pieczemy przez 14–16 minut, bez otwierania piekarnika, aż dutch baby mocno wyrośnie, a brzegi będą wyraźnie zrumienione i sztywne. Środek powinien być sprężysty, ale nadal lekko miękki.\n6. Wyjmujemy dutch baby z piekarnika. Naleśnik naturalnie opadnie, jest to prawidłowe.\n7. Do rondelka wsypujemy wiśnie, dodajemy erytrol i wlewamy wodę. W przypadku użycia mrożonych owoców, nie ma potrzeby ich wcześniej rozmrażać. Rondelek stawiamy na średnim ogniu, gotujemy bez przykrycia ok. 8–10 minut, aż owoce puszczą sok i zmiękną. Próbujemy i opcjonalnie dosładzamy jeszcze erytrolem według własnego smaku.\n8. W trakcie gotowania mieszamy owoce co jakiś czas. Jeśli fruzelina jest zbyt rzadka, zwiększamy ogień i gotujemy jeszcze 1–2 minuty do lekkiego odparowania płynu. Zdejmujemy z ognia – konsystencja powinna być gęsta, z wyraźnymi kawałkami owoców.\n9. Na środek naleśnika wykładamy owoce. Następnie kładziemy jogurt skyr. Całość opcjonalnie posypujemy pudrem z erytrolu według własnego uznania.",
                'ingredients' => [
                    'Jajko kurze całe' => ['me' => 112, 'wife' => 56],
                    'Mąka pszenna typ 500' => ['me' => 60, 'wife' => 35],
                    'Mleko 1,5%' => ['me' => 125, 'wife' => 75],
                    'Erytrol' => ['me' => 20, 'wife' => 12.5],
                    'Masło extra' => ['me' => 5, 'wife' => 10],
                    'Sól' => ['me' => 0.3, 'wife' => 0.1],
                    'Wiśnie, świeże lub mrożone' => ['me' => 150, 'wife' => 87.5],
                    'Woda' => ['me' => 60, 'wife' => 30],
                    'Jogurt skyr' => ['me' => 100, 'wife' => 75],
                    'Puder z erytrolu' => ['me' => 5, 'wife' => 2.5],
                ],
                'macros' => ['me' => [593, 38, 18, 72], 'wife' => [397, 23, 16, 43]],
            ],
            [
                'name' => 'Pieczony pstrąg z masłem czosnkowym, młodymi ziemniakami i mizerią',
                'content' => "1. Rozgrzewamy piekarnik do temperatury 200°C (tryb góra-dół, bez termoobiegu).\n2. Młode ziemniaki dokładnie myjemy (jeśli korzystamy ze starszych ziemniaków, to obieramy je i kroimy na kawałki podobnej wielkości) i wrzucamy do garnka z osoloną wodą (wlewamy tyle wody, aby ziemniaki były przykryte).\n3. Garnek stawiamy na palniku ustawiony na dużą moc i doprowadzamy do wrzenia bez przykrycia przez około 7–10 minut, następnie zmniejszamy moc palnika na średnią, nakładamy pokrywkę z częściowym przykryciem i gotujemy przez około 20 minut, kontrolując miękkość poprzez wbicie widelca – jeśli wchodzi gładko do samego środka, oznacza to moment zakończenia gotowania.\n4. Przekładamy miękkie masło do małej miseczki, dodajemy przeciśnięty przez praskę czosnek, dodajemy szczyptę soli oraz połowę drobno posiekanego koperku, a następnie dokładnie mieszamy całość, aż powstanie jednolita pasta.\n5. Rybę układamy na folii aluminiowej umieszczonej na blaszce do pieczenia.\n6. Oprószamy rybę solą oraz pieprzem, smarujemy całą powierzchnię przygotowanym masłem czosnkowym, obkładamy plastrami dokładnie umytej cytryny i szczelnie zawijamy folię, tworząc luźną, ale zamkniętą paczuszkę.\n7. Rybę pieczemy w folii przez 20 minut, po czym ostrożnie rozchylamy folię od góry i dopiekamy bez przykrycia przez kolejne 5 minut na dużej mocy grzałki, do lekkiego zrumienienia maślanej skórki.\n8. Ogórka kroimy na cienkie plasterki, solimy i odstawiamy na 5 minut. Po tym czasie odciskamy nadmiar soku z ogórków. Dodajemy jogurt, sól oraz pieprz, mieszamy.\n9. Rybę podajemy z ziemniakami i mizerią.",
                'ingredients' => [
                    'Pstrąg strumieniowy' => ['me' => 175, 'wife' => 125],
                    'Ziemniaki' => ['me' => 225, 'wife' => 100],
                    'Ogórek świeży' => ['me' => 150, 'wife' => 75],
                    'Masło extra' => ['me' => 20, 'wife' => 10],
                    'Czosnek świeży' => ['me' => 6, 'wife' => 3],
                    'Jogurt naturalny gęsty' => ['me' => 100, 'wife' => 40],
                    'Koper posiekany' => ['me' => 18, 'wife' => 8],
                    'Cytryna' => ['me' => 40, 'wife' => 10],
                    'Sól' => ['me' => 0.5, 'wife' => 0.5],
                    'Pieprz czarny' => ['me' => 0.5, 'wife' => 0.5],
                ],
                'macros' => ['me' => [610, 44, 24, 58], 'wife' => [321, 28, 12, 25]],
            ],
            [
                'name' => 'Owsianka sernikowa',
                'content' => "1. Brzoskwinię myjemy, a następnie kroimy w kostkę.\n2. Płatki owsiane zalewamy gorącą wodą, nieco ponad wysokość płatków i odstawiamy na 10 minut, aby zmiękły. Płatki owsiane można w ten sposób przygotować wieczorem poprzedniego dnia.\n3. Serek homogenizowany, jogurt skyr oraz aromat waniliowy przekładamy do miski z płatkami owsianymi i mieszamy. W razie potrzeby dosładzamy erytrolem wedle uznania.\n4. Na wierzchu układamy posiekane orzechy oraz pokrojone owoce.",
                'ingredients' => [
                    'Serek homogenizowany naturalny' => ['me' => 150, 'wife' => 75],
                    'Brzoskwinia' => ['me' => 90, 'wife' => 90],
                    'Płatki owsiane górskie' => ['me' => 50, 'wife' => 20],
                    'Mieszanka orzechów' => ['me' => 20, 'wife' => 15],
                    'Aromat waniliowy' => ['me' => 0.3, 'wife' => 0.3],
                    'Jogurt skyr' => ['me' => 75, 'wife' => 150],
                    'Erytrol' => ['me' => 10, 'wife' => 10],
                ],
                'macros' => ['me' => [609, 31, 24, 73], 'wife' => [408, 30, 15, 45]],
            ],
            [
                'name' => 'Marry me gnocchi',
                'content' => "1. Gnocchi gotujemy według instrukcji na opakowaniu.\n2. Suszone pomidory kroimy w mniejsze paski, a czosnek drobno siekamy. Pomidorki koktajlowe kroimy na połówki lub ćwiartki.\n3. Na dużej patelni rozpuszczamy masło. Podgrzewamy na średniej mocy palnika.\n4. Dodajemy czosnek i smażymy bez przykrycia przez około 30 sekund, do momentu, gdy uwolnimy jego aromat (uważamy, by go nie spalić, aby nie nabrał gorzkiego smaku).\n5. Dodajemy pokrojone suszone pomidory i smażymy przez około 1 minutę, mieszając.\n6. Dodajemy pomidorki koktajlowe. Dusimy bez przykrycia na średniej mocy palnika przez około 3 minuty, aż pomidorki lekko zmiękną i puszczą sok.\n7. Wlewamy bulion warzywny oraz śmietanę. Zmniejszamy moc palnika na małą i gotujemy bez przykrycia przez około 5 minut, aż sos zacznie się lekko zagęszczać.\n8. Do sosu dodajemy starty parmezan, a następnie wrzucamy szpinak. Mieszamy, aż liście zwiędną i zmniejszą swoją objętość. Doprawiamy sos solą oraz pieprzem.\n9. Gnocchi dodajemy do sosu. Mieszamy delikatnie, aby gnocchi pokryły się sosem. Jeśli sos jest zbyt gęsty, możemy dodać odrobinę wody z gotowania gnocchi, aby uzyskać idealną kremową konsystencję.\n10. Przed podaniem posypujemy bazylią świeżą.",
                'ingredients' => [
                    'Gnocchi' => ['me' => 175, 'wife' => 87.5],
                    'Śmietana 18%' => ['me' => 75, 'wife' => 37.5],
                    'Pomidory suszone w oleju (odsączone)' => ['me' => 30, 'wife' => 15],
                    'Szpinak' => ['me' => 50, 'wife' => 25],
                    'Pomidorki koktajlowe' => ['me' => 160, 'wife' => 80],
                    'Czosnek świeży' => ['me' => 3, 'wife' => 1.5],
                    'Bulion warzywny' => ['me' => 75, 'wife' => 37.5],
                    'Masło extra' => ['me' => 5, 'wife' => 2.5],
                    'Parmezan starty' => ['me' => 10, 'wife' => 5],
                    'Bazylia świeża' => ['me' => 3, 'wife' => 1.5],
                    'Sól' => ['me' => 0.3, 'wife' => 0.15],
                    'Pieprz czarny' => ['me' => 0.3, 'wife' => 0.15],
                ],
                'macros' => ['me' => [576, 18, 27, 68], 'wife' => [288, 9, 13, 34]],
            ],
            [
                'name' => 'Pomidorowa zapiekanka makaronowa',
                'content' => "1. Makaron gotujemy chwilę krócej (ok. 2 minuty) niż al dente, aby makaron był lekko niedogotowany (później dojdzie w piekarniku). Odcedzamy.\n2. Piekarnik nagrzewamy do 180°C, tryb góra-dół. W przypadku termoobiegu ustawiamy 160°C.\n3. Cebulę kroimy w drobną kostkę. Czosnek drobno siekamy.\n4. Na dużej patelni rozgrzewamy oliwę na średnim ogniu. Dodajemy cebulę oraz czosnek. Smażymy bez przykrycia przez ok. 2-3 minuty, aż cebula się zeszkli.\n5. Dodajemy mięso i smażymy bez przykrycia na dużym ogniu przez ok. 5-6 minut, aż mięso się zarumieni. Wlewamy passatę pomidorową. Doprawiamy oregano, pieprzem, solą oraz bazylią świeżą wedle uznania. Mieszamy i dusimy na małym ogniu pod częściowym przykryciem przez ok. 5 minut, aby smaki się połączyły.\n6. Do naczynia żaroodpornego o wymiarach ok. 20 × 25 cm wykładamy ugotowany makaron, dodajemy sos z patelni i całość dokładnie mieszamy. Wstawiamy naczynie do nagrzanego piekarnika i pieczemy przez 20 minut.\n7. Po tym czasie wyjmujemy naczynie, na wierzchu układamy kawałki sera mozzarelli i ponownie wstawiamy do piekarnika. Zapiekamy jeszcze ok. 5-7 minut, aż ser się rozpuści i lekko zrumieni.\n8. Gotową zapiekankę możemy jeszcze opcjonalnie przyozdobić bazylią świeżą. Zapiekankę podajemy na ciepło.",
                'ingredients' => [
                    'Makaron pełnoziarnisty' => ['me' => 60, 'wife' => 33.33],
                    'Oliwa z oliwek' => ['me' => 5, 'wife' => 1.67],
                    'Cebula' => ['me' => 20, 'wife' => 6.67],
                    'Czosnek świeży' => ['me' => 3, 'wife' => 1],
                    'Mięso mielone z indyka' => ['me' => 125, 'wife' => 50],
                    'Passata pomidorowa' => ['me' => 200, 'wife' => 83.33],
                    'Ser mozzarella kulka light' => ['me' => 60, 'wife' => 40],
                    'Oregano suszone' => ['me' => 1.5, 'wife' => 0.5],
                    'Bazylia świeża' => ['me' => 1.5, 'wife' => 0.5],
                    'Pieprz czarny' => ['me' => 0.3, 'wife' => 0.1],
                    'Sól' => ['me' => 0.3, 'wife' => 0.1],
                ],
                'macros' => ['me' => [612, 48, 18, 52], 'wife' => [302, 24, 8, 27]],
            ],
            [
                'name' => 'Kasza jęczmienna perłowa z ciecierzycą w sosie meksykańskim',
                'content' => "1. Kaszę przygotowujemy według instrukcji na opakowaniu.\n2. Warzywa kroimy w kostkę. Ciecierzycę odcedzamy.\n3. Na patelni rozgrzewamy oliwę. Dodajemy cukinię i smażymy przez kilka minut do zarumienienia. Następnie dodajemy pomidory z puszki oraz ciecierzycę i podsmażamy jeszcze przez 2-3 minuty.\n4. Na koniec dodajemy na patelnię kaszę. Doprawiamy solą, pieprzem, kuminem oraz czosnkiem granulowanym wedle uznania. Podsmażamy całość przez ok. 2 minuty. Po tym czasie gotowe danie przekładamy na talerz.",
                'ingredients' => [
                    'Kasza jęczmienna perłowa' => ['me' => 60, 'wife' => 30],
                    'Oliwa z oliwek' => ['me' => 15, 'wife' => 5],
                    'Pomidory z puszki' => ['me' => 200, 'wife' => 200],
                    'Cukinia' => ['me' => 100, 'wife' => 100],
                    'Ciecierzyca konserwowa' => ['me' => 180, 'wife' => 90],
                    'Sól' => ['me' => 0.5, 'wife' => 0.5],
                    'Pieprz czarny' => ['me' => 0.5, 'wife' => 0.5],
                    'Kumin' => ['me' => 2, 'wife' => 2],
                    'Czosnek granulowany' => ['me' => 1, 'wife' => 1],
                ],
                'macros' => ['me' => [603, 19, 22, 83], 'wife' => [316, 12, 9, 48]],
            ],
            [
                'name' => 'Drożdżówka z czekoladą i wiśniami',
                'content' => "1. Piekarnik rozgrzewamy do 180°C (funkcja góra-dół) pod koniec drugiego wyrastania ciasta.\n2. Jeśli używamy mrożonych wiśni, przed dodaniem do ciasta rozmrażamy je i dokładnie odsączamy z nadmiaru soku na papierowym ręczniku. Aby zapobiec powstawaniu zakalca wokół owoców, obtaczamy je w skrobi ziemniaczanej wymieszanej z erytrolem.\n3. Masło roztapiamy w małym rondelku lub mikrofali na płynną, ciepłą masę. Odstawiamy do ostygnięcia, ale nie do ponownego zestalenia.\n4. W małej miseczce kruszymy świeże drożdże, zalewamy je ciepłym mlekiem (podgrzanym w rondelku do temperatury 37-42 stopni), dodajemy 2 łyżki mąki oraz cukier. Mieszamy i odstawiamy w ciepłe miejsce, aż drożdże zaczną pracować i wyraźnie się spienią.\n5. Do dużej miski przesiewamy pozostałą mąkę, dodajemy puder z erytrolu, żółtko oraz wyrośnięty, spieniony rozczyn. Zaczynamy mieszać składniki dłonią, po czym stopniowo wlewamy roztopione, lekko przestudzone masło. Ciasto wyrabiamy energicznie, aż stanie się elastyczne, gładkie i zacznie łatwo odklejać się od dłoni oraz ścianek naczynia. Formujemy z niego kulę, przykrywamy miskę czystą ściereczką kuchenną i odstawiamy w ciepłe miejsce bez przeciągów, do momentu, aż podwoi swoją objętość (ok. 60 minut).\n6. Wyrośnięte ciasto przekładamy na blat lub stolnicę lekko oprószoną mąką i krótko zagniatamy. Rozwałkowujemy je na kształt prostokąta.\n7. Czekoladę gorzką kroimy nożem w drobną kostkę i równomiernie rozsypujemy na powierzchni ciasta, a następnie rozkładamy przygotowane wiśnie.\n8. Ciasto zwijamy wzdłuż dłuższego boku w dość ścisły rulon. Ostrym nożem kroimy go na grube plastry, a następnie układamy je obok siebie w małej okrągłej formie (ok. 16-18 cm) wyłożonej papierem do pieczenia. Odstawiamy na kilkanaście minut do ponownego napuszenia.\n9. Wyrośnięte ciasto wstawiamy od razu do dobrze rozgrzanego piekarnika. Pieczemy w temperaturze określonej w kroku 1 (180°C), aż wierzch ładnie się złoci, nabierze złotobrązowego koloru i wyraźnie się zarumieni (ok. 25-30 minut). Stan upieczenia sprawdzamy metodą „do suchego patyczka” - włożony w środek ciasta drewniany patyczek po wyciągnięciu powinien być całkowicie suchy. Po upieczeniu wyłączamy piekarnik i zostawiamy drożdżówkę na chwilę przy delikatnie uchylonych drzwiczkach. Następnie wyciągamy do całkowitego wystudzenia.\n10. W małej miseczce umieszczamy puder z erytrolu. Dodajemy sok z cytryny, aromat waniliowy (lub ekstrakt) oraz gorącą wodę. Całość bardzo energicznie i intensywnie ucieramy łyżeczką. Pod wpływem tarcia i gorącej wody puder idealnie się połączy, tworząc masę podobną do klasycznego lukru.\n11. Całkowicie ostudzoną drożdżówkę polewamy przygotowaną masą. Dzielimy całość na porcje.",
                'ingredients' => [
                    'Mąka pszenna typ 500' => ['me' => 65, 'wife' => 50],
                    'Drożdże świeże' => ['me' => 7.5, 'wife' => 5],
                    'Mleko 1,5%' => ['me' => 30, 'wife' => 15],
                    'Cukier' => ['me' => 2.5, 'wife' => 2.5],
                    'Puder z erytrolu' => ['me' => 20, 'wife' => 20],
                    'Masło extra' => ['me' => 10, 'wife' => 5],
                    'Żółtko jaja' => ['me' => 10, 'wife' => 10],
                    'Wiśnie, świeże lub mrożone' => ['me' => 75, 'wife' => 62.5],
                    'Czekolada gorzka 70%' => ['me' => 30, 'wife' => 15],
                    'Skrobia ziemniaczana' => ['me' => 2.5, 'wife' => 2.5],
                    'Erytrol' => ['me' => 5, 'wife' => 5],
                    'Sok z cytryny' => ['me' => 4.5, 'wife' => 4.5],
                    'Woda' => ['me' => 7.5, 'wife' => 7.5],
                    'Wanilia ekstrakt' => ['me' => 0.8, 'wife' => 0.8],
                ],
                'macros' => ['me' => [590, 13, 26, 78], 'wife' => [395, 9, 15, 57]],
            ],
        ];
    }

    private function purchaseTimingFor(string $name): string
    {
        return in_array($name, self::FRESH_INGREDIENTS, true)
            ? Ingredient::PURCHASE_TIMING_FRESH
            : Ingredient::PURCHASE_TIMING_ADVANCE;
    }

    private function categoryFor(string $name): string
    {
        foreach (self::CATEGORY_INGREDIENTS as $category => $names) {
            if (in_array($name, $names, true)) {
                return $category;
            }
        }

        return Ingredient::CATEGORY_UNCATEGORIZED;
    }

    private function days(): array
    {
        return [
            1 => [
                'breakfast' => [
                    'name' => 'Orzechowa nocna owsianka z kremem orzechowym',
                    'content' => "1. Wsypujemy do słoika płatki owsiane, nasiona chia oraz erytrol, wlewamy mleko i za pomocą łyżki dokładnie mieszamy składniki przez około 1 minutę, aż płatki będą równomiernie zanurzone w płynie.\n2. Wstawiamy naczynie z płatkami na całą noc lub na minimum 6 godzin, aby płatki oraz nasiona chia wchłonęły płyn i zmiękły, tworząc gęstą konsystencję. Wyjmujemy słoik z lodówki bezpośrednio przed podaniem.\n3. Smarujemy wierzch owsianki warstwą kremu. Kroimy banana w plasterki i układamy je na wierzchu kremu. Siekamy nożem gorzką czekoladę oraz orzechy włoskie na mniejsze kawałki, a następnie posypujemy nimi wierzch deseru.",
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
                    'content' => "1. Przekrajamy bułkę wzdłuż na pół, aby powstały dwie równe części.\n2. Rozgrzewamy suchą patelnię na średniej mocy palnika.\n3. Za pomocą pędzelka kuchennego lub łyżeczki równomiernie smarujemy oliwą wewnętrzne strony przeciętej bułki. Układamy obie połówki bułki na rozgrzanej patelni, stroną posmarowaną oliwą do dołu, i opiekamy bez przykrycia na średniej mocy palnika przez około 3 minuty, aż pieczywo wyraźnie się zrumieni i stanie się chrupkie.\n4. Na dolnej połówce układamy delikatnie plastry prosciutto cotto. Odsączamy suszone pomidory z nadmiaru oleju, kroimy je na mniejsze paski i rozkładamy równomiernie na szynce.\n5. Wyjmujemy burratę z zalewy, delikatnie rozrywamy ją w dłoniach na mniejsze kawałki i układamy na pomidorach.\n6. Na samej górze kładziemy umytą i dokładnie osuszoną rukolę.\n7. Przykrywamy całość górną połówką bułki, lekko dociskając dłonią całą kanapkę, aby składniki dobrze do siebie przylegały.",
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
                    'content' => "1. Młode ziemniaki dokładnie szorujemy szczoteczką (nie obieramy ich). Jeśli używamy późniejszych ziemniaków obieramy je ze skórki i kroimy na połówki lub ćwiartki. Wkładamy do garnka, zalewamy zimną wodą ponad wysokość ziemniaków, dodajemy sól i doprowadzamy do wrzenia. Zmniejszamy ogień na średni i gotujemy pod przykryciem przez około 20 minut, aż będą idealnie miękkie. Nakłuwamy widelcem i w razie potrzeby wydłużamy czas gotowania. Ugotowane ziemniaczki odcedzamy.\n2. Plastry schabu rozbijamy tłuczkiem przez folię spożywczą na grubość około 0,5 cm. Czosnek rozgniatamy płaską stroną noża.\n3. Rozbite kotlety układamy w głębokim talerzu, obkładamy rozgniecionym czosnkiem, posypujemy majerankiem, świeżo mielonym pieprzem oraz solą według własnego uznania. Całość zalewamy mlekiem i odstawiamy na 10-15 minut.\n4. Ogórka myjemy, obieramy i kroimy w bardzo cienkie plasterki. Przekładamy na sitko, posypujemy solą, mieszamy i odstawiamy na 10 minut, aby puściły nadmiar wody. W miseczce łączymy śmietanę, sok z cytryny, erytrol oraz świeżo posiekany szczypiorek. Ogórki dokładnie odciskamy w dłoniach z soku i wrzucamy do miski z sosem. Całość mieszamy, próbujemy i opcjonalnie doprawiamy solą oraz pieprzem według własnego smaku.\n5. Przygotowujemy trzy głębokie talerze. Do pierwszego wsypujemy mąkę, w drugim roztrzepujemy jajko, a do trzeciego wsypujemy bułkę tartą. Każdy kotlet obtaczamy najpierw w mące (strzepując nadmiar), potem w jajku, a na końcu dokładnie dociskamy do bułki tartej.\n6. Na patelni o nieprzywierającej powłoce rozgrzewamy olej na średnio-wysokim ogniu (tłuszcz musi być dobrze rozgrzany, inaczej panierka go spije). Układamy schabowe i smażymy przez około 3-4 minuty z każdej strony, aż panierka uzyska piękny, złotobrązowy kolor. Usmażone kotlety wykładamy na talerz wyścielony ręcznikiem papierowym.\n7. Ziemniaki posypujemy świeżo posiekanym koperkiem. Na talerze wykładamy schabowe, ziemniaki i mizerię. Do dania podajemy zsiadłe mleko.",
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
                    'content' => "1. Suszone pomidory kroimy w paski, wrzucamy na patelnię razem z oliwą (lub olejem z pomidorów) i szpinakiem.\n2. Smażymy przez 2-3 minuty, dodajemy sól, pieprz oraz jajko.\n3. Smażymy, aż jajko się zetnie. Podajemy z chlebem razowym.",
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
                    'content' => "1. Zjedz loda i owoce na przekąskę. Smacznego!",
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
                    'content' => "1. Kaszę gryczaną płuczemy, wrzucamy do garnuszka, dodajemy sól, mleko oraz kakao. Po zagotowaniu zmniejszamy ogień i gotujemy 10 minut. Jeśli kasza wchłonie zbyt dużo płynu podczas gotowania, dolewamy stopniowo gorącą wodę, aby uzyskać preferowaną konsystencję.\n2. Po tym czasie wyłączamy ogień, przykrywamy garnek i czekamy kolejne 10 minut, aż kasza całkowicie zmięknie i wchłonie płyn. Jeśli po tym czasie gryczanka jest zbyt gęsta, dolewamy odrobinę wody, aby uzyskać preferowaną, kremową konsystencję. Jeśli natomiast jest zbyt rzadka, gotujemy ją chwilę dłużej bez przykrycia, aż nadmiar płynu odparuje.\n3. Dodajemy jogurt skyr oraz syrop klonowy, mieszamy i przekładamy do miseczki. Podajemy z owocami i orzechami włoskimi.",
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
                    'content' => "1. Ser kroimy w kostkę.\n2. Warzywa kroimy. Mieszamy oliwę, oregano, bazylię suszoną oraz sok z cytryny.\n3. Wszystkie składniki mieszamy, posypujemy serem i polewamy sosem. Całość podajemy z pitą. Smacznego!",
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
                    'content' => "1. Ryż gotujemy według instrukcji na opakowaniu, następnie dokładnie odcedzamy.\n2. Odcinamy skórkę z łososia i dzielimy go na mniejsze kawałki.\n3. W misce mieszamy składniki marynaty (miód, sos sojowy, sok z cytryny, sól oraz pieprz). Kawałki ryby wkładamy do marynaty i dokładnie obtaczamy. Odstawiamy na bok, aby ryba się zamarynowała (na czas przygotowania warzyw, około 5-7 minut).\n4. Piekarnik rozgrzewamy do 200°C w trybie góra–dół lub do 180°C w termoobiegu.\n5. Cukinię myjemy, odcinamy końce i kroimy w kostkę o boku około 2 cm. Przekładamy do miski.\n6. Pomidorki koktajlowe myjemy i przekrawamy na pół. Dodajemy do cukinii.\n7. Do warzyw dodajemy sól, pieprz oraz zioła prowansalskie. Dokładnie mieszamy, aby warzywa były równomiernie pokryte.\n8. Ryż przekładamy do naczynia żaroodpornego, następnie układamy na nim łososia, cukinię oraz pomidorki koktajlowe.\n9. Resztę marynaty wylewamy równomiernie na rybę i warzywa.\n10. Pieczemy przez 30 do 35 minut. Danie jest gotowe, gdy ryba jest ścięta i ma lekko zrumieniony wierzch, a cukinia jest miękka i poddaje się lekkim naciśnięciom widelca.",
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
                    'content' => "1. Kromki chleba przekrajamy na pół, po przekątnej, aby uzyskać trójkąty.\n2. Na trójkąt układamy ser gouda oraz szynkę z kurczaka. Przykrywamy drugim trójkątem.\n3. W misce rozkłócamy jajko. Mieszamy z solą oraz bazylią suszoną.\n4. Kromki obtaczamy w jajku (jeśli zostanie, wylewamy na patelnię obok tostów). Smażymy na rozgrzanej patelni posmarowanej oliwą pod przykryciem, na niewielkiej mocy palnika do zarumienienia z obu stron.\n5. Podajemy z warzywami, zaraz po przygotowaniu.",
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
                    'content' => "1. Wszystkie składniki koktajlu blendujemy na gładką masę. W razie potrzeby dolewamy wody, aby uzyskać preferowaną konsystencję.",
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
                    'content' => "1. Kroimy banana i siekamy orzechy włoskie.\n2. Pokrojone owoce mieszamy z jogurtem greckim oraz odżywką białkową i przekładamy do miseczki.\n3. Na jogurt grecki z bananem nakładamy dżem z czarnych porzeczek i posypujemy orzechami włoskimi. Smacznego!",
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
                    'content' => "1. Jajka wkładamy do garnka i zalewamy zimną wodą tak, aby były przykryte wodą na około 2–3 cm. Garnek stawiamy na palniku, gotujemy na dużym ogniu do momentu zagotowania wody. Gdy woda zacznie wrzeć, zmniejszamy ogień na średni i gotujemy bez przykrycia przez 7-8 minut. Po ugotowaniu jajka odcedzamy, zalewamy zimną wodą i odstawiamy na około 5 minut do całkowitego ostudzenia, następnie obieramy ze skorupek.\n2. Obrane jajka kroimy w drobną kostkę.\n3. Do miski z jajkami dodajemy jogurt naturalny oraz majonez. Doprawiamy solą oraz pieprzem według własnego uznania. Dokładnie mieszamy, aż powstanie jednolita pasta.\n4. Awokado obieramy, usuwamy pestkę i kroimy w cienkie plasterki lub półplasterki. Skrapiamy sokiem z cytryny, aby nie ściemniało.\n5. Na tortilli równomiernie rozkładamy pastę jajeczną, zostawiając wolne brzegi po bokach. Na paście układamy plasterki awokado, a następnie równomiernie rozkładamy roszponkę.\n6. Tortillę zawijamy ciasno w wrapa, najpierw składając boki do środka, a następnie zwijając całość w rulon.\n7. Gotowy wrap możemy pozostawić w całości lub przekroić na pół.",
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
                    'content' => "1. Piekarnik nagrzewamy do 190 stopni w trybie góra-dół.\n2. Obieramy ziemniaki, marchew, seler naciowy, cebulę oraz pora. Kroimy w kostkę o boku ok. 3 cm.\n3. Mięso kroimy na cienkie plastry lub w kostkę, w zależności od preferencji.\n4. Obieramy czosnek i drobno go siekamy.\n5. W dużej misce umieszczamy pokrojone mięso i pokrojone warzywa. Dodajemy oliwę, sos sojowy, tymianek suszony, sól, pieprz oraz zioła prowansalskie.\n6. Dokładnie mieszamy mięso i warzywa ręką lub dużą łyżką, tak aby każdy kawałek był pokryty przyprawami.\n7. Przygotowujemy duży arkusz folii aluminiowej. Musi być na tyle duży, aby można było szczelnie zawinąć całą potrawę.\n8. Na środku przygotowanej folii układamy równomierną warstwę warzyw. Na warzywach układamy kawałki mięsa.\n9. Podlewamy całość 1/3 szklanki wody. Woda stworzy w zamkniętej folii parę, która pomoże w upieczeniu składników. Szczelnie zawijamy folię aluminiową, tworząc paczkę. Przekładamy na blachę do pieczenia.\n10. Pieczemy przez 40-45 minut. Po tym czasie ostrożnie rozcinamy folię, uważając na gorącą parę. Zwiększamy temperaturę do 210°C i pieczemy danie bez przykrycia przez dodatkowe 10-15 minut, aż ziemniaki będą całkowicie miękkie, a kurczak i warzywa apetycznie się zarumienią. Wyjmujemy blachę z piekarnika i sprawdzamy stopień upieczenia kurczaka (czy jest biały w środku) i miękkość warzyw (np. nakłuwając ziemniaki widelcem – powinny być miękkie).",
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
                    'content' => "1. Płatki zalewamy wrzątkiem i czekamy, aż zmiękną.\n2. Zimne, miękkie płatki mieszamy z jogurtem, lodami i owocami.\n3. Przed podaniem posypujemy wiórkami kokosowymi.",
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
                    'content' => "1. Arbuza kroimy w kostkę.\n2. Ser typu feta kroimy w kostkę. Miętę siekamy.\n3. Dodajemy oliwę oraz pieprz. Wszystkie składniki mieszamy. Smacznego!",
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
                    'content' => "1. Zagotowujemy mleko. Dodajemy płatki owsiane i gotujemy do miękkości, co jakiś czas mieszając. Delikatnie studzimy.\n2. Banana kroimy na drobniejsze kawałki. Do owsianki dodajemy banana i mieszamy.\n3. Posypujemy malinami oraz orzechami włoskimi.\n4. Mieszamy z jogurtem skyr. Smacznego!",
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
                    'content' => "1. Bagietkę przekrajamy. Jeśli lubimy wyjątkowo chrupiące pieczywo, połówki bagietki możemy włożyć na 2-3 minuty do rozgrzanego opiekacza lub podpiec na suchej, gorącej patelni wewnętrzną stroną do dołu, aż lekko się zazłocą.\n2. Pomidora kroimy w plastry. Pieczywo smarujemy równomiernie serkiem kanapkowym.\n3. Na serku układamy ser żółty, następnie rozkładamy równomiernie pomidora oraz rukolę. Całość doprawiamy solą oraz pieprzem według uznania.",
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
                    'content' => "1. Obieramy cebulę i kroimy ją w drobną kostkę. W garnku rozgrzewamy olej i wrzucamy cebulę. Smażymy, aż się zeszkli przez 2-3 minuty, co jakiś czas mieszając.\n2. Mięso kroimy w kostkę wielkości około 2 cm. Dodajemy do garnka i smażymy, aż mięso się delikatnie zrumieni ze wszystkich stron – trwa to około 6–8 minut. Doprawiamy solą, pieprzem oraz tymiankiem suszonym wedle uznania.\n3. Marchew obieramy i kroimy w grube plasterki. Dodajemy ją do garnka i mieszamy całość. Podsmażamy jeszcze przez około 3 minuty.\n4. Wsypujemy pęczak, mieszamy, a następnie zalewamy całość gorącym bulionem. Doprowadzamy do wrzenia, zmniejszamy ogień i gotujemy pod przykryciem przez około 25 minut – do momentu, aż pęczak będzie miękki.\n5. W międzyczasie młodą kapustę szatkujemy na mniejsze kawałki. Po upływie 25 minut dodajemy młodą kapustę oraz groszek do garnka. Gotujemy jeszcze przez 10 minut, aż warzywa zmiękną, ale zachowają jędrność.\n6. Na koniec dodajemy natkę pietruszki i mieszamy. Gotujemy jeszcze 1–2 minuty bez przykrycia, aby smaki się połączyły. W razie potrzeby doprawiamy do smaku solą i pieprzem.",
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
                    'content' => "1. Piekarnik rozgrzewamy do 170 stopni, tryb góra-dół.\n2. Masło rozpuszczamy w mikrofali lub w rondelku na małym ogniu do momentu aż będzie płynne. Pozostawiamy do całkowitego przestygnięcia, ale nie do ponownego zestalenia.\n3. Oddzielamy żółtka od białek. Białka ubijamy mikserem ze szczyptą soli, na wysokich obrotach do uzyskania sztywnej piany. Przekładamy ją do innej miski.\n4. Następnie ubijamy żółtka z erytrolem na jasną masę. Dodajemy roztopione masło, sok z cytryny, skórkę z cytryny, ekstrakt waniliowy, sodę oczyszczoną oraz ocet. Mieszamy na wolnych obrotach miksera do dokładnego połączenia składników.\n5. Następnie dodajemy jogurt, mieszamy do połączenia.\n6. Do masy dodajemy mąkę, mieszamy powoli, aż wchłonie płyn i nie będzie widocznych żadnych grudek.\n7. Delikatnie, stopniowo dodajemy ubite białka. Mieszamy już za pomocą szpatułki do połączenia składników.\n8. Masę przekładamy do tortownicy (średnica ok. 24 cm) wyłożonej papierem do pieczenia.\n9. Na wierzchu delikatnie i równomiernie rozkładamy owoce.\n10. Wstawiamy do piekarnika i pieczemy przez 35–40 minut, aż ciasto będzie sprężyste i lekko wilgotne w środku (sprawdzamy patyczkiem – może być lekko wilgotne, ale bez surowego, lepkiego ciasta).\n11. Po upieczeniu pozostawiamy ciasto w wyłączonym, lekko uchylonym piekarniku na 15 minut. Po tym czasie wyjmujemy z piekarnika i pozostawiamy do całkowitego ostudzenia. Kroimy dopiero po ostudzeniu.",
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
                    'content' => "1. Orzechy zjadamy na przekąskę.",
                    'ingredients' => [
                        'Mieszanka orzechów' => ['me' => 15, 'wife' => 15],
                    ],
                    'macros' => ['me' => [91, 3, 8, 3], 'wife' => [91, 3, 8, 3]],
                ],
            ],
            5 => [
                'breakfast' => [
                    'name' => 'Kanapki z hummusem, wędliną i warzywami',
                    'content' => "1. Pomidora oraz ogórka kroimy w cienkie plastry. Kalarepę kroimy w drobne paski. Szczypiorek drobno siekamy.\n2. Pieczywo kroimy i równomiernie smarujemy hummusem.\n3. Na pieczywie układamy szynkę z kurczaka, następnie dodajemy pomidora, kalarepę oraz ogórka.\n4. Posypujemy całość szczypiorkiem i podajemy. Część warzyw możemy ułożyć obok na talerzu i zjeść do kanapek.",
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
                    'content' => "1. Pieczywo smarujemy serkiem śmietankowym.\n2. Pomidora oraz szczypiorek kroimy. Warzywa nakładamy na kanapki. Do posiłku zjadamy serek wiejski.",
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
                    'content' => "1. Makaron gotujemy al dente, zgodnie z instrukcją na opakowaniu.\n2. W międzyczasie kroimy kurczaka w większą kostkę, cukinię w cienkie półplasterki. Cebulę kroimy w drobną kostkę.\n3. Na patelni rozgrzewamy oliwę na średnim ogniu. Wrzucamy pokrojone mięso i smażymy, aż będzie białe w środku i lekko zrumienione z zewnątrz.\n4. Do kurczaka dodajemy cebulę i smażymy, aż się zeszkli, co zajmie około 3-4 minut. Następnie dodajemy cukinię i smażymy przez kolejne 5 minut, aż zmięknie, ale nadal pozostanie jędrna.\n5. Zmniejszamy ogień, wlewamy śmietankę i dokładnie mieszamy. Doprawiamy zioła prowansalskie, sól oraz pieprz do smaku. Gotujemy na małym ogniu przez 2-3 minuty, aż sos lekko zgęstnieje.\n6. Ugotowany makaron odcedzamy i dodajemy bezpośrednio na patelnię z sosem. Mieszamy, aby makaron pokrył się sosem, posypujemy natkę pietruszki.",
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
                    'content' => "1. Pieczywo kroimy w niewielką kostkę. Podsmażamy na suchej, rozgrzanej patelni do zarumienienia.\n2. Owoce kroimy w kostkę. Orzechy siekamy.\n3. Na talerzu układamy szpinak, kawałki owoców, grzanki, orzechy i pokruszony ser.\n4. W małej miseczce dokładnie mieszamy składniki dressingu (oliwę, sok z limonki oraz miód).\n5. Sałatkę przed podaniem polewamy dressingiem.",
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
                    'content' => "1. Spożywamy jako przekąskę po posiłku lub w ciągu dnia.",
                    'ingredients' => [
                        'Borówki amerykańskie' => ['me' => 200, 'wife' => 200],
                    ],
                    'macros' => ['me' => [114, 1, 1, 29], 'wife' => [114, 1, 1, 29]],
                ],
            ],
            6 => [
                'breakfast' => [
                    'name' => 'Bajgiel z serem i chorizo',
                    'content' => "1. Pieczywo przekrajamy na pół.\n2. W miseczce mieszamy jogurt naturalny, majonez oraz sos sriracha. Pieczywo smarujemy sosem.\n3. Na jednej połówce układamy sałatę, chorizo, ser gouda oraz plasterki ogórka. Składamy kanapkę.",
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
                    'content' => "1. Do miski wkładamy ser twarogowy, jogurt, sól oraz pieprz. Całość rozgniatamy widelcem do połączenia składników.\n2. Kalarepę oraz białą rzodkiew dokładnie myjemy pod zimną, bieżącą wodą, a następnie obieramy cienko ze skórki. Kroimy w cienkie plasterki.\n3. Suchą, czystą patelnię rozgrzewamy na palniku ustawionym na średnią moc, po czym układamy na niej kromki chleba i opiekamy je bez przykrycia przez około 3 minuty z każdej strony do zrumienienia i uzyskania wyraźnej, chrupiącej skórki.\n4. Na duży, płaski talerz przekładamy przygotowany wcześniej twarożek.\n5. W zagłębienie w twarożku wlewamy powoli oliwę, po czym całość posypujemy koperkiem oraz świeżo mielonym czarnym pieprzem dla zaostrzenia smaku.\n6. Twarożek podajemy z warzywami i pieczywem.",
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
                    'content' => "1. Młode ziemniaki dokładnie szorujemy szczoteczką pod zimną, bieżącą wodą, a następnie kroimy w równą, średnią kostkę o boku około 2 centymetrów. Cebulę dymkę kroimy w plasterki, oddzielając białą część od zielonego szczypiorku. Świeży koper drobno siekamy nożem na desce. Boczek kroimy w bardzo drobną kostkę.\n2. W dużym garnku umieszczamy oliwę i ustawiamy średnią moc palnika. Gdy tłuszcz lekko się rozgrzeje, wrzucamy pokrojoną białą część dymki, pokrojone w kostkę mięso i smażymy bez przykrycia przez 3 minuty, cały czas mieszając, aż do momentu zmięknięcia i lekkiego zeszklenia warzywa.\n3. Dodajemy pokrojone ziemniaki, smażymy przez około 5 minut, regularnie mieszając.\n4. Wlewamy bulion warzywny, zwiększamy moc palnika na dużą i pod pełnym przykryciem doprowadzamy całość do wrzenia, w razie potrzeby dodajemy więcej bulionu.\n5. Gdy zupa mocno zabulgocze, zmniejszamy moc palnika na małą, lekko przesuwamy pokrywkę, zostawiając częściowe przykrycie, i gotujemy całość, aż ziemniaki zmiękną, około 15 minut.\n6. W czasie gotowania zupy wrzucamy pokrojony boczek na suchą, zimną patelnię i ustawiamy średnią moc palnika. Smażymy go bez przykrycia przez około 6-7 minut, często mieszając, aż wytopi się z niego tłuszcz, a kawałki staną się mocno zrumienione i chrupiące. Gotowe skwarki zdejmujemy z patelni na osobny talerzyk.\n7. Wyłączamy palnik pod garnkiem z zupą. Do osobnego, czystego kubka wlewamy zimną śmietankę, a następnie za pomocą małej chochelki dolewamy do niej powoli, małym strumieniem gorącą zupę z garnka, jednocześnie bardzo energicznie mieszając zawartość kubka łyżką, aż płyn w kubku stanie się wyraźnie ciepły. Tak zahartowaną mieszankę wlewamy z powrotem do garnka, cały czas mieszając.\n8. Zupę podgrzewamy przez chwilę, doprawiamy do smaku solą oraz pieprzem. Dodajemy koperek i szczypiorek, mieszamy.\n9. Zupę przelewamy do miseczki. Podajemy z kawałkami boczku.",
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
                    'content' => "1. Wszystkie składniki koktajlu blendujemy do uzyskania jednolitej konsystencji. Podczas blendowania dodajemy stopniowo chłodną wodę, aby uzyskać preferowaną konsystencję. W przypadku użycia mrożonych owoców warto je wcześniej rozmrozić.\n2. Koktajl podajemy od razu, najlepiej smakuje na świeżo.",
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
                    'content' => "1. Obieramy cytrynę i usuwamy pestki. Kroimy owoce na mniejsze kawałki.\n2. Obieramy banana i również kroimy na mniejsze części, aby łatwiej się blendowało.\n3. Do kielicha blendera dodajemy przygotowane owoce, ksylitol oraz 50 g zimnej wody. Blendujemy całość na gładką, jednolitą masę. W razie potrzeby dolewamy więcej wody (maksymalnie do 75 g), aż masa będzie miała konsystencję gęstego musu.\n4. Przelewamy masę do płaskiego pojemnika z pokrywką. Wstawiamy do zamrażarki.\n5. Po 1 godzinie wyjmujemy sorbet z zamrażarki i dokładnie mieszamy masę łyżką lub widelcem, rozbijając powstające kryształki lodu. Wyrównujemy powierzchnię i ponownie przykrywamy.\n6. Wkładamy sorbet do zamrażarki na minimum 2 godziny. Gotowy sorbet ma zwartą, ale nadal miękką i kremową konsystencję – łatwo daje się nabierać łyżką.\n7. Przed podaniem możemy wyjąć sorbet na 5 minut wcześniej, aby ułatwić porcjowanie. Smacznego!",
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
                    'content' => "1. Cebulę kroimy w cienkie półplasterki, czosnek drobno siekamy.\n2. Na średnim ogniu rozgrzewamy oliwę na patelni. Dodajemy cebulę i smażymy przez około 2 minuty bez przykrycia, aż lekko się zeszkli. Dodajemy czosnek oraz kumin, smażymy jeszcze 30 sekund, aż zacznie intensywnie pachnieć.\n3. Dodajemy pomidory z puszki. Gotujemy na średnim ogniu przez 5–7 minut bez przykrycia, aż sos lekko zgęstnieje. Doprawiamy solą oraz pieprzem według własnego uznania.\n4. Robimy w sosie wgłębienia - tyle ile jajek i wbijamy w nie jajka. Przykrywamy patelnię częściowo pokrywką, zmniejszamy ogień do małego i gotujemy przez 5–6 minut, aż białka się zetną, a żółtka pozostaną płynne (lub dłużej – według preferencji).\n5. Zdejmujemy z ognia. Posypujemy drobno posiekaną natką pietruszki.\n6. Gotową szakszukę podajemy z pieczywem.",
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
                    'content' => "1. Pieczywo przekrajamy, następnie podsmażamy je na patelni na średnim ogniu przez 2–3 minuty, aż lekko się zrumienią, bez przykrycia.\n2. Chrzan dokładnie mieszamy z serkiem śmietankowym.\n3. Koperek drobno siekamy. Ogórka kroimy na mniejsze kawałki.\n4. Na połowie pieczywa rozsmarowujemy pastę chrzanowo-serową. Układamy łososia, koperek, ogórka oraz kiełki. Przykrywamy pozostałym pieczywem.",
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
                    'content' => "1. W małej miseczce dokładnie mieszamy skyr i śmietankę. Dosładzamy połową erytrolu. W razie potrzeby dodajemy więcej słodzika we własnym zakresie.\n2. Na dużej patelni, na średnim ogniu, rozpuszczamy masło. Układamy gotowe kluski leniwe bezpośrednio z opakowania. Smażymy je przez około 4-5 minut z każdej strony, aż uzyskają wyraźny, złotobrązowy kolor i chrupiącą skórkę.\n3. Część jagód rozgniatamy widelcem, aby puściły sok. Mrożone owoce należy wcześniej rozmrozić.\n4. Odsmażone, gorące kluski leniwe wykładamy na talerz. Na środek porcji nakładamy przygotowaną śmietanę, a na sam wierzch wykładamy owoce. Całość oprószamy pozostałym erytrolem według własnego uznania.",
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
                    'content' => "1. Do miseczki kruszymy świeże drożdże, dodajemy cukier, erytrol, 1 łyżkę mąki (odważonej z całości) oraz wlewamy ciepłe (ale nie gorące) mleko (37-42 stopnie). Całość dokładnie mieszamy, aż drożdże się rozpuszczą, po czym odstawiamy w ciepłe miejsce na 10-15 minut, aby rozczyn zaczął mocno pracować i się spienił. Jeśli wolimy słodsze racuchy ilość erytrolu możemy dopasować do własnych preferencji.\n2. Przygotowujemy owoce. Większe owoce kroimy na mniejsze kawałki. W przypadku mrożonych wyjmujemy je wcześniej z zamrażarki, aby całkowicie rozmarzły i odsączamy z nadmiaru płynu.\n3. Do dużej miski przesiewamy pozostałą mąkę pszenną, dodajemy szczyptę soli, jogurt, jajko oraz ekstrakt z wanilii. Na koniec wlewamy wyrośnięty rozczyn drożdżowy.\n4. Wszystkie składniki mieszamy energicznie łyżką lub trzepaczką, aż uzyskamy jednolite, dość gęste i elastyczne ciasto. Miskę przykrywamy czystą ściereczką kuchenną i odstawiamy w ciepłe miejsce bez przeciągów na około 30 minut, do momentu, aż ciasto wyraźnie podwoi swoją objętość i pojawią się w nim pęcherzyki powietrza.\n5. Patelnię teflonową z nieprzywierającą powłoką mocno rozgrzewamy na średnim ogniu, a następnie za pomocą pędzelka kuchennego rozprowadzamy olej po całej powierzchni.\n6. Na rozgrzaną patelnię nakładamy partiami ciasto (około 1,5 do 2 łyżek na jednego racucha), formując zgrabne, regularne koła zachowując przerwy między porcjami. Smażymy na średnim ogniu przez około 2-3 minuty z jednej strony, aż placki wyraźnie urosną, a ich spód uzyska piękny, złocistobrązowy kolor. Następnie delikatnie obracamy płaską szpatułką racuchy na drugą stronę i smażymy przez kolejne 2 minuty.\n7. Gotowe, gorące racuchy zdejmujemy z patelni i układamy na talerzu. Racuchy dekorujemy przygotowanymi owocami. Całość delikatnie oprószamy pudrem z erytrolu.",
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
                    'content' => "1. Bób gotujemy w osolonej wodzie przez, ok 20 minut aż stanie się miękki (należy sprawdzać podczas gotowania).",
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
