<?php

use App\Actions\MealPlan\BuildWeekReview;
use App\Models\Meal;
use App\Models\Recipe;
use App\Models\RecipeMealAssignment;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

// 2026-11-18 is a Wednesday; the reviewed week is 18–24 November.
beforeEach(function () {
    app()->setLocale('pl');
    Carbon::setTestNow(Carbon::parse('2026-11-18 10:00'));
    $this->actingAs(User::factory()->create());

    $this->dinner = Tag::query()->firstOrCreate(['name' => 'Dinner', 'category' => Tag::MEAL_TYPE]);
    $this->supper = Tag::query()->firstOrCreate(['name' => 'Supper', 'category' => Tag::MEAL_TYPE]);
    $this->vege = Tag::query()->firstOrCreate(['name' => 'Vegetarian', 'category' => Tag::DIET_TYPE]);
    $this->fish = Tag::query()->firstOrCreate(['name' => 'Fish', 'category' => Tag::DIET_TYPE]);
    $this->chicken = Tag::query()->firstOrCreate(['name' => 'Chicken', 'category' => Tag::DIET_TYPE]);
});

afterEach(fn () => Carbon::setTestNow());

function wrRecipe(string $name, ?string $rating = null, array $tags = []): Recipe
{
    $recipe = Recipe::query()->create(['name' => $name, 'rating' => $rating]);
    $recipe->tags()->sync(array_map(fn (Tag $tag) => $tag->id, $tags));

    return $recipe;
}

function wrPlan(Recipe $recipe, string $date, string $type = 'dinner', string $mode = RecipeMealAssignment::MODE_SINGLE): RecipeMealAssignment
{
    $meal = Meal::query()->whereDate('date', $date)->where('type', $type)->first()
        ?? Meal::query()->create(['date' => $date.' 12:00:00', 'type' => $type]);

    return RecipeMealAssignment::query()->create(['meal_id' => $meal->id, 'recipe_id' => $recipe->id, 'mode' => $mode]);
}

function wrReview(): array
{
    return app(BuildWeekReview::class)(now());
}

/** The mockup's week: 3 healthy, 5 medium, 2 unhealthy. */
function wrMockupWeek(): void
{
    $slots = [
        ['2026-11-18', 'dinner'], ['2026-11-18', 'supper'], ['2026-11-19', 'dinner'], ['2026-11-19', 'supper'], ['2026-11-20', 'dinner'],
        ['2026-11-20', 'supper'], ['2026-11-21', 'dinner'], ['2026-11-21', 'supper'], ['2026-11-22', 'dinner'], ['2026-11-22', 'supper'],
    ];
    $ratings = [...array_fill(0, 3, Recipe::RATING_HEALTHY), ...array_fill(0, 5, Recipe::RATING_MEDIUM), ...array_fill(0, 2, Recipe::RATING_UNHEALTHY)];

    foreach ($ratings as $index => $rating) {
        wrPlan(wrRecipe("Danie {$index}", $rating, [test()->vege]), ...$slots[$index]);
    }
}

test('health follows the 80/20 goal as in the mockup: 3 healthy, 5 medium, 2 unhealthy', function () {
    wrMockupWeek();

    $review = wrReview();

    expect($review['meals'])->toBe(10)
        ->and($review['days'])->toBe(5)
        ->and($review['health'])->toMatchArray([
            'healthy' => 3, 'medium' => 5, 'unhealthy' => 2, 'rated' => 10,
            'healthyPercent' => 30, 'unhealthyPercent' => 20,
            'healthyOk' => false, 'unhealthyOk' => true,
            'missingHealthy' => 5, 'excessUnhealthy' => 0, 'enough' => true,
        ]);
});

test('the verdict is decided on whole meals, not on the rounded percentage', function () {
    // 4 of 6 = 67% healthy: 5 are needed (ceil of 4.8); 2 of 6 unhealthy is one over floor(1.2).
    foreach (range(1, 4) as $day) {
        wrPlan(wrRecipe("Zdrowe {$day}", Recipe::RATING_HEALTHY), '2026-11-'.(17 + $day));
    }
    wrPlan(wrRecipe('Pizza', Recipe::RATING_UNHEALTHY), '2026-11-22');
    wrPlan(wrRecipe('Burger', Recipe::RATING_UNHEALTHY), '2026-11-23');

    expect(wrReview()['health'])->toMatchArray([
        'healthyPercent' => 67, 'unhealthyPercent' => 33,
        'healthyOk' => false, 'unhealthyOk' => false,
        'missingHealthy' => 1, 'excessUnhealthy' => 1,
    ]);
});

test('notes and unrated recipes stay out of the health numbers; leftovers count', function () {
    $soup = wrRecipe('Zupa', Recipe::RATING_HEALTHY, [$this->vege]);
    wrPlan($soup, '2026-11-18', 'dinner', RecipeMealAssignment::MODE_FULL);
    wrPlan($soup, '2026-11-19', 'dinner', RecipeMealAssignment::MODE_LEFTOVER);
    wrPlan(wrRecipe('Bez oceny', null, [$this->fish]), '2026-11-20');
    Meal::query()->create(['date' => '2026-11-21 12:00:00', 'type' => 'dinner', 'note' => 'Na mieście']);
    wrPlan(wrRecipe('Poza tygodniem', Recipe::RATING_UNHEALTHY), '2026-11-25');

    $review = wrReview();

    expect($review['meals'])->toBe(3)
        ->and($review['health'])->toMatchArray(['healthy' => 2, 'rated' => 2, 'unrated' => 1, 'enough' => false])
        ->and($review['variety'])->toMatchArray(['tagged' => 3, 'untagged' => 0, 'distinct' => 2])
        ->and(collect($review['cards'])->pluck('key'))->not->toContain(BuildWeekReview::CARD_HEALTH);
});

test('variety counts meals per main ingredient, zeros included, and names a recipe planned three times', function () {
    $pierogi = wrRecipe('Pierogi ruskie', Recipe::RATING_MEDIUM, [$this->vege]);
    wrPlan($pierogi, '2026-11-18', 'dinner', RecipeMealAssignment::MODE_FULL);
    wrPlan($pierogi, '2026-11-19', 'dinner', RecipeMealAssignment::MODE_LEFTOVER);
    wrPlan($pierogi, '2026-11-20', 'dinner', RecipeMealAssignment::MODE_LEFTOVER);
    wrPlan(wrRecipe('Łosoś', Recipe::RATING_HEALTHY, [$this->fish]), '2026-11-21');

    $variety = wrReview()['variety'];

    // Every main-ingredient group is listed: most meals first, then by name.
    expect(collect($variety['groups'])->pluck('count', 'label')->take(3)->all())->toBe(['Wegetariańskie' => 3, 'Ryba' => 1, 'Drób' => 0])
        ->and(collect($variety['groups'])->sum('count'))->toBe(4)
        ->and($variety['groups'][0]['percent'])->toBe(75)
        ->and($variety['distinct'])->toBe(2)
        ->and($variety['mostRepeated'])->toBe(['name' => 'Pierogi ruskie', 'count' => 3]);
});

test('the same recipe four days in a row gets a card; three days do not', function () {
    $pierogi = wrRecipe('Pierogi ruskie', Recipe::RATING_MEDIUM, [$this->vege]);
    wrPlan($pierogi, '2026-11-18', 'dinner', RecipeMealAssignment::MODE_FULL);
    wrPlan($pierogi, '2026-11-19', 'dinner', RecipeMealAssignment::MODE_LEFTOVER);
    wrPlan($pierogi, '2026-11-20', 'dinner', RecipeMealAssignment::MODE_LEFTOVER);

    expect(collect(wrReview()['cards'])->pluck('key'))->not->toContain('streak-'.$pierogi->id);

    wrPlan($pierogi, '2026-11-21', 'dinner', RecipeMealAssignment::MODE_LEFTOVER);

    $card = collect(wrReview()['cards'])->firstWhere('key', 'streak-'.$pierogi->id);

    expect($card['title'])->toBe('Pierogi ruskie 4 dni z rzędu')
        ->and($card['body'])->toContain('Środa, czwartek, piątek, sobota')->toContain('z jednego gotowania')
        ->and($card['day'])->toBe('2026-11-20');
});

test('cards suggest healthy recipes, missing groups and long-unseen recipes, each recipe once', function () {
    wrMockupWeek();

    $salmon = wrRecipe('Łosoś z piekarnika', Recipe::RATING_HEALTHY, [$this->fish, $this->dinner]);
    wrPlan($salmon, '2026-10-20');
    $turkey = wrRecipe('Indyk z warzywami', Recipe::RATING_MEDIUM, [$this->chicken, $this->dinner]);
    wrPlan($turkey, '2026-11-10');
    $risotto = wrRecipe('Risotto', Recipe::RATING_MEDIUM, [$this->vege]);
    wrPlan($risotto, '2026-10-01');
    wrRecipe('Sałatka grecka', Recipe::RATING_MEDIUM, [$this->vege]);
    wrRecipe('Zarchiwizowany', Recipe::RATING_HEALTHY, [$this->fish])->update(['archived_at' => now()]);

    $cards = collect(wrReview()['cards'])->keyBy('key');

    expect($cards[BuildWeekReview::CARD_HEALTH]['title'])->toBe('Do celu 80/20 brakuje 5 zdrowych posiłków')
        ->and($cards[BuildWeekReview::CARD_HEALTH]['body'])->toContain('Jeszcze puste: poniedziałek, wtorek.')
        ->and(array_column($cards[BuildWeekReview::CARD_HEALTH]['suggestions'], 'name'))->toBe(['Łosoś z piekarnika'])
        ->and($cards[BuildWeekReview::CARD_HEALTH]['suggestions'][0]['last'])->toBe('ostatnio: 4 tyg. temu')
        // Fish went to the health card already, so only chicken is left to suggest here.
        ->and($cards[BuildWeekReview::CARD_GROUPS]['title'])->toBe('W planie brakuje: drób, ryba')
        ->and($cards[BuildWeekReview::CARD_GROUPS]['body'])->toBe('10 z 10 posiłków to wegetariańskie.')
        ->and(array_column($cards[BuildWeekReview::CARD_GROUPS]['suggestions'], 'name'))->toBe(['Indyk z warzywami'])
        ->and(array_column($cards[BuildWeekReview::CARD_LONG_AGO]['suggestions'], 'name'))->toBe(['Risotto', 'Sałatka grecka'])
        ->and(array_column($cards[BuildWeekReview::CARD_LONG_AGO]['suggestions'], 'last'))->toBe(['ostatnio: 6 tyg. temu', 'jeszcze nie było']);
});

test('the page shows the numbers and is reachable from the calendar', function () {
    wrMockupWeek();

    $this->get(route('meals.index'))->assertOk()->assertSee('Ocena tygodnia')->assertSee('Zdrowe 30% · cel 80%');

    $this->get(route('meals.review'))->assertOk()
        ->assertSee('10 posiłków w 5 dniach')
        ->assertSee('poniżej celu')
        ->assertSee('w celu')
        ->assertSee('Do 80% brakuje 5 zdrowych.')
        ->assertSee('10 różnych przepisów na 10 posiłków');
});

test('"Insert" puts a suggestion into a free dinner, keeps it on the card, and "Undo" takes it back', function () {
    wrMockupWeek();
    $salmon = wrRecipe('Łosoś z piekarnika', Recipe::RATING_HEALTHY, [$this->fish, $this->dinner, $this->supper]);
    $omelette = wrRecipe('Omlet', Recipe::RATING_HEALTHY, [$this->vege, $this->supper]);

    $page = Livewire::test('pages::meal.review')
        ->assertSee('Łosoś z piekarnika')
        ->call('insert', $salmon->id, BuildWeekReview::CARD_HEALTH)
        ->assertSee('W planie: poniedziałek, obiad')
        ->call('insert', $omelette->id, BuildWeekReview::CARD_HEALTH)
        // Monday now has a dinner, so the emptier Tuesday gets the supper.
        ->assertSee('W planie: wtorek, kolacja');

    $entries = RecipeMealAssignment::query()->with('meal')->whereIn('recipe_id', [$salmon->id, $omelette->id])->get()->keyBy('recipe_id');

    expect($entries[$salmon->id]->meal->date->toDateString())->toBe('2026-11-23')
        ->and($entries[$salmon->id]->meal->type)->toBe('dinner')
        ->and($entries[$salmon->id]->mode)->toBe(RecipeMealAssignment::MODE_SINGLE)
        ->and($entries[$omelette->id]->meal->type)->toBe('supper')
        ->and(wrReview()['health']['healthy'])->toBe(5);

    $page->call('undo', $salmon->id)->assertDontSee('W planie: poniedziałek, obiad');

    expect(RecipeMealAssignment::query()->where('recipe_id', $salmon->id)->exists())->toBeFalse()
        ->and(Meal::query()->whereDate('date', '2026-11-23')->where('type', 'dinner')->exists())->toBeFalse()
        ->and(RecipeMealAssignment::query()->where('recipe_id', $omelette->id)->exists())->toBeTrue();
});

test('nothing is inserted into past days or places holding a note, and "Undo" only removes own insertions', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-24 10:00'));
    $salmon = wrRecipe('Łosoś', Recipe::RATING_HEALTHY, [$this->fish, $this->dinner]);
    $planned = wrPlan(wrRecipe('Zupa', Recipe::RATING_HEALTHY), '2026-11-19');
    Meal::query()->create(['date' => '2026-11-24 12:00:00', 'type' => 'dinner', 'note' => 'Na mieście']);

    // Reviewing 18–24 on the 24th: the only day left has a note in the dinner place.
    Livewire::withQueryParams(['week' => '2026-11-18'])->test('pages::meal.review')
        ->call('insert', $salmon->id, BuildWeekReview::CARD_LONG_AGO)
        ->call('undo', $planned->recipe_id);

    expect(RecipeMealAssignment::query()->where('recipe_id', $salmon->id)->exists())->toBeFalse()
        ->and(RecipeMealAssignment::query()->whereKey($planned->id)->exists())->toBeTrue();
});
