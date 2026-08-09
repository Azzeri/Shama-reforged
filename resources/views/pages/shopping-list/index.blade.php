<?php

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
            ->with('recipe:id,name')
            ->orderByDesc('created_at')
            ->get();
    }

    #[Computed]
    public function unassignedActiveItems(): Collection
    {
        return $this->items
            ->where(ShoppingListItem::IS_CHECKED_COLUMN, false)
            ->whereNull(ShoppingListItem::WEEK_DAY_COLUMN)
            ->values();
    }

    #[Computed]
    public function activeByDay(): Collection
    {
        return $this->groupNonEmpty(false);
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
            ->filter(fn (ShoppingListItem $item) => $item->created_at?->betweenIncluded($weekStart, $weekEnd))
            ->values();
    }

    #[Computed]
    public function editingItem(): ?ShoppingListItem
    {
        return $this->editingItemId
            ? ShoppingListItem::query()->with('recipe:id,name')->find($this->editingItemId)
            : null;
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
                        fn (ShoppingListItem $item) => $item->created_at?->betweenIncluded($weekStart, $weekEnd)
                    ))
                    ->values();

                return [
                    'day' => $day,
                    'label' => ShoppingListItem::dayLabel($day),
                    'date' => $weekStart->copy()->addDays($index)->format('d.m'),
                    'items' => $items,
                ];
            })
            ->filter(fn (array $group) => $group['items']->isNotEmpty())
            ->values();
    }

    public function toggle(int $itemId): void
    {
        $item = ShoppingListItem::query()->findOrFail($itemId);
        $item->update([ShoppingListItem::IS_CHECKED_COLUMN => ! $item->is_checked]);
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

        Flux::toast(variant: 'success', text: __('Unchecked items cleared.'));
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
                    🗑 {{ __('Clear unchecked') }}
                </button>
            </flux:modal.trigger>
        </div>

        <div>
            <div class="uppercase text-[12px] font-extrabold tracking-[0.08em] text-ink/55 mb-2.5 font-manrope">{{ __('No day assigned') }}</div>
            <div class="grid grid-cols-3 gap-2.5">
                @forelse ($this->unassignedActiveItems as $item)
                <x-shopping-list.item-tile :item="$item" wire:key="item-{{ $item->id }}" />
                @empty
                <div class="col-span-3 border border-dashed border-ink/24 rounded-2xl px-4 py-4 text-center font-manrope text-[13px] text-ink/40">
                    {{ __('No items') }}
                </div>
                @endforelse
            </div>
        </div>

        @foreach ($this->activeByDay as $group)
        <div wire:key="active-day-{{ $group['day'] }}">
            <div class="uppercase text-[12px] font-extrabold tracking-[0.08em] text-ink/55 mb-2.5 font-manrope">{{ $group['label'] }} · {{ $group['date'] }}</div>
            <div class="grid grid-cols-3 gap-2.5">
                @foreach ($group['items'] as $item)
                <x-shopping-list.item-tile :item="$item" wire:key="item-{{ $item->id }}" />
                @endforeach
            </div>
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
                        @foreach ($group['items'] as $item)
                        <x-shopping-list.item-tile :item="$item" bought wire:key="item-{{ $item->id }}" />
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

    <flux:modal name="clear-unchecked-modal" variant="flyout" position="bottom" :closable="false" class="rounded-t-[24px] bg-cream! max-h-[88dvh] overflow-y-auto">
        <div class="space-y-6">
            <div class="flex items-start justify-between gap-2">
                <h3 class="font-fraunces text-xl font-semibold text-ink">{{ __('Clear unchecked items?') }}</h3>
                <flux:modal.close>
                    <button type="button" class="text-ink/25 hover:text-ink/50 p-1.5 text-lg leading-none">✕</button>
                </flux:modal.close>
            </div>
            <p class="font-manrope text-sm text-ink/60 -mt-4">
                {{ __('This will remove every unchecked item from your shopping list. This action cannot be undone.') }}
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
                        {{ __('Clear unchecked') }}
                    </button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</div>
