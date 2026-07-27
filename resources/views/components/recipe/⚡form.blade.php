<?php

use Livewire\Component;
use App\Models\Recipe;
use App\Models\Tag;
use App\Models\Ingredient;
use Livewire\Attributes\Validate;
use Livewire\Attributes\Computed;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public ?Recipe $recipe = null;

    #[Validate('required|string|max:255')]
    public string $recipeName = '';

    #[Validate('nullable|url|max:255')]
    public string $recipeUrl = '';

    #[Validate('nullable|string|max:1024')]
    public string $recipeContent = '';

    #[Validate(['recipeTags' => 'nullable|array', 'recipeTags.*' => 'exists:tags,id'])]
    public array $recipeTags = [];

    #[Validate([
        'recipeIngredients' => 'nullable|array',
        'recipeIngredients.*.name' => 'required|string|max:255',
        'recipeIngredients.*.quantity' => 'nullable|string|max:255',
    ])]
    public array $recipeIngredients = [];

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
                    'quantity' => $ingredient->pivot?->quantity ?? '',
                ];
            })->toArray();
        }
    }

    #[Computed]
    public function tagsByCategory(): Collection
    {
        return Tag::query()
            ->whereIn(Tag::CATEGORY_COLUMN, [Tag::MEAL_TYPE, Tag::DIET_TYPE])
            ->orderBy(Tag::NAME_COLUMN)
            ->get(['id', Tag::NAME_COLUMN, Tag::CATEGORY_COLUMN])
            ->groupBy(Tag::CATEGORY_COLUMN);
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
            'quantity' => '',
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

        DB::transaction(function () {
            $recipe = $this->recipe ?? new Recipe();
            $recipe->name = trim($this->recipeName);
            $recipe->link = trim($this->recipeUrl ?: null);
            $recipe->content = $this->recipeContent ?: null;
            $recipe->save();

            $recipe->tags()->sync($this->recipeTags);

            $ingredientsSyncData = [];
            foreach ($this->recipeIngredients as $item) {
                $name = trim($item['name'] ?? '');

                if ($name !== '') {
                    $ingredient = Ingredient::firstOrCreate(['name' => $name]);

                    $ingredientsSyncData[$ingredient->id] = [
                        'quantity' => $item['quantity'] ?: null,
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
            </p>

            <div class="space-y-2.5">
                @foreach ($recipeIngredients as $index => $item)
                <div wire:key="ingredient-row-{{ $index }}">
                    <div class="flex items-center gap-2.5">
                        <x-ui.text-input
                            wire:model="recipeIngredients.{{ $index }}.name"
                            list="ingredients-autocomplete-list"
                            placeholder="{{ __('Ingredient name') }}"
                            class="flex-1" />

                        <x-ui.text-input
                            wire:model="recipeIngredients.{{ $index }}.quantity"
                            placeholder="{{ __('e.g. 200g') }}"
                            class="w-24 shrink-0" />

                        <button
                            type="button"
                            wire:click="removeIngredient({{ $index }})"
                            class="shrink-0 text-ink/25 hover:text-terracotta-dark p-1.5">
                            <flux:icon.trash class="size-4" />
                        </button>
                    </div>
                    <x-ui.field-error name="recipeIngredients.{{ $index }}.name" />
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