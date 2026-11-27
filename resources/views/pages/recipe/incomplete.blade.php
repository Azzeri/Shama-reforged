<?php

use App\Models\Meal;
use App\Models\Recipe;
use App\Models\Tag;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/*
 * "To fill in": active recipes missing a meal, main ingredient, rating or the
 * "for packing" answer. Chips save right away. Phone: a row opens a bottom
 * sheet with the missing fields; closing it with a complete recipe drops the
 * row and offers "Undo". Desktop: list on the left, the selected recipe's card
 * on the right with "Next recipe".
 */
new #[Layout('layouts::app')] class extends Component {
    public const FILTER_ALL = 'all';

    #[Url(as: 'filter')]
    public string $filter = self::FILTER_ALL;

    /** The recipe being filled in (phone sheet or desktop card). */
    public ?int $openId = null;

    /** Phone: the sheet is shown (desktop shows the card instead). */
    public bool $sheetOpen = false;

    /** Fields that were missing when the recipe was opened — they stay visible while picking. */
    public array $openFields = [];

    /** Undo after the sheet closed on a complete recipe: recipe id + its labels before opening. */
    public ?array $undo = null;

    /** Labels of the open recipe as they were when it was opened (for undo). */
    public ?array $snapshot = null;

    /** Bumped for every toast, so a new one restarts its timer. */
    public int $toastKey = 0;

    public function render()
    {
        return $this->view()->title(__('To fill in'));
    }

    public function mount(): void
    {
        if (! in_array($this->filter, [self::FILTER_ALL, ...Recipe::LABEL_FIELDS], true)) {
            $this->filter = self::FILTER_ALL;
        }
    }

    /** Incomplete recipes in name order. */
    #[Computed]
    public function incomplete(): Collection
    {
        return Recipe::query()->incomplete()->with('tags')->orderBy('name')->get();
    }

    /** Rows for the current filter, plus the open recipe even once it is complete. */
    #[Computed]
    public function rows(): Collection
    {
        $rows = $this->incomplete->filter(fn (Recipe $recipe) => $this->matches($recipe));

        $open = $this->openRecipe;

        if ($open && ! $rows->contains(fn (Recipe $recipe) => $recipe->is($open))) {
            $rows = $rows->push($open)->sortBy(fn (Recipe $recipe) => mb_strtolower($recipe->name));
        }

        return $rows->values();
    }

    /** filter key => count of incomplete recipes it shows */
    #[Computed]
    public function filterCounts(): array
    {
        return [
            self::FILTER_ALL => $this->incomplete->count(),
            ...collect(Recipe::LABEL_FIELDS)->mapWithKeys(fn (string $field) => [
                $field => $this->incomplete->filter(fn (Recipe $recipe) => in_array($field, $recipe->missingLabels(), true))->count(),
            ])->all(),
        ];
    }

    #[Computed]
    public function openRecipe(): ?Recipe
    {
        return $this->openId ? Recipe::query()->with('tags')->find($this->openId) : null;
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

    private function matches(Recipe $recipe): bool
    {
        $missing = $recipe->missingLabels();

        return $this->filter === self::FILTER_ALL ? $missing !== [] : in_array($this->filter, $missing, true);
    }

    private function refresh(): void
    {
        unset($this->incomplete, $this->rows, $this->filterCounts, $this->openRecipe);
    }

    public function setFilter(string $filter): void
    {
        if (! in_array($filter, [self::FILTER_ALL, ...Recipe::LABEL_FIELDS], true)) {
            return;
        }

        $this->filter = $filter;
        $this->refresh();

        // Desktop: keep the card when the open recipe fits the filter, otherwise take the first that does.
        if (! $this->sheetOpen && $this->openRecipe && ! $this->matches($this->openRecipe)) {
            $first = $this->incomplete->first(fn (Recipe $recipe) => $this->matches($recipe));
            $first ? $this->select($first->id) : $this->reset('openId', 'openFields', 'snapshot');
            $this->refresh();
        }
    }

    /** Phone: open the sheet. */
    public function open(int $id): void
    {
        $this->select($id);
        $this->sheetOpen = $this->openId !== null;
    }

    /** Desktop: show the recipe's card. */
    public function select(int $id): void
    {
        $recipe = Recipe::query()->active()->with('tags')->find($id);

        $this->openId = $recipe?->id;
        $this->openFields = $recipe?->missingLabels() ?? [];
        $this->snapshot = $recipe ? $this->labelsOf($recipe) : null;
        $this->undo = null;
        unset($this->openRecipe, $this->rows);
    }

    /** Phone: "Done" or tapping outside the sheet. */
    public function closeSheet(): void
    {
        $recipe = $this->openRecipe;

        $this->undo = $recipe && $recipe->missingLabels() === [] && $this->snapshot
            ? ['id' => $recipe->id, 'name' => $recipe->name, 'labels' => $this->snapshot]
            : null;

        if ($this->undo) {
            $this->toastKey++;
        }

        $this->reset('openId', 'openFields', 'snapshot', 'sheetOpen');
        $this->refresh();
    }

    /** Desktop: the next recipe (after the open one, wrapping round) that fits the filter. */
    public function next(): void
    {
        $list = $this->incomplete;
        $index = $list->search(fn (Recipe $recipe) => $recipe->id === $this->openId);
        $ordered = $index === false ? $list : $list->slice($index + 1)->concat($list->slice(0, $index));
        $next = $ordered->first(fn (Recipe $recipe) => $recipe->id !== $this->openId && $this->matches($recipe));

        $next ? $this->select($next->id) : $this->reset('openId', 'openFields', 'snapshot');
        $this->refresh();
    }

    public function undoComplete(): void
    {
        $recipe = $this->undo ? Recipe::query()->find($this->undo['id']) : null;

        if ($recipe) {
            $this->applyLabels($recipe, $this->undo['labels']);
        }

        $this->undo = null;
        $this->refresh();
    }

    public function dismissToast(): void
    {
        $this->undo = null;
    }

    public function toggleTag(int $tagId): void
    {
        $recipe = $this->openRecipe;
        $tag = Tag::query()->find($tagId);

        if (! $recipe || ! $tag || ! ($tag->isMealType() || $tag->isDietType())) {
            return;
        }

        $recipe->tags()->toggle($tag->id);
        $this->refresh();
    }

    public function setRating(string $rating): void
    {
        $recipe = $this->openRecipe;

        if (! $recipe || ! in_array($rating, Recipe::RATINGS, true)) {
            return;
        }

        $recipe->update(['rating' => $recipe->rating === $rating ? null : $rating]);
        $this->refresh();
    }

    public function setPackable(bool $packable): void
    {
        $recipe = $this->openRecipe;

        if (! $recipe) {
            return;
        }

        $recipe->update(['packable' => $recipe->packable === $packable ? null : $packable]);
        $this->refresh();
    }

    /** @return array{tags: list<int>, rating: ?string, packable: ?bool} */
    private function labelsOf(Recipe $recipe): array
    {
        return [
            'tags' => $recipe->tags->filter(fn (Tag $tag) => $tag->isMealType() || $tag->isDietType())->modelKeys(),
            'rating' => $recipe->rating,
            'packable' => $recipe->packable,
        ];
    }

    private function applyLabels(Recipe $recipe, array $labels): void
    {
        $labelTagIds = Tag::query()->whereIn(Tag::CATEGORY_COLUMN, [Tag::MEAL_TYPE, Tag::DIET_TYPE])->pluck('id')->all();
        $otherTags = $recipe->tags()->whereKeyNot($labelTagIds)->pluck('tags.id')->all();

        $recipe->tags()->sync([...$otherTags, ...$labels['tags']]);
        $recipe->update(['rating' => $labels['rating'], 'packable' => $labels['packable']]);
    }

    public function filterLabel(string $filter): string
    {
        return $filter === self::FILTER_ALL ? __('All') : Recipe::labelFieldName($filter);
    }

    /** "Missing: meal, rating" or "All labels set". */
    public function statusLine(Recipe $recipe): string
    {
        $missing = $recipe->missingLabels();

        return $missing === []
            ? __('All labels set')
            : __('Missing: :fields', ['fields' => collect($missing)->map(fn (string $field) => Recipe::labelFieldShort($field))->implode(', ')]);
    }

    public function nextLabel(): string
    {
        $others = $this->incomplete->filter(fn (Recipe $recipe) => $recipe->id !== $this->openId && $this->matches($recipe))->count();

        return $others > 0 ? __('Next recipe') : __('Done');
    }
};
?>

@php
    $counts = $this->filterCounts;
    $total = $counts['all'];
    $rows = $this->rows;
    $open = $this->openRecipe;
    $hasAny = $total > 0 || $open;
    $firstId = $rows->first()?->id;
@endphp

<div class="space-y-3 lg:space-y-4"
    x-init="if (window.isDesktop() && ! $wire.openId && {{ $firstId ?? 'null' }}) $wire.select({{ $firstId ?? 'null' }})">
    <x-ui.sub-header :title="__('To fill in')" :back-href="route('recipes.index')" :back-label="__('Recipes')">
        @if ($hasAny)
        <p class="text-[13px] lg:text-[14px] text-ink-soft">{{ trans_choice(':count recipe|:count recipes', $total) }}</p>
        @endif
    </x-ui.sub-header>

    @if ($hasAny)
    <div class="-mx-4 px-4 flex gap-1.5 overflow-x-auto no-scrollbar lg:mx-0 lg:px-0 lg:flex-wrap lg:overflow-visible">
        @foreach ($counts as $key => $count)
        @php $on = $filter === $key; @endphp
        <button type="button" wire:key="filter-{{ $key }}" wire:click="setFilter('{{ $key }}')" aria-pressed="{{ $on ? 'true' : 'false' }}"
            @class(['flex h-9 shrink-0 items-center gap-1.5 rounded-full border pl-3.5 pr-3 text-[13px] font-medium whitespace-nowrap', 'border-ink bg-ink text-on-ink' => $on, 'border-field bg-surface text-ink' => ! $on])>
            {{ $this->filterLabel($key) }}
            <span @class(['flex h-5 min-w-[18px] items-center justify-center rounded-full px-1.5 text-[12px] font-semibold tabular-nums text-ink', 'bg-cream' => $on, 'bg-sand' => ! $on])>{{ $count }}</span>
        </button>
        @endforeach
    </div>
    @endif

    @if ($rows->isNotEmpty())
    <div class="lg:flex lg:items-start lg:gap-5">
        <div class="space-y-2.5 lg:w-[440px] lg:shrink-0">
            <div class="rounded-[20px] border border-line bg-surface overflow-hidden divide-y divide-divider">
                @foreach ($rows as $recipe)
                @php $isOpen = $openId === $recipe->id; @endphp
                <button type="button" wire:key="row-{{ $recipe->id }}"
                    x-on:click="window.isDesktop() ? $wire.select({{ $recipe->id }}) : $wire.open({{ $recipe->id }})"
                    @if ($isOpen) aria-current="true" @endif
                    @class(['flex w-full items-center gap-2.5 px-3.5 py-3 text-left border-l-3', 'lg:bg-selected lg:border-l-terracotta border-l-transparent' => $isOpen, 'border-l-transparent' => ! $isOpen])>
                    <span class="flex-1 min-w-0 flex flex-col gap-1.5">
                        <span class="font-fraunces text-[17px] lg:text-[16px] font-semibold leading-tight text-ink">{{ $recipe->name }}</span>
                        @include('pages.recipe.partials.label-tags', ['recipe' => $recipe])
                    </span>
                    <x-ui.chevron-right class="size-4 lg:hidden" />
                </button>
                @endforeach
            </div>
            <p class="px-1 text-[12px] leading-normal text-muted">
                <span class="lg:hidden">{{ __('Fields with a plus are empty. Tap a recipe to fill them in.') }}</span>
                <span class="hidden lg:inline">{{ __('Fields with a plus are empty. A recipe leaves the list once complete and you move on.') }}</span>
            </p>
        </div>

        {{-- Desktop: card of the selected recipe. --}}
        @if ($open && ! $sheetOpen)
        <article class="hidden lg:flex flex-1 min-w-0 max-w-[640px] flex-col gap-4 rounded-[20px] border border-line bg-surface px-[22px] pt-5 pb-[18px]">
            <div class="flex flex-wrap items-start gap-3">
                <div class="flex-1 min-w-[240px] space-y-1">
                    <h2 class="font-fraunces text-[24px] font-semibold leading-tight tracking-[-0.01em] text-ink">{{ $open->name }}</h2>
                    <p class="text-[13px] text-ink-soft">{{ $this->statusLine($open) }}</p>
                </div>
                <a href="{{ route('recipes.edit', $open) }}" wire:navigate class="inline-flex h-10 items-center rounded-full border border-field bg-surface px-3.5 text-[14px] font-semibold text-ink whitespace-nowrap">{{ __('Full edit') }}</a>
            </div>
            @include('pages.recipe.partials.label-pickers', ['recipe' => $open, 'fields' => $openFields])
            <div class="flex justify-end border-t border-divider pt-3">
                <button type="button" wire:click="next" class="inline-flex h-11 items-center gap-1.5 rounded-full bg-terracotta px-5 text-[14px] font-semibold text-white hover:bg-terracotta-dark">
                    {{ $this->nextLabel() }}
                    <x-icon.chevron-right class="size-3.5" />
                </button>
            </div>
        </article>
        @endif
    </div>
    @elseif ($total > 0)
    <p class="rounded-[20px] border-[1.5px] border-dashed border-field px-3.5 py-5 text-center text-[14px] leading-normal text-ink-soft lg:max-w-[440px]">{{ __('Nothing is missing in this category.') }}</p>
    @else
    <div class="mt-6 lg:mt-0 flex flex-col items-center lg:items-start gap-2.5 rounded-[20px] border border-line bg-surface px-5 py-7 lg:px-7 lg:py-8 text-center lg:text-left lg:max-w-[520px]">
        <span class="flex size-12 items-center justify-center rounded-full bg-sage-light text-forest"><x-icon.check class="size-6" /></span>
        <h2 class="font-fraunces text-[20px] lg:text-[22px] font-semibold text-ink">{{ __('All labelled') }}</h2>
        <p class="text-[14px] leading-normal text-ink-soft">{{ __('Every recipe has a meal, a main ingredient, a rating and whether it can be packed. New recipes with gaps will show up here.') }}</p>
        <a href="{{ route('recipes.index') }}" wire:navigate class="mt-1 inline-flex h-11 items-center rounded-full border border-field bg-surface px-[18px] text-[14px] font-semibold text-ink">{{ __('Back to recipes') }}</a>
    </div>
    @endif

    {{-- Phone: bottom sheet with the fields that were missing. --}}
    @if ($sheetOpen && $open)
    <x-ui.sheet :title="$open->name" close="closeSheet">
        <div class="space-y-3.5">
            <p class="-mt-1 text-[13px] text-ink-soft">{{ $this->statusLine($open) }}</p>
            @include('pages.recipe.partials.label-pickers', ['recipe' => $open, 'fields' => $openFields])
        </div>
        <x-slot:footer>
            <div class="flex items-center gap-3">
                <a href="{{ route('recipes.edit', $open) }}" wire:navigate class="flex-1 inline-flex min-h-11 items-center text-[14px] font-semibold text-ink-soft">{{ __('Full edit') }}</a>
                <button type="button" wire:click="closeSheet" class="h-11 rounded-full bg-terracotta px-[22px] text-[14px] font-semibold text-white">{{ __('Done') }}</button>
            </div>
        </x-slot:footer>
    </x-ui.sheet>
    @endif

    @if ($undo)
    <div wire:key="toast-{{ $toastKey }}" role="status"
        x-data x-init="setTimeout(() => $wire.dismissToast(), 5000)"
        class="fixed inset-x-4 z-50 mx-auto flex max-w-xl items-center gap-2.5 rounded-2xl bg-ink py-1.5 pl-4 pr-1.5 min-h-[52px] text-[14px] leading-snug text-cream shadow-[0_6px_20px_rgba(31,27,22,0.25)] bottom-[calc(1.25rem+env(safe-area-inset-bottom))]">
        <span class="flex-1 min-w-0">{{ __('“:name” has all labels.', ['name' => $undo['name']]) }}</span>
        <button type="button" wire:click="undoComplete" class="h-10 shrink-0 rounded-xl px-3.5 text-[14px] font-semibold text-[#F4C7B9] dark:text-terracotta">{{ __('Undo') }}</button>
    </div>
    @endif
</div>
