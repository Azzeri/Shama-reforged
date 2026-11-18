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
            $order = array_flip(Meal::orderedTagNames());
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

    public function delete()
    {
        $this->recipe?->delete();

        return $this->redirect(route('recipes.index'), navigate: true);
    }

    public function save()
    {
        $this->validate();

        $isNewRecipe = $this->recipe === null;

        DB::transaction(function () {
            $recipe = $this->recipe ?? new Recipe();
            $recipe->name = $this->recipeName;
            $recipe->link = trim($this->recipeUrl) ?: null;
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

        return $this->redirect(route('recipes.show', $this->recipe), navigate: true);
    }
};
?>

<div>
    <form id="recipe-form" class="space-y-5" wire:submit="save">
        <div>
            <x-ui.eyebrow>{{ __('Recipe name') }}</x-ui.eyebrow>
            <x-ui.text-input wire:model="recipeName" class="w-full" required />
            <x-ui.field-error name="recipeName" />
        </div>

        <x-recipe.tag-pill-group
            :tags="$this->tagsByCategory->get(Tag::MEAL_TYPE, [])"
            label="{{ __('Meal') }}"
            color="gold"
            wire:model="recipeTags" />

        <x-recipe.tag-pill-group
            :tags="$this->tagsByCategory->get(Tag::DIET_TYPE, [])"
            label="{{ __('Main ingredient') }}"
            color="sage"
            wire:model="recipeTags" />

        <div>
            <x-ui.eyebrow>{{ __('Ingredients') }}</x-ui.eyebrow>

            <div class="rounded-[18px] border border-ink/10 bg-white">
                @if ($recipeIngredients)
                <div class="grid grid-cols-[1fr_1fr_1fr] gap-2 px-4 pt-3 font-manrope text-[12.5px] font-semibold text-ink/55">
                    <span>{{ Recipe::nutritionProfileLabel('me') }}</span>
                    <span>{{ Recipe::nutritionProfileLabel('wife') }}</span>
                    <span>{{ __('Unit') }}</span>
                </div>
                @endif

                <div class="divide-y divide-ink/8">
                    @foreach ($recipeIngredients as $index => $item)
                    <div wire:key="ingredient-row-{{ $index }}" class="px-4 py-3 space-y-2">
                        <div class="flex items-center gap-2">
                            <input
                                type="text"
                                wire:model="recipeIngredients.{{ $index }}.name"
                                list="ingredients-autocomplete-list"
                                placeholder="{{ __('Ingredient name') }}"
                                class="flex-1 min-w-0 bg-transparent font-manrope text-[16px] font-semibold text-ink placeholder:font-normal placeholder:text-ink/35 focus:outline-none" />
                            <button type="button" wire:click="removeIngredient({{ $index }})" class="shrink-0 p-1 text-ink/40 hover:text-terracotta-dark" aria-label="{{ __('Remove') }}">
                                <flux:icon.x-mark class="size-4" />
                            </button>
                        </div>
                        <x-ui.field-error name="recipeIngredients.{{ $index }}.name" />

                        <div class="grid grid-cols-[1fr_1fr_1fr] gap-2">
                            @foreach (['me', 'wife'] as $profile)
                            <input
                                type="number"
                                step="any"
                                min="0"
                                inputmode="decimal"
                                wire:model="recipeIngredients.{{ $index }}.amount_{{ $profile }}"
                                aria-label="{{ Recipe::nutritionProfileLabel($profile) }}"
                                class="w-full min-w-0 rounded-xl border border-ink/12 bg-cream px-3 py-2.5 font-manrope text-base text-ink focus:outline-none focus:ring-2 focus:ring-terracotta/30" />
                            @endforeach
                            <select
                                wire:model="recipeIngredients.{{ $index }}.unit"
                                aria-label="{{ __('Unit') }}"
                                class="w-full min-w-0 rounded-xl border border-ink/12 bg-cream px-2.5 py-2.5 font-manrope text-base text-ink focus:outline-none focus:ring-2 focus:ring-terracotta/30">
                                @foreach (RecipeIngredientAssignment::UNITS as $unit)
                                <option value="{{ $unit }}">{{ \App\Support\ShoppingList\Quantity::unitLabel($unit) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <x-ui.field-error name="recipeIngredients.{{ $index }}.amount_me" />
                        <x-ui.field-error name="recipeIngredients.{{ $index }}.amount_wife" />
                    </div>
                    @endforeach
                </div>

                <button
                    type="button"
                    wire:click="addIngredient"
                    @class(['w-full flex items-center gap-1.5 px-4 py-3.5 font-manrope text-[15px] font-bold text-terracotta-dark', 'border-t border-ink/8' => $recipeIngredients])>
                    <flux:icon.plus class="size-4" />
                    {{ __('Add ingredient') }}
                </button>
            </div>

            <datalist id="ingredients-autocomplete-list">
                @foreach ($this->allIngredients as $ingredientName)
                <option value="{{ $ingredientName }}"></option>
                @endforeach
            </datalist>

            <p class="mt-2 font-manrope text-[12.5px] text-ink/50">
                {{ __('Amounts go to the shopping list. A new name creates a new ingredient — you can set its aisle on the Ingredients tab.') }}
            </p>
        </div>

        <div>
            <x-ui.eyebrow optional>{{ __('Nutrition per serving') }}</x-ui.eyebrow>

            <div class="rounded-[18px] border border-ink/10 bg-white px-4 py-3">
                <div class="grid grid-cols-[72px_1fr_1fr_1fr_1fr] gap-2 font-manrope text-[12.5px] font-semibold text-ink/55">
                    <span></span>
                    <span>kcal</span>
                    <span>{{ __('protein g') }}</span>
                    <span>{{ __('carbs g') }}</span>
                    <span>{{ __('fat g') }}</span>
                </div>

                @foreach (Recipe::NUTRITION_PROFILES as $profile)
                <div class="mt-2 grid grid-cols-[72px_1fr_1fr_1fr_1fr] items-center gap-2">
                    <span class="font-manrope text-[14.5px] font-bold text-ink truncate">{{ Recipe::nutritionProfileLabel($profile) }}</span>
                    @foreach (['calories', 'protein', 'carbs', 'fat'] as $macro)
                    <input
                        type="number"
                        step="{{ $macro === 'calories' ? '1' : '0.1' }}"
                        min="0"
                        inputmode="decimal"
                        wire:model="recipeNutrition.{{ $profile }}.{{ $macro }}"
                        aria-label="{{ Recipe::nutritionProfileLabel($profile) }} {{ $macro }}"
                        class="w-full min-w-0 rounded-xl border border-ink/12 bg-cream px-2 py-2.5 font-manrope text-base text-ink focus:outline-none focus:ring-2 focus:ring-terracotta/30" />
                    @endforeach
                </div>
                @foreach (['calories', 'protein', 'carbs', 'fat'] as $macro)
                <x-ui.field-error name="recipeNutrition.{{ $profile }}.{{ $macro }}" />
                @endforeach
                @endforeach
            </div>
        </div>

        <div>
            <x-ui.eyebrow optional>{{ __('Preparation') }}</x-ui.eyebrow>
            <x-ui.textarea wire:model="recipeContent" rows="5" placeholder="{{ __('One step per line') }}" />
            <x-ui.field-error name="recipeContent" />
        </div>

        <div>
            <x-ui.eyebrow optional>{{ __('Source') }}</x-ui.eyebrow>
            <x-ui.text-input wire:model="recipeUrl" type="url" placeholder="https://..." class="w-full" />
            <x-ui.field-error name="recipeUrl" />
        </div>

        @if ($recipe)
        <flux:modal.trigger name="delete-recipe-modal">
            <button type="button" class="w-full inline-flex items-center justify-center gap-1.5 rounded-[14px] border border-terracotta/50 py-2.5 font-manrope text-[14.5px] font-bold text-terracotta-dark">
                <flux:icon.trash class="size-4" />
                {{ __('Delete recipe') }}
            </button>
        </flux:modal.trigger>
        @endif

        <div class="sticky bottom-0 -mx-4 px-4 pt-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))] bg-gradient-to-t from-cream from-70% to-cream/0">
            <button
                type="submit"
                wire:loading.attr="disabled"
                class="w-full rounded-[16px] bg-terracotta py-4 font-manrope text-[16px] font-bold text-white shadow-[0_10px_22px_rgba(193,68,45,0.3)] disabled:opacity-60">
                {{ $recipe ? __('Save changes') : __('Add recipe') }}
            </button>
        </div>
    </form>

    @if ($recipe)
    <flux:modal name="delete-recipe-modal" variant="flyout" position="bottom" :closable="false" class="rounded-t-[24px] bg-cream! max-h-[88dvh] overflow-y-auto">
        <div class="space-y-5">
            <h3 class="font-fraunces text-xl font-semibold text-ink">{{ __('Delete recipe?') }}</h3>
            <p class="font-manrope text-sm text-ink/60">{{ __('The recipe will also disappear from planned meals. This cannot be undone.') }}</p>
            <div class="flex gap-2 justify-end">
                <flux:modal.close>
                    <button type="button" class="font-manrope text-sm font-extrabold text-ink/60 px-4 py-2.5">{{ __('Cancel') }}</button>
                </flux:modal.close>
                <button type="button" wire:click="delete" class="bg-terracotta text-white rounded-2xl px-5 py-2.5 font-manrope text-sm font-extrabold">{{ __('Delete recipe') }}</button>
            </div>
        </div>
    </flux:modal>
    @endif
</div>
