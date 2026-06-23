@php
    /** @var array<int, array{ingredient_id?: string, ingredient_name?: string, custom_name?: string, quantity?: string}> $ingredientRows */
    $ingredientRows = $ingredientRows ?? [['ingredient_id' => '', 'ingredient_name' => '', 'custom_name' => '', 'quantity' => '']];
    $ingredientOptions = $ingredientOptions ?? collect();
    $mealTypeTags = $mealTypeTags ?? collect();
    $dietTypeTags = $dietTypeTags ?? collect();
    $selectedTagIds = collect($selectedTagIds ?? [])->map(fn ($id) => (int) $id);
    $errors = $errors ?? new \Illuminate\Support\ViewErrorBag();
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

<flux:input
    name="name"
    :label="__('Nazwa')"
    type="text"
    :value="old('name', $recipe->name ?? '')"
    required
/>

<flux:input
    name="link"
    :label="__('Link do przepisu (opcjonalnie)')"
    type="url"
    :value="old('link', $recipe->link ?? '')"
    :placeholder="__('https://example.com/przepis')"
/>

<flux:textarea
    name="content"
    :label="__('Przygotowanie / opis (opcjonalnie)')"
    rows="6"
>{{ old('content', $recipe->content ?? '') }}</flux:textarea>

<div class="space-y-3">
    <div class="space-y-2">
        <label class="block text-sm font-medium text-zinc-800 dark:text-zinc-200">Posiłek</label>
        <p class="text-xs text-zinc-500 dark:text-zinc-400">Wybierz typ posiłku.</p>
        <div class="flex flex-wrap gap-2">
            @foreach ($mealTypeTags as $tag)
                <label class="inline-flex items-center gap-2 rounded-full border border-zinc-300 px-3 py-1.5 text-sm dark:border-zinc-700">
                    <input
                        type="checkbox"
                        name="tags[]"
                        value="{{ $tag->id }}"
                        @checked($selectedTagIds->contains((int) $tag->id))
                    >
                    <span>{{ $tag->name }}</span>
                </label>
            @endforeach
        </div>
    </div>

    <div class="space-y-2">
        <label class="block text-sm font-medium text-zinc-800 dark:text-zinc-200">Dieta</label>
        <p class="text-xs text-zinc-500 dark:text-zinc-400">Wybierz typ diety / składników głównych.</p>
        <div class="flex flex-wrap gap-2">
            @foreach ($dietTypeTags as $tag)
                <label class="inline-flex items-center gap-2 rounded-full border border-zinc-300 px-3 py-1.5 text-sm dark:border-zinc-700">
                    <input
                        type="checkbox"
                        name="tags[]"
                        value="{{ $tag->id }}"
                        @checked($selectedTagIds->contains((int) $tag->id))
                    >
                    <span>{{ $tag->name }}</span>
                </label>
            @endforeach
        </div>
    </div>
</div>

<div class="space-y-3">
    <div class="flex items-center justify-between">
        <label class="block text-sm font-medium text-zinc-800 dark:text-zinc-200">Składniki</label>
        <flux:button type="button" id="add-ingredient-row" variant="primary" icon="plus" size="sm">
            Dodaj składnik
        </flux:button>
    </div>

    <p class="text-xs text-zinc-500 dark:text-zinc-400">Wpisz nazwę składnika i wybierz z podpowiedzi. Jeśli nie znajdziesz, zostaw wpisaną nazwę - składnik zostanie utworzony automatycznie.</p>

    <datalist id="ingredient-name-options">
        @foreach ($ingredientOptions as $ingredientOption)
            <option value="{{ $ingredientOption->name }}"></option>
        @endforeach
    </datalist>

    <div id="ingredients-container" class="space-y-2">
        @foreach ($ingredientRows as $index => $row)
            <div class="ingredient-row rounded-xl border border-zinc-200 p-3 dark:border-zinc-700">
                <div class="grid grid-cols-1 gap-2 md:grid-cols-[minmax(0,1.3fr)_minmax(0,0.8fr)] md:items-start" data-row-index="{{ $index }}">
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between gap-2">
                            <label class="block text-sm font-medium text-zinc-800 dark:text-zinc-200">Składnik</label>
                            <flux:button
                                type="button"
                                icon="trash"
                                variant="danger"
                                size="sm"
                                class="h-7 min-h-0 px-1.5"
                                data-remove-ingredient-row
                            />
                        </div>

                        <flux:input
                            :name="'ingredients['.$index.'][ingredient_name]'"
                            :value="$row['ingredient_name'] ?? ($row['custom_name'] ?? '')"
                            :placeholder="__('Zacznij wpisywać nazwę składnika')"
                            list="ingredient-name-options"
                            required
                        />
                    </div>

                    <input type="hidden" :name="'ingredients['.$index.'][ingredient_id]'" value="{{ $row['ingredient_id'] ?? '' }}" />

                    <flux:input
                        :name="'ingredients['.$index.'][quantity]'"
                        type="text"
                        :label="__('Ilość')"
                        :value="$row['quantity'] ?? ''"
                        :placeholder="__('np. 2 łyżki')"
                    />
                </div>
            </div>
        @endforeach
    </div>
</div>

<div class="flex items-center justify-end gap-3">
    <flux:link href="{{ route('recipes.index') }}">Anuluj</flux:link>
    <flux:button type="submit" variant="primary">{{ $submitLabel }}</flux:button>
</div>

<template id="ingredient-row-template">
    <div class="ingredient-row rounded-xl border border-zinc-200 p-3 dark:border-zinc-700">
        <div class="grid grid-cols-1 gap-2 md:grid-cols-[minmax(0,1.3fr)_minmax(0,0.8fr)] md:items-start" data-row-index="__INDEX__">
            <div class="space-y-1.5">
                <div class="flex items-center justify-between gap-2">
                    <label class="block text-sm font-medium text-zinc-800 dark:text-zinc-200">Składnik</label>
                    <flux:button type="button" icon="trash" variant="danger" size="sm" class="h-7 min-h-0 px-1.5" data-remove-ingredient-row />
                </div>

                <flux:input
                    :name="'ingredients[__INDEX__][ingredient_name]'"
                    :placeholder="__('Zacznij wpisywać nazwę składnika')"
                    list="ingredient-name-options"
                    required
                />
            </div>

            <input type="hidden" :name="'ingredients[__INDEX__][ingredient_id]'" value="" />

            <flux:input
                :name="'ingredients[__INDEX__][quantity]'"
                type="text"
                :label="__('Ilość')"
                :placeholder="__('np. 2 łyżki')"
            />
        </div>
    </div>
</template>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const container = document.getElementById('ingredients-container');
        const addButton = document.getElementById('add-ingredient-row');
        const template = document.getElementById('ingredient-row-template');

        if (!container || !addButton || !template) {
            return;
        }

        const reindexRows = () => {
            container.querySelectorAll('.ingredient-row').forEach((row, index) => {
                row.dataset.rowIndex = String(index);

                row.querySelectorAll('[name]').forEach((input) => {
                    if (input.name.includes('[ingredient_id]')) {
                        input.name = `ingredients[${index}][ingredient_id]`;
                    }

                    if (input.name.includes('[ingredient_name]')) {
                        input.name = `ingredients[${index}][ingredient_name]`;
                    }

                    if (input.name.includes('[custom_name]')) {
                        input.name = `ingredients[${index}][custom_name]`;
                    }

                    if (input.name.includes('[quantity]')) {
                        input.name = `ingredients[${index}][quantity]`;
                    }
                });
            });
        };

        const attachRemoveHandler = (button) => {
            button.addEventListener('click', () => {
                const row = button.closest('.ingredient-row');
                if (!row) {
                    return;
                }

                row.remove();

                if (container.querySelectorAll('.ingredient-row').length === 0) {
                    addButton.click();
                }

                reindexRows();
            });
        };

        container.querySelectorAll('[data-remove-ingredient-row]').forEach((button) => {
            attachRemoveHandler(button);
        });

        addButton.addEventListener('click', () => {
            const index = container.querySelectorAll('.ingredient-row').length;
            const html = template.innerHTML.replaceAll('__INDEX__', String(index));
            container.insertAdjacentHTML('beforeend', html);

            const newRow = container.querySelector('.ingredient-row:last-child');
            const removeButton = newRow ? newRow.querySelector('[data-remove-ingredient-row]') : null;
            if (removeButton) {
                attachRemoveHandler(removeButton);
            }

            const ingredientNameInput = newRow ? newRow.querySelector('input[name$="[ingredient_name]"]') : null;
            if (ingredientNameInput instanceof HTMLInputElement) {
                ingredientNameInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                ingredientNameInput.focus();
            }
        });
    });
</script>