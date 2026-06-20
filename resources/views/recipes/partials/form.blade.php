@php
    /** @var array<int, array{name?: string, quantity?: string}> $ingredientRows */
    $ingredientRows = $ingredientRows ?? [['name' => '', 'quantity' => '']];
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

<div class="space-y-2">
    <label for="recipe-name" class="block text-sm font-medium text-zinc-800 dark:text-zinc-200">Nazwa</label>
    <input
        id="recipe-name"
        name="name"
        type="text"
        value="{{ old('name', $recipe->name ?? '') }}"
        class="w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-zinc-500 focus:outline-none focus:ring-2 focus:ring-zinc-500/40 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
        required
    >
</div>

<div class="space-y-2">
    <label for="recipe-content" class="block text-sm font-medium text-zinc-800 dark:text-zinc-200">Przygotowanie</label>
    <textarea
        id="recipe-content"
        name="content"
        rows="6"
        class="w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-zinc-500 focus:outline-none focus:ring-2 focus:ring-zinc-500/40 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
        required
    >{{ old('content', $recipe->content ?? '') }}</textarea>
</div>

<div class="space-y-3">
    <div class="flex items-center justify-between">
        <label class="block text-sm font-medium text-zinc-800 dark:text-zinc-200">Składniki</label>
        <button
            type="button"
            id="add-ingredient-row"
            class="rounded-md border border-zinc-300 px-3 py-1.5 text-xs font-medium text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800"
        >
            Dodaj składnik
        </button>
    </div>

    <p class="text-xs text-zinc-500 dark:text-zinc-400">Wpisz nazwę i wybierz z listy. Jeśli składnika nie ma, wpisz nową nazwę i zostanie utworzony automatycznie.</p>

    <datalist id="ingredient-names-list">
        @foreach ($allIngredientNames as $ingredientName)
            <option value="{{ $ingredientName }}"></option>
        @endforeach
    </datalist>

    <div id="ingredients-container" class="space-y-2">
        @foreach ($ingredientRows as $index => $row)
            <div class="ingredient-row grid grid-cols-1 gap-2 md:grid-cols-[1fr_220px_auto]" data-row-index="{{ $index }}">
                <input
                    type="text"
                    name="ingredients[{{ $index }}][name]"
                    value="{{ $row['name'] ?? '' }}"
                    list="ingredient-names-list"
                    placeholder="Nazwa składnika"
                    class="rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-zinc-500 focus:outline-none focus:ring-2 focus:ring-zinc-500/40 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                >

                <input
                    type="text"
                    name="ingredients[{{ $index }}][quantity]"
                    value="{{ $row['quantity'] ?? '' }}"
                    placeholder="Ilość (np. 2 łyżki)"
                    class="rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-zinc-500 focus:outline-none focus:ring-2 focus:ring-zinc-500/40 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                >

                <button
                    type="button"
                    class="remove-ingredient-row rounded-md border border-rose-300 px-3 py-2 text-sm text-rose-700 hover:bg-rose-50 dark:border-rose-800 dark:text-rose-300 dark:hover:bg-rose-950/40"
                >
                    Usuń
                </button>
            </div>
        @endforeach
    </div>
</div>

<div class="flex items-center justify-end gap-3">
    <a href="{{ route('recipes.index') }}" class="rounded-md border border-zinc-300 px-4 py-2 text-sm text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800">Anuluj</a>
    <button
        type="submit"
        class="rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-700 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-200"
    >
        {{ $submitLabel }}
    </button>
</div>

<template id="ingredient-row-template">
    <div class="ingredient-row grid grid-cols-1 gap-2 md:grid-cols-[1fr_220px_auto]" data-row-index="__INDEX__">
        <input
            type="text"
            name="ingredients[__INDEX__][name]"
            list="ingredient-names-list"
            placeholder="Nazwa składnika"
            class="rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-zinc-500 focus:outline-none focus:ring-2 focus:ring-zinc-500/40 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
        >

        <input
            type="text"
            name="ingredients[__INDEX__][quantity]"
            placeholder="Ilość (np. 2 łyżki)"
            class="rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-zinc-500 focus:outline-none focus:ring-2 focus:ring-zinc-500/40 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
        >

        <button
            type="button"
            class="remove-ingredient-row rounded-md border border-rose-300 px-3 py-2 text-sm text-rose-700 hover:bg-rose-50 dark:border-rose-800 dark:text-rose-300 dark:hover:bg-rose-950/40"
        >
            Usuń
        </button>
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

                row.querySelectorAll('input').forEach((input) => {
                    const isName = input.name.includes('[name]');
                    input.name = `ingredients[${index}][${isName ? 'name' : 'quantity'}]`;
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

        container.querySelectorAll('.remove-ingredient-row').forEach((button) => {
            attachRemoveHandler(button);
        });

        addButton.addEventListener('click', () => {
            const index = container.querySelectorAll('.ingredient-row').length;
            const html = template.innerHTML.replaceAll('__INDEX__', String(index));
            container.insertAdjacentHTML('beforeend', html);

            const inserted = container.querySelector('.ingredient-row:last-child .remove-ingredient-row');
            if (inserted) {
                attachRemoveHandler(inserted);
            }
        });
    });
</script>