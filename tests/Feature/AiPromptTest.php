<?php

use App\Actions\MealPlan\BuildMealPlanReport;
use App\Models\Meal;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    app()->setLocale('pl');
    Carbon::setTestNow(Carbon::parse('2026-11-18 10:00'));
});

afterEach(fn () => Carbon::setTestNow());

test('an edited prompt is saved for the user and can be restored to the default', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $page = Livewire::test('pages::settings.ai-report')
        ->assertSet('promptTemplate', BuildMealPlanReport::DEFAULT_TEMPLATE)
        ->assertDontSee('zmieniony')
        ->set('promptTemplate', "Zaplanuj {liczba_dni} dni od {plan_od}.\n{przepisy}")
        ->assertSee('zmieniony');

    expect($user->fresh()->ai_prompt_template)->toBe("Zaplanuj {liczba_dni} dni od {plan_od}.\n{przepisy}");

    Livewire::test('pages::settings.ai-report')->assertSet('promptTemplate', "Zaplanuj {liczba_dni} dni od {plan_od}.\n{przepisy}");

    $page->call('resetPrompt')->assertSet('promptTemplate', BuildMealPlanReport::DEFAULT_TEMPLATE);

    expect($user->fresh()->ai_prompt_template)->toBeNull();
});

test('generating fills the fields of the user template with form data and history', function () {
    $user = User::factory()->create(['ai_prompt_template' => "Od {plan_od} do {plan_do}, {liczba_dni} dni.\nSkładniki: {skladniki}\nŻyczenia: {zyczenia}\nHistoria:\n{historia}\nPrzepisy:\n{przepisy}"]);
    $this->actingAs($user);

    $soup = Recipe::query()->create(['name' => 'Zupa pomidorowa', 'servings' => 4, 'rating' => Recipe::RATING_HEALTHY]);
    Recipe::query()->create(['name' => 'Stara zupa', 'archived_at' => now()]);
    Meal::query()->create(['date' => '2026-11-10 12:00:00', 'type' => 'lunch'])->recipes()->attach($soup->id);

    $report = Livewire::test('pages::settings.ai-report')
        ->set('ingredientsToUse', 'cukinia,  feta')
        ->set('planDays', 3)
        ->call('generate')
        ->get('report');

    expect($report)
        ->toContain('Od 2026-11-19 do 2026-11-21, 3 dni.')
        ->toContain('Składniki: cukinia, feta')
        ->not->toContain('Życzenia:') // empty optional field drops its line
        ->toContain('lunch: Zupa pomidorowa (#'.$soup->id.')')
        ->toContain('| Zupa pomidorowa | - | - | 4 | healthy |')
        ->not->toContain('Stara zupa')
        ->not->toMatch('/\{[a-z_]+\}/');
});

test('the default template mentions servings and no calories', function () {
    $this->actingAs(User::factory()->create());
    Recipe::query()->create(['name' => 'Owsianka', 'calories_me' => 400]);

    $report = app(BuildMealPlanReport::class)(now()->subDays(7), now(), now()->addDay(), 7);

    expect($report)->toContain('2 porcje')->not->toContain('kcal')->not->toMatch('/\{[a-z_]+\}/');
});
