<?php

use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\Recipe;
use App\Models\Tag;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::app')] class extends Component {
    /** Matches the recipe name or any of its ingredients. */
    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'tags')]
    public array $tagIds = [];

    #[Url(as: 'ingredients')]
    public array $ingredientIds = [];

    public string $ingredientSearch = '';

    public function render()
    {
        return $this->view()->title(__('Recipes'));
    }

    #[Computed]
    public function recipes(): Collection
    {
        $search = trim($this->search);

        return Recipe::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhereHas('ingredients', fn ($q) => $q->where('ingredients.name', 'like', "%{$search}%"))))
            ->when($this->tagIds, function ($query) {
                // Tags of the same kind are alternatives (breakfast OR lunch),
                // different kinds narrow down (breakfast AND vegetarian).
                Tag::query()->whereKey($this->tagIds)->get()->groupBy('category')
                    ->each(fn (Collection $tags) => $query->whereHas('tags', fn ($q) => $q->whereKey($tags->modelKeys())));
            })
            ->when($this->ingredientIds, function ($query) {
                foreach ($this->ingredientIds as $ingredientId) {
                    $query->whereHas('ingredients', fn ($q) => $q->whereKey((int) $ingredientId));
                }
            })
            ->with('tags')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function filterTags(): Collection
    {
        $order = array_flip(Meal::orderedTagNames());

        return Tag::query()
            ->whereIn(Tag::CATEGORY_COLUMN, [Tag::MEAL_TYPE, Tag::DIET_TYPE])
            ->get()
            ->sortBy(fn (Tag $tag) => [$tag->isMealType() ? 0 : 1, $order[$tag->name] ?? PHP_INT_MAX, $tag->label()])
            ->values();
    }

    #[Computed]
    public function selectedIngredients(): Collection
    {
        return Ingredient::query()->whereKey($this->ingredientIds)->orderBy('name')->get();
    }

    #[Computed]
    public function ingredientOptions(): Collection
    {
        return Ingredient::query()
            ->whereHas('recipes')
            ->when(trim($this->ingredientSearch) !== '', fn ($query) => $query->where('name', 'like', '%' . trim($this->ingredientSearch) . '%'))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function toggleTag(int $tagId): void
    {
        $this->tagIds = in_array($tagId, $this->tagIds)
            ? array_values(array_diff($this->tagIds, [$tagId]))
            : [...$this->tagIds, $tagId];
    }

    public function toggleIngredient(int $ingredientId): void
    {
        $this->ingredientIds = in_array($ingredientId, $this->ingredientIds)
            ? array_values(array_diff($this->ingredientIds, [$ingredientId]))
            : [...$this->ingredientIds, $ingredientId];
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'tagIds', 'ingredientIds');
    }
};
?>

<div>
    <div class="space-y-4">
        <div class="flex items-center justify-between gap-3">
            <h1 class="font-fraunces text-[34px] font-semibold leading-none text-ink">{{ __('Recipes') }}</h1>

            <a href="{{ route('recipes.create') }}" wire:navigate class="inline-flex items-center gap-1.5 rounded-full bg-terracotta px-4 py-2.5 font-manrope text-[15px] font-bold text-white shadow-[0_6px_14px_rgba(193,68,45,0.3)]">
                <flux:icon.plus class="size-4" />
                {{ __('New') }}
            </a>
        </div>

        <div class="relative">
            <flux:icon.magnifying-glass class="absolute left-3.5 top-1/2 -translate-y-1/2 size-4 text-ink/45" />
            <input
                type="search"
                wire:model.live.debounce.250ms="search"
                placeholder="{{ __('Search for a recipe or ingredient') }}"
                class="w-full rounded-2xl border border-ink/10 bg-white py-3 pl-10 pr-9 font-manrope text-base text-ink placeholder:text-ink/40 focus:outline-none focus:ring-2 focus:ring-terracotta/30" />
            @if ($search !== '')
            <button type="button" wire:click="$set('search', '')" class="absolute right-3 top-1/2 -translate-y-1/2 text-ink/35" aria-label="{{ __('Clear') }}">
                <flux:icon.x-mark class="size-4" />
            </button>
            @endif
        </div>

        <div class="-mx-4 px-4 flex gap-2 overflow-x-auto pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            <flux:modal.trigger name="ingredient-filter-modal">
                <button type="button" @class([
                    'shrink-0 inline-flex items-center gap-1.5 rounded-full border px-3.5 py-2 font-manrope text-[14px] font-bold',
                    'bg-ink border-ink text-white' => $ingredientIds,
                    'bg-white border-ink/10 text-ink' => ! $ingredientIds,
                ])>
                    <flux:icon.funnel class="size-4" />
                    {{ __('Ingredient') }}@if ($ingredientIds) · {{ count($ingredientIds) }}@endif
                </button>
            </flux:modal.trigger>

            @foreach ($this->filterTags as $tag)
            @php $active = in_array($tag->id, $tagIds); @endphp
            <button
                type="button"
                wire:key="filter-tag-{{ $tag->id }}"
                wire:click="toggleTag({{ $tag->id }})"
                @class([
                    'shrink-0 rounded-full border px-3.5 py-2 font-manrope text-[14px] font-semibold whitespace-nowrap',
                    'bg-white border-ink/10 text-ink' => ! $active,
                    'bg-gold border-gold text-white' => $active && $tag->isMealType(),
                    'bg-forest border-forest text-white' => $active && ! $tag->isMealType(),
                ])>
                {{ $tag->label() }}
            </button>
            @endforeach
        </div>

        @if ($tagIds || $ingredientIds || $search !== '')
        <div class="flex items-center justify-between gap-3 font-manrope text-[13.5px]">
            <span class="text-ink/55 truncate">
                {{ trans_choice(':count recipe|:count recipes', $this->recipes->count()) }}
                @if ($this->selectedIngredients->isNotEmpty())
                · {{ $this->selectedIngredients->pluck('name')->implode(', ') }}
                @endif
            </span>
            <button type="button" wire:click="clearFilters" class="shrink-0 font-bold text-terracotta-dark">{{ __('Clear filters') }}</button>
        </div>
        @endif

        @if ($this->recipes->isNotEmpty())
        <div class="rounded-[22px] border border-ink/10 bg-white divide-y divide-ink/8 overflow-hidden">
            @foreach ($this->recipes as $recipe)
            @php $kcal = Recipe::shortCalorieLine(collect(Recipe::NUTRITION_PROFILES)->mapWithKeys(fn ($p) => [$p => $recipe->caloriesFor($p)])->filter()->all()); @endphp
            <a href="{{ route('recipes.show', $recipe) }}" wire:navigate wire:key="recipe-{{ $recipe->id }}" class="flex items-center gap-3 px-4 py-3.5">
                <div class="flex-1 min-w-0">
                    <div class="font-fraunces text-[17px] font-semibold leading-snug text-ink">{{ $recipe->name }}</div>
                    @if ($recipe->tags->isNotEmpty() || $kcal !== '')
                    <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                        @foreach ($recipe->tags->sortBy(fn ($tag) => $tag->isMealType() ? 0 : 1) as $tag)
                        <x-recipe.tag-badge :tag="$tag" />
                        @endforeach
                        @if ($kcal !== '')
                        <span class="font-manrope text-[12.5px] text-ink/50">{{ $kcal }}</span>
                        @endif
                    </div>
                    @endif
                </div>
                <flux:icon.chevron-right class="size-4 shrink-0 text-ink/35" />
            </a>
            @endforeach
        </div>
        @else
        <div class="rounded-[22px] border border-dashed border-ink/20 px-4 py-10 text-center font-manrope text-[15px] text-ink/50">
            {{ __('No recipes match these filters.') }}
        </div>
        @endif
    </div>

    <flux:modal name="ingredient-filter-modal" variant="flyout" position="bottom" :closable="false" class="rounded-t-[24px] bg-cream! max-h-[88dvh] overflow-y-auto">
        <div class="space-y-4">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <h3 class="font-fraunces text-xl font-semibold text-ink">{{ __('Filter by ingredient') }}</h3>
                    <p class="font-manrope text-[13px] text-ink/55">{{ __('Recipes containing all selected ingredients.') }}</p>
                </div>
                <flux:modal.close>
                    <button type="button" class="text-ink/25 hover:text-ink/50 p-1.5 text-lg leading-none">✕</button>
                </flux:modal.close>
            </div>

            <input
                type="search"
                wire:model.live.debounce.200ms="ingredientSearch"
                placeholder="{{ __('Search ingredients') }}"
                class="w-full rounded-2xl border border-ink/10 bg-white px-3.5 py-3 font-manrope text-base text-ink placeholder:text-ink/40 focus:outline-none focus:ring-2 focus:ring-terracotta/30" />

            <div class="rounded-2xl border border-ink/10 bg-white divide-y divide-ink/8 max-h-[50dvh] overflow-y-auto">
                @forelse ($this->ingredientOptions as $ingredient)
                @php $selected = in_array($ingredient->id, $ingredientIds); @endphp
                <button type="button" wire:key="ingredient-option-{{ $ingredient->id }}" wire:click="toggleIngredient({{ $ingredient->id }})" class="w-full flex items-center gap-3 px-4 py-3 text-left">
                    <span @class([
                        'flex items-center justify-center size-[22px] shrink-0 rounded-[7px] border-[1.5px]',
                        'bg-forest border-forest' => $selected,
                        'border-ink/25' => ! $selected,
                    ])>
                        @if ($selected)<flux:icon.check variant="mini" class="size-3.5 text-white" />@endif
                    </span>
                    <span class="font-manrope text-[15px] text-ink">{{ $ingredient->name }}</span>
                </button>
                @empty
                <div class="px-4 py-6 text-center font-manrope text-sm text-ink/50">{{ __('No ingredients found.') }}</div>
                @endforelse
            </div>

            <flux:modal.close>
                <button type="button" class="w-full rounded-[14px] bg-terracotta py-3.5 font-manrope text-sm font-extrabold text-white">
                    {{ trans_choice('Show :count recipe|Show :count recipes', $this->recipes->count()) }}
                </button>
            </flux:modal.close>
        </div>
    </flux:modal>
</div>
