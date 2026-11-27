<?php

namespace App\Actions\MealPlan;

use App\Models\Meal;
use App\Models\Recipe;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Imports an AI-generated meal plan. Only fills empty slots: a (date, type)
 * that already has recipes or a note is skipped, never overwritten.
 */
class ImportMealPlan
{
    /**
     * @return array{created: list<string>, skipped: list<string>}
     *
     * @throws ValidationException
     */
    public function __invoke(array $payload): array
    {
        $data = Validator::make($payload, [
            'meals' => ['required', 'array', 'min:1', 'max:200'],
            'meals.*.date' => ['required', 'date_format:Y-m-d'],
            'meals.*.type' => ['required', Rule::in(Meal::TYPES)],
            'meals.*.recipe_ids' => ['present', 'array'],
            'meals.*.recipe_ids.*' => ['integer', 'distinct', Rule::exists(Recipe::class, 'id')->where('is_semi_product', false)],
            'meals.*.note' => ['nullable', 'string', 'max:255'],
        ])->validate();

        $created = [];
        $skipped = [];

        DB::transaction(function () use ($data, &$created, &$skipped) {
            foreach ($data['meals'] as $row) {
                $label = "{$row['date']} {$row['type']}";
                $note = trim((string) ($row['note'] ?? ''));

                if (empty($row['recipe_ids']) && $note === '') {
                    continue;
                }

                $existing = Meal::query()
                    ->with('recipes:id')
                    ->whereDate(Meal::DATE_COLUMN, $row['date'])
                    ->where(Meal::TYPE_COLUMN, $row['type'])
                    ->get();

                if ($existing->contains(fn (Meal $meal) => $meal->recipes->isNotEmpty() || filled($meal->note))) {
                    $skipped[] = $label;

                    continue;
                }

                $meal = $existing->first() ?? new Meal([
                    'date' => Carbon::parse($row['date'])->setTime(12, 0),
                    'type' => $row['type'],
                ]);
                $meal->note = $note !== '' ? $note : null;
                $meal->save();
                $meal->recipes()->sync($row['recipe_ids']);

                $created[] = $label;
            }
        });

        return ['created' => $created, 'skipped' => $skipped];
    }
}
