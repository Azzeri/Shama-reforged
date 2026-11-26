<?php

use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\Recipe;
use App\Models\RecipeIngredientAssignment;
use App\Models\RecipeMealAssignment;
use App\Models\Tag;
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

    public bool $packable = false;

    /** @var list<array{name: string, amount: string, unit: string, optional: bool, substitute: string, showSubstitute: bool}> */
    public array $recipeIngredients = [];

    public string $recipeContent = '';

    public string $recipeUrl = '';

    public ?string $noteDate = null;

    public ?string $noteType = null;

    public function mount(?Recipe $recipe = null, ?string $noteDate = null, ?string $noteType = null): void
    {
        if ($recipe && $recipe->exists) {
            $this->recipe = $recipe;
            $this->recipeName = $recipe->name;
            $this->servings = max(1, (int) $recipe->servings);
            $this->rating = $recipe->rating;
            $this->packable = (bool) $recipe->packable;
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

        if (! $this->recipeIngredients) {
            $this->addIngredient();
        }
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

    #[Computed]
    public function allIngredients(): Collection
    {
        return Ingredient::query()->orderBy('name')->pluck('name');
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
                $ingredient = Ingredient::query()->firstOrCreate(['name' => $name]);
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
    $field = 'w-full border border-field bg-white rounded-xl px-3 text-base text-ink placeholder:text-muted focus:outline-none focus:border-ink focus:ring-1 focus:ring-ink';
@endphp

<form wire:submit="save" class="space-y-5 pb-24">
    @if ($this->noteContext)
    <div class="rounded-[14px] bg-gold-light px-3.5 py-3 text-[14px] leading-normal text-gold-dark">
        {{ __('You are creating a recipe from the note :note. After saving, the recipe will replace this note in the plan.', ['note' => $this->noteContext]) }}
    </div>
    @endif

    <div>
        <label for="recipe-name" class="{{ $label }}">{{ __('Name') }}</label>
        <input id="recipe-name" type="text" wire:model="recipeName" class="{{ $field }} h-11" required maxlength="255" />
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

    <div class="rounded-2xl border border-line bg-white px-3.5 py-1.5">
        <label class="flex items-center gap-3 min-h-11 cursor-pointer">
            <x-icon.bag class="size-[18px] text-ink-soft" />
            <span class="flex-1 min-w-0">
                <span class="block text-[15px] font-medium text-ink">{{ __('For packing') }}</span>
                <span class="block text-[12.5px] text-muted">{{ __('Can be taken to work or on a trip') }}</span>
            </span>
            <input type="checkbox" role="switch" wire:model.live="packable" class="peer sr-only" />
            <span class="relative h-7 w-12 shrink-0 rounded-full bg-field transition-colors peer-checked:bg-sage peer-focus-visible:ring-2 peer-focus-visible:ring-ink after:absolute after:left-0.5 after:top-0.5 after:size-6 after:rounded-full after:bg-white after:shadow after:transition-transform peer-checked:after:translate-x-5"></span>
        </label>
    </div>

    <div>
        <div class="{{ $label }}">{{ __('Ingredients') }} <span class="normal-case tracking-normal font-normal">· {{ __('for :servings', ['servings' => Recipe::servingsLabel($servings)]) }}</span></div>
        <div class="rounded-[20px] border border-line bg-white divide-y divide-divider">
            @foreach ($recipeIngredients as $index => $item)
            <div wire:key="ingredient-row-{{ $index }}" class="px-3.5 py-3 space-y-2">
                <div class="flex items-center gap-2">
                    <label for="ingredient-{{ $index }}-name" class="sr-only">{{ __('Ingredient') }}</label>
                    <input id="ingredient-{{ $index }}-name" type="text" wire:model="recipeIngredients.{{ $index }}.name" list="ingredients-autocomplete-list" placeholder="{{ __('Ingredient name') }}"
                        class="flex-1 min-w-0 h-10 bg-transparent text-[16px] font-medium text-ink placeholder:text-muted focus:outline-none" />
                    <button type="button" wire:click="removeIngredient({{ $index }})" class="-mr-2 flex size-10 items-center justify-center text-muted" aria-label="{{ __('Remove :name', ['name' => $item['name'] ?: __('ingredient')]) }}">
                        <x-icon.x class="size-4" />
                    </button>
                </div>
                <div class="flex items-center gap-2">
                    <label for="ingredient-{{ $index }}-amount" class="sr-only">{{ __('Amount') }}</label>
                    <input id="ingredient-{{ $index }}-amount" type="text" inputmode="decimal" wire:model="recipeIngredients.{{ $index }}.amount" placeholder="{{ __('Amount') }}"
                        class="w-20 h-10 rounded-[10px] border border-field bg-field-bg px-2.5 text-base text-ink focus:outline-none focus:border-ink focus:ring-1 focus:ring-ink" />
                    <label for="ingredient-{{ $index }}-unit" class="sr-only">{{ __('Unit') }}</label>
                    <select id="ingredient-{{ $index }}-unit" wire:model="recipeIngredients.{{ $index }}.unit" class="w-[4.75rem] h-10 rounded-[10px] border border-field bg-field-bg px-2 text-base text-ink">
                        @foreach (RecipeIngredientAssignment::UNITS as $unit)
                        <option value="{{ $unit }}">{{ \App\Support\ShoppingList\Quantity::unitLabel($unit) }}</option>
                        @endforeach
                    </select>
                    <span class="flex-1"></span>
                    <button type="button" wire:click="$toggle('recipeIngredients.{{ $index }}.optional')" aria-pressed="{{ $item['optional'] ? 'true' : 'false' }}"
                        @class(['h-10 rounded-full px-3 text-[13px] font-medium', 'bg-ink text-white' => $item['optional'], 'border border-dashed border-faint text-ink-soft' => ! $item['optional']])>
                        {{ __('optional ingredient') }}
                    </button>
                </div>
                @if ($item['showSubstitute'] || filled($item['substitute']))
                <div class="flex items-center gap-2">
                    <label for="ingredient-{{ $index }}-substitute" class="text-[13px] text-muted">{{ __('or') }}:</label>
                    <input id="ingredient-{{ $index }}-substitute" type="text" wire:model="recipeIngredients.{{ $index }}.substitute" placeholder="{{ __('e.g. hummus') }}"
                        class="flex-1 min-w-0 h-10 rounded-[10px] border border-field bg-field-bg px-2.5 text-base text-ink focus:outline-none focus:border-ink focus:ring-1 focus:ring-ink" />
                </div>
                @else
                <button type="button" wire:click="$set('recipeIngredients.{{ $index }}.showSubstitute', true)" class="h-8 text-[13px] font-medium text-ink-soft">+ {{ __('substitute') }}</button>
                @endif
                <x-ui.field-error name="recipeIngredients.{{ $index }}.name" />
                <x-ui.field-error name="recipeIngredients.{{ $index }}.amount" />
            </div>
            @endforeach

            <button type="button" wire:click="addIngredient" class="flex w-full items-center gap-1.5 px-3.5 min-h-12 text-[15px] font-semibold text-terracotta">
                <x-icon.plus class="size-4" />
                {{ __('Add ingredient') }}
            </button>
        </div>
        <datalist id="ingredients-autocomplete-list">
            @foreach ($this->allIngredients as $ingredientName)
            <option value="{{ $ingredientName }}"></option>
            @endforeach
        </datalist>
    </div>

    <div>
        <label for="recipe-content" class="{{ $label }}">{{ __('Preparation') }} <span class="normal-case tracking-normal font-normal">· {{ __('optional') }}</span></label>
        <x-ui.textarea id="recipe-content" wire:model="recipeContent" rows="4">{{ $recipeContent }}</x-ui.textarea>
    </div>

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
                <button type="button" x-on:click="confirming = false" class="h-11 rounded-full border border-field bg-white px-4 text-[14px] font-semibold text-ink">{{ __('No') }}</button>
                <button type="button" wire:click="delete" class="h-11 rounded-full bg-terracotta px-4 text-[14px] font-semibold text-white">{{ __('Delete') }}</button>
            </div>
        </div>
    </div>
    @endif

    <div class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-cream/95 backdrop-blur px-4 pt-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))]">
        <div class="max-w-xl mx-auto">
            <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">
                {{ $this->isFromNote() ? __('Save and add to the plan') : ($isEdit ? __('Save changes') : __('Add recipe')) }}
            </x-ui.button>
        </div>
    </div>
</form>
