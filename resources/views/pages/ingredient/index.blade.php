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
        $this->panelOpen = false;
        unset($this->ingredients, $this->filters);
        $this->dispatch('modal-close', name: 'ingredient-modal');
    }

    public function delete(): void
    {
        Ingredient::query()->whereKey($this->editingId)->delete();

        $this->resetForm();
        $this->panelOpen = false;
        unset($this->ingredients, $this->filters);
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
        <h1 class="font-fraunces text-[28px] lg:text-[32px] font-semibold leading-none tracking-[-0.01em] text-ink">{{ __('Ingredients') }}</h1>
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
        <x-ui.chip wire:key="filter-{{ $key }}" :active="$filter === $key" wire:click="setFilter('{{ $key }}')">{{ $filterLabel }}</x-ui.chip>
        @endforeach
    </div>

    <div @class(['lg:grid lg:items-start lg:gap-6 lg:pt-1', 'lg:grid-cols-[minmax(0,1fr)_420px]' => $panelOpen])>
    <div class="min-w-0">
    @if ($this->ingredients->isEmpty())
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
    <div class="hidden lg:block rounded-[20px] border border-line bg-white overflow-hidden">
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
                <tr wire:key="ingredient-row-{{ $ingredient->id }}" wire:click="openEdit({{ $ingredient->id }})" @class(['cursor-pointer hover:bg-field-bg', 'relative bg-[#FBF6F0] [&>td:first-child]:shadow-[inset_3px_0_0_var(--color-terracotta)]' => $isSelected])>
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
        <div class="sticky top-6 rounded-[20px] border border-line bg-white p-5" x-data x-on:keydown.escape.window="$wire.closePanel()">
            @include('pages.ingredient.partials.form', ['prefix' => 'd-', 'desktop' => true])
        </div>
        @endif
    </div>
    </div>

    <flux:modal name="ingredient-modal" variant="flyout" position="bottom" :closable="false" class="rounded-t-[24px] bg-white! max-h-[90dvh] overflow-y-auto">
        @include('pages.ingredient.partials.form', ['prefix' => 'm-', 'desktop' => false])
    </flux:modal>
</div>
