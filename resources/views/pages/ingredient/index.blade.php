<?php

use App\Models\Ingredient;
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

        return [
            self::FILTER_ALL => __('All'),
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

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
    }

    public function openNew(): void
    {
        $this->resetForm();
        $this->dispatch('modal-show', name: 'ingredient-modal');
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

        $this->dispatch('modal-show', name: 'ingredient-modal');
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'name', 'purchaseTiming', 'category', 'skipShopping', 'confirmingDelete');
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
            'name' => ['required', 'string', 'max:255', Rule::unique('ingredients', 'name')->ignore($this->editingId)],
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
        unset($this->ingredients, $this->filters);
        $this->dispatch('modal-close', name: 'ingredient-modal');
    }

    public function delete(): void
    {
        Ingredient::query()->whereKey($this->editingId)->delete();

        $this->resetForm();
        unset($this->ingredients, $this->filters);
        $this->dispatch('modal-close', name: 'ingredient-modal');
    }
};
?>

@php
    $label = 'block text-[11px] font-semibold uppercase tracking-[0.09em] text-muted mb-1.5';
    $filledWeights = collect($unitWeights)->filter(fn ($grams) => trim((string) $grams) !== '')->count();
@endphp

<div class="space-y-3">
    <x-ui.page-header :title="__('Ingredients')">
        <x-ui.pill wire:click="openNew">{{ __('New') }}</x-ui.pill>
    </x-ui.page-header>

    <x-ui.search-input id="ingredient-search" wire:model.live.debounce.250ms="search" :placeholder="__('Search ingredient')" />

    <div class="-mx-4 px-4 flex gap-2 overflow-x-auto no-scrollbar">
        @foreach ($this->filters as $key => $filterLabel)
        <x-ui.chip wire:key="filter-{{ $key }}" :active="$filter === $key" wire:click="setFilter('{{ $key }}')">{{ $filterLabel }}</x-ui.chip>
        @endforeach
    </div>

    @if ($this->ingredients->isEmpty())
    <div class="rounded-[20px] border border-dashed border-field px-5 py-10 text-center text-[15px] text-muted">{{ __('No ingredients found.') }}</div>
    @else
    <x-ui.card class="divide-y divide-divider">
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
    @endif

    <flux:modal name="ingredient-modal" variant="flyout" position="bottom" :closable="false" class="rounded-t-[24px] bg-white! max-h-[90dvh] overflow-y-auto">
        <form wire:submit="save" class="space-y-4">
            <div class="flex items-center justify-between gap-2">
                <h2 class="font-fraunces text-[20px] font-semibold text-ink">{{ $editingId ? __('Edit ingredient') : __('New ingredient') }}</h2>
                <flux:modal.close>
                    <button type="button" class="-mr-2.5 flex size-11 items-center justify-center text-ink" aria-label="{{ __('Close') }}"><x-icon.x class="size-[18px]" /></button>
                </flux:modal.close>
            </div>

            <div>
                <label for="ingredient-name" class="{{ $label }}">{{ __('Name') }}</label>
                <x-ui.text-input id="ingredient-name" wire:model.live="name" class="w-full bg-field-bg" maxlength="255" />
                <x-ui.field-error name="name" />
                @if ($editingId && $this->editingRecipeCount)
                <p class="mt-1 text-[12.5px] text-muted">{{ trans_choice('Used in :count recipe|Used in :count recipes', $this->editingRecipeCount) }}</p>
                @endif
            </div>

            <div>
                <span class="{{ $label }}">{{ __('When to buy') }}</span>
                <div role="radiogroup" class="flex rounded-[14px] bg-sand p-1 gap-1">
                    @foreach (Ingredient::PURCHASE_TIMINGS as $timing)
                    @php $on = $purchaseTiming === $timing; @endphp
                    <button type="button" role="radio" aria-checked="{{ $on ? 'true' : 'false' }}" wire:click="$set('purchaseTiming', '{{ $timing }}')"
                        @class(['flex-1 min-w-0 rounded-[10px] px-2 py-1.5 min-h-11 text-[13px] leading-tight', 'bg-white text-ink font-semibold shadow-[0_1px_2px_rgba(31,27,22,0.12)]' => $on, 'text-muted font-medium' => ! $on])>
                        {{ Ingredient::purchaseTimingLabel($timing) }}
                    </button>
                    @endforeach
                </div>
            </div>

            <div>
                <label for="ingredient-category" class="{{ $label }}">{{ __('Store section') }}</label>
                <select id="ingredient-category" wire:model="category" class="w-full h-11 border border-field bg-field-bg rounded-xl px-3 text-base text-ink">
                    @foreach (Ingredient::CATEGORIES as $categoryOption)
                    <option value="{{ $categoryOption }}">{{ Ingredient::categoryLabel($categoryOption) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="rounded-2xl border border-line px-3.5 py-1">
                <x-ui.toggle wire:model="skipShopping" :label="__('Always at home')" :hint="__('Never added to the shopping list (salt, water...)')" />
            </div>

            <div x-data="{ open: false }" class="rounded-2xl border border-line">
                <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open" class="flex w-full items-center gap-2 px-3.5 min-h-12 text-left">
                    <span class="flex-1 text-[14px] font-semibold text-ink">{{ __('Unit weights') }} <span class="font-normal text-muted">· {{ __('optional') }}</span></span>
                    @if ($filledWeights)
                    <span class="text-[12.5px] text-muted">{{ __('filled in :count', ['count' => $filledWeights]) }}</span>
                    @endif
                    <x-icon.chevron-down class="size-4 text-muted transition-transform" x-bind:class="open && 'rotate-180'" />
                </button>
                <div x-show="open" x-collapse class="border-t border-divider px-3.5 py-2 space-y-1.5">
                    @foreach (Ingredient::WEIGHABLE_UNITS as $unit)
                    <div wire:key="weight-{{ $unit }}" class="flex items-center gap-2">
                        <label for="weight-{{ $unit }}" class="w-24 shrink-0 text-[14px] text-ink">1 {{ Quantity::unitLabel($unit) }} =</label>
                        <input id="weight-{{ $unit }}" type="text" inputmode="decimal" wire:model="unitWeights.{{ $unit }}" class="w-24 h-10 rounded-[10px] border border-field bg-field-bg px-2.5 text-right text-base text-ink focus:outline-none focus:border-ink focus:ring-1 focus:ring-ink" />
                        <span class="text-[14px] text-muted">g</span>
                    </div>
                    <x-ui.field-error name="unitWeights.{{ $unit }}" />
                    @endforeach
                </div>
            </div>

            <div class="border-t border-divider pt-3">
                <x-ui.delete-footer :name="$name" action="delete" :can-delete="(bool) $editingId">
                    <button type="submit" @disabled(trim($name) === '') class="h-12 rounded-[14px] bg-terracotta px-5 text-[15px] font-semibold text-white disabled:opacity-40">
                        {{ $editingId ? __('Save') : __('Add ingredient') }}
                    </button>
                </x-ui.delete-footer>
            </div>
        </form>
    </flux:modal>
</div>
