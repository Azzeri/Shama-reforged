<?php

use App\Models\Ingredient;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Support\ShoppingList\Quantity;
use App\Support\ShoppingList\ShoppingListBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Flux\Flux;

new #[Layout('layouts::app')] class extends Component {
    /** Bought rows older than this are deleted on visit. */
    private const PURGE_BOUGHT_AFTER_DAYS = 14;

    /** Unbought rows for dates this far in the past are deleted on visit. */
    private const PURGE_STALE_AFTER_DAYS = 7;

    /** "list" or "cards" (loyalty cards). */
    #[Url(except: 'list')]
    public string $tab = 'list';

    public string $newItemName = '';
    public string $newItemQuantity = '';
    public string $newItemDate = '';
    public string $newItemNotes = '';

    /** @var list<int> rows of the group opened in the item modal */
    public array $openIds = [];
    public string $editName = '';
    public string $editQuantity = '';
    public string $editDate = '';
    public string $editNotes = '';

    public function mount(): void
    {
        $list = ShoppingList::main();

        $list->items()
            ->where(ShoppingListItem::IS_CHECKED_COLUMN, true)
            ->where(fn ($query) => $query
                ->where(ShoppingListItem::CHECKED_AT_COLUMN, '<', now()->subDays(self::PURGE_BOUGHT_AFTER_DAYS))
                ->orWhere(fn ($query) => $query
                    ->whereNull(ShoppingListItem::CHECKED_AT_COLUMN)
                    ->where('updated_at', '<', now()->subDays(self::PURGE_BOUGHT_AFTER_DAYS))))
            ->delete();

        $list->items()
            ->where(ShoppingListItem::IS_CHECKED_COLUMN, false)
            ->where(ShoppingListItem::NEEDED_ON_COLUMN, '<', today()->subDays(self::PURGE_STALE_AFTER_DAYS)->toDateString())
            ->delete();
    }

    public function render()
    {
        return $this->view()->title(__('Shopping'));
    }

    #[Computed]
    public function cardsCount(): int
    {
        return \App\Models\LoyaltyCard::query()->count();
    }

    #[On('cards-changed')]
    public function cardsChanged(): void
    {
        unset($this->cardsCount);
    }

    #[Computed]
    public function items(): Collection
    {
        return ShoppingList::main()->items()
            ->with(['recipe:id,name', 'ingredient:id,name,purchase_timing,skip_shopping,category,unit_weights'])
            ->get();
    }

    #[Computed]
    public function list(): array
    {
        return app(ShoppingListBuilder::class)->build($this->items, today());
    }

    #[Computed]
    public function rangeLabel(): ?string
    {
        if (! $range = $this->list['range']) {
            return null;
        }

        [$from, $to] = $range;
        $locale = app()->getLocale();

        if ($from->isSameDay($to)) {
            return $to->locale($locale)->isoFormat('D MMMM');
        }

        return $from->isSameMonth($to)
            ? $from->format('j') . ' – ' . $to->locale($locale)->isoFormat('D MMMM')
            : $from->locale($locale)->isoFormat('D MMM') . ' – ' . $to->locale($locale)->isoFormat('D MMM');
    }

    #[Computed]
    public function openGroupData(): ?array
    {
        if (empty($this->openIds)) {
            return null;
        }

        $items = ShoppingListItem::query()
            ->with(['recipe:id,name', 'ingredient:id,name,purchase_timing,skip_shopping,category,unit_weights', 'meal:id,date,type'])
            ->whereIn('id', $this->openIds)
            ->orderBy(ShoppingListItem::NEEDED_ON_COLUMN)
            ->get();

        if ($items->isEmpty()) {
            return null;
        }

        return [
            'items' => $items,
            'name' => $items->first()->ingredient?->name ?? $items->first()->name,
            'total' => app(ShoppingListBuilder::class)->quantityLabel($items, $items->first()->ingredient),
            'single' => $items->count() === 1,
            'canMoveEarlier' => $items->contains(fn (ShoppingListItem $item) => $item->needed_on !== null),
        ];
    }

    /** "Czwartek" style day name in the app locale. */
    public function dayName(\Carbon\CarbonInterface $date): string
    {
        return mb_convert_case($date->locale(app()->getLocale())->isoFormat('dddd'), MB_CASE_TITLE);
    }

    /** Dates offered in the add/edit forms: today + the next 13 days. */
    #[Computed]
    public function dateOptions(): Collection
    {
        return collect(range(0, 13))->map(function (int $offset) {
            $date = today()->addDays($offset);

            return [
                'value' => $date->toDateString(),
                'label' => $this->dayName($date) . ' ' . $date->format('d.m'),
            ];
        });
    }

    private function refreshList(): void
    {
        unset($this->items, $this->list, $this->rangeLabel, $this->openGroupData);
    }

    /** Ids from the client, restricted to rows of the shopping list. */
    private function rows(array $ids)
    {
        return ShoppingList::main()->items()->whereIn('id', array_map('intval', $ids));
    }

    /**
     * Sets the state the user asked for instead of flipping what's stored,
     * so two people ticking the same row at once don't undo each other.
     */
    public function toggle(array $ids, bool $checked = true): void
    {
        $this->rows($ids)->update([
            ShoppingListItem::IS_CHECKED_COLUMN => $checked,
            ShoppingListItem::CHECKED_AT_COLUMN => $checked ? now() : null,
        ]);

        $this->refreshList();
    }

    public function deleteItems(array $ids): void
    {
        $this->rows($ids)->delete();
        $this->openIds = array_values(array_diff($this->openIds, $ids));
        $this->refreshList();
    }

    /** Moves the rows to the previous day's shopping (e.g. to buy together). */
    public function moveEarlier(array $ids): void
    {
        $this->rows($ids)->whereNotNull(ShoppingListItem::NEEDED_ON_COLUMN)->get()
            ->each(fn (ShoppingListItem $item) => $item->update([
                ShoppingListItem::NEEDED_ON_COLUMN => $item->needed_on->copy()->subDay()->toDateString(),
            ]));

        $this->refreshList();
        $this->dispatch('modal-close', name: 'item-modal');

        Flux::toast(variant: 'success', text: __('Moved to the day before.'));
    }

    public function openGroup(array $ids): void
    {
        $this->openIds = array_map('intval', $ids);
        unset($this->openGroupData);
        $this->resetErrorBag();

        $item = $this->openGroupData['items'][0] ?? null;

        if ($item) {
            $this->editName = $item->name;
            $this->editQuantity = $item->displayQuantity() ?? '';
            $this->editDate = $item->needed_on?->toDateString() ?? '';
            $this->editNotes = $item->notes ?? '';
        }
    }

    /**
     * Saves the single-row edit form. A typed "300 g" goes into amount+unit
     * (so it keeps summing with other rows), anything else is free text.
     */
    public function saveItem(): void
    {
        $this->validate([
            'editName' => ['required', 'string', 'max:255'],
            'editQuantity' => ['nullable', 'string', 'max:255'],
            'editDate' => ['nullable', 'date'],
            'editNotes' => ['nullable', 'string', 'max:500'],
        ]);

        $item = $this->rows($this->openIds)->firstOrFail();

        $item->update([
            'name' => trim($this->editName),
            ...$this->quantityAttributes($this->editQuantity),
            'needed_on' => $this->editDate ?: null,
            'notes' => trim($this->editNotes) ?: null,
        ]);

        $this->openIds = [];
        $this->refreshList();
        $this->dispatch('modal-close', name: 'item-modal');

        Flux::toast(variant: 'success', text: __('Item updated.'));
    }

    /** Edits one row's quantity inside a merged group. */
    public function updateRowQuantity(int $id, string $quantity): void
    {
        $this->rows([$id])->firstOrFail()->update($this->quantityAttributes($quantity));
        $this->refreshList();
    }

    private function quantityAttributes(string $text): array
    {
        $text = trim($text);
        $parsed = $text !== '' ? Quantity::parse($text) : null;

        return $parsed
            ? ['amount' => $parsed[0], 'unit' => $parsed[1], 'quantity' => null]
            : ['amount' => null, 'unit' => null, 'quantity' => $text !== '' ? $text : null];
    }

    public function resetNewItemForm(): void
    {
        $this->reset('newItemName', 'newItemQuantity', 'newItemDate', 'newItemNotes');
        $this->resetErrorBag();
    }

    /**
     * A manual item whose name matches a known ingredient is linked to it,
     * so it lands in the right aisle and sums with the same ingredient from
     * recipes.
     */
    public function addItem(): void
    {
        $this->validate([
            'newItemName' => ['required', 'string', 'max:255'],
            'newItemQuantity' => ['nullable', 'string', 'max:255'],
            'newItemDate' => ['nullable', 'date'],
            'newItemNotes' => ['nullable', 'string', 'max:500'],
        ]);

        $name = trim($this->newItemName);
        $ingredient = Ingredient::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();

        ShoppingList::main()->items()->create([
            'name' => $ingredient?->name ?? $name,
            ...$this->quantityAttributes($this->newItemQuantity),
            'needed_on' => $this->newItemDate ?: null,
            'notes' => trim($this->newItemNotes) ?: null,
            'ingredient_id' => $ingredient?->id,
            'is_checked' => false,
        ]);

        $this->resetNewItemForm();
        $this->refreshList();
        $this->dispatch('modal-close', name: 'add-item-modal');

        Flux::toast(variant: 'success', text: __('Item added.'));
    }

    public function clearBought(): void
    {
        ShoppingList::main()->items()->where(ShoppingListItem::IS_CHECKED_COLUMN, true)->delete();
        $this->refreshList();

        Flux::toast(variant: 'success', text: __('Bought items cleared.'));
    }

    public function clearAll(): void
    {
        ShoppingList::main()->items()->delete();
        $this->refreshList();

        Flux::toast(variant: 'success', text: __('List cleared.'));
    }
};
?>

@php
    $isList = $tab !== 'cards';
    $list = $isList ? $this->list : null;
    $progress = $list['progress'] ?? null;
@endphp

{{-- Polls so changes made by someone else on another phone show up live. --}}
<div wire:poll.5s>
    <div class="space-y-5">
        <div>
            <div class="flex items-center justify-between gap-3">
                <h1 class="font-fraunces text-[34px] font-semibold leading-none text-ink">{{ __('Shopping') }}</h1>

                @if (! $isList)
                <button type="button" wire:click="$dispatch('card-new')" class="inline-flex items-center gap-1.5 rounded-full bg-terracotta px-4 py-2.5 font-manrope text-[15px] font-bold text-white shadow-[0_6px_14px_rgba(193,68,45,0.3)]">
                    <flux:icon.plus class="size-4" />
                    {{ __('Card') }}
                </button>
                @else
                <div class="flex items-center gap-2">
                    <flux:modal.trigger name="add-item-modal">
                        <button type="button" wire:click="resetNewItemForm" class="inline-flex items-center gap-1.5 rounded-full bg-terracotta px-4 py-2.5 font-manrope text-[15px] font-bold text-white shadow-[0_6px_14px_rgba(193,68,45,0.3)]">
                            <flux:icon.plus class="size-4" />
                            {{ __('Add') }}
                        </button>
                    </flux:modal.trigger>

                    <flux:dropdown position="bottom" align="end">
                        <button type="button" class="flex items-center justify-center size-11 rounded-full border border-ink/10 bg-white text-ink" aria-label="{{ __('More') }}">
                            <flux:icon.ellipsis-horizontal class="size-5" />
                        </button>
                        <flux:menu>
                            <flux:menu.item icon="calendar-days" :href="route('meals.index')" wire:navigate>{{ __('Generate from the meal plan') }}</flux:menu.item>
                            <flux:menu.item icon="check-circle" wire:click="clearBought">{{ __('Clear bought items') }}</flux:menu.item>
                            <flux:modal.trigger name="clear-list-modal">
                                <flux:menu.item icon="trash" variant="danger">{{ __('Clear the whole list') }}</flux:menu.item>
                            </flux:modal.trigger>
                        </flux:menu>
                    </flux:dropdown>
                </div>
                @endif
            </div>

            <div class="mt-4 grid grid-cols-2 gap-1 rounded-2xl bg-sand p-1">
                @foreach (['list' => __('List'), 'cards' => __('Cards')] as $key => $label)
                <button type="button" wire:click="$set('tab', '{{ $key }}')" @class([
                    'rounded-xl py-2.5 font-manrope text-[15px] font-bold text-ink transition-colors',
                    'bg-white shadow-sm' => ($key === 'cards') === ! $isList,
                ])>
                    {{ $label }}
                    @if ($key === 'cards' && $this->cardsCount > 0)
                    <span class="ml-1 font-semibold text-ink/50">{{ $this->cardsCount }}</span>
                    @endif
                </button>
                @endforeach
            </div>

            @if ($isList && $progress['total'] > 0)
            <div class="mt-4 flex items-center justify-between font-manrope text-[13.5px] text-ink/60">
                <span>{{ __('Bought :bought of :total', $progress) }}</span>
                @if ($this->rangeLabel)
                <span>{{ $this->rangeLabel }}</span>
                @endif
            </div>
            <div class="mt-1.5 h-1.5 rounded-full bg-ink/10 overflow-hidden">
                <div class="h-full rounded-full bg-forest transition-all" style="width: {{ round($progress['bought'] / $progress['total'] * 100) }}%"></div>
            </div>
            @endif
        </div>

        @if (! $isList)
        <livewire:shopping-list.cards />
        @else
        @if ($progress['total'] === 0)
        <div class="rounded-[22px] border border-dashed border-ink/20 px-5 py-10 text-center space-y-3">
            <p class="font-manrope text-[15px] text-ink/60">{{ __('The shopping list is empty.') }}</p>
            <a href="{{ route('meals.index') }}" wire:navigate class="inline-flex items-center gap-1.5 font-manrope text-[15px] font-bold text-terracotta-dark">
                <flux:icon.calendar-days class="size-4" />
                {{ __('Generate from the meal plan') }}
            </a>
        </div>
        @endif

        @if ($list['weekly']['count'] > 0)
        <section x-data="{ open: $persist(true).as('shopping-weekly-open') }">
            <button type="button" x-on:click="open = ! open" class="w-full flex items-center gap-2 text-left">
                <span class="font-fraunces text-[22px] font-semibold text-ink">{{ __('For the whole week') }}</span>
                <span class="font-manrope text-[13px] text-ink/50">{{ __('buy right away') }}</span>
                <span class="ml-auto font-manrope text-[14px] font-bold text-ink/60">{{ $list['weekly']['count'] }}</span>
                <flux:icon.chevron-down class="size-4 text-ink/50 transition-transform" x-bind:class="open || '-rotate-90'" />
            </button>

            <div x-show="open" x-collapse class="mt-3 space-y-4">
                @foreach ($list['weekly']['categories'] as $category)
                <x-shopping-list.category :category="$category" wire:key="weekly-{{ $category['category'] }}" />
                @endforeach
            </div>
        </section>
        @endif

        @foreach ($list['days'] as $day)
        @php $dateKey = $day['date']->toDateString(); @endphp
        <section wire:key="day-{{ $dateKey }}" x-data="{ open: true }">
            <button type="button" x-on:click="open = ! open" class="w-full flex items-baseline gap-2 text-left">
                <span class="font-fraunces text-[22px] font-semibold {{ $day['date']->isToday() ? 'text-terracotta-dark' : 'text-ink' }}">{{ $this->dayName($day['date']) }}</span>
                <span class="font-manrope text-[13px] text-ink/50">{{ $day['date']->isToday() ? __('today') : $day['date']->format('d.m') }}</span>
                @if ($day['date']->lt(today()))
                <span class="font-manrope text-[12px] font-bold text-terracotta-dark">{{ __('overdue') }}</span>
                @endif
                <span class="ml-auto font-manrope text-[14px] font-bold text-ink/60">{{ $day['count'] }}</span>
                <flux:icon.chevron-down class="size-4 self-center text-ink/50 transition-transform" x-bind:class="open || '-rotate-90'" />
            </button>

            <div x-show="open" x-collapse class="mt-3 space-y-4">
                @foreach ($day['categories'] as $category)
                <x-shopping-list.category :category="$category" can-move-earlier wire:key="day-{{ $dateKey }}-{{ $category['category'] }}" />
                @endforeach

                @if ($day['coveredCount'] > 0)
                <p class="flex items-start gap-1.5 font-manrope text-[13px] text-ink/50">
                    <flux:icon.check-badge class="size-4 shrink-0 mt-px" />
                    {{ trans_choice(':count product for this day is already in the For the whole week section|:count products for this day are already in the For the whole week section', $day['coveredCount']) }}
                </p>
                @endif
            </div>
        </section>
        @endforeach

        @if ($list['bought']['count'] > 0)
        <section x-data="{ open: $persist(false).as('shopping-bought-open') }">
            <button type="button" x-on:click="open = ! open" class="w-full flex items-center gap-2 text-left">
                <span class="font-fraunces text-[22px] font-semibold text-ink">{{ __('Bought') }}</span>
                <span class="font-manrope text-[13px] text-ink/50">{{ __('last 7 days') }}</span>
                <span class="ml-auto font-manrope text-[14px] font-bold text-ink/60">{{ $list['bought']['count'] }}</span>
                <flux:icon.chevron-down class="size-4 text-ink/50 transition-transform" x-bind:class="open || '-rotate-90'" />
            </button>

            <div x-show="open" x-collapse class="mt-3">
                <div class="rounded-[18px] border border-ink/10 overflow-hidden divide-y divide-ink/8 bg-white">
                    @foreach ($list['bought']['groups'] as $group)
                    <x-shopping-list.item-tile :group="$group" bought />
                    @endforeach
                </div>
            </div>
        </section>
        @endif
        @endif
    </div>

    <flux:modal name="add-item-modal" variant="flyout" position="bottom" :closable="false" class="rounded-t-[24px] bg-cream! max-h-[88dvh] overflow-y-auto">
        <form wire:submit="addItem" class="space-y-5">
            <div class="flex items-start justify-between gap-2">
                <h3 class="font-fraunces text-xl font-semibold text-ink">{{ __('Add item') }}</h3>
                <flux:modal.close>
                    <button type="button" class="text-ink/25 hover:text-ink/50 p-1.5 text-lg leading-none">✕</button>
                </flux:modal.close>
            </div>

            <div>
                <x-ui.eyebrow>{{ __('Product name') }}</x-ui.eyebrow>
                <x-ui.text-input wire:model="newItemName" class="w-full" required autofocus />
                <x-ui.field-error name="newItemName" />
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <x-ui.eyebrow optional>{{ __('Quantity') }}</x-ui.eyebrow>
                    <x-ui.text-input wire:model="newItemQuantity" placeholder="{{ __('e.g. 500 g') }}" class="w-full" />
                </div>
                <div>
                    <x-ui.eyebrow optional>{{ __('For when') }}</x-ui.eyebrow>
                    <select wire:model="newItemDate" class="w-full border-[1.5px] border-ink/25 bg-white rounded-2xl px-3.5 py-[13px] font-manrope text-base sm:text-sm text-ink">
                        <option value="">{{ __('Whole week') }}</option>
                        @foreach ($this->dateOptions as $option)
                        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <x-ui.eyebrow optional>{{ __('Note') }}</x-ui.eyebrow>
                <x-ui.text-input wire:model="newItemNotes" class="w-full" />
            </div>

            <button type="submit" class="w-full bg-terracotta hover:bg-terracotta-dark transition-colors text-white rounded-[14px] py-3.5 font-manrope text-sm font-extrabold">
                + {{ __('Add') }}
            </button>
        </form>
    </flux:modal>

    <flux:modal name="item-modal" variant="flyout" position="bottom" :closable="false" class="rounded-t-[24px] bg-cream! max-h-[88dvh] overflow-y-auto">
        @if ($group = $this->openGroupData)
        <div class="space-y-5">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <h3 class="font-fraunces text-xl font-semibold text-ink">{{ $group['name'] }}</h3>
                    @if ($group['total'])
                    <div class="font-manrope text-[14px] text-ink/60">{{ __('Total') }}: {{ $group['total'] }}</div>
                    @endif
                </div>
                <flux:modal.close>
                    <button type="button" class="text-ink/25 hover:text-ink/50 p-1.5 text-lg leading-none">✕</button>
                </flux:modal.close>
            </div>

            @if ($group['single'])
            <form wire:submit="saveItem" class="space-y-4">
                <div>
                    <x-ui.eyebrow>{{ __('Product name') }}</x-ui.eyebrow>
                    <x-ui.text-input wire:model="editName" class="w-full" required />
                    <x-ui.field-error name="editName" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <x-ui.eyebrow optional>{{ __('Quantity') }}</x-ui.eyebrow>
                        <x-ui.text-input wire:model="editQuantity" class="w-full" />
                    </div>
                    <div>
                        <x-ui.eyebrow optional>{{ __('For when') }}</x-ui.eyebrow>
                        <select wire:model="editDate" class="w-full border-[1.5px] border-ink/25 bg-white rounded-2xl px-3.5 py-[13px] font-manrope text-base sm:text-sm text-ink">
                            <option value="">{{ __('Whole week') }}</option>
                            @if ($editDate && ! $this->dateOptions->contains('value', $editDate))
                            <option value="{{ $editDate }}">{{ $this->dayName(Carbon::parse($editDate)) }} {{ Carbon::parse($editDate)->format('d.m') }}</option>
                            @endif
                            @foreach ($this->dateOptions as $option)
                            <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <x-ui.eyebrow optional>{{ __('Note') }}</x-ui.eyebrow>
                    <x-ui.text-input wire:model="editNotes" class="w-full" />
                </div>
                @if ($recipe = $group['items'][0]->recipe)
                <p class="font-manrope text-[13px] text-ink/50">{{ __('From recipe') }}: {{ $recipe->name }}</p>
                @endif
                <button type="submit" class="w-full bg-terracotta hover:bg-terracotta-dark transition-colors text-white rounded-[14px] py-3.5 font-manrope text-sm font-extrabold">
                    {{ __('Save') }}
                </button>
            </form>
            @else
            <div class="rounded-2xl border border-ink/10 bg-white divide-y divide-ink/8">
                @foreach ($group['items'] as $row)
                <div wire:key="open-row-{{ $row->id }}" class="flex items-center gap-3 px-3.5 py-2.5">
                    <div class="flex-1 min-w-0">
                        <div class="font-manrope text-[14px] font-semibold text-ink truncate">{{ $row->recipe?->name ?? $row->name }}</div>
                        <div class="font-manrope text-[12px] text-ink/50">
                            {{ $row->needed_on ? $this->dayName($row->needed_on) . ' ' . $row->needed_on->format('d.m') : __('Whole week') }}
                        </div>
                    </div>
                    <input
                        type="text"
                        value="{{ $row->displayQuantity() }}"
                        x-on:change="$wire.updateRowQuantity({{ $row->id }}, $event.target.value)"
                        class="w-24 rounded-xl border border-ink/15 bg-cream px-2.5 py-1.5 text-right font-manrope text-base sm:text-sm text-ink" />
                    <button type="button" wire:click="deleteItems([{{ $row->id }}])" class="text-ink/40 p-1" aria-label="{{ __('Delete') }}">
                        <flux:icon.x-mark class="size-4" />
                    </button>
                </div>
                @endforeach
            </div>
            @endif

            <div class="grid grid-cols-2 gap-2">
                @if ($group['canMoveEarlier'])
                <button type="button" wire:click="moveEarlier({{ \Illuminate\Support\Js::from($openIds) }})" class="rounded-[14px] border border-ink/10 bg-white py-3 font-manrope text-sm font-bold text-ink">
                    {{ __('Buy a day earlier') }}
                </button>
                @endif
                <flux:modal.close>
                    <button type="button" wire:click="deleteItems({{ \Illuminate\Support\Js::from($openIds) }})" class="w-full rounded-[14px] py-3 font-manrope text-sm font-bold text-terracotta-dark">
                        {{ __('Delete') }}
                    </button>
                </flux:modal.close>
            </div>
        </div>
        @endif
    </flux:modal>

    <flux:modal name="clear-list-modal" variant="flyout" position="bottom" :closable="false" class="rounded-t-[24px] bg-cream! max-h-[88dvh] overflow-y-auto">
        <div class="space-y-5">
            <h3 class="font-fraunces text-xl font-semibold text-ink">{{ __('Clear the whole list?') }}</h3>
            <p class="font-manrope text-sm text-ink/60">{{ __('All items will be removed, including bought ones. This cannot be undone.') }}</p>
            <div class="flex gap-2 justify-end">
                <flux:modal.close>
                    <button type="button" class="font-manrope text-sm font-extrabold text-ink/60 px-4 py-2.5">{{ __('Cancel') }}</button>
                </flux:modal.close>
                <flux:modal.close>
                    <button type="button" wire:click="clearAll" class="bg-terracotta text-white rounded-2xl px-5 py-2.5 font-manrope text-sm font-extrabold">{{ __('Clear list') }}</button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</div>
