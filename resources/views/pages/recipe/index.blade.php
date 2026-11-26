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

    /** healthy | medium | unhealthy */
    #[Url(as: 'rating')]
    public ?string $rating = null;

    #[Url(as: 'packable')]
    public bool $packable = false;

    /** Filter panel (main ingredient, rating, packing) unfolded. */
    public bool $filtersOpen = false;

    public bool $archiveOpen = false;

    /** Desktop split view: the recipe shown next to the list. */
    #[Url(as: 'r')]
    public ?int $selectedId = null;

    public function select(int $recipeId): void
    {
        $this->selectedId = $recipeId;
    }

    /** The embedded details archived the recipe: it leaves the list. */
    #[\Livewire\Attributes\On('recipe-archived')]
    public function recipeArchived(): void
    {
        $this->selectedId = null;
        unset($this->recipes, $this->archivedRecipes);
    }

    public string $ingredientSearch = '';

    /** The ingredient list in the filter sheet is only rendered once opened. */
    public bool $ingredientFilterLoaded = false;

    public function render()
    {
        return $this->view()->title(__('Recipes'));
    }

    #[Computed]
    public function recipes(): Collection
    {
        $search = trim($this->search);

        return $this->filtered(Recipe::query()->active())
            ->with('tags')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function archivedRecipes(): Collection
    {
        return Recipe::query()->archived()->with('tags')->orderBy('name')->get();
    }

    /** Search + all filters; also used by tests through the page. */
    private function filtered($query)
    {
        $search = trim($this->search);

        return $query
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
            ->when(in_array($this->rating, Recipe::RATINGS, true), fn ($query) => $query->where('rating', $this->rating))
            ->when($this->packable, fn ($query) => $query->where('packable', true));
    }

    #[Computed]
    public function mealTags(): Collection
    {
        return $this->filterTags->filter(fn (Tag $tag) => $tag->isMealType())->values();
    }

    #[Computed]
    public function dietTags(): Collection
    {
        return $this->filterTags->filter(fn (Tag $tag) => $tag->isDietType())->values();
    }

    /** Filters inside the panel (meal chips are always visible, so not counted). */
    #[Computed]
    public function panelFilterCount(): int
    {
        return $this->dietTags->whereIn('id', $this->tagIds)->count()
            + ($this->rating ? 1 : 0)
            + ($this->packable ? 1 : 0)
            + count($this->ingredientIds);
    }

    public function hasFilters(): bool
    {
        return trim($this->search) !== '' || $this->tagIds || $this->ingredientIds || $this->rating || $this->packable;
    }

    public function setRating(string $rating): void
    {
        $this->rating = $this->rating === $rating || ! in_array($rating, Recipe::RATINGS, true) ? null : $rating;
    }

    public function restore(int $recipeId): void
    {
        Recipe::query()->whereKey($recipeId)->update(['archived_at' => null]);
        unset($this->recipes, $this->archivedRecipes);
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

    public function openIngredientFilter(): void
    {
        $this->ingredientFilterLoaded = true;
        $this->dispatch('modal-show', name: 'ingredient-filter-modal');
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
        $this->reset('search', 'tagIds', 'ingredientIds', 'rating', 'packable');
    }
};
?>

@php
    $recipes = $this->recipes;
    $filtersActive = $this->hasFilters();
@endphp

<div class="space-y-3">
    <div class="flex items-center gap-3 lg:gap-6">
        <h1 class="font-fraunces text-[28px] lg:text-[32px] font-semibold leading-none tracking-[-0.01em] text-ink">{{ __('Recipes') }}</h1>
        <div class="hidden lg:block flex-1 max-w-[420px]">
            <x-ui.search-input id="recipe-search-desktop" wire:model.live.debounce.250ms="search" :placeholder="__('Search recipe')" />
        </div>
        <div class="ml-auto">
            <x-ui.pill :href="route('recipes.create')"><span class="lg:hidden">{{ __('New') }}</span><span class="hidden lg:inline">{{ __('New recipe') }}</span></x-ui.pill>
        </div>
    </div>

    <div class="lg:hidden">
        <x-ui.search-input id="recipe-search" wire:model.live.debounce.250ms="search" :placeholder="__('Search recipe')" />
    </div>

    {{-- Desktop: every filter in one wrapping row, no panel to unfold. --}}
    <div class="hidden lg:flex flex-wrap items-center gap-2">
        @foreach ($this->mealTags as $tag)
        <x-ui.chip wire:key="d-meal-chip-{{ $tag->id }}" color="gold" :active="in_array($tag->id, $tagIds)" wire:click="toggleTag({{ $tag->id }})">{{ $tag->label() }}</x-ui.chip>
        @endforeach
        <span class="h-6 w-px bg-field mx-1" aria-hidden="true"></span>
        @foreach ($this->dietTags as $tag)
        <x-ui.chip wire:key="d-diet-chip-{{ $tag->id }}" color="sage" :active="in_array($tag->id, $tagIds)" wire:click="toggleTag({{ $tag->id }})">{{ $tag->label() }}</x-ui.chip>
        @endforeach
        <span class="h-6 w-px bg-field mx-1" aria-hidden="true"></span>
        @foreach (Recipe::RATINGS as $ratingOption)
        <x-ui.chip wire:key="d-rating-chip-{{ $ratingOption }}" :active="$rating === $ratingOption" wire:click="setRating('{{ $ratingOption }}')">
            <span class="size-2 rounded-full {{ Recipe::ratingDotClass($ratingOption) }}" aria-hidden="true"></span>
            {{ Recipe::ratingLabel($ratingOption) }}
        </x-ui.chip>
        @endforeach
        <x-ui.chip :active="$packable" wire:click="$toggle('packable')">{{ __('for packing') }}</x-ui.chip>
        @foreach ($this->selectedIngredients as $ingredient)
        <x-ui.chip wire:key="d-ingredient-chip-{{ $ingredient->id }}" active wire:click="toggleIngredient({{ $ingredient->id }})">
            {{ $ingredient->name }} <x-icon.x class="size-3.5" />
        </x-ui.chip>
        @endforeach
        <button type="button" wire:click="openIngredientFilter" class="inline-flex h-9 items-center gap-1 rounded-full border border-dashed border-faint px-3.5 text-[14px] font-medium text-terracotta">
            <x-icon.plus class="size-4" />
            {{ __('Contains ingredient') }}
        </button>
    </div>

    <div class="lg:hidden -mx-4 px-4 flex gap-2 overflow-x-auto no-scrollbar">
        <button type="button" wire:click="$toggle('filtersOpen')" aria-expanded="{{ $filtersOpen ? 'true' : 'false' }}"
            @class(['inline-flex h-9 shrink-0 items-center gap-1.5 rounded-full border px-3.5 text-[14px] font-semibold', 'bg-sand border-faint text-ink' => $filtersOpen || $this->panelFilterCount, 'bg-white border-field text-ink' => ! $filtersOpen && ! $this->panelFilterCount])>
            <x-icon.filter class="size-4" />
            {{ __('Filters') }}@if ($this->panelFilterCount) ({{ $this->panelFilterCount }})@endif
        </button>
        @foreach ($this->mealTags as $tag)
        <x-ui.chip wire:key="meal-chip-{{ $tag->id }}" color="gold" :active="in_array($tag->id, $tagIds)" wire:click="toggleTag({{ $tag->id }})">{{ $tag->label() }}</x-ui.chip>
        @endforeach
    </div>

    @if ($filtersOpen)
    <div class="space-y-3 pt-1 lg:hidden">
        <div>
            <x-ui.section-label class="mb-1.5">{{ __('Main ingredient') }}</x-ui.section-label>
            <div class="flex flex-wrap gap-2">
                @foreach ($this->dietTags as $tag)
                <x-ui.chip wire:key="diet-chip-{{ $tag->id }}" color="sage" :active="in_array($tag->id, $tagIds)" wire:click="toggleTag({{ $tag->id }})">{{ $tag->label() }}</x-ui.chip>
                @endforeach
            </div>
        </div>
        <div>
            <x-ui.section-label class="mb-1.5">{{ __('Rating and packing') }}</x-ui.section-label>
            <div class="flex flex-wrap gap-2">
                @foreach (Recipe::RATINGS as $ratingOption)
                <x-ui.chip wire:key="rating-chip-{{ $ratingOption }}" :active="$rating === $ratingOption" wire:click="setRating('{{ $ratingOption }}')">{{ Recipe::ratingLabel($ratingOption) }}</x-ui.chip>
                @endforeach
                <x-ui.chip :active="$packable" wire:click="$toggle('packable')">{{ __('for packing') }}</x-ui.chip>
            </div>
        </div>
        <div>
            <x-ui.section-label class="mb-1.5">{{ __('Ingredients') }}</x-ui.section-label>
            <div class="flex flex-wrap gap-2">
                @foreach ($this->selectedIngredients as $ingredient)
                <x-ui.chip wire:key="ingredient-chip-{{ $ingredient->id }}" active wire:click="toggleIngredient({{ $ingredient->id }})">
                    {{ $ingredient->name }} <x-icon.x class="size-3.5" />
                </x-ui.chip>
                @endforeach
                <button type="button" wire:click="openIngredientFilter" class="inline-flex h-9 items-center gap-1 rounded-full border border-dashed border-faint px-3.5 text-[14px] font-medium text-terracotta">
                    <x-icon.plus class="size-4" />
                    {{ __('Contains ingredient') }}
                </button>
            </div>
        </div>
    </div>
    @endif

    <div class="lg:grid lg:grid-cols-[minmax(340px,420px)_1fr] lg:items-start lg:gap-8 lg:pt-2">
    {{-- On desktop a row selects the recipe for the right-hand pane instead of opening its page. --}}
    <div class="space-y-3"
        x-data="desktopSelect('a[data-recipe-id]', 'recipeId', (id) => $wire.select(id))"
        x-init="if (window.isDesktop() && ! $wire.selectedId && {{ $recipes->first()?->id ?? 'null' }}) $wire.select({{ $recipes->first()?->id ?? 'null' }})">
    @if ($filtersActive)
    <div class="flex items-center justify-between pt-1">
        <span class="text-[14px] text-muted">{{ trans_choice(':count recipe|:count recipes', $recipes->count()) }}</span>
        <button type="button" wire:click="clearFilters" class="h-9 text-[14px] font-semibold text-terracotta">{{ __('Clear filters') }}</button>
    </div>
    @endif

    @if ($recipes->isEmpty())
    <div class="rounded-[20px] border border-dashed border-field px-5 py-10 text-center text-[15px] text-muted">
        {{ $filtersActive ? __('No recipes match the filters.') : __('No recipes yet.') }}
    </div>
    @else
    <x-ui.card class="divide-y divide-divider">
        @foreach ($recipes as $recipe)
        <x-recipe.row :recipe="$recipe" :selected="$selectedId === $recipe->id" data-recipe-id="{{ $recipe->id }}" wire:key="recipe-{{ $recipe->id }}" />
        @endforeach
    </x-ui.card>
    @endif

    @if ($this->archivedRecipes->isNotEmpty())
    <div class="pt-1">
        <button type="button" wire:click="$toggle('archiveOpen')" aria-expanded="{{ $archiveOpen ? 'true' : 'false' }}" class="inline-flex h-11 items-center gap-2 px-1 text-[15px] font-semibold text-ink-soft">
            <x-icon.archive class="size-[18px]" />
            {{ __('Archive') }} ({{ $this->archivedRecipes->count() }})
            <x-icon.chevron-down class="size-4 transition-transform {{ $archiveOpen ? 'rotate-180' : '' }}" />
        </button>

        @if ($archiveOpen)
        <div class="mt-1 rounded-[20px] border border-dashed border-faint bg-white/60 divide-y divide-divider overflow-hidden">
            @foreach ($this->archivedRecipes as $archived)
            <div wire:key="archived-{{ $archived->id }}" class="flex items-center gap-3 px-4 py-2.5 min-h-14">
                <a href="{{ route('recipes.show', $archived) }}" wire:navigate class="flex-1 min-w-0 font-fraunces text-[16px] font-semibold text-ink-soft truncate">{{ $archived->name }}</a>
                <button type="button" wire:click="restore({{ $archived->id }})" class="h-9 shrink-0 rounded-full border border-field bg-white px-3 text-[13px] font-semibold text-ink">{{ __('Restore') }}</button>
            </div>
            @endforeach
        </div>
        @endif
    </div>
    @endif

    </div>

    <div class="hidden lg:block min-w-0">
        @if ($selectedId && Recipe::query()->whereKey($selectedId)->exists())
        <livewire:pages::recipe.show :recipe="Recipe::find($selectedId)" :embedded="true" :key="'recipe-pane-'.$selectedId" />
        @endif
    </div>
    </div>

    <flux:modal name="ingredient-filter-modal" variant="flyout" position="bottom" :closable="false" class="rounded-t-[24px] bg-white! max-h-[88dvh] overflow-y-auto">
        <div class="space-y-4">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <h3 class="font-fraunces text-xl font-semibold text-ink">{{ __('Filter by ingredient') }}</h3>
                    <p class="font-inter text-[13px] text-muted">{{ __('Recipes containing all selected ingredients.') }}</p>
                </div>
                <flux:modal.close>
                    <button type="button" class="-mr-2.5 flex size-11 items-center justify-center text-ink" aria-label="{{ __('Close') }}"><x-icon.x class="size-[18px]" /></button>
                </flux:modal.close>
            </div>

            <input
                type="search"
                wire:model.live.debounce.200ms="ingredientSearch"
                placeholder="{{ __('Search ingredients') }}"
                aria-label="{{ __('Search ingredients') }}"
                class="w-full h-11 rounded-xl border border-field bg-field-bg px-3 text-base text-ink placeholder:text-muted focus:outline-none focus:border-ink focus:ring-1 focus:ring-ink" />

            <div class="rounded-2xl border border-line bg-white divide-y divide-divider max-h-[50dvh] overflow-y-auto">
                @forelse ($ingredientFilterLoaded ? $this->ingredientOptions : [] as $ingredient)
                @php $selected = in_array($ingredient->id, $ingredientIds); @endphp
                <button type="button" wire:key="ingredient-option-{{ $ingredient->id }}" wire:click="toggleIngredient({{ $ingredient->id }})" class="w-full flex items-center gap-3 px-4 py-3 text-left">
                    <span @class([
                        'flex items-center justify-center size-[22px] shrink-0 rounded-[7px] border-[1.5px]',
                        'bg-sage border-sage' => $selected,
                        'border-field' => ! $selected,
                    ])>
                        @if ($selected)<x-icon.check class="size-3.5 text-white" />@endif
                    </span>
                    <span class="font-inter text-[15px] text-ink">{{ $ingredient->name }}</span>
                </button>
                @empty
                <div class="px-4 py-6 text-center font-inter text-sm text-muted">{{ __('No ingredients found.') }}</div>
                @endforelse
            </div>

            <flux:modal.close>
                <button type="button" class="w-full h-12 rounded-[14px] bg-terracotta text-[15px] font-semibold text-white">
                    {{ trans_choice('Show :count recipe|Show :count recipes', $this->recipes->count()) }}
                </button>
            </flux:modal.close>
        </div>
    </flux:modal>
</div>
