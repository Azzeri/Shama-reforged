<?php

use App\Models\Recipe;
use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use App\Models\Tag;
use App\Models\Meal;
use Illuminate\Support\Collection;

new #[Layout('layouts::app')] class extends Component {
    use WithPagination;

    #[Url]
    public $searchName = '';

    #[Url(as: 'categories')]
    public array $searchCategories = [];

    public function render()
    {
        return $this->view()
            ->title(__('Recipes'));
    }

    public function updatedSearchName(): void
    {
        $this->resetPage();
    }

    public function updatedSearchCategories(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function recipes()
    {
        return Recipe::query()
            ->when($this->searchName, function ($query) {
                $query->where('name', 'like', '%' . $this->searchName . '%');
            })
            ->when(!empty($this->searchCategories), function ($query) {
                $query->whereHas('tags', function ($tagQuery) {
                    $tagQuery->whereIn('tags.id', $this->searchCategories);
                });
            })
            ->with(['tags', 'ingredients'])
            ->orderBy('name')
            ->simplePaginate(10);
    }

    #[Computed]
    public function activeFiltersCount(): int
    {
        return count($this->searchCategories) + ($this->searchName !== '' ? 1 : 0);
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

    public function clearFilters(): void
    {
        $this->searchName = '';
        $this->searchCategories = [];
        $this->resetPage();
    }

    public function goToDetails(Recipe $recipe): void
    {
        $this->redirectRoute('recipes.show', $recipe);
    }
};
?>
<div>
    <div class="space-y-5">
        <div>
            <x-ui.eyebrow color="forest">{{ __('Your cookbook') }}</x-ui.eyebrow>
            <h1 class="font-fraunces text-[30px] font-semibold leading-tight text-ink">{{ __('Recipes') }}</h1>
        </div>

        <a
            href="{{ route('recipes.create') }}"
            wire:navigate
            class="block w-full text-center bg-terracotta hover:bg-terracotta-dark transition-colors text-white rounded-2xl py-[15px] font-manrope text-[14.5px] font-extrabold shadow-[0_10px_22px_rgba(193,68,45,0.3)]">
            + {{ __('New recipe') }}
        </a>

        <form class="space-y-5">
            <div>
                <x-ui.eyebrow>{{ __('Search by recipe name') }}</x-ui.eyebrow>
                <div class="relative">
                    <x-ui.text-input
                        wire:model.live="searchName"
                        placeholder="{{ __('Enter recipe name') }}"
                        class="w-full pr-9" />
                    @if ($searchName)
                    <button
                        type="button"
                        wire:click="$set('searchName', '')"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-ink/30 hover:text-ink/60">
                        <flux:icon.x-mark class="size-4" />
                    </button>
                    @endif
                </div>
            </div>

            <x-recipe.tag-pill-group
                :tags="$this->tagsByCategory->get(Tag::MEAL_TYPE, [])"
                label="{{ __('Meal type') }}"
                color="gold"
                wire:model.live="searchCategories" />

            <x-recipe.tag-pill-group
                :tags="$this->tagsByCategory->get(Tag::DIET_TYPE, [])"
                label="{{ __('Diet type') }}"
                color="sage"
                wire:model.live="searchCategories" />

            @if ($this->activeFiltersCount > 0)
            <div class="flex items-center justify-between bg-sand rounded-2xl px-3.5 py-[11px]">
                <span class="font-manrope text-[12.5px] font-bold text-ink/60">
                    {{ __(':count active filters', ['count' => $this->activeFiltersCount]) }}
                </span>
                <button type="button" wire:click="clearFilters" class="font-manrope text-[12.5px] font-extrabold text-terracotta-dark">
                    {{ __('Clear') }}
                </button>
            </div>
            @endif
        </form>

        <div class="space-y-3">
            @forelse ($this->recipes as $recipe)
            <x-recipe.card :recipe="$recipe" :dot="['terracotta', 'gold', 'sage'][$loop->index % 3]" />
            @empty
            <div class="border border-dashed border-ink/25 rounded-2xl px-4 py-8 text-center font-manrope text-sm text-ink/50">
                {{ __('No recipes for the given filters') }}
            </div>
            @endforelse

            <flux:pagination :paginator="$this->recipes" class="mt-2" />
        </div>
    </div>
</div>