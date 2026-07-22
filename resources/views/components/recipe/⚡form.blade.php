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
        <flux:input wire:model="recipeName" label="{{ __('Recipe Name') }}" required clearable />

        <flux:checkbox.group wire:model="recipeTags" label="{{ __('Meal type') }}" variant="pills">
            @foreach ($this->tagsByCategory->get(Tag::MEAL_TYPE, []) as $tag)
            <flux:checkbox value="{{ $tag->id }}" label="{{ $tag->name }}" />
            @endforeach
        </flux:checkbox.group>

        <flux:checkbox.group wire:model="recipeTags" label="{{ __('Diet type') }}" variant="pills">
            @foreach ($this->tagsByCategory->get(Tag::DIET_TYPE, []) as $tag)
            <flux:checkbox value="{{ $tag->id }}" label="{{ $tag->name }}" />
            @endforeach
        </flux:checkbox.group>

        <flux:field>
            <flux:label>{{ __('Ingredients') }}</flux:label>

            <div class="space-y-3 mt-2">
                @foreach ($recipeIngredients as $index => $item)
                <div class="flex items-start gap-2" wire:key="ingredient-row-{{ $index }}">
                    <div class="flex-1">
                        <flux:input
                            wire:model="recipeIngredients.{{ $index }}.name"
                            list="ingredients-autocomplete-list"
                            placeholder="{{ __('Ingredient name') }}" />
                    </div>

                    <div class="w-1/3">
                        <flux:input
                            wire:model="recipeIngredients.{{ $index }}.quantity"
                            placeholder="{{ __('e.g. 200g') }}" />
                    </div>

                    <flux:button
                        type="button"
                        icon="trash"
                        variant="ghost"
                        wire:click="removeIngredient({{ $index }})" />
                </div>
                @endforeach

                <datalist id="ingredients-autocomplete-list">
                    @foreach ($this->allIngredients as $ingredientName)
                    <option value="{{ $ingredientName }}"></option>
                    @endforeach
                </datalist>

                <flux:button
                    type="button"
                    icon="plus"
                    variant="subtle"
                    wire:click="addIngredient"
                    class="w-full">
                    {{ __('Add ingredient') }}
                </flux:button>
            </div>
        </flux:field>

        <flux:textarea wire:model="recipeContent" rows="auto" resize="none" badge="optional" label="{{ __('Recipe Content') }}" />

        <flux:input wire:model="recipeUrl" badge="optional" label="{{ __('Recipe URL') }}" clearable />

        <flux:button type="submit" variant="primary" class="w-full">{{ __("Save") }}</flux:button>
    </form>
</div>