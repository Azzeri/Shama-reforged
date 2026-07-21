<?php

use App\Models\Recipe;
use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithPagination;
use App\Models\Tag;
use Illuminate\Support\Collection;

new #[Title('Recipes')] #[Layout('layouts::app')] class extends Component {
    use WithPagination;

    #[Url]
    public $searchName = '';

    #[Url(as: 'categories')]
    public array $searchCategories = [];

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
    public function tagsByCategory(): Collection
    {
        return Tag::query()
            ->whereIn(Tag::CATEGORY_COLUMN, [Tag::MEAL_TYPE, Tag::DIET_TYPE])
            ->orderBy(Tag::NAME_COLUMN)
            ->get(['id', Tag::NAME_COLUMN, Tag::CATEGORY_COLUMN])
            ->groupBy(Tag::CATEGORY_COLUMN);
    }

    public function clearFilters(): void
    {
        $this->searchName = '';
        $this->searchCategories = [];
        $this->resetPage();
    }
};
?>
<div>
    <div class="space-y-5">
        <flux:heading size="xl">{{ __('Recipes') }}</flux:heading>
        <flux:button
            variant="primary"
            as="a"
            href="{{ route('recipes.create') }}"
            wire:navigate
            class="w-full">
            {{ __('New recipe') }}
        </flux:button>
        <form class="space-y-5">
            <flux:field>
                <flux:label>{{__("Search by recipe name")}}</flux:label>
                <flux:input placeholder="{{__('Enter recipe name')}}" clearable wire:model.live="searchName" />
            </flux:field>

            <flux:checkbox.group wire:model.live="searchCategories" label="{{ __('Meal type') }}" variant="pills">
                @foreach ($this->tagsByCategory->get(Tag::MEAL_TYPE, []) as $tag)
                <flux:checkbox value="{{ $tag->id }}" label="{{ $tag->name }}" />
                @endforeach
            </flux:checkbox.group>

            <flux:checkbox.group wire:model.live="searchCategories" label="{{ __('Diet type') }}" variant="pills">
                @foreach ($this->tagsByCategory->get(Tag::DIET_TYPE, []) as $tag)
                <flux:checkbox value="{{ $tag->id }}" label="{{ $tag->name }}" />
                @endforeach
            </flux:checkbox.group>

            <div class="flex justify-end w-full">
                <flux:button icon="funnel" wire:click="clearFilters">{{ __('Clear') }}</flux:button>
            </div>
        </form>

        <div class="space-y-3">
            @forelse ($this->recipes as $recipe)
            <flux:card>
                <a href="{{ route('recipes.show', $recipe) }}">
                    <flux:heading size="lg">{{$recipe->name ?? 'Unknown recipe'}}</flux:heading>
                </a>

                <flux:text class="mt-2 mb-4" size="sm">
                    {{__("Składniki")}}: {{ $recipe->ingredients->take(10)->pluck('name')->join(', ') ?: '-' }}
                </flux:text>
                <div class="mt-2">
                    @foreach ($recipe->tags as $tag)
                    <flux:badge rounded size="sm" color="orange">{{$tag->name}}</flux:badge>
                    @endforeach
                </div>
            </flux:card>
            @empty
            <flux:callout icon="face-frown">
                <flux:callout.heading>{{__('No recipes for the given filters')}}</flux:callout.heading>
            </flux:callout>
            @endforelse

            <flux:pagination :paginator="$this->recipes" />
        </div>
    </div>
</div>