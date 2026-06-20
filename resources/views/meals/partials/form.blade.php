@php
    /** @var array<int, array{recipe_id?: string}> $mealRecipeRows */
    $mealRecipeRows = $mealRecipeRows ?? [['recipe_id' => '']];
    $recipeOptions = $recipeOptions ?? collect();
    $typeOptions = $typeOptions ?? \App\Models\Meal::TYPE_LABELS;
    $errors = $errors ?? new \Illuminate\Support\ViewErrorBag();
    $meal = $meal ?? null;
    $typeValue = old('type', $meal?->type ?? '');
    $dateValue = old('date', $prefilledDate ?? ($meal?->date?->format('Y-m-d\TH:i') ?? now()->format('Y-m-d\TH:i')));
@endphp

@if ($errors->any())
    <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950/50 dark:text-rose-300">
        <ul class="list-disc space-y-1 ps-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<flux:select name="type" :label="__('Typ posiłku')" required>
    <flux:select.option value="" :selected="empty($typeValue)">{{ __('Wybierz typ') }}</flux:select.option>
    @foreach ($typeOptions as $value => $label)
        <flux:select.option value="{{ $value }}" :selected="(string) $typeValue === (string) $value">{{ $label }}</flux:select.option>
    @endforeach
</flux:select>

<flux:input
    name="date"
    :label="__('Data i godzina')"
    type="datetime-local"
    :value="$dateValue"
    required
/>

<div class="space-y-3">
    <div class="flex items-center justify-between gap-3">
        <label class="block text-sm font-medium text-zinc-800 dark:text-zinc-200">Przepisy</label>
        <flux:button type="button" id="add-meal-recipe-row" variant="ghost" size="sm">Dodaj przepis</flux:button>
    </div>

    <p class="text-xs text-zinc-500 dark:text-zinc-400">Wybierz jeden lub więcej przepisów, które chcesz przypisać do tego posiłku.</p>

    <div id="meal-recipes-container" class="space-y-2">
        @foreach ($mealRecipeRows as $index => $row)
            <div class="meal-recipe-row grid grid-cols-1 gap-2 md:grid-cols-[minmax(0,1fr)_auto] md:items-start" data-row-index="{{ $index }}">
                <flux:select searchable variant="default" :name="'recipes['.$index.'][recipe_id]'" :placeholder="__('Wybierz przepis')">
                    <flux:select.option value="" :selected="empty($row['recipe_id'] ?? '')">{{ __('Wybierz przepis') }}</flux:select.option>
                    @foreach ($recipeOptions as $recipeOption)
                        <flux:select.option value="{{ $recipeOption->id }}" :selected="(string) ($row['recipe_id'] ?? '') === (string) $recipeOption->id">{{ $recipeOption->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <div class="flex items-start pt-7 md:pt-0">
                    <flux:button type="button" icon="trash" variant="danger" size="sm" data-remove-meal-recipe-row />
                </div>
            </div>
        @endforeach
    </div>
</div>

<div class="flex items-center justify-end gap-3">
    <flux:link href="{{ route('meals.index') }}">Anuluj</flux:link>
    <flux:button type="submit" variant="primary">{{ $submitLabel }}</flux:button>
</div>

<template id="meal-recipe-row-template">
    <div class="meal-recipe-row grid grid-cols-1 gap-2 md:grid-cols-[minmax(0,1fr)_auto] md:items-start" data-row-index="__INDEX__">
        <flux:select searchable variant="default" :name="'recipes[__INDEX__][recipe_id]'" :placeholder="__('Wybierz przepis')">
            <flux:select.option value="" selected>{{ __('Wybierz przepis') }}</flux:select.option>
            @foreach ($recipeOptions as $recipeOption)
                <flux:select.option value="{{ $recipeOption->id }}">{{ $recipeOption->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <div class="flex items-start pt-7 md:pt-0">
            <flux:button type="button" icon="trash" variant="danger" size="sm" data-remove-meal-recipe-row />
        </div>
    </div>
</template>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const container = document.getElementById('meal-recipes-container');
        const addButton = document.getElementById('add-meal-recipe-row');
        const template = document.getElementById('meal-recipe-row-template');

        if (!container || !addButton || !template) {
            return;
        }

        const reindexRows = () => {
            container.querySelectorAll('.meal-recipe-row').forEach((row, index) => {
                row.dataset.rowIndex = String(index);

                row.querySelectorAll('[name]').forEach((input) => {
                    if (input.name.includes('[recipe_id]')) {
                        input.name = `recipes[${index}][recipe_id]`;
                    }
                });
            });
        };

        const attachRemoveHandler = (button) => {
            button.addEventListener('click', () => {
                const row = button.closest('.meal-recipe-row');
                if (!row) {
                    return;
                }

                row.remove();

                if (container.querySelectorAll('.meal-recipe-row').length === 0) {
                    container.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', '0'));
                    const newButton = container.querySelector('[data-remove-meal-recipe-row]');
                    if (newButton) {
                        attachRemoveHandler(newButton);
                    }
                    reindexRows();
                } else {
                    reindexRows();
                }
            });
        };

        container.querySelectorAll('[data-remove-meal-recipe-row]').forEach((button) => attachRemoveHandler(button));

        addButton.addEventListener('click', () => {
            const nextIndex = container.querySelectorAll('.meal-recipe-row').length;
            container.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(nextIndex)));

            const rows = container.querySelectorAll('.meal-recipe-row');
            const newRow = rows[rows.length - 1];
            const newButton = newRow ? newRow.querySelector('[data-remove-meal-recipe-row]') : null;
            if (newButton) {
                attachRemoveHandler(newButton);
            }

            reindexRows();
        });
    });
</script>