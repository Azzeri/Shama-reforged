<?php

use App\Models\Ingredient;
use App\Support\ShoppingList\Quantity;
use Flux\Flux;
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

        Flux::toast(variant: 'success', text: $this->editingId ? __('Ingredient updated.') : __('Ingredient added.'));

        $this->resetForm();
        unset($this->ingredients, $this->filters);
        $this->dispatch('modal-close', name: 'ingredient-modal');
    }

    public function delete(): void
    {
        Ingredient::query()->whereKey($this->editingId)->delete();

        Flux::toast(variant: 'success', text: __('Ingredient deleted.'));

        $this->resetForm();
        unset($this->ingredients, $this->filters);
        $this->dispatch('modal-close', name: 'ingredient-modal');
    }
};
?>

<div>
    <div class="space-y-4">
        <div class="flex items-center justify-between gap-3">
            <h1 class="font-fraunces text-[34px] font-semibold leading-none text-ink">{{ __('Ingredients') }}</h1>

            <button type="button" wire:click="openNew" class="inline-flex items-center gap-1.5 rounded-full bg-terracotta px-4 py-2.5 font-manrope text-[15px] font-bold text-white shadow-[0_6px_14px_rgba(193,68,45,0.3)]">
                <flux:icon.plus class="size-4" />
                {{ __('New') }}
            </button>
        </div>

        <div class="relative">
            <flux:icon.magnifying-glass class="absolute left-3.5 top-1/2 -translate-y-1/2 size-4 text-ink/45" />
            <input
                type="search"
                wire:model.live.debounce.250ms="search"
                placeholder="{{ __('Search ingredients') }}"
                class="w-full rounded-2xl border border-ink/10 bg-white py-3 pl-10 pr-9 font-manrope text-base text-ink placeholder:text-ink/40 focus:outline-none focus:ring-2 focus:ring-terracotta/30" />
            @if ($search !== '')
            <button type="button" wire:click="$set('search', '')" class="absolute right-3 top-1/2 -translate-y-1/2 text-ink/35" aria-label="{{ __('Clear') }}">
                <flux:icon.x-mark class="size-4" />
            </button>
            @endif
        </div>

        <div class="-mx-4 px-4 flex gap-2 overflow-x-auto pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            @foreach ($this->filters as $key => $label)
            <button
                type="button"
                wire:key="filter-{{ $key }}"
                wire:click="setFilter('{{ $key }}')"
                @class([
                    'shrink-0 rounded-full border px-3.5 py-2 font-manrope text-[14px] font-semibold whitespace-nowrap',
                    'bg-ink border-ink text-white' => $filter === $key,
                    'bg-white border-ink/10 text-ink' => $filter !== $key,
                ])>
                {{ $label }}
            </button>
            @endforeach
        </div>

        @if ($this->ingredients->isNotEmpty())
        <div class="rounded-[22px] border border-ink/10 bg-white divide-y divide-ink/8 overflow-hidden">
            @foreach ($this->ingredients as $ingredient)
            <button type="button" wire:key="ingredient-{{ $ingredient->id }}" wire:click="openEdit({{ $ingredient->id }})" class="w-full flex items-center gap-3 px-4 py-3 text-left">
                <span class="flex-1 min-w-0">
                    <span class="block font-manrope text-[16px] font-medium text-ink truncate">{{ $ingredient->name }}</span>
                    <span class="mt-0.5 flex flex-wrap items-center gap-1.5">
                        <span class="font-manrope text-[12.5px] text-ink/50">{{ Ingredient::categoryLabel($ingredient->category) }}</span>
                        @if ($ingredient->skip_shopping)
                        <span class="rounded-md bg-ink/8 px-1.5 py-px font-manrope text-[11.5px] font-semibold text-ink/55">{{ __('not on the list') }}</span>
                        @elseif ($ingredient->purchase_timing === Ingredient::PURCHASE_TIMING_ADVANCE)
                        <span class="rounded-md bg-sage/18 px-1.5 py-px font-manrope text-[11.5px] font-semibold text-forest">{{ __('for the whole week') }}</span>
                        @endif
                    </span>
                </span>
                <x-ui.chevron-right />
            </button>
            @endforeach
        </div>
        @else
        <div class="rounded-[22px] border border-dashed border-ink/20 px-4 py-10 text-center font-manrope text-[15px] text-ink/50">
            {{ __('No ingredients found.') }}
        </div>
        @endif
    </div>

    <flux:modal name="ingredient-modal" variant="flyout" position="bottom" :closable="false" class="rounded-t-[24px] bg-white! max-h-[90dvh] overflow-y-auto">
        <form wire:submit="save" class="space-y-5">
            <div class="flex items-center justify-between gap-2">
                <h3 class="font-fraunces text-[22px] font-semibold text-ink">{{ $editingId ? __('Edit ingredient') : __('New ingredient') }}</h3>
                <flux:modal.close>
                    <button type="button" class="p-1 text-ink/60" aria-label="{{ __('Close') }}">
                        <flux:icon.x-mark class="size-5" />
                    </button>
                </flux:modal.close>
            </div>

            <div>
                <x-ui.eyebrow>{{ __('Name') }}</x-ui.eyebrow>
                <x-ui.text-input wire:model.live.debounce.200ms="name" placeholder="{{ __('e.g. Basmati rice') }}" class="w-full bg-cream" />
                <x-ui.field-error name="name" />
            </div>

            <div>
                <x-ui.eyebrow>{{ __('When to buy') }}</x-ui.eyebrow>
                <div class="grid grid-cols-2 gap-1 rounded-2xl bg-sand p-1">
                    @foreach ([Ingredient::PURCHASE_TIMING_FRESH => [__('Fresh'), __('day before')], Ingredient::PURCHASE_TIMING_ADVANCE => [__('For the whole week'), __('buy right away')]] as $timing => [$title, $hint])
                    <button
                        type="button"
                        wire:click="$set('purchaseTiming', '{{ $timing }}')"
                        @class([
                            'rounded-xl px-2 py-2 text-center transition-colors',
                            'bg-white shadow-sm' => $purchaseTiming === $timing,
                        ])>
                        <span class="block font-manrope text-[14.5px] font-bold text-ink">{{ $title }}</span>
                        <span class="block font-manrope text-[12px] text-ink/55">{{ $hint }}</span>
                    </button>
                    @endforeach
                </div>
            </div>

            <div>
                <x-ui.eyebrow>{{ __('Store aisle') }}</x-ui.eyebrow>
                <select wire:model="category" class="w-full rounded-[14px] border border-ink/15 bg-cream px-3.5 py-3 font-manrope text-base text-ink focus:outline-none focus:ring-2 focus:ring-terracotta/30">
                    @foreach (Ingredient::CATEGORIES as $categoryOption)
                    <option value="{{ $categoryOption }}">{{ Ingredient::categoryLabel($categoryOption) }}</option>
                    @endforeach
                </select>
            </div>

            <label class="flex items-center gap-3 rounded-2xl border border-ink/10 bg-cream px-3.5 py-3 cursor-pointer">
                <span class="flex-1">
                    <span class="block font-manrope text-[15px] font-semibold text-ink">{{ __('Skip on the shopping list') }}</span>
                    <span class="block font-manrope text-[12.5px] text-ink/55">{{ __('Always at home, e.g. salt or water — never added to the list.') }}</span>
                </span>
                <button
                    type="button"
                    role="switch"
                    aria-checked="{{ $skipShopping ? 'true' : 'false' }}"
                    wire:click="$toggle('skipShopping')"
                    @class(['relative h-6 w-11 shrink-0 rounded-full transition-colors', 'bg-forest' => $skipShopping, 'bg-ink/20' => ! $skipShopping])>
                    <span @class(['absolute top-0.5 left-0.5 size-5 rounded-full bg-white shadow transition-transform', 'translate-x-5' => $skipShopping])></span>
                </button>
            </label>

            <div x-data="{ open: {{ collect($unitWeights)->filter(fn ($v) => $v !== '')->isNotEmpty() || ! $editingId ? 'true' : 'false' }} }" class="border-t border-ink/8 pt-4">
                <button type="button" x-on:click="open = ! open" class="w-full flex items-center justify-between">
                    <x-ui.eyebrow optional class="mb-0!">{{ __('Unit weights') }}</x-ui.eyebrow>
                    <flux:icon.chevron-down class="size-4 text-ink/50 transition-transform" x-bind:class="open && 'rotate-180'" />
                </button>
                <div x-show="open" x-collapse>
                    <p class="mt-2 mb-3 font-manrope text-[12.5px] text-ink/55">{{ __('How many grams one unit weighs, e.g. 1 pcs of egg = 50 g.') }}</p>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach (Ingredient::WEIGHABLE_UNITS as $unit)
                        <label wire:key="unit-weight-{{ $unit }}" class="flex items-center gap-1.5 rounded-xl border border-ink/12 bg-cream px-3 py-2.5">
                            <span class="shrink-0 font-manrope text-[13.5px] text-ink/70">1 {{ Quantity::unitLabel($unit) }} =</span>
                            <input
                                type="text"
                                inputmode="decimal"
                                wire:model="unitWeights.{{ $unit }}"
                                placeholder="—"
                                class="w-full min-w-0 bg-transparent text-right font-manrope text-base text-ink placeholder:text-ink/40 focus:outline-none" />
                            <span class="font-manrope text-[13.5px] text-ink/50">g</span>
                        </label>
                        @endforeach
                    </div>
                    @foreach (Ingredient::WEIGHABLE_UNITS as $unit)
                    <x-ui.field-error name="unitWeights.{{ $unit }}" />
                    @endforeach
                </div>
            </div>

            <div class="border-t border-ink/8 pt-4">
                @if ($confirmingDelete)
                <div class="flex items-center gap-3">
                    <p class="flex-1 font-manrope text-[13.5px] text-ink/70">
                        {{ __('Delete ":name"? This cannot be undone.', ['name' => $name]) }}
                        @if ($this->editingRecipeCount > 0)
                        {{ trans_choice('It will disappear from :count recipe.|It will disappear from :count recipes.', $this->editingRecipeCount) }}
                        @endif
                    </p>
                    <button type="button" wire:click="$set('confirmingDelete', false)" class="font-manrope text-[14.5px] font-bold text-ink px-2 py-2">{{ __('No') }}</button>
                    <button type="button" wire:click="delete" class="rounded-[14px] bg-terracotta px-4 py-2.5 font-manrope text-[14.5px] font-bold text-white">{{ __('Delete') }}</button>
                </div>
                @else
                <div class="flex items-center justify-between gap-3">
                    @if ($editingId)
                    <button type="button" wire:click="$set('confirmingDelete', true)" class="inline-flex items-center gap-1.5 font-manrope text-[14.5px] font-bold text-terracotta-dark">
                        <flux:icon.trash class="size-4" />
                        {{ __('Delete') }}
                    </button>
                    @else
                    <span></span>
                    @endif
                    <button
                        type="submit"
                        @disabled(trim($name) === '')
                        class="rounded-[14px] bg-terracotta px-5 py-3 font-manrope text-[15px] font-bold text-white disabled:opacity-40">
                        {{ $editingId ? __('Save') : __('Add ingredient') }}
                    </button>
                </div>
                @endif
            </div>
        </form>
    </flux:modal>
</div>
