<?php

use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\Recipe;
use App\Models\RecipeIngredientAssignment;
use App\Models\RecipeMealAssignment;
use App\Models\Tag;
use App\Support\Ingredients\IngredientMatcher;
use App\Support\Ingredients\IngredientName;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/*
 * Recipe form (new / edit). Amounts are for the whole recipe (all of its
 * servings). Calories, macros and per-person amounts are no longer edited —
 * they stay in the DB untouched.
 *
 * "From a note" mode (noteDate + noteType): name and meal come from a note
 * in the plan, and after saving the recipe replaces that note.
 */
new class extends Component {
    public ?Recipe $recipe = null;

    public string $recipeName = '';

    public int $servings = 2;

    public array $recipeTags = [];

    public ?string $rating = null;

    /** null = not set yet (the recipe stays on the "to fill in" page). */
    public ?bool $packable = null;

    /** @var list<array{name: string, amount: string, unit: string, optional: bool, substitute: string, showSubstitute: bool}> */
    public array $recipeIngredients = [];

    public string $recipeContent = '';

    public string $recipeUrl = '';

    public ?string $noteDate = null;

    public ?string $noteType = null;

    /** Set when the form was filled from a link: host + counts for the banner. */
    public ?array $importInfo = null;

    public function mount(?Recipe $recipe = null, ?string $noteDate = null, ?string $noteType = null, ?string $importToken = null, ?string $source = null): void
    {
        if ($recipe && $recipe->exists) {
            $this->recipe = $recipe;
            $this->recipeName = $recipe->name;
            $this->servings = max(1, (int) $recipe->servings);
            $this->rating = $recipe->rating;
            $this->packable = $recipe->packable;
            $this->recipeUrl = $recipe->link ?? '';
            $this->recipeContent = $recipe->content ?? '';
            $this->recipeTags = $recipe->tags()->pluck('tags.id')->all();
            $this->recipeIngredients = $recipe->ingredients->map(fn (Ingredient $ingredient) => [
                'name' => $ingredient->name,
                'amount' => ($amount = $ingredient->pivot->totalAmount()) !== null ? rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.') : '',
                'unit' => $ingredient->pivot->unit ?? 'g',
                'optional' => (bool) $ingredient->pivot->is_optional,
                'substitute' => $ingredient->pivot->substitute ?? '',
                'showSubstitute' => filled($ingredient->pivot->substitute),
            ])->all();

            return;
        }

        if ($noteDate && $noteType && in_array($noteType, Meal::TYPES, true)) {
            $meal = $this->noteMeal($noteDate, $noteType);

            if ($meal && filled($meal->note)) {
                $this->noteDate = Carbon::parse($noteDate)->toDateString();
                $this->noteType = $noteType;
                $this->recipeName = Str::ucfirst($meal->note);
                $tagId = Tag::query()->where('category', Tag::MEAL_TYPE)->where('name', Meal::tagName($noteType))->value('id');
                $this->recipeTags = $tagId ? [$tagId] : [];
            }
        }

        if ($importToken && is_array($draft = session()->pull('recipe-import.' . $importToken))) {
            $this->fillFromImport($draft);
        } elseif ($source && filter_var($source, FILTER_VALIDATE_URL)) {
            $this->recipeUrl = $source;
        }

        if (! $this->recipeIngredients) {
            $this->addIngredient();
        }
    }

    /** Draft from RecipeImporter: nothing is saved until "Dodaj przepis". */
    private function fillFromImport(array $draft): void
    {
        $this->recipeName = $draft['name'];
        $this->servings = $draft['servings'] ?? $this->servings;
        $this->recipeUrl = $draft['link'];
        $this->recipeContent = implode("\n", $draft['steps']);
        $this->recipeIngredients = collect($draft['ingredients'])->map(fn (array $row) => [
            'name' => $row['name'],
            'amount' => $row['amount'],
            'unit' => in_array($row['unit'], RecipeIngredientAssignment::UNITS, true) ? $row['unit'] : 'g',
            'optional' => false,
            'substitute' => '',
            'showSubstitute' => false,
            'original' => $row['original'],
        ])->all();
        $this->importInfo = [
            'host' => $draft['host'],
            'ingredients' => count($draft['ingredients']),
            'steps' => count($draft['steps']),
        ];
    }

    private function noteMeal(string $date, string $type): ?Meal
    {
        return Meal::query()->whereDate(Meal::DATE_COLUMN, Carbon::parse($date)->toDateString())->where(Meal::TYPE_COLUMN, $type)->orderBy('id')->first();
    }

    public function isFromNote(): bool
    {
        return $this->noteDate !== null && $this->noteType !== null;
    }

    /** "„Makaron z pesto” (środa, lunch)" for the from-note banner. */
    #[Computed]
    public function noteContext(): ?string
    {
        if (! $this->isFromNote()) {
            return null;
        }

        $meal = $this->noteMeal($this->noteDate, $this->noteType);
        $day = Carbon::parse($this->noteDate)->locale(app()->getLocale())->isoFormat('dddd');

        return '„' . ($meal?->note ?? $this->recipeName) . '” (' . $day . ', ' . mb_strtolower(Meal::typeLabel($this->noteType)) . ')';
    }

    #[Computed]
    public function mealTags(): Collection
    {
        $order = array_flip(Meal::orderedTagNames());

        return Tag::query()->where(Tag::CATEGORY_COLUMN, Tag::MEAL_TYPE)->get()
            ->sortBy(fn (Tag $tag) => $order[$tag->name] ?? PHP_INT_MAX)->values();
    }

    #[Computed]
    public function dietTags(): Collection
    {
        return Tag::query()->where(Tag::CATEGORY_COLUMN, Tag::DIET_TYPE)->get()->sortBy(fn (Tag $tag) => $tag->label())->values();
    }

    /** Row whose name field is being typed in (its suggestions are shown). */
    public ?int $activeRow = null;

    /** Folded names of all existing ingredients, to mark rows that would create a new one. */
    #[Computed]
    public function existingKeys(): array
    {
        return array_flip(Ingredient::query()->pluck('name_key')->all());
    }

    public function isNewIngredient(array $item): bool
    {
        $fold = IngredientName::fold($item['name'] ?? '');

        return $fold !== '' && ! isset($this->existingKeys[$fold]);
    }

    /**
     * Suggestions for the active row: matching / similar existing ingredients
     * and whether the typed name may be added as a new one.
     *
     * @return array{mode: string, items: \Illuminate\Support\Collection}|null
     *   mode: "exists" (same name in another case — must pick it), "similar"
     *   ("Czy chodziło o:" + add as new), "matches" (plain list + add as new)
     */
    #[Computed]
    public function suggestions(): ?array
    {
        $name = trim($this->recipeIngredients[$this->activeRow]['name'] ?? '');

        if ($this->activeRow === null || mb_strlen($name) < 2) {
            return null;
        }

        $matcher = app(IngredientMatcher::class);
        $same = $matcher->same($name);

        if ($same && $same->name === $name) {
            return null;
        }

        $items = $matcher->suggest($name, 5);

        if ($same) {
            return ['mode' => 'exists', 'items' => collect([['ingredient' => $same, 'similar' => true]])->concat($items->reject(fn ($row) => $row['ingredient']->is($same)))->take(5)->values()];
        }

        if ($items->isEmpty()) {
            return null;
        }

        return ['mode' => $items->contains('similar', true) ? 'similar' : 'matches', 'items' => $items];
    }

    public function focusRow(int $index): void
    {
        $this->activeRow = isset($this->recipeIngredients[$index]) ? $index : null;
        unset($this->suggestions);
    }

    public function closeSuggestions(): void
    {
        $this->activeRow = null;
    }

    public function pickIngredient(int $index, int $ingredientId): void
    {
        if (isset($this->recipeIngredients[$index]) && ($ingredient = Ingredient::query()->find($ingredientId))) {
            $this->recipeIngredients[$index]['name'] = $ingredient->name;
        }

        $this->activeRow = null;
    }

    public function decrementServings(): void
    {
        $this->servings = max(1, $this->servings - 1);
    }

    public function incrementServings(): void
    {
        $this->servings = min(99, $this->servings + 1);
    }

    public function setRating(string $rating): void
    {
        $this->rating = $this->rating === $rating || ! in_array($rating, Recipe::RATINGS, true) ? null : $rating;
    }

    /** Tapping the selected chip again clears it (back to "not set"). */
    public function setPackable(bool $packable): void
    {
        $this->packable = $this->packable === $packable ? null : $packable;
    }

    public function toggleTag(int $tagId): void
    {
        $this->recipeTags = in_array($tagId, $this->recipeTags)
            ? array_values(array_diff($this->recipeTags, [$tagId]))
            : [...$this->recipeTags, $tagId];
    }

    public function addIngredient(): void
    {
        $this->recipeIngredients[] = ['name' => '', 'amount' => '', 'unit' => 'g', 'optional' => false, 'substitute' => '', 'showSubstitute' => false];
    }

    public function removeIngredient(int $index): void
    {
        unset($this->recipeIngredients[$index]);
        $this->recipeIngredients = array_values($this->recipeIngredients);
    }

    public function archive()
    {
        $this->recipe?->update(['archived_at' => now()]);

        return $this->redirect(route('recipes.index'), navigate: true);
    }

    public function delete()
    {
        $this->recipe?->delete();

        return $this->redirect(route('recipes.index'), navigate: true);
    }

    public function save()
    {
        // Empty rows (e.g. the one a new form starts with) are not ingredients.
        $this->recipeIngredients = array_values(array_filter(
            $this->recipeIngredients,
            fn (array $item) => trim($item['name'] ?? '') !== '' || trim((string) ($item['amount'] ?? '')) !== '',
        ));

        foreach ($this->recipeIngredients as $index => $item) {
            $this->recipeIngredients[$index]['amount'] = str_replace(',', '.', trim((string) ($item['amount'] ?? '')));
        }

        $this->validate([
            'recipeName' => ['required', 'string', 'max:255'],
            'servings' => ['required', 'integer', 'min:1', 'max:99'],
            'recipeTags' => ['array'],
            'recipeTags.*' => ['exists:tags,id'],
            'rating' => ['nullable', Rule::in(Recipe::RATINGS)],
            'recipeIngredients.*.name' => ['required', 'string', 'max:255'],
            'recipeIngredients.*.amount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'recipeIngredients.*.unit' => ['nullable', 'string', 'max:20'],
            'recipeIngredients.*.substitute' => ['nullable', 'string', 'max:255'],
            'recipeContent' => ['nullable', 'string'],
            'recipeUrl' => ['nullable', 'url', 'max:255'],
        ]);

        $fromNote = $this->isFromNote();

        DB::transaction(function () use ($fromNote) {
            $recipe = $this->recipe ?? new Recipe();
            $recipe->name = $this->recipeName;
            $recipe->servings = $this->servings;
            $recipe->rating = $this->rating;
            $recipe->packable = $this->packable;
            $recipe->link = trim($this->recipeUrl) ?: null;
            $recipe->content = trim($this->recipeContent) ?: null;
            $recipe->save();

            $recipe->tags()->sync($this->recipeTags);

            $sync = [];
            foreach ($this->recipeIngredients as $item) {
                $name = Str::ucfirst(trim($item['name']));
                $ingredient = Ingredient::findOrCreateByName($name);
                $hasAmount = $item['amount'] !== '' && in_array($item['unit'] ?? '', RecipeIngredientAssignment::UNITS, true);

                $sync[$ingredient->id] = [
                    'amount' => $hasAmount ? (float) $item['amount'] : null,
                    'unit' => $hasAmount ? $item['unit'] : null,
                    'is_optional' => (bool) ($item['optional'] ?? false),
                    'substitute' => trim($item['substitute'] ?? '') ?: null,
                ];
            }
            $recipe->ingredients()->sync($sync);

            if ($fromNote) {
                $meal = $this->noteMeal($this->noteDate, $this->noteType)
                    ?? Meal::query()->create(['date' => Carbon::parse($this->noteDate)->setTime(12, 0), 'type' => $this->noteType]);
                $meal->update(['note' => null]);
                RecipeMealAssignment::query()->firstOrCreate(['meal_id' => $meal->id, 'recipe_id' => $recipe->id], ['mode' => RecipeMealAssignment::MODE_SINGLE]);
            }

            $this->recipe = $recipe;
        });

        return $fromNote
            ? $this->redirect(route('meals.day.edit', $this->noteDate), navigate: true)
            : $this->redirect(route('recipes.show', $this->recipe), navigate: true);
    }
};
?>

@php
    $isEdit = $recipe !== null;
    $label = 'block text-[11px] font-semibold uppercase tracking-[0.09em] text-muted mb-1.5';
    $field = 'w-full border border-field bg-surface rounded-xl px-3 text-base text-ink placeholder:text-muted focus:outline-none focus:border-ink focus:ring-1 focus:ring-ink';
@endphp

<form id="recipe-form" wire:submit="save" class="space-y-5 pb-24 lg:pb-0 lg:grid lg:grid-cols-[340px_minmax(0,1fr)] lg:items-start lg:gap-x-10 lg:gap-y-5 lg:space-y-0">
    @if ($importInfo)
    <div class="lg:col-span-2 flex gap-2.5 rounded-[14px] bg-sage-light px-3.5 py-3 text-[14px] leading-normal text-forest">
        <x-icon.link class="mt-0.5 size-4" />
        <p>{{ __('Imported from :host: :ingredients, :steps. Check the data — nothing is saved until you click “Add recipe”.', [
            'host' => $importInfo['host'],
            'ingredients' => trans_choice(':count ingredient|:count ingredients', $importInfo['ingredients']),
            'steps' => trans_choice(':count step|:count steps', $importInfo['steps']),
        ]) }}</p>
    </div>
    @endif

    @if ($this->noteContext)
    <div class="lg:col-span-2 rounded-[14px] bg-gold-light px-3.5 py-3 text-[14px] leading-normal text-gold-dark">
        {{ __('You are creating a recipe from the note :note. After saving, the recipe will replace this note in the plan.', ['note' => $this->noteContext]) }}
    </div>
    @endif

    <div class="space-y-5">
    <div>
        <label for="recipe-name" class="{{ $label }}">{{ __('Name') }}</label>
        <input id="recipe-name" type="text" enterkeyhint="next" wire:model="recipeName" class="{{ $field }} h-11" required maxlength="255" />
        <x-ui.field-error name="recipeName" />
    </div>

    <div>
        <span id="servings-label" class="{{ $label }}">{{ __('Number of servings') }}</span>
        <div class="flex items-center gap-3">
            <x-ui.stepper :value="$servings" :min="1" :max="99" :label="(string) $servings" decrement="decrementServings" increment="incrementServings" aria-labelledby="servings-label" class="shrink-0" />
            <p class="text-[12.5px] leading-snug text-muted">{{ __('Ingredient amounts are for the whole recipe. One meal in the plan uses :count servings, unless you cook it all at once.', ['count' => Recipe::servingsPerMeal()]) }}</p>
        </div>
    </div>

    <fieldset>
        <legend class="{{ $label }}">{{ __('Meal') }}</legend>
        <div class="flex flex-wrap gap-2">
            @foreach ($this->mealTags as $tag)
            <x-ui.chip wire:key="form-meal-{{ $tag->id }}" color="gold" :active="in_array($tag->id, $recipeTags)" wire:click="toggleTag({{ $tag->id }})">{{ $tag->label() }}</x-ui.chip>
            @endforeach
        </div>
    </fieldset>

    <fieldset>
        <legend class="{{ $label }}">{{ __('Main ingredient') }}</legend>
        <div class="flex flex-wrap gap-2">
            @foreach ($this->dietTags as $tag)
            <x-ui.chip wire:key="form-diet-{{ $tag->id }}" color="sage" :active="in_array($tag->id, $recipeTags)" wire:click="toggleTag({{ $tag->id }})">{{ $tag->label() }}</x-ui.chip>
            @endforeach
        </div>
    </fieldset>

    <fieldset>
        <legend class="{{ $label }}">{{ __('Rating') }}</legend>
        <div class="flex flex-wrap gap-2">
            @foreach (Recipe::RATINGS as $ratingOption)
            <x-ui.chip wire:key="form-rating-{{ $ratingOption }}" :active="$rating === $ratingOption" wire:click="setRating('{{ $ratingOption }}')">
                <span class="size-2 rounded-full {{ Recipe::ratingDotClass($ratingOption) }}" aria-hidden="true"></span>
                {{ Recipe::ratingLabel($ratingOption) }}
            </x-ui.chip>
            @endforeach
        </div>
    </fieldset>

    <fieldset>
        <legend class="{{ $label }}">{{ __('For packing') }}</legend>
        <div class="flex flex-wrap gap-2">
            <x-ui.chip :active="$packable === true" wire:click="setPackable(true)">{{ __('Yes, can be taken along') }}</x-ui.chip>
            <x-ui.chip :active="$packable === false" wire:click="setPackable(false)">{{ __('No') }}</x-ui.chip>
        </div>
        <p @class(['mt-1.5 text-[12.5px] leading-snug', 'text-gold-dark' => $packable === null, 'text-muted' => $packable !== null])>
            @if ($packable === null)
            {{ __('Not set: the recipe will be on the “To fill in” list.') }}
            @elseif ($packable)
            {{ __('Shows up with the “for packing” filter.') }}
            @else
            {{ __('Not suitable for taking along.') }}
            @endif
        </p>
    </fieldset>

    <div class="hidden lg:block space-y-5 border-t border-line pt-5">
    <div>
        <label for="recipe-url-desktop" class="{{ $label }}">{{ __('Source') }} <span class="normal-case tracking-normal font-normal">· {{ __('optional') }}</span></label>
        <input id="recipe-url-desktop" type="url" wire:model="recipeUrl" placeholder="https://" class="{{ $field }} h-11" />
        <x-ui.field-error name="recipeUrl" />
    </div>

    @if ($isEdit)
    <div class="space-y-2 pt-1">
        <x-ui.button variant="outline" wire:click="archive">
            <x-icon.archive class="size-4" />
            {{ __('Move to the archive') }}
        </x-ui.button>
        <p class="text-[13px] text-muted text-center">{{ __('An archived recipe disappears from the list and from planning, but nothing is deleted.') }}</p>

        <div x-data="{ confirming: false }" class="flex justify-center pt-1">
            <button type="button" x-show="! confirming" x-on:click="confirming = true" class="inline-flex h-11 items-center gap-1.5 text-[15px] font-semibold text-terracotta">
                {{ __('Delete the recipe permanently') }}
            </button>
            <div x-show="confirming" x-cloak class="flex w-full items-center gap-2">
                <span class="flex-1 text-[15px] font-semibold text-ink">{{ __('Delete “:name”?', ['name' => $recipe->name]) }}</span>
                <button type="button" x-on:click="confirming = false" class="h-11 rounded-full border border-field bg-surface px-4 text-[14px] font-semibold text-ink">{{ __('No') }}</button>
                <button type="button" wire:click="delete" class="h-11 rounded-full bg-terracotta px-4 text-[14px] font-semibold text-white">{{ __('Delete') }}</button>
            </div>
        </div>
    </div>
    @endif

    </div>
    </div>

    <div class="space-y-5">
    <div>
        <div class="{{ $label }}">{{ __('Ingredients') }} <span class="normal-case tracking-normal font-normal">· {{ __('for :servings', ['servings' => Recipe::servingsLabel($servings)]) }}</span></div>
        <div class="rounded-[20px] border border-line bg-surface divide-y divide-divider">
            @foreach ($recipeIngredients as $index => $item)
            <div wire:key="ingredient-row-{{ $index }}" class="px-3.5 py-3 space-y-2 lg:grid lg:grid-cols-[minmax(0,1fr)_76px_76px_auto_150px_40px] lg:items-center lg:gap-2 lg:space-y-0 lg:py-2.5">
                <div class="flex items-center gap-2 lg:contents">
                    @php
                        $isActive = $activeRow === $index;
                        $suggest = $isActive ? $this->suggestions : null;
                    @endphp
                    <div class="relative flex-1 min-w-0 lg:order-1" @if ($isActive) x-data x-on:click.outside="$wire.closeSuggestions()" x-on:keydown.escape="$wire.closeSuggestions()" @endif>
                        <label for="ingredient-{{ $index }}-name" class="sr-only">{{ __('Ingredient') }}</label>
                        <input id="ingredient-{{ $index }}-name" type="text" autocomplete="off" enterkeyhint="next"
                            wire:model.live.debounce.250ms="recipeIngredients.{{ $index }}.name"
                            @unless ($isActive) wire:focus="focusRow({{ $index }})" @endunless
                            role="combobox" aria-autocomplete="list" aria-expanded="{{ $suggest ? 'true' : 'false' }}" aria-controls="ingredient-{{ $index }}-suggestions"
                            placeholder="{{ __('Ingredient name') }}"
                            @class(['w-full h-10 text-[16px] font-medium text-ink placeholder:text-muted focus:outline-none', 'rounded-[10px] border border-ink bg-surface px-2.5' => $isActive, 'bg-transparent' => ! $isActive]) />
                        @if (! $isActive && $this->isNewIngredient($item))
                        <div class="flex items-center gap-2 text-[12px]">
                            <span class="rounded-full bg-gold-light px-1.5 py-px font-semibold text-gold-dark">{{ __('new ingredient') }}</span>
                            <button type="button" wire:click="focusRow({{ $index }})" class="font-semibold text-terracotta">{{ __('Replace') }}</button>
                        </div>
                        @endif
                        @if ($suggest)
                        <div id="ingredient-{{ $index }}-suggestions" role="listbox" class="absolute left-0 right-0 top-full z-20 mt-1 overflow-hidden rounded-[14px] border border-line bg-surface shadow-[0_12px_30px_rgba(31,27,22,0.14)]">
                            @if ($suggest['mode'] === 'exists')
                            <p class="px-3 pt-2.5 pb-1 text-[12px] font-semibold text-gold-dark">{{ __('This ingredient already exists — pick it from the list.') }}</p>
                            @elseif ($suggest['mode'] === 'similar')
                            <p class="px-3 pt-2.5 pb-1 text-[12px] font-semibold text-muted">{{ __('Did you mean:') }}</p>
                            @endif
                            @foreach ($suggest['items'] as $row)
                            <button type="button" role="option" wire:key="suggestion-{{ $index }}-{{ $row['ingredient']->id }}" wire:click="pickIngredient({{ $index }}, {{ $row['ingredient']->id }})"
                                class="block w-full border-b border-divider px-3 py-2 text-left hover:bg-field-bg">
                                <span class="block text-[15px] text-ink">{{ $row['ingredient']->name }}</span>
                                <span class="block text-[12px] text-muted">{{ Ingredient::categoryLabel($row['ingredient']->category ?? Ingredient::CATEGORY_UNCATEGORIZED) }}</span>
                            </button>
                            @endforeach
                            @if ($suggest['mode'] !== 'exists')
                            <button type="button" wire:click="closeSuggestions" class="block w-full bg-field-bg px-3 py-2.5 text-left text-[14px] font-semibold text-terracotta">
                                {{ __('Add “:name” as a new ingredient', ['name' => trim($item['name'])]) }}
                            </button>
                            @endif
                        </div>
                        @endif
                    </div>
                    <button type="button" wire:click="removeIngredient({{ $index }})" class="-mr-2 lg:mr-0 lg:order-6 flex size-10 items-center justify-center text-muted" aria-label="{{ __('Remove :name', ['name' => $item['name'] ?: __('ingredient')]) }}">
                        <x-icon.x class="size-4" />
                    </button>
                </div>
                <div class="flex items-center gap-2 lg:contents">
                    <label for="ingredient-{{ $index }}-amount" class="sr-only">{{ __('Amount') }}</label>
                    <input id="ingredient-{{ $index }}-amount" type="text" inputmode="decimal" enterkeyhint="next" wire:model="recipeIngredients.{{ $index }}.amount" placeholder="{{ __('Amount') }}"
                        class="lg:order-2 w-20 lg:w-full h-10 rounded-[10px] border border-field bg-field-bg px-2.5 text-base text-ink focus:outline-none focus:border-ink focus:ring-1 focus:ring-ink" />
                    <label for="ingredient-{{ $index }}-unit" class="sr-only">{{ __('Unit') }}</label>
                    <select id="ingredient-{{ $index }}-unit" wire:model="recipeIngredients.{{ $index }}.unit" class="lg:order-3 w-[4.75rem] lg:w-full h-10 rounded-[10px] border border-field bg-field-bg px-2 text-base text-ink">
                        @foreach (RecipeIngredientAssignment::UNITS as $unit)
                        <option value="{{ $unit }}">{{ \App\Support\ShoppingList\Quantity::unitLabel($unit) }}</option>
                        @endforeach
                    </select>
                    <span class="flex-1 lg:hidden"></span>
                    <button type="button" wire:click="$toggle('recipeIngredients.{{ $index }}.optional')" aria-pressed="{{ $item['optional'] ? 'true' : 'false' }}"
                        @class(['lg:order-4 h-10 rounded-full px-3 text-[13px] font-medium', 'bg-ink text-surface' => $item['optional'], 'border border-dashed border-faint text-ink-soft' => ! $item['optional']])>
                        {{ __('optional ingredient') }}
                    </button>
                </div>
                @if ($item['showSubstitute'] || filled($item['substitute']))
                <div class="flex items-center gap-2 lg:order-5">
                    <label for="ingredient-{{ $index }}-substitute" class="text-[13px] text-muted">{{ __('or') }}:</label>
                    <input id="ingredient-{{ $index }}-substitute" type="text" wire:model="recipeIngredients.{{ $index }}.substitute" placeholder="{{ __('e.g. hummus') }}"
                        class="flex-1 min-w-0 h-10 rounded-[10px] border border-field bg-field-bg px-2.5 text-base text-ink focus:outline-none focus:border-ink focus:ring-1 focus:ring-ink" />
                </div>
                @else
                <button type="button" wire:click="$set('recipeIngredients.{{ $index }}.showSubstitute', true)" class="lg:hidden h-8 text-[13px] font-medium text-ink-soft">+ {{ __('substitute') }}</button>
                <div class="hidden lg:block lg:order-5">
                    <label for="ingredient-{{ $index }}-substitute-desktop" class="sr-only">{{ __('substitute') }}</label>
                    <input id="ingredient-{{ $index }}-substitute-desktop" type="text" wire:model="recipeIngredients.{{ $index }}.substitute" placeholder="{{ __('or') }}: {{ __('substitute') }}"
                        class="w-full h-10 rounded-[10px] border border-field bg-field-bg px-2.5 text-base text-ink placeholder:text-muted focus:outline-none focus:border-ink focus:ring-1 focus:ring-ink" />
                </div>
                @endif
                @if (filled($item['original'] ?? null) && trim((string) $item['amount']) === '')
                <p class="lg:order-7 lg:col-span-6 text-[12.5px] leading-snug text-muted">{{ __('In the original: “:text” — enter an amount or leave it empty.', ['text' => $item['original']]) }}</p>
                @endif
                <div class="lg:order-7 lg:col-span-6"><x-ui.field-error name="recipeIngredients.{{ $index }}.name" /><x-ui.field-error name="recipeIngredients.{{ $index }}.amount" /></div>
            </div>
            @endforeach

            <button type="button" wire:click="addIngredient" class="flex w-full items-center gap-1.5 px-3.5 min-h-12 text-[15px] font-semibold text-terracotta">
                <x-icon.plus class="size-4" />
                {{ __('Add ingredient') }}
            </button>
        </div>
    </div>

    <div>
        <label for="recipe-content" class="{{ $label }}">{{ __('Preparation') }} <span class="normal-case tracking-normal font-normal">· {{ __('optional') }}</span></label>
        <x-ui.textarea id="recipe-content" wire:model="recipeContent" rows="4">{{ $recipeContent }}</x-ui.textarea>
    </div>

    <div class="lg:hidden space-y-5">
    <div>
        <label for="recipe-url" class="{{ $label }}">{{ __('Source') }} <span class="normal-case tracking-normal font-normal">· {{ __('optional') }}</span></label>
        <input id="recipe-url" type="url" wire:model="recipeUrl" placeholder="https://" class="{{ $field }} h-11" />
        <x-ui.field-error name="recipeUrl" />
    </div>

    @if ($isEdit)
    <div class="space-y-2 pt-1">
        <x-ui.button variant="outline" wire:click="archive">
            <x-icon.archive class="size-4" />
            {{ __('Move to the archive') }}
        </x-ui.button>
        <p class="text-[13px] text-muted text-center">{{ __('An archived recipe disappears from the list and from planning, but nothing is deleted.') }}</p>

        <div x-data="{ confirming: false }" class="flex justify-center pt-1">
            <button type="button" x-show="! confirming" x-on:click="confirming = true" class="inline-flex h-11 items-center gap-1.5 text-[15px] font-semibold text-terracotta">
                {{ __('Delete the recipe permanently') }}
            </button>
            <div x-show="confirming" x-cloak class="flex w-full items-center gap-2">
                <span class="flex-1 text-[15px] font-semibold text-ink">{{ __('Delete “:name”?', ['name' => $recipe->name]) }}</span>
                <button type="button" x-on:click="confirming = false" class="h-11 rounded-full border border-field bg-surface px-4 text-[14px] font-semibold text-ink">{{ __('No') }}</button>
                <button type="button" wire:click="delete" class="h-11 rounded-full bg-terracotta px-4 text-[14px] font-semibold text-white">{{ __('Delete') }}</button>
            </div>
        </div>
    </div>
    @endif

    </div>
    </div>

    <div class="lg:hidden fixed inset-x-0 bottom-0 z-30 border-t border-line bg-cream/95 backdrop-blur px-4 pt-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))]">
        <div class="max-w-xl mx-auto">
            <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">
                {{ $this->isFromNote() ? __('Save and add to the plan') : ($isEdit ? __('Save changes') : __('Add recipe')) }}
            </x-ui.button>
        </div>
    </div>
</form>
