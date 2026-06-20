<?php

namespace Database\Seeders;

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\Tag;
use Illuminate\Database\Seeder;

class RecipeCatalogSeeder extends Seeder
{
    /**
     * Seed the application's recipe catalog.
     */
    public function run(): void
    {
        foreach ($this->recipes() as $recipeData) {
            $recipe = Recipe::query()->updateOrCreate(
                [Recipe::NAME_COLUMN => $recipeData['name']],
                ['content' => $recipeData['content']]
            );

            $syncPayload = [];

            foreach ($recipeData['ingredients'] as $ingredientName => $quantity) {
                $ingredient = Ingredient::query()->firstOrCreate([
                    Ingredient::NAME_COLUMN => $ingredientName,
                ]);

                $syncPayload[$ingredient->id] = [
                    'quantity' => $quantity,
                ];
            }

            $recipe->ingredients()->sync($syncPayload);

            if (isset($recipeData['tags']) && is_array($recipeData['tags'])) {
                $tagIds = collect($recipeData['tags'])
                    ->map(function (string $tagName) {
                        $tag = Tag::query()->firstOrCreate([
                            Tag::NAME_COLUMN => $tagName,
                        ]);

                        return $tag->id;
                    })
                    ->values()
                    ->all();

                $recipe->tags()->sync($tagIds);
            }
        }
    }

    /**
     * @return array<int, array{name: string, content: string, ingredients: array<string, string>, tags: array<int, string>}>
     */
    private function recipes(): array
    {
        return [
            // 5 sniadan
            [
                'name' => 'Owsianka z bananem',
                'content' => 'Ugotuj platki na mleku, dodaj banana i miod.',
                'ingredients' => [
                    'Platki owsiane' => '80 g',
                    'Mleko' => '250 ml',
                    'Banan' => '1 sztuka',
                    'Miod' => '1 lyzka',
                ],
                'tags' => ['sniadanie'],
            ],
            [
                'name' => 'Jajecznica z pomidorem',
                'content' => 'Usmaz jajka na masle i podaj z pomidorem.',
                'ingredients' => [
                    'Jajka' => '3 sztuki',
                    'Maslo' => '1 lyzka',
                    'Pomidor' => '1 sztuka',
                    'Szczypiorek' => '1 garsc',
                ],
                'tags' => ['sniadanie'],
            ],
            [
                'name' => 'Tosty z awokado',
                'content' => 'Zrumien pieczywo i posmaruj rozgniecionym awokado.',
                'ingredients' => [
                    'Chleb pelnoziarnisty' => '2 kromki',
                    'Awokado' => '1 sztuka',
                    'Sok z cytryny' => '1 lyzeczka',
                    'Sol' => 'szczypta',
                ],
                'tags' => ['sniadanie'],
            ],
            [
                'name' => 'Jogurt z granola i owocami',
                'content' => 'Wymieszaj jogurt z granola i ulubionymi owocami.',
                'ingredients' => [
                    'Jogurt naturalny' => '200 g',
                    'Granola' => '60 g',
                    'Truskawki' => '100 g',
                    'Borowki' => '50 g',
                ],
                'tags' => ['sniadanie'],
            ],
            [
                'name' => 'Omlet ze szpinakiem',
                'content' => 'Usmaz omlet i dodaj podsmazony szpinak.',
                'ingredients' => [
                    'Jajka' => '2 sztuki',
                    'Szpinak swiezy' => '80 g',
                    'Ser feta' => '40 g',
                    'Oliwa' => '1 lyzeczka',
                ],
                'tags' => ['sniadanie'],
            ],

            // 5 obiadow
            [
                'name' => 'Kurczak curry z ryzem',
                'content' => 'Udus kurczaka z pasta curry i mleczkiem kokosowym, podaj z ryzem.',
                'ingredients' => [
                    'Piers z kurczaka' => '400 g',
                    'Ryż basmati' => '200 g',
                    'Mleczko kokosowe' => '400 ml',
                    'Pasta curry' => '2 lyzki',
                ],
                'tags' => ['obiad'],
            ],
            [
                'name' => 'Spaghetti bolognese',
                'content' => 'Przygotuj sos miesny i wymieszaj z makaronem.',
                'ingredients' => [
                    'Makaron spaghetti' => '300 g',
                    'Mieso mielone wolowe' => '400 g',
                    'Passata pomidorowa' => '500 ml',
                    'Cebula' => '1 sztuka',
                ],
                'tags' => ['obiad'],
            ],
            [
                'name' => 'Pieczony losos z ziemniakami',
                'content' => 'Upiecz lososia i ziemniaki, podaj z cytryna.',
                'ingredients' => [
                    'Filet z lososia' => '2 sztuki',
                    'Ziemniaki' => '600 g',
                    'Cytryna' => '1 sztuka',
                    'Koper' => '1 garsc',
                ],
                'tags' => ['obiad'],
            ],
            [
                'name' => 'Risotto z grzybami',
                'content' => 'Gotuj ryz arborio partiami z bulionem i grzybami.',
                'ingredients' => [
                    'Ryz arborio' => '300 g',
                    'Pieczarki' => '250 g',
                    'Bulion warzywny' => '1 l',
                    'Parmezan' => '50 g',
                ],
                'tags' => ['obiad'],
            ],
            [
                'name' => 'Chili con carne',
                'content' => 'Duś mieso z fasola i pomidorami na ostrzej.',
                'ingredients' => [
                    'Mieso mielone wolowe' => '500 g',
                    'Fasola czerwona' => '1 puszka',
                    'Pomidory krojone' => '1 puszka',
                    'Papryczka chili' => '1 sztuka',
                ],
                'tags' => ['obiad'],
            ],

            // 5 deserow
            [
                'name' => 'Sernik na zimno',
                'content' => 'Polacz mase serowa z zelatyna i schlodz.',
                'ingredients' => [
                    'Twarog sernikowy' => '500 g',
                    'Smietanka 30%' => '200 ml',
                    'Cukier puder' => '80 g',
                    'Zelatyna' => '2 lyzki',
                ],
                'tags' => ['deser'],
            ],
            [
                'name' => 'Brownie czekoladowe',
                'content' => 'Wymieszaj skladniki i piecz do wilgotnego srodka.',
                'ingredients' => [
                    'Gorzka czekolada' => '200 g',
                    'Maslo' => '180 g',
                    'Jajka' => '3 sztuki',
                    'Maka pszenna' => '100 g',
                ],
                'tags' => ['deser'],
            ],
            [
                'name' => 'Nalesniki z twarogiem',
                'content' => 'Usmaz nalesniki i nadziej slodkim twarogiem.',
                'ingredients' => [
                    'Maka pszenna' => '200 g',
                    'Mleko' => '300 ml',
                    'Twarog poltlusty' => '250 g',
                    'Cukier waniliowy' => '1 opakowanie',
                ],
                'tags' => ['deser'],
            ],
            [
                'name' => 'Tiramisu',
                'content' => 'Przekladaj biszkopty kremem mascarpone i schlodz.',
                'ingredients' => [
                    'Mascarpone' => '500 g',
                    'Biszkopty' => '200 g',
                    'Kawa espresso' => '250 ml',
                    'Kakao' => '2 lyzki',
                ],
                'tags' => ['deser'],
            ],
            [
                'name' => 'Crumble jablkowe',
                'content' => 'Zapiekaj jablka pod kruszonka.',
                'ingredients' => [
                    'Jablka' => '5 sztuk',
                    'Maka pszenna' => '150 g',
                    'Maslo' => '100 g',
                    'Cukier trzcinowy' => '80 g',
                ],
                'tags' => ['deser'],
            ],

            // 5 kolacji
            [
                'name' => 'Salatka grecka',
                'content' => 'Pokroj warzywa, dodaj fete i oliwki.',
                'ingredients' => [
                    'Ogorek' => '1 sztuka',
                    'Pomidor' => '2 sztuki',
                    'Ser feta' => '150 g',
                    'Oliwki czarne' => '80 g',
                ],
                'tags' => ['kolacja'],
            ],
            [
                'name' => 'Tortilla z kurczakiem',
                'content' => 'Podsmaz kurczaka i zawin w tortille z warzywami.',
                'ingredients' => [
                    'Tortilla pszenna' => '4 sztuki',
                    'Piers z kurczaka' => '300 g',
                    'Salata' => '1 glowka',
                    'Jogurt naturalny' => '150 g',
                ],
                'tags' => ['kolacja'],
            ],
            [
                'name' => 'Zapiekanki z pieczarkami',
                'content' => 'Zapiecz pieczywo z pieczarkami i serem.',
                'ingredients' => [
                    'Bagietka' => '1 sztuka',
                    'Pieczarki' => '200 g',
                    'Ser zolty' => '120 g',
                    'Cebula' => '1 sztuka',
                ],
                'tags' => ['kolacja'],
            ],
            [
                'name' => 'Krem z pomidorow',
                'content' => 'Ugotuj i zblenduj pomidory z bulionem.',
                'ingredients' => [
                    'Pomidory' => '800 g',
                    'Bulion warzywny' => '700 ml',
                    'Czosnek' => '2 zabki',
                    'Smietanka 18%' => '100 ml',
                ],
                'tags' => ['kolacja'],
            ],
            [
                'name' => 'Kanapki z pasta jajeczna',
                'content' => 'Przygotuj paste jajeczna i podaj na pieczywie.',
                'ingredients' => [
                    'Jajka' => '4 sztuki',
                    'Majonez' => '2 lyzki',
                    'Szczypiorek' => '1 garsc',
                    'Chleb pelnoziarnisty' => '6 kromek',
                ],
                'tags' => ['kolacja'],
            ],
        ];
    }
}