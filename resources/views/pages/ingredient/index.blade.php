<?php

use App\Actions\Ingredients\MergeIngredients;
use App\Models\Ingredient;
use App\Support\Ingredients\IngredientMatcher;
use App\Support\Ingredients\IngredientName;
use App\Support\ShoppingList\Quantity;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::app')] class extends Component {
    /** "all", "advance" or a category code. */
    public const FILTER_ALL = 'all';

    public const FILTER_ADVANCE = 'advance';

    public const FILTER_SKIPPED = 'skipped';

    public const FILTER_DUPLICATES = 'duplicates';

    /** Merge sheet: the ingredient that disappears and the one it becomes. */
    public ?int $mergeSourceId = null;

    public ?int $mergeTargetId = null;

    /** Whose settings stay after merging: target | source. */
    public string $mergeKeep = MergeIngredients::KEEP_TARGET;

    public string $mergeSearch = '';

    /** "Did you mean: X?" under a new name was answered with "add as new". */
    public bool $similarDismissed = false;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'filter')]
    public string $filter = self::FILTER_ALL;

    /** null = the sheet creates a new ingredient. */
    public ?int $editingId = null;

    public string $name = '';

    public string $purchaseTiming = Ingredient::PURCHASE_TIMING_FRESH;

    public string $category = Ingredient::CATEGORY_UNCATEGORIZED;

    public bool $skipShopping = false;

    /** unit => grams per 1 unit, as typed */
    public array $unitWeights = [];

    public bool $confirmingDelete = false;

    /** Desktop: the edit form is open in the panel next to the table. */
    public bool $panelOpen = false;

    public function closePanel(): void
    {
        $this->resetForm();
        $this->panelOpen = false;
    }

    public function render()
    {
        return $this->view()->title(__('Ingredients'));
    }

    #[Computed]
    public function ingredients(): Collection
    {
        $search = trim($this->search);

        return Ingredient::query()
            ->when($search !== '', fn ($query) => $query->where(Ingredient::NAME_COLUMN, 'like', "%{$search}%"))
            ->when($this->filter === self::FILTER_ADVANCE, fn ($query) => $query->where(Ingredient::PURCHASE_TIMING_COLUMN, Ingredient::PURCHASE_TIMING_ADVANCE))
            ->when($this->filter === self::FILTER_SKIPPED, fn ($query) => $query->where(Ingredient::SKIP_SHOPPING_COLUMN, true))
            ->when(in_array($this->filter, Ingredient::CATEGORIES, true), fn ($query) => $query->where(Ingredient::CATEGORY_COLUMN, $this->filter))
            ->orderBy(Ingredient::NAME_COLUMN)
            ->get();
    }

    /** Filter chips: all, "for the whole week", then aisles that have ingredients. */
    #[Computed]
    public function filters(): array
    {
        $used = Ingredient::query()->distinct()->pluck(Ingredient::CATEGORY_COLUMN)->all();

        $duplicates = $this->duplicatePairs->count();

        return [
            self::FILTER_ALL => __('All'),
            ...($duplicates > 0 ? [self::FILTER_DUPLICATES => __('Possible duplicates (:count)', ['count' => $duplicates])] : []),
            self::FILTER_ADVANCE => __('For the whole week'),
            ...(Ingredient::query()->where(Ingredient::SKIP_SHOPPING_COLUMN, true)->exists()
                ? [self::FILTER_SKIPPED => __('Not on the shopping list')]
                : []),
            ...collect(Ingredient::CATEGORIES)
                ->filter(fn (string $category) => in_array($category, $used, true))
                ->mapWithKeys(fn (string $category) => [$category => Ingredient::categoryLabel($category)])
                ->all(),
        ];
    }

    #[Computed]
    public function editingRecipeCount(): int
    {
        return $this->editingId
            ? Ingredient::query()->find($this->editingId)?->recipes()->count() ?? 0
            : 0;
    }

    /** recipe count per ingredient id */
    #[Computed]
    public function recipeCounts(): array
    {
        return \App\Models\RecipeIngredientAssignment::query()
            ->selectRaw('ingredient_id, COUNT(DISTINCT recipe_id) as aggregate')
            ->groupBy('ingredient_id')
            ->pluck('aggregate', 'ingredient_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /** Pairs of similar names, the more used ingredient first. */
    #[Computed]
    public function duplicatePairs(): Collection
    {
        return app(IngredientMatcher::class)->duplicatePairs()
            ->map(fn (array $pair) => collect($pair)->sortByDesc(fn (Ingredient $i) => $this->recipeCounts[$i->id] ?? 0)->values()->all());
    }

    public function recipeCountLabel(Ingredient $ingredient): string
    {
        return trans_choice(':count recipe|:count recipes', $this->recipeCounts[$ingredient->id] ?? 0);
    }

    /** Existing ingredient similar to the name typed in the form ("Did you mean: Banan?"). */
    #[Computed]
    public function similarExisting(): ?Ingredient
    {
        if ($this->similarDismissed || trim($this->name) === '') {
            return null;
        }

        $matcher = app(IngredientMatcher::class);

        if ($matcher->same($this->name, $this->editingId)) {
            return null;
        }

        $row = $matcher->suggest($this->name, 3, $this->editingId)->firstWhere('similar', true);

        return $row ? Ingredient::query()->find($row['ingredient']->id) : null;
    }

    public function updatedName(): void
    {
        $this->similarDismissed = false;
    }

    public function openMerge(int $sourceId, ?int $targetId = null): void
    {
        $this->panelOpen = false;
        $this->dispatch('modal-close', name: 'ingredient-modal');
        $this->mergeSourceId = $sourceId;
        $this->mergeTargetId = $targetId !== $sourceId ? $targetId : null;
        $this->mergeKeep = MergeIngredients::KEEP_TARGET;
        $this->mergeSearch = '';
        unset($this->mergeSource, $this->mergeTarget);
    }

    public function closeMerge(): void
    {
        $this->reset('mergeSourceId', 'mergeTargetId', 'mergeSearch');
    }

    public function chooseMergeTarget(int $id): void
    {
        $this->mergeTargetId = $id !== $this->mergeSourceId ? $id : null;
        unset($this->mergeTarget);
    }

    public function changeMergeTarget(): void
    {
        $this->mergeTargetId = null;
    }

    #[Computed]
    public function mergeSource(): ?Ingredient
    {
        return $this->mergeSourceId ? Ingredient::query()->find($this->mergeSourceId) : null;
    }

    #[Computed]
    public function mergeTarget(): ?Ingredient
    {
        return $this->mergeTargetId ? Ingredient::query()->find($this->mergeTargetId) : null;
    }

    /**
     * Step 1 of merging: similar names first (tagged), then whatever the
     * search finds.
     *
     * @return Collection<int, array{ingredient: Ingredient, similar: bool}>
     */
    #[Computed]
    public function mergeCandidates(): Collection
    {
        $source = $this->mergeSource;

        if (! $source) {
            return collect();
        }

        $similar = Ingredient::query()->whereKeyNot($source->id)->orderBy('name')->get()
            ->filter(fn (Ingredient $i) => IngredientName::similar($source->name, $i->name))
            ->map(fn (Ingredient $i) => ['ingredient' => $i, 'similar' => true]);

        $search = trim($this->mergeSearch);
        $found = $search === '' ? collect() : Ingredient::query()->whereKeyNot($source->id)
            ->where('name', 'like', '%' . $search . '%')->orderBy('name')->limit(20)->get()
            ->reject(fn (Ingredient $i) => $similar->contains(fn ($row) => $row['ingredient']->is($i)))
            ->map(fn (Ingredient $i) => ['ingredient' => $i, 'similar' => false]);

        return $similar->concat($found)->values();
    }

    public function merge(MergeIngredients $merge): void
    {
        $source = $this->mergeSource;
        $target = $this->mergeTarget;

        if (! $source || ! $target || $source->is($target)) {
            return;
        }

        $merge($source, $target, $this->mergeKeep === MergeIngredients::KEEP_SOURCE ? MergeIngredients::KEEP_SOURCE : MergeIngredients::KEEP_TARGET);

        $this->closeMerge();
        unset($this->ingredients, $this->filters, $this->duplicatePairs, $this->recipeCounts);

        if ($this->filter === self::FILTER_DUPLICATES && $this->duplicatePairs->isEmpty()) {
            $this->filter = self::FILTER_ALL;
        }
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
    }

    public function openNew(): void
    {
        $this->resetForm();
        $this->panelOpen = true;
        $this->dispatch('ingredient-form-opened');
    }

    public function openEdit(int $id): void
    {
        $ingredient = Ingredient::query()->findOrFail($id);

        $this->resetForm();
        $this->editingId = $ingredient->id;
        $this->name = $ingredient->name;
        $this->purchaseTiming = in_array($ingredient->purchase_timing, Ingredient::PURCHASE_TIMINGS, true)
            ? $ingredient->purchase_timing
            : Ingredient::PURCHASE_TIMING_FRESH;
        $this->category = in_array($ingredient->category, Ingredient::CATEGORIES, true)
            ? $ingredient->category
            : Ingredient::CATEGORY_UNCATEGORIZED;
        $this->skipShopping = (bool) $ingredient->skip_shopping;
        $this->unitWeights = collect(Ingredient::WEIGHABLE_UNITS)
            ->mapWithKeys(fn (string $unit) => [$unit => isset($ingredient->unit_weights[$unit]) ? Quantity::number((float) $ingredient->unit_weights[$unit]) : ''])
            ->all();

        $this->panelOpen = true;
        $this->dispatch('ingredient-form-opened');
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'name', 'purchaseTiming', 'category', 'skipShopping', 'confirmingDelete', 'similarDismissed');
        $this->unitWeights = array_fill_keys(Ingredient::WEIGHABLE_UNITS, '');
        unset($this->editingRecipeCount);
        $this->resetErrorBag();
    }

    public function save(): void
    {
        $this->name = Str::ucfirst(trim($this->name));
        $this->unitWeights = array_map(
            fn ($grams) => trim(str_replace(',', '.', (string) $grams)),
            $this->unitWeights,
        );

        $this->validate([
            'name' => ['required', 'string', 'max:255', function (string $attribute, string $value, \Closure $fail) {
                if (\App\Support\Ingredients\IngredientName::fold($value) !== ''
                    && Ingredient::query()->where('name_key', \App\Support\Ingredients\IngredientName::fold($value))->whereKeyNot($this->editingId)->exists()) {
                    $fail(__('This ingredient already exists — pick it from the list.'));
                }
            }],
            'purchaseTiming' => ['required', Rule::in(Ingredient::PURCHASE_TIMINGS)],
            'category' => ['required', Rule::in(Ingredient::CATEGORIES)],
            'unitWeights.*' => ['nullable', 'numeric', 'gt:0', 'max:100000'],
        ], attributes: [
            'name' => __('name'),
            'unitWeights.*' => __('unit weight'),
        ]);

        $unitWeights = collect($this->unitWeights)
            ->only(Ingredient::WEIGHABLE_UNITS)
            ->filter(fn (string $grams) => $grams !== '')
            ->map(fn (string $grams) => (float) $grams)
            ->all();

        $attributes = [
            Ingredient::NAME_COLUMN => $this->name,
            Ingredient::PURCHASE_TIMING_COLUMN => $this->purchaseTiming,
            Ingredient::CATEGORY_COLUMN => $this->category,
            Ingredient::SKIP_SHOPPING_COLUMN => $this->skipShopping,
            Ingredient::UNIT_WEIGHTS_COLUMN => $unitWeights ?: null,
        ];

        $this->editingId
            ? Ingredient::query()->findOrFail($this->editingId)->update($attributes)
            : Ingredient::query()->create($attributes);

        $this->resetForm();
        $this->panelOpen = false;
        unset($this->ingredients, $this->filters, $this->duplicatePairs, $this->recipeCounts);
        $this->dispatch('modal-close', name: 'ingredient-modal');
    }

    public function delete(): void
    {
        Ingredient::query()->whereKey($this->editingId)->delete();

        $this->resetForm();
        $this->panelOpen = false;
        unset($this->ingredients, $this->filters, $this->duplicatePairs, $this->recipeCounts);
        $this->dispatch('modal-close', name: 'ingredient-modal');
    }
};
?>

@php
    $label = 'block text-[11px] font-semibold uppercase tracking-[0.09em] text-muted mb-1.5';
    $filledWeights = collect($unitWeights)->filter(fn ($grams) => trim((string) $grams) !== '')->count();
@endphp

<div class="space-y-3" x-on:ingredient-form-opened.window="if (! window.isDesktop()) $dispatch('modal-show', { name: 'ingredient-modal' })">
    <div class="flex items-center gap-3 lg:gap-6">
        <h1 data-large-title class="font-fraunces text-[28px] lg:text-[32px] font-semibold leading-none tracking-[-0.01em] text-ink">{{ __('Ingredients') }}</h1>
        <div class="hidden lg:block flex-1 max-w-[420px]">
            <x-ui.search-input id="ingredient-search-desktop" wire:model.live.debounce.250ms="search" :placeholder="__('Search ingredient')" />
        </div>
        <div class="ml-auto">
            <x-ui.pill wire:click="openNew"><span class="lg:hidden">{{ __('New') }}</span><span class="hidden lg:inline">{{ __('New ingredient') }}</span></x-ui.pill>
        </div>
    </div>

    <div class="lg:hidden">
        <x-ui.search-input id="ingredient-search" wire:model.live.debounce.250ms="search" :placeholder="__('Search ingredient')" />
    </div>

    <div class="-mx-4 px-4 flex gap-2 overflow-x-auto no-scrollbar lg:mx-0 lg:px-0 lg:flex-wrap lg:overflow-visible">
        @foreach ($this->filters as $key => $filterLabel)
        <x-ui.chip wire:key="filter-{{ $key }}" :active="$filter === $key" wire:click="setFilter('{{ $key }}')" @class(['border-gold! bg-gold-light! text-gold-dark!' => $key === 'duplicates' && $filter !== $key])>{{ $filterLabel }}</x-ui.chip>
        @endforeach
    </div>

    <div @class(['lg:grid lg:items-start lg:gap-6 lg:pt-1', 'lg:grid-cols-[minmax(0,1fr)_420px]' => $panelOpen])>
    <div class="min-w-0">
    @if ($filter === 'duplicates')
    <div class="space-y-3 lg:max-w-[720px]">
        <p class="text-[14px] leading-normal text-muted">{{ __('Similar names make separate rows on the shopping list. Merge them so the amounts add up.') }}</p>
        @forelse ($this->duplicatePairs as [$keep, $drop])
        <div wire:key="pair-{{ $keep->id }}-{{ $drop->id }}" class="flex items-center gap-3 rounded-[20px] border border-line bg-surface px-4 py-3">
            <div class="flex-1 min-w-0">
                <p class="text-[16px] text-ink">{{ $keep->name }} <span class="text-muted">{{ __('and') }}</span> {{ $drop->name }}</p>
                <p class="text-[13px] text-muted">{{ $keep->name }}: {{ $this->recipeCountLabel($keep) }} · {{ $drop->name }}: {{ $this->recipeCountLabel($drop) }}</p>
            </div>
            <button type="button" wire:click="openMerge({{ $drop->id }}, {{ $keep->id }})" class="inline-flex h-11 shrink-0 items-center gap-1.5 rounded-full border border-field bg-surface px-4 text-[14px] font-semibold text-ink">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3v6a4 4 0 0 0 4 4h8"/><path d="m15 9 4 4-4 4"/><path d="M6 21v-4"/></svg>
                {{ __('Merge') }}
            </button>
        </div>
        @empty
        <p class="rounded-[20px] border border-dashed border-field px-5 py-8 text-center text-[15px] text-muted">{{ __('No similar names left.') }}</p>
        @endforelse
    </div>
    @elseif ($this->ingredients->isEmpty())
    <div class="rounded-[20px] border border-dashed border-field px-5 py-10 text-center text-[15px] text-muted">{{ __('No ingredients found.') }}</div>
    @else
    <x-ui.card class="divide-y divide-divider lg:hidden">
        @foreach ($this->ingredients as $ingredient)
        <button type="button" wire:key="ingredient-{{ $ingredient->id }}" wire:click="openEdit({{ $ingredient->id }})" class="flex w-full items-center gap-3 px-4 py-2.5 min-h-14 text-left">
            <span class="flex-1 min-w-0">
                <span class="block text-[15px] text-ink truncate">{{ $ingredient->name }}</span>
                <span class="flex flex-wrap items-center gap-1.5 text-[12.5px] text-muted">
                    {{ Ingredient::categoryLabel($ingredient->category ?? Ingredient::CATEGORY_UNCATEGORIZED) }}
                    @if ($ingredient->purchase_timing === Ingredient::PURCHASE_TIMING_ADVANCE)
                    <span class="rounded-full bg-sage-light px-1.5 py-px text-[11px] font-semibold text-forest">{{ __('for the whole week') }}</span>
                    @endif
                    @if ($ingredient->skip_shopping)
                    <span class="rounded-full bg-sand px-1.5 py-px text-[11px] font-semibold text-ink-soft">{{ __('not on the list') }}</span>
                    @endif
                </span>
            </span>
            <x-ui.chevron-right class="size-4" />
        </button>
        @endforeach
    </x-ui.card>

    {{-- Desktop: a table; the selected row opens in the panel on the right. --}}
    <div class="hidden lg:block rounded-[20px] border border-line bg-surface overflow-hidden">
        <table class="w-full text-left">
            <thead>
                <tr class="border-b border-line text-[13px] font-semibold text-ink-soft">
                    <th scope="col" class="px-4 py-3 font-semibold">{{ __('Name') }}</th>
                    <th scope="col" class="px-4 py-3 font-semibold">{{ __('Store section') }}</th>
                    <th scope="col" class="px-4 py-3 font-semibold whitespace-nowrap">{{ __('When to buy') }}</th>
                    <th scope="col" class="w-10"><span class="sr-only">{{ __('Edit') }}</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-divider">
                @foreach ($this->ingredients as $ingredient)
                @php $isSelected = $panelOpen && $editingId === $ingredient->id; @endphp
                <tr wire:key="ingredient-row-{{ $ingredient->id }}" wire:click="openEdit({{ $ingredient->id }})" @class(['cursor-pointer hover:bg-field-bg', 'relative bg-selected [&>td:first-child]:shadow-[inset_3px_0_0_var(--color-terracotta)]' => $isSelected])>
                    <td class="px-4 py-3 text-[15px] text-ink">
                        <button type="button" class="text-left" aria-label="{{ __('Edit') }}: {{ $ingredient->name }}">{{ $ingredient->name }}</button>
                    </td>
                    <td class="px-4 py-3 text-[14px] text-ink-soft whitespace-nowrap">{{ Ingredient::categoryLabel($ingredient->category ?? Ingredient::CATEGORY_UNCATEGORIZED) }}</td>
                    <td class="px-4 py-3 text-[13px]">
                        @if ($ingredient->skip_shopping)
                        <span class="whitespace-nowrap rounded-full bg-sand px-2 py-0.5 font-semibold text-ink-soft">{{ __('not on the list') }}</span>
                        @elseif ($ingredient->purchase_timing === Ingredient::PURCHASE_TIMING_ADVANCE)
                        <span class="whitespace-nowrap rounded-full bg-sage-light px-2 py-0.5 font-semibold text-forest">{{ __('for the whole week') }}</span>
                        @else
                        <span class="text-muted">{{ __('fresh') }}</span>
                        @endif
                    </td>
                    <td class="pr-4"><x-ui.chevron-right class="size-4" /></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
    </div>

    <div @class(['hidden', 'lg:block' => $panelOpen])>
        @if ($panelOpen)
        <div class="sticky top-6 rounded-[20px] border border-line bg-surface p-5" x-data x-on:keydown.escape.window="$wire.closePanel()">
            @include('pages.ingredient.partials.form', ['prefix' => 'd-', 'desktop' => true])
        </div>
        @endif
    </div>
    </div>

    @if ($mergeSourceId && ($source = $this->mergeSource))
    <x-ui.sheet :title="__('Merge ingredient')" close="closeMerge">
        <div class="space-y-4">
            <p class="text-[15px] leading-normal text-ink-soft">{!! __('The ingredient :name will disappear. Everything that uses it moves to the chosen ingredient.', ['name' => '<strong class="text-ink">' . e($source->name) . '</strong>']) !!}</p>

            @if (! ($target = $this->mergeTarget))
            <x-ui.search-input id="merge-search" wire:model.live.debounce.250ms="mergeSearch" :placeholder="__('Search ingredient')" bg="field" />
            <div role="listbox" aria-label="{{ __('Merge into') }}" class="flex flex-col">
                @forelse ($this->mergeCandidates as $row)
                <button type="button" role="option" wire:key="merge-candidate-{{ $row['ingredient']->id }}" wire:click="chooseMergeTarget({{ $row['ingredient']->id }})" class="flex items-center gap-2 min-h-[52px] border-b border-divider px-1 text-left">
                    <span class="flex-1 min-w-0">
                        <span class="block text-[15px] text-ink">{{ $row['ingredient']->name }}</span>
                        <span class="block text-[12px] text-muted">{{ Ingredient::categoryLabel($row['ingredient']->category ?? Ingredient::CATEGORY_UNCATEGORIZED) }} · {{ $this->recipeCountLabel($row['ingredient']) }}</span>
                    </span>
                    @if ($row['similar'])
                    <span class="shrink-0 rounded-full bg-gold-light px-2 py-0.5 text-[11px] font-semibold text-gold-dark">{{ __('similar name') }}</span>
                    @endif
                    <x-ui.chevron-right class="size-4" />
                </button>
                @empty
                <p class="px-1 py-3 text-[14px] text-muted">{{ __('Type a name to find the ingredient to merge with.') }}</p>
                @endforelse
            </div>
            @else
            @php $moves = app(MergeIngredients::class)->preview($source); @endphp
            <div class="flex items-center gap-3 rounded-[14px] bg-cream px-3.5 py-3">
                <p class="flex-1 text-[15px] leading-snug text-ink">
                    {{ trans_choice(':count recipe|:count recipes', $moves['recipes']) }} {{ __('and') }} {{ trans_choice(':count item on the list|:count items on the list', $moves['shopping']) }}
                    {!! __('will move to: :name', ['name' => '<strong>' . e($target->name) . '</strong>']) !!}
                </p>
                <button type="button" wire:click="changeMergeTarget" class="h-10 shrink-0 px-2 text-[14px] font-semibold text-terracotta">{{ __('Change') }}</button>
            </div>

            <div>
                <x-ui.section-label class="mb-2">{{ __('Settings after merging') }}</x-ui.section-label>
                <div role="radiogroup" class="flex flex-col gap-2">
                    @foreach ([MergeIngredients::KEEP_TARGET => $target, MergeIngredients::KEEP_SOURCE => $source] as $keep => $option)
                    @php $on = $mergeKeep === $keep; @endphp
                    <button type="button" role="radio" aria-checked="{{ $on ? 'true' : 'false' }}" wire:click="$set('mergeKeep', '{{ $keep }}')"
                        @class(['flex items-center gap-3 min-h-[60px] px-3 py-2 rounded-[14px] border-[1.5px] text-left', 'border-ink bg-field-bg' => $on, 'border-line bg-surface' => ! $on])>
                        <span @class(['flex size-5 shrink-0 items-center justify-center rounded-full border-2', 'border-ink' => $on, 'border-faint' => ! $on])>
                            @if ($on)<span class="size-2.5 rounded-full bg-ink"></span>@endif
                        </span>
                        <span>
                            <span class="block text-[15px] font-semibold text-ink">{{ __('As in “:name”', ['name' => $option->name]) }}</span>
                            <span class="block text-[13px] text-muted">{{ Ingredient::categoryLabel($option->category ?? Ingredient::CATEGORY_UNCATEGORIZED) }} · {{ $option->purchase_timing === Ingredient::PURCHASE_TIMING_ADVANCE ? __('for the whole week') : __('fresh') }}</span>
                        </span>
                    </button>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        @if ($this->mergeTarget)
        <x-slot:footer>
            <div x-data="{ confirming: false }" class="min-h-12 flex items-center gap-3">
                <template x-if="! confirming">
                    <div class="flex w-full items-center gap-3">
                        <button type="button" wire:click="changeMergeTarget" class="h-12 px-2 text-[15px] font-semibold text-ink">{{ __('Back') }}</button>
                        <button type="button" x-on:click="confirming = true" class="ml-auto h-12 rounded-[14px] bg-terracotta px-6 text-[15px] font-semibold text-white">{{ __('Merge') }}</button>
                    </div>
                </template>
                <template x-if="confirming">
                    <div class="flex w-full items-center gap-2">
                        <span class="flex-1 min-w-0 text-[14px] leading-snug text-ink">{{ __('Merge “:source” with “:target”? This cannot be undone.', ['source' => $source->name, 'target' => $this->mergeTarget->name]) }}</span>
                        <button type="button" x-on:click="confirming = false" class="h-11 px-3 text-[14px] font-semibold text-ink">{{ __('No') }}</button>
                        <button type="button" wire:click="merge" class="h-11 rounded-[14px] bg-terracotta px-4 text-[14px] font-semibold text-white">{{ __('Merge') }}</button>
                    </div>
                </template>
            </div>
        </x-slot:footer>
        @endif
    </x-ui.sheet>
    @endif

    <flux:modal name="ingredient-modal" variant="flyout" position="bottom" :closable="false" class="rounded-t-[24px] bg-surface! max-h-[90dvh] overflow-y-auto">
        @include('pages.ingredient.partials.form', ['prefix' => 'm-', 'desktop' => false])
    </flux:modal>
</div>
