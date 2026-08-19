<?php

use App\Models\Ingredient;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Flux\Flux;

new #[Layout('layouts::app')] class extends Component {
    public string $newItemName = '';
    public string $newItemQuantity = '';
    public string $newItemWeekDay = '';
    public string $newItemNotes = '';

    public ?int $editingItemId = null;
    public string $editItemName = '';
    public string $editItemQuantity = '';
    public string $editItemWeekDay = '';
    public string $editItemNotes = '';

    /** @var array<int> */
    public array $groupedItemIds = [];

    public function render()
    {
        return $this->view()
            ->title(__('Shopping list'));
    }

    #[Computed]
    public function shoppingList(): ShoppingList
    {
        return ShoppingList::query()->firstOrCreate(['id' => 1], ['name' => 'Main shopping list']);
    }

    #[Computed]
    public function items(): Collection
    {
        return $this->shoppingList->items()
            ->with(['recipe:id,name', 'ingredient:id,purchase_timing,category'])
            ->orderByDesc('created_at')
            ->get();
    }

    #[Computed]
    public function advanceItemGroups(): Collection
    {
        $items = $this->items
            ->where(ShoppingListItem::IS_CHECKED_COLUMN, false)
            ->filter(fn (ShoppingListItem $item) => $item->ingredient?->purchase_timing === Ingredient::PURCHASE_TIMING_ADVANCE
                || $item->week_day === null)
            ->values();

        return $this->groupAndSum($items);
    }

    #[Computed]
    public function advanceItems(): Collection
    {
        return $this->groupByCategory($this->advanceItemGroups);
    }

    #[Computed]
    public function advanceCountLabel(): string
    {
        return $this->pluralItems($this->advanceItemGroups->count());
    }

    /**
     * Merges rows for the same ingredient + unit (e.g. several recipes on
     * the same day each needing salt in grams) into a single tile with a
     * summed amount. Rows without a matching ingredient/unit pair (manual
     * items, free-text quantities) stay as their own singleton group so
     * nothing gets merged on a guess. Also tracks which distinct week days
     * fed into the sum, so a merged tile can show a per-day breakdown
     * instead of hiding where the quantity came from.
     */
    private function groupAndSum(Collection $items): Collection
    {
        return $items
            ->groupBy(fn (ShoppingListItem $item) => $item->ingredient_id && $item->unit
                ? "{$item->ingredient_id}:{$item->unit}"
                : "solo:{$item->id}")
            ->map(function (Collection $group) {
                $first = $group->first();
                $isMerged = $group->count() > 1;

                if ($isMerged) {
                    $sum = rtrim(rtrim(number_format($group->sum('amount'), 2, '.', ''), '0'), '.');
                    $displayQuantity = "{$sum} {$first->unit}";
                } else {
                    $displayQuantity = $first->displayQuantity();
                }

                $weekDays = $group->pluck('week_day')->filter()->unique()
                    ->sortBy(fn (string $day) => array_search($day, ShoppingListItem::WEEK_DAYS))
                    ->values();

                $dayBreakdown = $isMerged && $weekDays->count() > 1
                    ? $group->filter(fn (ShoppingListItem $item) => $item->week_day !== null)
                        ->groupBy('week_day')
                        ->sortBy(fn (Collection $rows, string $day) => array_search($day, ShoppingListItem::WEEK_DAYS))
                        ->map(fn (Collection $rows) => [
                            'day' => ShoppingListItem::dayLabel($rows->first()->week_day),
                            'displayQuantity' => rtrim(rtrim(number_format($rows->sum('amount'), 2, '.', ''), '0'), '.') . " {$first->unit}",
                        ])
                        ->values()
                    : collect();

                return [
                    'item' => $first,
                    'ids' => $group->pluck('id')->all(),
                    'displayQuantity' => $displayQuantity,
                    'weekDays' => $weekDays,
                    'showDaysBadge' => $weekDays->count() > 1,
                    'daysLabel' => $weekDays->count() . ' ' . __('days'),
                    'dayBreakdown' => $dayBreakdown,
                ];
            })
            ->values();
    }

    /**
     * Splits already-merged item groups into aisle sections (dairy, bread,
     * ...) in Ingredient::CATEGORIES order, so the day's list reads like a
     * shopping trip instead of a random pile. Sections with nothing in them
     * are dropped rather than shown empty.
     */
    private function groupByCategory(Collection $itemGroups): Collection
    {
        $byCategory = $itemGroups->groupBy(
            fn (array $group) => $group['item']->ingredient?->category ?? Ingredient::CATEGORY_UNCATEGORIZED
        );

        return collect(Ingredient::CATEGORIES)
            ->map(fn (string $category) => [
                'category' => $category,
                'label' => Ingredient::categoryLabel($category),
                'items' => $byCategory->get($category, collect()),
            ])
            ->filter(fn (array $section) => $section['items']->isNotEmpty())
            ->values();
    }

    /**
     * Per-day view of what's still needed: "fresh" items grouped into aisle
     * sections, plus a cross-reference to any ingredients the day's recipes
     * need that are already covered by the "buy anytime" pool above, so they
     * aren't shown (and don't need buying) twice.
     */
    #[Computed]
    public function activeByDay(): Collection
    {
        $weekStart = now()->startOfWeek();

        return collect(ShoppingListItem::WEEK_DAYS)
            ->map(function (string $day, int $index) use ($weekStart) {
                $dayItems = $this->items
                    ->where(ShoppingListItem::IS_CHECKED_COLUMN, false)
                    ->where(ShoppingListItem::WEEK_DAY_COLUMN, $day)
                    ->values();

                $freshItems = $dayItems->reject(
                    fn (ShoppingListItem $item) => $item->ingredient?->purchase_timing === Ingredient::PURCHASE_TIMING_ADVANCE
                );

                $freshGroups = $this->groupAndSum($freshItems);

                $coveredChips = $this->advanceItemGroups
                    ->filter(fn (array $group) => $group['weekDays']->contains($day))
                    ->values();

                if ($freshGroups->isEmpty() && $coveredChips->isEmpty()) {
                    return null;
                }

                return [
                    'day' => $day,
                    'label' => ShoppingListItem::dayLabel($day),
                    'date' => $weekStart->copy()->addDays($index)->format('d.m'),
                    'countLabel' => $this->pluralItems($freshGroups->count()),
                    'categories' => $this->groupByCategory($freshGroups),
                    'noFresh' => $freshGroups->isEmpty(),
                    'coveredChips' => $coveredChips,
                    'coveredLabel' => $coveredChips->count() . ' ' . __('from today are already covered by the anytime list'),
                ];
            })
            ->filter()
            ->values();
    }

    #[Computed]
    public function boughtByDay(): Collection
    {
        return $this->groupNonEmpty(true);
    }

    #[Computed]
    public function unassignedBoughtItems(): Collection
    {
        $weekStart = now()->startOfWeek();
        $weekEnd = now()->endOfWeek();

        return $this->items
            ->where(ShoppingListItem::IS_CHECKED_COLUMN, true)
            ->whereNull(ShoppingListItem::WEEK_DAY_COLUMN)
            ->filter(fn (ShoppingListItem $item) => $item->updated_at?->betweenIncluded($weekStart, $weekEnd))
            ->values();
    }

    #[Computed]
    public function editingItem(): ?ShoppingListItem
    {
        return $this->editingItemId
            ? ShoppingListItem::query()->with('recipe:id,name')->find($this->editingItemId)
            : null;
    }

    #[Computed]
    public function groupedItemGroup(): ?array
    {
        if (empty($this->groupedItemIds)) {
            return null;
        }

        $items = ShoppingListItem::query()->whereIn('id', $this->groupedItemIds)->get();

        return $this->groupAndSum($items)->first();
    }

    private function groupNonEmpty(bool $checked): Collection
    {
        $weekStart = now()->startOfWeek();
        $weekEnd = now()->endOfWeek();

        return collect(ShoppingListItem::WEEK_DAYS)
            ->map(function (string $day, int $index) use ($checked, $weekStart, $weekEnd) {
                $items = $this->items
                    ->where(ShoppingListItem::IS_CHECKED_COLUMN, $checked)
                    ->where(ShoppingListItem::WEEK_DAY_COLUMN, $day)
                    ->when($checked, fn (Collection $collection) => $collection->filter(
                        fn (ShoppingListItem $item) => $item->updated_at?->betweenIncluded($weekStart, $weekEnd)
                    ))
                    ->values();

                return [
                    'day' => $day,
                    'label' => ShoppingListItem::dayLabel($day),
                    'date' => $weekStart->copy()->addDays($index)->format('d.m'),
                    'items' => $this->groupAndSum($items),
                ];
            })
            ->filter(fn (array $group) => $group['items']->isNotEmpty())
            ->values();
    }

    private function dayShort(string $day): string
    {
        return match ($day) {
            'monday' => __('Mon'),
            'tuesday' => __('Tue'),
            'wednesday' => __('Wed'),
            'thursday' => __('Thu'),
            'friday' => __('Fri'),
            'saturday' => __('Sat'),
            'sunday' => __('Sun'),
        };
    }

    private function pluralItems(int $n): string
    {
        return $n === 1 ? '1 ' . __('item') : "{$n} " . __('items');
    }

    public function toggle(int|array $itemIds): void
    {
        $ids = is_array($itemIds) ? $itemIds : [$itemIds];

        $items = ShoppingListItem::query()->whereIn('id', $ids)->get();
        $allChecked = $items->every(fn (ShoppingListItem $item) => $item->is_checked);

        ShoppingListItem::query()->whereIn('id', $ids)->update([
            ShoppingListItem::IS_CHECKED_COLUMN => ! $allChecked,
        ]);

        unset($this->items);
    }

    public function editItem(int $itemId): void
    {
        $item = ShoppingListItem::query()->findOrFail($itemId);
        $this->editingItemId = $itemId;
        $this->editItemName = $item->name;
        $this->editItemQuantity = $item->quantity ?? '';
        $this->editItemWeekDay = $item->week_day ?? '';
        $this->editItemNotes = $item->notes ?? '';
        $this->resetErrorBag();
    }

    /** @param array<int> $ids */
    public function showGroupDetails(array $ids): void
    {
        $this->groupedItemIds = $ids;
    }

    public function saveItem(): void
    {
        $this->validate([
            'editItemName' => ['required', 'string', 'max:255'],
            'editItemQuantity' => ['nullable', 'string', 'max:255'],
            'editItemWeekDay' => ['nullable', 'string', Rule::in(ShoppingListItem::WEEK_DAYS)],
            'editItemNotes' => ['nullable', 'string', 'max:500'],
        ]);

        ShoppingListItem::query()->whereKey($this->editingItemId)->update([
            ShoppingListItem::NAME_COLUMN => $this->editItemName,
            ShoppingListItem::QUANTITY_COLUMN => $this->editItemQuantity ?: null,
            ShoppingListItem::WEEK_DAY_COLUMN => $this->editItemWeekDay ?: null,
            'notes' => $this->editItemNotes ?: null,
        ]);

        $this->editingItemId = null;
        unset($this->items);
        $this->dispatch('modal-close', name: 'edit-item-modal');

        Flux::toast(variant: 'success', text: __('Item updated.'));
    }

    public function resetNewItemForm(): void
    {
        $this->newItemName = '';
        $this->newItemQuantity = '';
        $this->newItemWeekDay = '';
        $this->newItemNotes = '';
        $this->resetErrorBag();
    }

    public function addItem(): void
    {
        $this->validate([
            'newItemName' => ['required', 'string', 'max:255'],
            'newItemQuantity' => ['nullable', 'string', 'max:255'],
            'newItemWeekDay' => ['nullable', 'string', Rule::in(ShoppingListItem::WEEK_DAYS)],
            'newItemNotes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->shoppingList->items()->create([
            ShoppingListItem::NAME_COLUMN => $this->newItemName,
            ShoppingListItem::QUANTITY_COLUMN => $this->newItemQuantity ?: null,
            ShoppingListItem::WEEK_DAY_COLUMN => $this->newItemWeekDay ?: null,
            ShoppingListItem::IS_CHECKED_COLUMN => false,
            'notes' => $this->newItemNotes ?: null,
        ]);

        $this->resetNewItemForm();
        unset($this->items);
        $this->dispatch('modal-close', name: 'add-item-modal');

        Flux::toast(variant: 'success', text: __('Item added.'));
    }

    public function clearUnchecked(): void
    {
        $this->shoppingList->items()->where(ShoppingListItem::IS_CHECKED_COLUMN, false)->delete();
        unset($this->items);

        Flux::toast(variant: 'success', text: __('Unbought items cleared.'));
    }
};
?>

<div>
    <div class="space-y-5">
        <h1 class="font-fraunces text-[28px] font-semibold text-ink">{{ __('Shopping list') }}</h1>

        <div class="flex items-center gap-4 flex-wrap">
            <flux:modal.trigger name="add-item-modal">
                <button class="bg-terracotta text-white rounded-2xl px-[18px] py-3 font-manrope text-[13.5px] font-extrabold shadow-[0_8px_18px_rgba(193,68,45,0.3)]">
                    + {{ __('Add item') }}
                </button>
            </flux:modal.trigger>

            <flux:modal.trigger name="clear-unchecked-modal">
                <button class="bg-transparent flex items-center gap-1.5 font-manrope text-[13px] font-bold text-terracotta-dark">
                    🗑 {{ __('Clear unbought') }}
                </button>
            </flux:modal.trigger>
        </div>

        @if ($this->advanceItems->isNotEmpty())
        <div>
            <div class="flex items-baseline gap-2.5 pb-2.5 mb-3.5 border-b-2 border-gold/70">
                <div class="font-fraunces text-xl font-semibold text-ink">{{ __('Anytime') }}</div>
                <div class="flex-1"></div>
                <div class="font-manrope text-[11px] font-extrabold tracking-[0.05em] text-gold-dark whitespace-nowrap">{{ $this->advanceCountLabel }}</div>
            </div>
            <div class="space-y-3">
                @foreach ($this->advanceItems as $categoryGroup)
                <div wire:key="advance-cat-{{ $categoryGroup['category'] }}">
                    @if ($this->advanceItems->count() > 1)
                    <div class="font-manrope text-[11px] font-bold uppercase tracking-[0.06em] text-ink/40 mb-2">{{ $categoryGroup['label'] }}</div>
                    @endif
                    <div class="grid grid-cols-3 gap-2.5 items-start">
                        @foreach ($categoryGroup['items'] as $itemGroup)
                        <x-shopping-list.item-tile
                            :item="$itemGroup['item']"
                            :ids="$itemGroup['ids']"
                            :display-quantity="$itemGroup['displayQuantity']"
                            :day-breakdown="$itemGroup['dayBreakdown']"
                            anytime
                            wire:key="item-advance-{{ $itemGroup['ids'][0] }}" />
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        @foreach ($this->activeByDay as $group)
        <div wire:key="active-day-{{ $group['day'] }}">
            <div class="flex items-baseline gap-2.5 pb-2.5 mb-3 border-b-2 border-ink/[0.16]">
                <div class="font-fraunces text-xl font-semibold text-ink">{{ $group['label'] }}</div>
                <div class="font-manrope text-xs font-bold text-ink/40">{{ $group['date'] }}</div>
                <div class="flex-1"></div>
                <div class="font-manrope text-[11px] font-extrabold tracking-[0.05em] text-ink/45 whitespace-nowrap">{{ $group['countLabel'] }}</div>
            </div>

            @if ($group['noFresh'])
            <div class="font-manrope text-xs font-semibold text-ink/35 {{ $group['coveredChips']->isNotEmpty() ? 'mb-2.5' : '' }}">{{ __('Nothing fresh needed today.') }}</div>
            @else
            <div class="space-y-3 {{ $group['coveredChips']->isNotEmpty() ? 'mb-3.5' : '' }}">
                @foreach ($group['categories'] as $categoryGroup)
                <div wire:key="active-day-{{ $group['day'] }}-cat-{{ $categoryGroup['category'] }}">
                    @if ($group['categories']->count() > 1)
                    <div class="font-manrope text-[11px] font-bold uppercase tracking-[0.06em] text-ink/40 mb-2">{{ $categoryGroup['label'] }}</div>
                    @endif
                    <div class="grid grid-cols-3 gap-2.5">
                        @foreach ($categoryGroup['items'] as $itemGroup)
                        <x-shopping-list.item-tile
                            :item="$itemGroup['item']"
                            :ids="$itemGroup['ids']"
                            :display-quantity="$itemGroup['displayQuantity']"
                            wire:key="item-{{ $itemGroup['ids'][0] }}" />
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
            @endif

            @if ($group['coveredChips']->isNotEmpty())
            <div class="border-t border-dashed border-ink/[0.16] pt-2.5" x-data="{ open: false }">
                <button type="button" @click="open = ! open" class="flex items-center gap-1.5 font-manrope text-[10.5px] font-bold uppercase tracking-[0.08em] text-ink/35">
                    <span>↑ {{ $group['coveredLabel'] }}</span>
                    <flux:icon.chevron-down class="size-3 shrink-0 transition-transform" x-bind:class="open ? 'rotate-180' : ''" />
                </button>
                <div x-show="open" x-collapse class="flex flex-wrap gap-1.5 mt-2">
                    @foreach ($group['coveredChips'] as $chip)
                    <span class="inline-flex items-center gap-1.5 border border-dashed border-ink/20 rounded-full px-2.5 py-1 font-manrope text-[11px] font-semibold text-ink/40">
                        {{ $chip['item']->name }}
                        <span class="opacity-70">{{ $chip['displayQuantity'] }}</span>
                        @if ($chip['showDaysBadge'])
                        <span class="font-extrabold text-[9.5px] tracking-[0.03em] text-gold-dark">Σ {{ $chip['daysLabel'] }}</span>
                        @endif
                    </span>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
        @endforeach

        @if ($this->boughtByDay->isNotEmpty() || $this->unassignedBoughtItems->isNotEmpty())
        <div>
            <div class="uppercase text-[12px] font-extrabold tracking-[0.08em] text-ink/55 mb-3 font-manrope">{{ __('Bought (this week)') }}</div>

            <div class="space-y-3.5">
                @foreach ($this->boughtByDay as $group)
                <div wire:key="bought-day-{{ $group['day'] }}">
                    <div class="text-[12.5px] font-bold text-ink/50 font-manrope mb-2">{{ $group['label'] }} · {{ $group['date'] }}</div>
                    <div class="grid grid-cols-3 gap-2.5">
                        @foreach ($group['items'] as $itemGroup)
                        <x-shopping-list.item-tile
                            :item="$itemGroup['item']"
                            :ids="$itemGroup['ids']"
                            :display-quantity="$itemGroup['displayQuantity']"
                            bought
                            wire:key="item-{{ $itemGroup['ids'][0] }}" />
                        @endforeach
                    </div>
                </div>
                @endforeach

                @if ($this->unassignedBoughtItems->isNotEmpty())
                <div wire:key="bought-unassigned">
                    <div class="text-[12.5px] font-bold text-ink/50 font-manrope mb-2">{{ __('No day assigned') }}</div>
                    <div class="grid grid-cols-3 gap-2.5">
                        @foreach ($this->unassignedBoughtItems as $item)
                        <x-shopping-list.item-tile :item="$item" bought wire:key="item-{{ $item->id }}" />
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif
    </div>

    <flux:modal name="add-item-modal" variant="flyout" position="bottom" :closable="false" class="rounded-t-[24px] bg-cream! max-h-[88dvh] overflow-y-auto">
        <form wire:submit="addItem" class="space-y-5">
            <div class="flex items-start justify-between gap-2">
                <h3 class="font-fraunces text-xl font-semibold text-ink">{{ __('Add item') }}</h3>
                <flux:modal.close>
                    <button type="button" wire:click="resetNewItemForm" class="text-ink/25 hover:text-ink/50 p-1.5 text-lg leading-none">✕</button>
                </flux:modal.close>
            </div>
            <p class="font-manrope text-[13px] text-ink/60">{{ __('Only the product name is required.') }}</p>

            <div>
                <x-ui.eyebrow>{{ __('Product name') }}</x-ui.eyebrow>
                <x-ui.text-input wire:model="newItemName" class="w-full" required autofocus />
                <x-ui.field-error name="newItemName" />
            </div>

            <div>
                <x-ui.eyebrow optional>{{ __('Quantity') }}</x-ui.eyebrow>
                <x-ui.text-input wire:model="newItemQuantity" class="w-full" />
            </div>

            <div>
                <x-ui.eyebrow optional>{{ __('Day of week') }}</x-ui.eyebrow>
                <select wire:model="newItemWeekDay" class="w-full border-[1.5px] border-ink/25 bg-white rounded-2xl px-3.5 py-[13px] font-manrope text-base sm:text-sm text-ink focus:outline-none focus:ring-2 focus:ring-terracotta/30">
                    <option value="">{{ __('Unassigned') }}</option>
                    @foreach (\App\Models\ShoppingListItem::WEEK_DAYS as $day)
                    <option value="{{ $day }}">{{ ShoppingListItem::dayLabel($day) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-ui.eyebrow optional>{{ __('Note') }}</x-ui.eyebrow>
                <x-ui.text-input wire:model="newItemNotes" class="w-full" />
            </div>

            <div class="flex items-center justify-end gap-3">
                <flux:modal.close>
                    <button type="button" wire:click="resetNewItemForm" class="font-manrope text-sm font-extrabold text-forest px-4 py-3">
                        {{ __('Cancel') }}
                    </button>
                </flux:modal.close>
                <button type="submit" class="bg-terracotta hover:bg-terracotta-dark transition-colors text-white rounded-[14px] px-5 py-3 font-manrope text-sm font-extrabold">
                    + {{ __('Add') }}
                </button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="edit-item-modal" variant="flyout" position="bottom" :closable="false" class="rounded-t-[24px] bg-cream! max-h-[88dvh] overflow-y-auto">
        <form wire:submit="saveItem" class="space-y-5">
            <div class="flex items-start justify-between gap-2">
                <h3 class="font-fraunces text-xl font-semibold text-ink">{{ __('Edit item') }}</h3>
                <flux:modal.close>
                    <button type="button" class="text-ink/25 hover:text-ink/50 p-1.5 text-lg leading-none">✕</button>
                </flux:modal.close>
            </div>

            <div>
                <x-ui.eyebrow>{{ __('Product name') }}</x-ui.eyebrow>
                <x-ui.text-input wire:model="editItemName" class="w-full" required autofocus />
                <x-ui.field-error name="editItemName" />
            </div>

            <div>
                <x-ui.eyebrow optional>{{ __('Quantity') }}</x-ui.eyebrow>
                <x-ui.text-input wire:model="editItemQuantity" class="w-full" />
            </div>

            <div>
                <x-ui.eyebrow optional>{{ __('Day of week') }}</x-ui.eyebrow>
                <select wire:model="editItemWeekDay" class="w-full border-[1.5px] border-ink/25 bg-white rounded-2xl px-3.5 py-[13px] font-manrope text-base sm:text-sm text-ink focus:outline-none focus:ring-2 focus:ring-terracotta/30">
                    <option value="">{{ __('Unassigned') }}</option>
                    @foreach (\App\Models\ShoppingListItem::WEEK_DAYS as $day)
                    <option value="{{ $day }}">{{ ShoppingListItem::dayLabel($day) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-ui.eyebrow optional>{{ __('Note') }}</x-ui.eyebrow>
                <x-ui.text-input wire:model="editItemNotes" class="w-full" />
            </div>

            @if ($this->editingItem?->recipe)
            <div>
                <x-ui.eyebrow>{{ __('Needed for recipes') }}</x-ui.eyebrow>
                <a
                    href="{{ $this->editingItem->recipe->showUrlWithBack() }}"
                    wire:navigate
                    class="flex items-center justify-between gap-2 bg-sand rounded-xl px-3.5 py-[11px] font-manrope text-[13.5px] font-bold text-ink">
                    <span>📖 {{ $this->editingItem->recipe->name }}</span>
                    <span class="text-gold-dark text-xs">{{ __('View') }} ›</span>
                </a>
            </div>
            @endif

            <div class="flex items-center justify-end gap-3">
                <flux:modal.close>
                    <button type="button" class="font-manrope text-sm font-extrabold text-forest px-4 py-3">
                        {{ __('Cancel') }}
                    </button>
                </flux:modal.close>
                <button type="submit" class="bg-terracotta hover:bg-terracotta-dark transition-colors text-white rounded-[14px] px-5 py-3 font-manrope text-sm font-extrabold">
                    {{ __('Save') }}
                </button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="grouped-item-modal" variant="flyout" position="bottom" :closable="false" class="rounded-t-[24px] bg-cream! max-h-[88dvh] overflow-y-auto">
        @if ($this->groupedItemGroup)
        <div class="space-y-5">
            <div class="flex items-start justify-between gap-2">
                <h3 class="font-fraunces text-xl font-semibold text-ink">{{ __('Grouped item') }}</h3>
                <flux:modal.close>
                    <button type="button" class="text-ink/25 hover:text-ink/50 p-1.5 text-lg leading-none">✕</button>
                </flux:modal.close>
            </div>

            <div>
                <x-ui.eyebrow>{{ __('Product name') }}</x-ui.eyebrow>
                <div class="font-manrope text-base font-bold text-ink">{{ $this->groupedItemGroup['item']->name }}</div>
            </div>

            <div>
                <x-ui.eyebrow>{{ __('Total quantity') }}</x-ui.eyebrow>
                <div class="font-manrope text-base font-extrabold text-gold-dark">{{ $this->groupedItemGroup['displayQuantity'] }}</div>
            </div>

            <div>
                <x-ui.eyebrow>{{ __('Split across days') }}</x-ui.eyebrow>
                <div class="border border-ink/10 rounded-2xl divide-y divide-dashed divide-ink/15 overflow-hidden">
                    @foreach ($this->groupedItemGroup['dayBreakdown'] as $row)
                    <div class="flex items-center justify-between px-4 py-3">
                        <span class="font-manrope text-sm font-bold text-ink">{{ $row['day'] }}</span>
                        <span class="font-manrope text-sm font-bold text-gold-dark">{{ $row['displayQuantity'] }}</span>
                    </div>
                    @endforeach
                </div>
            </div>

            <div class="flex items-center justify-end gap-3">
                <flux:modal.close>
                    <button type="button" class="font-manrope text-sm font-extrabold text-forest px-4 py-3">
                        {{ __('Close') }}
                    </button>
                </flux:modal.close>
            </div>
        </div>
        @endif
    </flux:modal>

    <flux:modal name="clear-unchecked-modal" variant="flyout" position="bottom" :closable="false" class="rounded-t-[24px] bg-cream! max-h-[88dvh] overflow-y-auto">
        <div class="space-y-6">
            <div class="flex items-start justify-between gap-2">
                <h3 class="font-fraunces text-xl font-semibold text-ink">{{ __('Clear unbought items?') }}</h3>
                <flux:modal.close>
                    <button type="button" class="text-ink/25 hover:text-ink/50 p-1.5 text-lg leading-none">✕</button>
                </flux:modal.close>
            </div>
            <p class="font-manrope text-sm text-ink/60 -mt-4">
                {{ __('This will remove every unbought item from your shopping list. This action cannot be undone.') }}
            </p>

            <div class="flex gap-2 justify-end">
                <flux:modal.close>
                    <button type="button" class="font-manrope text-sm font-extrabold text-ink/60 hover:text-ink px-4 py-2.5">
                        {{ __('Cancel') }}
                    </button>
                </flux:modal.close>

                <flux:modal.close>
                    <button
                        type="button"
                        wire:click="clearUnchecked"
                        class="bg-terracotta hover:bg-terracotta-dark transition-colors text-white rounded-2xl px-5 py-2.5 font-manrope text-sm font-extrabold">
                        {{ __('Clear unbought') }}
                    </button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</div>
