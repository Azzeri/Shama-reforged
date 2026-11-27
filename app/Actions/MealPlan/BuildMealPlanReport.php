<?php

namespace App\Actions\MealPlan;

use App\Models\Meal;
use App\Models\Recipe;
use App\Models\RecipeMealAssignment;
use App\Models\Tag;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Builds the brief to paste into an AI assistant so it can propose a meal
 * plan. The text comes from a template (per user, default below) whose
 * {fields} are filled from the form and the data: recent eating history,
 * slots already taken in the target period, the recipe catalog and the
 * expected JSON output. Lines with an optional field ({skladniki},
 * {zyczenia}) are dropped when the field is empty.
 */
class BuildMealPlanReport
{
    public const FIELDS = [
        'historia_od', 'historia_do', 'historia', 'plan_od', 'plan_do', 'liczba_dni',
        'zaplanowane', 'przepisy', 'skladniki', 'zyczenia', 'format', 'api_url',
    ];

    /** Fields whose whole line disappears when they are empty. */
    public const OPTIONAL_FIELDS = ['skladniki', 'zyczenia'];

    public const DEFAULT_TEMPLATE = <<<'TXT'
# Prośba o plan posiłków

Pomagasz dwuosobowemu gospodarstwu domowemu zaplanować posiłki.
Zaplanuj wszystkie posiłki od {plan_od} do {plan_do} ({liczba_dni} dni).

## Zasady
- Rodzaje posiłków: breakfast (śniadanie), lunch, dinner (obiad, główny ciepły posiłek), supper (kolacja) — codziennie. `dessert` tylko od czasu do czasu.
- Jemy razem: jeden posiłek w planie to 2 porcje. Przepis na więcej porcji (np. 6) gotujemy raz i jemy przez kolejne dni — zaplanuj go wtedy kilka dni z rzędu na ten sam posiłek.
- Wybieraj przepisy z „Katalogu przepisów” poniżej (po numerycznym `id`) — mają składniki i trafiają prosto na listę zakupów. Preferuj te z pasującym rodzajem posiłku.
- Możesz zaproponować własne dania spoza katalogu, jeśli to poprawi plan (urozmaicenie, wykorzystanie składników). Wypisz wtedy ich składniki w podsumowaniu.
- Jeden posiłek może mieć więcej niż jeden przepis (np. danie główne + dodatek).
- Notatki w historii (np. „jemy na mieście”, nazwa dania) to prawdziwe posiłki bez przepisu w aplikacji — uwzględnij je przy preferencjach i urozmaiceniu.
- Dbaj o urozmaicenie w tygodniu i względem historii, poza gotowaniem na kilka dni i wyraźnymi ulubionymi daniami.
- NIE planuj posiłków z sekcji „Już zaplanowane” — są zajęte.
- Wykorzystaj składniki, które już mamy: {skladniki}.
- Dodatkowe życzenia: {zyczenia}

## Format odpowiedzi
{format}

## Historia posiłków ({historia_od} – {historia_do})
{historia}

## Już zaplanowane ({plan_od} – {plan_do})
{zaplanowane}

## Katalog przepisów
{przepisy}
TXT;

    public function __invoke(
        CarbonInterface $historyFrom,
        CarbonInterface $historyTo,
        CarbonInterface $planFrom,
        int $planDays,
        string $ingredientsToUse = '',
        string $extraWishes = '',
        ?string $template = null,
    ): string {
        $planTo = $planFrom->copy()->addDays($planDays - 1);

        $values = [
            'historia_od' => $historyFrom->toDateString(),
            'historia_do' => $historyTo->toDateString(),
            'historia' => $this->historySection($historyFrom, $historyTo),
            'plan_od' => $planFrom->toDateString(),
            'plan_do' => $planTo->toDateString(),
            'liczba_dni' => (string) $planDays,
            'zaplanowane' => implode("\n", $this->dayLines($this->mealsBetween($planFrom, $planTo), $planFrom, $planTo)) ?: 'Nic.',
            'przepisy' => $this->catalogSection(),
            'skladniki' => $this->oneLine($ingredientsToUse),
            'zyczenia' => $this->oneLine($extraWishes),
            'format' => $this->formatSection($planFrom),
            'api_url' => url('/api/meal-plan'),
        ];

        return self::fill(filled($template) ? $template : self::DEFAULT_TEMPLATE, $values);
    }

    /** Substitutes {fields}; lines with an empty optional field are dropped. */
    public static function fill(string $template, array $values): string
    {
        $lines = preg_split('/\R/u', $template);

        foreach (self::OPTIONAL_FIELDS as $field) {
            if (trim($values[$field] ?? '') === '') {
                $lines = array_filter($lines, fn (string $line) => ! str_contains($line, '{'.$field.'}'));
            }
        }

        $replacements = collect($values)->mapWithKeys(fn ($value, $field) => ['{'.$field.'}' => (string) $value])->all();

        return rtrim(strtr(implode("\n", $lines), $replacements))."\n";
    }

    private function formatSection(CarbonInterface $planFrom): string
    {
        $lines = [];
        $lines[] = 'Najpierw krótkie podsumowanie dla ludzi (jedna linia na dzień; przy własnych daniach dopisz składniki). Potem dokładnie jeden blok kodu JSON w tym formacie.';
        $lines[] = 'Przepisy z katalogu idą do `recipe_ids`. Własne dania idą do `note` (nazwa dania, maks. 255 znaków) z `recipe_ids: []`.';
        $lines[] = '';
        $lines[] = '```json';
        $lines[] = json_encode(['meals' => [
            ['date' => $planFrom->toDateString(), 'type' => 'breakfast', 'recipe_ids' => [12]],
            ['date' => $planFrom->toDateString(), 'type' => 'dinner', 'recipe_ids' => [34, 56], 'note' => null],
            ['date' => $planFrom->toDateString(), 'type' => 'supper', 'recipe_ids' => [], 'note' => 'Szakszuka z fetą i pieczywem'],
        ]], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $lines[] = '```';
        $lines[] = '';
        $lines[] = 'JSON można wysłać do aplikacji: `POST '.url('/api/meal-plan').'` z nagłówkiem `Authorization: Bearer <API token>` albo wkleić w polu importu. Istniejące posiłki nigdy nie są nadpisywane.';

        return implode("\n", $lines);
    }

    private function historySection(CarbonInterface $from, CarbonInterface $to): string
    {
        $history = $this->mealsBetween($from, $to);
        $lines = $this->dayLines($history, $from, $to);

        $topRecipes = $history->flatMap->recipes->countBy('id')->sortDesc()->take(10);
        $topNotes = $history->pluck('note')->filter()->map(fn ($note) => trim($note))->countBy()->sortDesc()->take(10);

        if ($topRecipes->isNotEmpty() || $topNotes->isNotEmpty()) {
            $lines[] = '';
        }

        if ($topRecipes->isNotEmpty()) {
            $byId = $history->flatMap->recipes->keyBy('id');
            $lines[] = 'Najczęściej jedzone przepisy: '.$topRecipes->map(fn ($count, $id) => "{$byId[$id]->name} (#{$id}) ×{$count}")->implode(', ').'.';
        }

        if ($topNotes->isNotEmpty()) {
            $lines[] = 'Najczęstsze notatki: '.$topNotes->map(fn ($count, $note) => "\"{$note}\" ×{$count}")->implode(', ').'.';
        }

        return implode("\n", $lines) ?: 'Brak.';
    }

    private function catalogSection(): string
    {
        $lines = ['Format: `id` | nazwa | rodzaje posiłku | główny składnik | porcje | ocena | do spakowania | składniki', ''];

        $recipes = Recipe::query()->plannable()->with(['tags', 'ingredients'])->orderBy('name')->get();

        foreach ($recipes as $recipe) {
            $mealTypes = $recipe->tags->where('category', Tag::MEAL_TYPE)->pluck('name')->implode(', ');
            $diet = $recipe->tags->where('category', Tag::DIET_TYPE)->pluck('name')->implode(', ');
            $ingredients = $recipe->ingredients
                ->map(fn ($ingredient) => $ingredient->name.($ingredient->pivot->is_optional ? ' (opcjonalnie)' : ''))
                ->implode(', ');

            $lines[] = "- `{$recipe->id}` | {$recipe->name} | ".($mealTypes ?: '-').' | '.($diet ?: '-')
                ." | {$recipe->servings} | ".($recipe->rating ?: '-').' | '.($recipe->packable ? 'tak' : '-')
                .' | '.($ingredients ?: '-');
        }

        return implode("\n", $lines);
    }

    private function mealsBetween(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return Meal::query()
            ->with('recipes:id,name')
            ->whereBetween(Meal::DATE_COLUMN, [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->orderBy(Meal::DATE_COLUMN)
            ->get();
    }

    /** @return list<string> */
    private function dayLines(Collection $meals, CarbonInterface $from, CarbonInterface $to): array
    {
        $lines = [];
        $byDay = $meals->groupBy(fn (Meal $meal) => $meal->date->toDateString());

        for ($day = $from->copy()->startOfDay(); $day->lte($to); $day = $day->addDay()) {
            $dayMeals = $byDay->get($day->toDateString(), collect())
                ->sortBy(fn (Meal $meal) => array_search($meal->type, Meal::TYPES, true));

            if ($dayMeals->isEmpty()) {
                continue;
            }

            if ($lines) {
                $lines[] = '';
            }

            $lines[] = "### {$day->toDateString()} ({$day->englishDayOfWeek})";

            foreach ($dayMeals as $meal) {
                $parts = $meal->recipes->map(fn (Recipe $r) => "{$r->name} (#{$r->id})".match ($r->pivot->mode) {
                    RecipeMealAssignment::MODE_FULL => ' [gotowany cały przepis]',
                    RecipeMealAssignment::MODE_LEFTOVER => ' [resztki]',
                    default => '',
                }.($r->pivot->skip_shopping ? ' [mam gotowe, bez zakupów]' : ''))->all();

                if (filled($meal->note)) {
                    $parts[] = "notatka: {$meal->note}";
                }

                $lines[] = "- {$meal->type}: ".(implode(', ', $parts) ?: '-');
            }
        }

        return $lines;
    }

    private function oneLine(string $text): string
    {
        return trim(preg_replace('/\s+/', ' ', $text));
    }
}
