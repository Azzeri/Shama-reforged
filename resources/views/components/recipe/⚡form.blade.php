<?php

use Livewire\Component;
use App\Models\Recipe;
use App\Models\Tag;
use App\Models\Ingredient;
use App\Models\Meal;
use Livewire\Attributes\Validate;
use Livewire\Attributes\Computed;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\RecipeIngredientAssignment;
use Flux\Flux;

new class extends Component {
    public ?Recipe $recipe = null;

    #[Validate('required|string|max:255')]
    public string $recipeName = '';

    #[Validate('nullable|url|max:255')]
    public string $recipeUrl = '';

    #[Validate('nullable|string')]
    public string $recipeContent = '';

    #[Validate(['recipeTags' => 'nullable|array', 'recipeTags.*' => 'exists:tags,id'])]
    public array $recipeTags = [];

    #[Validate([
        'recipeIngredients' => 'nullable|array',
        'recipeIngredients.*.name' => 'required|string|max:255',
        'recipeIngredients.*.amount_me' => 'nullable|numeric|min:0|max:999999.99',
        'recipeIngredients.*.amount_wife' => 'nullable|numeric|min:0|max:999999.99',
        'recipeIngredients.*.unit' => 'nullable|string|max:20',
    ])]
    public array $recipeIngredients = [];

    #[Validate([
        'recipeNutrition.me.calories' => 'nullable|numeric|min:0|max:9999',
        'recipeNutrition.me.protein' => 'nullable|numeric|min:0|max:9999',
        'recipeNutrition.me.fat' => 'nullable|numeric|min:0|max:9999',
        'recipeNutrition.me.carbs' => 'nullable|numeric|min:0|max:9999',
        'recipeNutrition.wife.calories' => 'nullable|numeric|min:0|max:9999',
        'recipeNutrition.wife.protein' => 'nullable|numeric|min:0|max:9999',
        'recipeNutrition.wife.fat' => 'nullable|numeric|min:0|max:9999',
        'recipeNutrition.wife.carbs' => 'nullable|numeric|min:0|max:9999',
    ])]
    public array $recipeNutrition = [
        'me' => ['calories' => '', 'protein' => '', 'fat' => '', 'carbs' => ''],
        'wife' => ['calories' => '', 'protein' => '', 'fat' => '', 'carbs' => ''],
    ];

    public function mount(?Recipe $recipe = null): void
    {
        if ($recipe && $recipe->exists) {
            $this->recipe = $recipe;
            $this->recipeName = $this->recipe->name;
            $this->recipeUrl = $this->recipe->link ?? '';
            $this->recipeContent = $this->recipe->content ?? '';
            $this->recipeTags = $this->recipe->tags()->pluck('tags.id')->toArray();

            $this->recipeIngredients = $this->recipe->ingredients->map(function ($ingredient) {
                return [
                    'name' => $ingredient->name,
                    'amount_me' => $ingredient->pivot?->amount_me ?? '',
                    'amount_wife' => $ingredient->pivot?->amount_wife ?? '',
                    'unit' => $ingredient->pivot?->unit ?? 'g',
                ];
            })->toArray();

            foreach (Recipe::NUTRITION_PROFILES as $profile) {
                $this->recipeNutrition[$profile] = $this->recipe->nutritionFor($profile);
            }
        }
    }

    #[Computed]
    public function tagsByCategory(): Collection
    {
        $grouped = Tag::query()
            ->whereIn(Tag::CATEGORY_COLUMN, [Tag::MEAL_TYPE, Tag::DIET_TYPE])
            ->orderBy(Tag::NAME_COLUMN)
            ->get(['id', Tag::NAME_COLUMN, Tag::CATEGORY_COLUMN])
            ->groupBy(Tag::CATEGORY_COLUMN);

        if ($grouped->has(Tag::MEAL_TYPE)) {
            $order = array_flip(Meal::orderedTypeLabels());
            $grouped[Tag::MEAL_TYPE] = $grouped[Tag::MEAL_TYPE]
                ->sortBy(fn (Tag $tag) => $order[$tag->name] ?? PHP_INT_MAX)
                ->values();
        }

        return $grouped;
    }

    #[Computed]
    public function allIngredients(): Collection
    {
        return Ingredient::query()
            ->orderBy('name')
            ->pluck('name');
    }

    public function addIngredient(): void
    {
        $this->recipeIngredients[] = [
            'name' => '',
            'amount_me' => '',
            'amount_wife' => '',
            'unit' => 'g',
        ];
    }

    public function removeIngredient(int $index): void
    {
        unset($this->recipeIngredients[$index]);
        $this->recipeIngredients = array_values($this->recipeIngredients);
    }

    public function save()
    {
        $this->validate();

        $isNewRecipe = $this->recipe === null;

        DB::transaction(function () {
            $recipe = $this->recipe ?? new Recipe();
            $recipe->name = $this->recipeName;
            $recipe->link = trim($this->recipeUrl ?: null);
            $recipe->content = $this->recipeContent ?: null;

            foreach (Recipe::NUTRITION_PROFILES as $profile) {
                $recipe->{"calories_{$profile}"} = $this->recipeNutrition[$profile]['calories'] ?: null;
                $recipe->{"protein_{$profile}"} = $this->recipeNutrition[$profile]['protein'] ?: null;
                $recipe->{"fat_{$profile}"} = $this->recipeNutrition[$profile]['fat'] ?: null;
                $recipe->{"carbs_{$profile}"} = $this->recipeNutrition[$profile]['carbs'] ?: null;
            }

            $recipe->save();

            $recipe->tags()->sync($this->recipeTags);

            $ingredientsSyncData = [];
            foreach ($this->recipeIngredients as $item) {
                $name = Str::ucfirst(trim($item['name'] ?? ''));

                if ($name !== '') {
                    $ingredient = Ingredient::firstOrCreate(['name' => $name]);

                    $hasValidUnit = in_array($item['unit'] ?? '', RecipeIngredientAssignment::UNITS, true);
                    $hasAnyAmount = ($item['amount_me'] ?? '') !== '' || ($item['amount_wife'] ?? '') !== '';

                    $ingredientsSyncData[$ingredient->id] = [
                        'amount_me' => $hasValidUnit && ($item['amount_me'] ?? '') !== '' ? $item['amount_me'] : null,
                        'amount_wife' => $hasValidUnit && ($item['amount_wife'] ?? '') !== '' ? $item['amount_wife'] : null,
                        'unit' => $hasValidUnit && $hasAnyAmount ? $item['unit'] : null,
                    ];
                }
            }

            $recipe->ingredients()->sync($ingredientsSyncData);

            $this->recipe = $recipe;
        });

        Flux::toast(variant: 'success', text: $isNewRecipe ? __('Recipe added.') : __('Recipe updated.'));

        return $this->redirect(route('recipes.show', $this->recipe), navigate: true);
    }
};
?>

<div class="space-y-5">
    <form class="space-y-5" wire:submit="save">
        <div>
            <x-ui.eyebrow>{{ __('Recipe Name') }}</x-ui.eyebrow>
            <x-ui.text-input wire:model="recipeName" placeholder="{{ __('Recipe Name') }}" class="w-full" required />
            <x-ui.field-error name="recipeName" />
        </div>

        <x-recipe.tag-pill-group
            :tags="$this->tagsByCategory->get(Tag::MEAL_TYPE, [])"
            label="{{ __('Meal type') }}"
            color="gold"
            wire:model="recipeTags" />

        <x-recipe.tag-pill-group
            :tags="$this->tagsByCategory->get(Tag::DIET_TYPE, [])"
            label="{{ __('Diet type') }}"
            color="sage"
            wire:model="recipeTags" />

        <div>
            <x-ui.eyebrow>{{ __('Ingredients') }}</x-ui.eyebrow>
            <p class="font-manrope text-xs text-ink/50 mb-2.5">
                {{ __('Choose from the list of existing ingredients or add a new one on the Ingredients tab.') }}
                {{ __('Amount and unit are optional and used to total the shopping list.') }}
            </p>

            <div class="space-y-2.5">
                @foreach ($recipeIngredients as $index => $item)
                <div
                    wire:key="ingredient-row-{{ $index }}"
                    x-data="{ open: true }"
                    class="bg-white border border-ink/5 rounded-2xl px-4 py-3.5 shadow-[0_1px_2px_rgba(43,33,24,0.06),0_4px_10px_rgba(43,33,24,0.05)]">
                    <div class="flex items-center gap-2.5">
                        <input
                            type="text"
                            wire:model="recipeIngredients.{{ $index }}.name"
                            list="ingredients-autocomplete-list"
                            placeholder="{{ __('Ingredient name') }}"
                            class="flex-1 min-w-0 bg-transparent font-fraunces text-[15px] font-semibold text-ink placeholder:font-manrope placeholder:text-sm placeholder:font-normal placeholder:text-ink/30 focus:outline-none" />

                        <button type="button" @click="open = !open" class="shrink-0 text-ink/35 p-1">
                            <flux:icon.chevron-down class="size-4 transition-transform" x-bind:class="{ 'rotate-180': open }" />
                        </button>
                    </div>
                    <x-ui.field-error name="recipeIngredients.{{ $index }}.name" />

                    <div x-show="open" x-collapse class="flex items-end gap-2.5 mt-3">
                        <div class="w-20 shrink-0">
                            <span class="block font-manrope text-[10px] font-bold uppercase tracking-wide text-ink/40 mb-1 truncate">
                                {{ \App\Models\Recipe::nutritionProfileLabel('me') }}
                            </span>
                            <input
                                type="number"
                                step="any"
                                min="0"
                                wire:model="recipeIngredients.{{ $index }}.amount_me"
                                placeholder="0"
                                class="w-full bg-sand/60 rounded-xl px-3 py-2.5 font-manrope text-sm text-ink focus:outline-none focus:ring-2 focus:ring-terracotta/30" />
                        </div>

                        <div class="w-20 shrink-0">
                            <span class="block font-manrope text-[10px] font-bold uppercase tracking-wide text-ink/40 mb-1 truncate">
                                {{ \App\Models\Recipe::nutritionProfileLabel('wife') }}
                            </span>
                            <input
                                type="number"
                                step="any"
                                min="0"
                                wire:model="recipeIngredients.{{ $index }}.amount_wife"
                                placeholder="0"
                                class="w-full bg-sand/60 rounded-xl px-3 py-2.5 font-manrope text-sm text-ink focus:outline-none focus:ring-2 focus:ring-terracotta/30" />
                        </div>

                        <select
                            wire:model="recipeIngredients.{{ $index }}.unit"
                            class="shrink-0 bg-sand/60 rounded-xl px-3 py-2.5 font-manrope text-sm text-ink focus:outline-none focus:ring-2 focus:ring-terracotta/30">
                            @foreach (RecipeIngredientAssignment::UNITS as $unit)
                            <option value="{{ $unit }}">{{ $unit }}</option>
                            @endforeach
                        </select>

                        <div class="flex-1"></div>

                        <button
                            type="button"
                            wire:click="removeIngredient({{ $index }})"
                            class="shrink-0 text-ink/25 hover:text-terracotta-dark p-1.5">
                            <flux:icon.trash class="size-4" />
                        </button>
                    </div>
                    <x-ui.field-error name="recipeIngredients.{{ $index }}.amount_me" />
                    <x-ui.field-error name="recipeIngredients.{{ $index }}.amount_wife" />
                </div>
                @endforeach

                <datalist id="ingredients-autocomplete-list">
                    @foreach ($this->allIngredients as $ingredientName)
                    <option value="{{ $ingredientName }}"></option>
                    @endforeach
                </datalist>

                <button
                    type="button"
                    wire:click="addIngredient"
                    class="flex items-center gap-2 text-terracotta font-manrope text-[13.5px] font-bold py-1.5">
                    + {{ __('Add ingredient') }}
                </button>
            </div>
        </div>

        <div>
            <x-ui.eyebrow optional>{{ __('Nutrition per serving') }}</x-ui.eyebrow>
            <p class="font-manrope text-xs text-ink/50 mb-2.5">
                {{ __('Optional. Enter values per single serving, separately for each of you.') }}
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach (\App\Models\Recipe::NUTRITION_PROFILES as $profile)
                <div class="space-y-2">
                    <span class="font-manrope text-[11px] font-extrabold uppercase tracking-[0.1em] text-ink/50">
                        {{ \App\Models\Recipe::nutritionProfileLabel($profile) }}
                    </span>

                    <div class="grid grid-cols-2 gap-2.5">
                        <div>
                            <x-ui.text-input
                                type="number"
                                step="1"
                                min="0"
                                wire:model="recipeNutrition.{{ $profile }}.calories"
                                placeholder="{{ __('kcal') }}"
                                class="w-full" />
                            <x-ui.field-error name="recipeNutrition.{{ $profile }}.calories" />
                        </div>
                        <div>
                            <x-ui.text-input
                                type="number"
                                step="0.1"
                                min="0"
                                wire:model="recipeNutrition.{{ $profile }}.protein"
                                placeholder="{{ __('protein (g)') }}"
                                class="w-full" />
                            <x-ui.field-error name="recipeNutrition.{{ $profile }}.protein" />
                        </div>
                        <div>
                            <x-ui.text-input
                                type="number"
                                step="0.1"
                                min="0"
                                wire:model="recipeNutrition.{{ $profile }}.fat"
                                placeholder="{{ __('fat (g)') }}"
                                class="w-full" />
                            <x-ui.field-error name="recipeNutrition.{{ $profile }}.fat" />
                        </div>
                        <div>
                            <x-ui.text-input
                                type="number"
                                step="0.1"
                                min="0"
                                wire:model="recipeNutrition.{{ $profile }}.carbs"
                                placeholder="{{ __('carbs (g)') }}"
                                class="w-full" />
                            <x-ui.field-error name="recipeNutrition.{{ $profile }}.carbs" />
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        <div>
            <x-ui.eyebrow optional>{{ __('Recipe Content') }}</x-ui.eyebrow>
            <x-ui.textarea wire:model="recipeContent" rows="4" placeholder="{{ __('Recipe Content') }}" />
            <x-ui.field-error name="recipeContent" />
        </div>

        <div>
            <x-ui.eyebrow optional>{{ __('Recipe URL') }}</x-ui.eyebrow>
            <x-ui.text-input wire:model="recipeUrl" type="url" placeholder="https://..." class="w-full" />
            <x-ui.field-error name="recipeUrl" />
        </div>

        <button
            type="submit"
            class="w-full bg-terracotta hover:bg-terracotta-dark transition-colors text-white rounded-2xl py-4 font-manrope text-[15px] font-extrabold shadow-[0_10px_22px_rgba(193,68,45,0.35)]">
            {{ __('Save') }}
        </button>
    </form>
</div>