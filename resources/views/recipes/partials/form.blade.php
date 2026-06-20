@php
    /** @var array<int, array{ingredient_id?: string, custom_name?: string, quantity?: string}> $ingredientRows */
    $ingredientRows = $ingredientRows ?? [['ingredient_id' => '', 'custom_name' => '', 'quantity' => '']];
    $ingredientOptions = $ingredientOptions ?? collect();
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

<flux:textarea
    name="content"
    :label="__('Przygotowanie')"
    rows="6"
    required
>{{ old('content', $recipe->content ?? '') }}</flux:textarea>

<div class="space-y-3">
    <div class="flex items-center justify-between">
        <label class="block text-sm font-medium text-zinc-800 dark:text-zinc-200">Składniki</label>
        <flux:button type="button" id="add-ingredient-row" variant="ghost" size="sm">
            Dodaj składnik
        </flux:button>
    </div>

    <p class="text-xs text-zinc-500 dark:text-zinc-400">Wpisz nazwę i wybierz z listy. Jeśli składnika nie ma, wpisz nową nazwę i zostanie utworzony automatycznie.</p>

    <div id="ingredients-container" class="space-y-2">
        @foreach ($ingredientRows as $index => $row)
            <div class="ingredient-row grid grid-cols-1 gap-2 md:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)_auto] md:items-start" data-row-index="{{ $index }}">
                <flux:select searchable variant="default" :name="'ingredients['.$index.'][ingredient_id]'" :placeholder="__('Wybierz składnik')">
                    <flux:select.option value="" :selected="empty($row['ingredient_id'] ?? '')">{{ __('Wybierz składnik') }}</flux:select.option>
                    @foreach ($ingredientOptions as $ingredientOption)
                        <flux:select.option value="{{ $ingredientOption->id }}" :selected="(string) ($row['ingredient_id'] ?? '') === (string) $ingredientOption->id">{{ $ingredientOption->name }}</flux:select.option>
                    @endforeach
                    <flux:select.option value="__new__" :selected="($row['ingredient_id'] ?? '') === '__new__'">{{ __('Dodaj nowy składnik') }}</flux:select.option>
                </flux:select>

                <flux:input
                    :name="'ingredients['.$index.'][quantity]'"
                    type="text"
                    :label="__('Ilość')"
                    :value="$row['quantity'] ?? ''"
                    :placeholder="__('np. 2 łyżki')"
                />

                <div class="flex items-start pt-7">
                    <flux:button
                        type="button"
                        icon="trash"
                        variant="danger"
                        size="sm"
                        data-remove-ingredient-row
                    />
                </div>

                <div class="custom-ingredient-row col-span-full @if (($row['ingredient_id'] ?? '') !== '__new__') hidden @endif">
                    <flux:input
                        :name="'ingredients['.$index.'][custom_name]'"
                        type="text"
                        :label="__('Nowy składnik')"
                        :value="$row['custom_name'] ?? ''"
                        :placeholder="__('Wpisz nazwę nowego składnika')"
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
    <div class="ingredient-row grid grid-cols-1 gap-2 md:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)_auto] md:items-start" data-row-index="__INDEX__">
        <flux:select searchable variant="default" :name="'ingredients[__INDEX__][ingredient_id]'" :placeholder="__('Wybierz składnik')">
            <flux:select.option value="" selected>{{ __('Wybierz składnik') }}</flux:select.option>
            @foreach ($ingredientOptions as $ingredientOption)
                <flux:select.option value="{{ $ingredientOption->id }}">{{ $ingredientOption->name }}</flux:select.option>
            @endforeach
            <flux:select.option value="__new__">{{ __('Dodaj nowy składnik') }}</flux:select.option>
        </flux:select>

        <flux:input
            :name="'ingredients[__INDEX__][quantity]'"
            type="text"
            :label="__('Ilość')"
            :placeholder="__('np. 2 łyżki')"
        />

        <div class="flex items-start pt-7">
            <flux:button type="button" icon="trash" variant="danger" size="sm" data-remove-ingredient-row />
        </div>

        <div class="custom-ingredient-row col-span-full hidden">
            <flux:input
                :name="'ingredients[__INDEX__][custom_name]'"
                type="text"
                :label="__('Nowy składnik')"
                :placeholder="__('Wpisz nazwę nowego składnika')"
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

                    if (input.name.includes('[custom_name]')) {
                        input.name = `ingredients[${index}][custom_name]`;
                    }

                    if (input.name.includes('[quantity]')) {
                        input.name = `ingredients[${index}][quantity]`;
                    }
                });
            });
        };

        const syncCustomIngredientVisibility = (row) => {
            const select = row.querySelector('[name*="[ingredient_id]"]');
            const customRow = row.querySelector('.custom-ingredient-row');

            if (!select || !customRow) {
                return;
            }

            customRow.classList.toggle('hidden', select.value !== '__new__');
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

        container.querySelectorAll('.ingredient-row').forEach((row) => {
            syncCustomIngredientVisibility(row);

            const select = row.querySelector('[name*="[ingredient_id]"]');
            if (select) {
                select.addEventListener('change', () => syncCustomIngredientVisibility(row));
            }
        });

        addButton.addEventListener('click', () => {
            const index = container.querySelectorAll('.ingredient-row').length;
            const html = template.innerHTML.replaceAll('__INDEX__', String(index));
            container.insertAdjacentHTML('beforeend', html);

            const inserted = container.querySelector('.ingredient-row:last-child .remove-ingredient-row');
            if (inserted) {
                attachRemoveHandler(inserted);
            }

            const insertedRow = container.querySelector('.ingredient-row:last-child');
            if (insertedRow) {
                const select = insertedRow.querySelector('[name*="[ingredient_id]"]');
                if (select) {
                    select.addEventListener('change', () => syncCustomIngredientVisibility(insertedRow));
                }

                syncCustomIngredientVisibility(insertedRow);
            }
        });
    });
</script>