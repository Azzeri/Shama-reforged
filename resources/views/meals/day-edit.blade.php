@php
    $typeOptions = $typeOptions ?? \App\Models\Meal::TYPE_LABELS;
    $recipeOptions = $recipeOptions ?? collect();
    $recipeNameById = $recipeOptions->mapWithKeys(fn ($recipe) => [(string) $recipe->id => $recipe->name]);
    $errors = $errors ?? new \Illuminate\Support\ViewErrorBag();
@endphp

<x-layouts::app :title="__('Edycja całego dnia')">
    <div class="mx-auto w-full max-w-5xl space-y-6">
        <div class="flex flex-col gap-4 rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900 md:flex-row md:items-end md:justify-between">
            <div class="space-y-1">
                <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">Edycja dnia: {{ $day->copy()->locale('pl')->isoFormat('dddd, D MMMM') }}</h1>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">Zmień typ i przepisy dla wszystkich posiłków i zapisz jednym kliknięciem.</p>
            </div>

            <flux:button as="a" href="{{ route('meals.day', $day->toDateString()) }}" variant="ghost" icon="arrow-left" size="sm">
                Wróć do dnia
            </flux:button>
        </div>

        @if ($errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950/50 dark:text-rose-300">
                <ul class="list-disc space-y-1 ps-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($meals->isEmpty())
            <div class="rounded-2xl border border-dashed border-zinc-300 bg-white p-8 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400">
                Brak posiłków dla tego dnia.
            </div>
        @else
            <datalist id="day-recipe-name-options">
                @foreach ($recipeOptions as $recipeOption)
                    <option value="{{ $recipeOption->name }}"></option>
                @endforeach
            </datalist>

            <form method="POST" action="{{ route('meals.day.update', $day->toDateString()) }}" class="space-y-4">
                @csrf
                @method('PUT')

                @foreach ($meals as $index => $meal)
                    @php
                        $oldMeal = old('meals.'.$index, []);
                        $selectedType = $oldMeal['type'] ?? $meal->type;
                        $recipeRows = old('meals.'.$index.'.recipes');
                        if (! is_array($recipeRows)) {
                            $recipeRows = $meal->recipes
                                ->map(fn ($recipe) => ['recipe_id' => (string) $recipe->id])
                                ->values()
                                ->all();
                        }

                        if ($recipeRows === []) {
                            $recipeRows = [['recipe_id' => '', 'recipe_name' => '']];
                        }
                    @endphp

                    <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                        <input type="hidden" name="meals[{{ $index }}][id]" value="{{ $meal->id }}">

                        <div class="grid gap-4">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-zinc-800 dark:text-zinc-200">Typ posiłku</label>
                                <select name="meals[{{ $index }}][type]" class="w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100" required>
                                    @foreach ($typeOptions as $value => $label)
                                        <option value="{{ $value }}" @selected((string) $selectedType === (string) $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="mt-4 space-y-2">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-sm font-medium text-zinc-800 dark:text-zinc-200">Przepisy</p>
                                <flux:button type="button" variant="ghost" size="sm" icon="plus" data-add-day-recipe-row="{{ $index }}">
                                    Dodaj przepis
                                </flux:button>
                            </div>

                            <p class="text-xs text-zinc-500 dark:text-zinc-400">Wpisz nazwę przepisu i wybierz z podpowiedzi.</p>

                            <div class="space-y-2" data-day-recipes-container="{{ $index }}">
                                @foreach ($recipeRows as $recipeIndex => $recipeRow)
                                    @php
                                        $selectedRecipeId = (string) ($recipeRow['recipe_id'] ?? '');
                                        $selectedRecipeName = $selectedRecipeId !== ''
                                            ? ($recipeNameById[$selectedRecipeId] ?? '')
                                            : (string) ($recipeRow['recipe_name'] ?? '');
                                    @endphp

                                    <div class="day-recipe-row grid grid-cols-[minmax(0,1fr)_auto] items-center gap-2" data-row-index="{{ $recipeIndex }}">
                                        <flux:input
                                            :name="'meals['.$index.'][recipes]['.$recipeIndex.'][recipe_name]'"
                                            :value="$selectedRecipeName"
                                            :placeholder="__('Zacznij wpisywać nazwę przepisu')"
                                            list="day-recipe-name-options"
                                            data-day-recipe-name
                                            required
                                        />

                                        <input
                                            type="hidden"
                                            :name="'meals['.$index.'][recipes]['.$recipeIndex.'][recipe_id]'"
                                            value="{{ $selectedRecipeId }}"
                                            data-day-recipe-id
                                        >

                                        <flux:button type="button" icon="trash" variant="danger" size="sm" data-remove-day-recipe-row />
                                    </div>
                                @endforeach
                            </div>

                            <template data-day-recipes-template="{{ $index }}">
                                <div class="day-recipe-row grid grid-cols-[minmax(0,1fr)_auto] items-center gap-2" data-row-index="__ROW_INDEX__">
                                    <flux:input
                                        :name="'meals['.$index.'][recipes][__ROW_INDEX__][recipe_name]'"
                                        :placeholder="__('Zacznij wpisywać nazwę przepisu')"
                                        list="day-recipe-name-options"
                                        data-day-recipe-name
                                        required
                                    />

                                    <input
                                        type="hidden"
                                        :name="'meals['.$index.'][recipes][__ROW_INDEX__][recipe_id]'"
                                        value=""
                                        data-day-recipe-id
                                    >

                                    <flux:button type="button" icon="trash" variant="danger" size="sm" data-remove-day-recipe-row />
                                </div>
                            </template>
                        </div>
                    </div>
                @endforeach

                <div class="flex items-center justify-end gap-3">
                    <flux:button as="a" href="{{ route('meals.day', $day->toDateString()) }}" variant="ghost">Anuluj</flux:button>
                    <flux:button type="submit" variant="primary" icon="check">Zapisz cały dzień</flux:button>
                </div>
            </form>
        @endif
    </div>
</x-layouts::app>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const containers = document.querySelectorAll('[data-day-recipes-container]');
        const recipeNameMap = new Map();

        @foreach ($recipeOptions as $recipeOption)
            recipeNameMap.set(@json(mb_strtolower(trim($recipeOption->name))), @json((string) $recipeOption->id));
        @endforeach

        const normalizeName = (value) => value.trim().toLocaleLowerCase('pl-PL');

        const resolveRecipeIdFromNameInput = (row) => {
            const recipeNameInput = row.querySelector('[data-day-recipe-name]');
            const recipeIdInput = row.querySelector('[data-day-recipe-id]');
            if (!(recipeNameInput instanceof HTMLInputElement) || !(recipeIdInput instanceof HTMLInputElement)) {
                return;
            }

            const normalized = normalizeName(recipeNameInput.value);
            const resolvedId = normalized !== '' ? recipeNameMap.get(normalized) ?? '' : '';
            recipeIdInput.value = resolvedId;

            if (recipeNameInput.value !== '' && resolvedId === '') {
                recipeNameInput.setCustomValidity('Wybierz przepis z podpowiedzi.');
            } else {
                recipeNameInput.setCustomValidity('');
            }
        };

        const attachNameHandlers = (row) => {
            const recipeNameInput = row.querySelector('[data-day-recipe-name]');
            if (!(recipeNameInput instanceof HTMLInputElement)) {
                return;
            }

            recipeNameInput.addEventListener('input', () => {
                resolveRecipeIdFromNameInput(row);
            });

            recipeNameInput.addEventListener('change', () => {
                resolveRecipeIdFromNameInput(row);
            });

            resolveRecipeIdFromNameInput(row);
        };

        const reindexRows = (container, mealIndex) => {
            container.querySelectorAll('.day-recipe-row').forEach((row, recipeIndex) => {
                row.dataset.rowIndex = String(recipeIndex);

                row.querySelectorAll('[name]').forEach((input) => {
                    if (input.name.includes('[recipe_name]')) {
                        input.name = `meals[${mealIndex}][recipes][${recipeIndex}][recipe_name]`;
                    }

                    if (input.name.includes('[recipe_id]')) {
                        input.name = `meals[${mealIndex}][recipes][${recipeIndex}][recipe_id]`;
                    }
                });
            });
        };

        const attachRemoveHandlers = (container, mealIndex) => {
            container.querySelectorAll('[data-remove-day-recipe-row]').forEach((button) => {
                button.onclick = () => {
                    const row = button.closest('.day-recipe-row');
                    if (!row) {
                        return;
                    }

                    row.remove();

                    if (container.querySelectorAll('.day-recipe-row').length === 0) {
                        const addButton = document.querySelector(`[data-add-day-recipe-row="${mealIndex}"]`);
                        if (addButton) {
                            addButton.click();
                        }
                    }

                    reindexRows(container, mealIndex);
                };
            });
        };

        containers.forEach((container) => {
            const mealIndex = container.getAttribute('data-day-recipes-container');
            if (!mealIndex) {
                return;
            }

            const template = document.querySelector(`[data-day-recipes-template="${mealIndex}"]`);
            const addButton = document.querySelector(`[data-add-day-recipe-row="${mealIndex}"]`);

            if (!template || !addButton) {
                return;
            }

            container.querySelectorAll('.day-recipe-row').forEach((row) => {
                attachNameHandlers(row);
            });

            attachRemoveHandlers(container, mealIndex);

            addButton.addEventListener('click', () => {
                const recipeIndex = container.querySelectorAll('.day-recipe-row').length;
                const html = template.innerHTML.replaceAll('__ROW_INDEX__', String(recipeIndex));
                container.insertAdjacentHTML('beforeend', html);

                const lastRow = container.querySelector('.day-recipe-row:last-child');
                if (lastRow) {
                    attachNameHandlers(lastRow);

                    const recipeNameInput = lastRow.querySelector('[data-day-recipe-name]');
                    if (recipeNameInput instanceof HTMLElement) {
                        recipeNameInput.focus();
                    }
                }

                attachRemoveHandlers(container, mealIndex);
                reindexRows(container, mealIndex);
            });
        });
    });
</script>
