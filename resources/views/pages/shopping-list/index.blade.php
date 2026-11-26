<?php

use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\PantryItem;
use App\Models\Recipe;
use App\Models\RecipeMealAssignment;
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

new #[Layout('layouts::app')] class extends Component {
    /** Bought rows older than this are deleted on visit. */
    private const PURGE_BOUGHT_AFTER_DAYS = 14;

    /** Unbought rows for dates this far in the past are deleted on visit. */
    private const PURGE_STALE_AFTER_DAYS = 7;

    public const TABS = ['list', 'pantry', 'lists', 'cards'];

    /** list (from meals) | pantry | lists (custom lists) | cards (loyalty cards). */
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
        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'list';
        }

        return $this->view()->title(__('Shopping'));
    }

    /** Household supplies marked low / out — shown in the "Zapasy domowe" group. */
    #[Computed]
    public function pantryToBuy(): Collection
    {
        return PantryItem::query()->toBuy()->orderBy('name')->get();
    }

    #[On('pantry-changed')]
    public function pantryChanged(): void
    {
        unset($this->pantryToBuy);
    }

    /** Ticking a household product off the list means it's back in stock. */
    public function restock(int $id): void
    {
        PantryItem::query()->whereKey($id)->update(['status' => PantryItem::STATUS_OK]);
        unset($this->pantryToBuy);
    }

    /**
     * Recipes cooked whole on the days shown, so a day's header can say
     * "cały przepis na 6 porcji". date => [recipe_id => servings].
     */
    #[Computed]
    public function fullCookingByDate(): array
    {
        $dates = collect($this->list['days'])->map(fn (array $day) => $day['date']->toDateString());

        if ($dates->isEmpty()) {
            return [];
        }

        return RecipeMealAssignment::query()
            ->where('mode', RecipeMealAssignment::MODE_FULL)
            ->with(['meal:id,date', 'recipe:id,servings'])
            ->whereHas('meal', fn ($query) => $query->where(fn ($query) => $dates->each(fn (string $date) => $query->orWhereDate(Meal::DATE_COLUMN, $date))))
            ->get()
            ->groupBy(fn (RecipeMealAssignment $assignment) => $assignment->meal->date->toDateString())
            ->map(fn ($assignments) => $assignments->mapWithKeys(fn ($assignment) => [$assignment->recipe_id => $assignment->recipe->servings])->all())
            ->all();
    }

    /** "21.11 · Pierogi ruskie, cały przepis na 6 porcji" */
    public function daySubtitle(array $day): string
    {
        $full = $this->fullCookingByDate[$day['date']->toDateString()] ?? [];
        $recipes = $day['recipes']->map(function ($recipe) use ($full) {
            return isset($full[$recipe->id])
                ? $recipe->name . ', ' . __('whole recipe for :servings', ['servings' => Recipe::servingsLabel($full[$recipe->id])])
                : $recipe->name;
        });

        return collect([$day['date']->format('d.m'), $recipes->implode(' · ')])->filter()->implode(' · ');
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
        unset($this->items, $this->list, $this->rangeLabel, $this->openGroupData, $this->fullCookingByDate);
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
    }

    public function clearBought(): void
    {
        ShoppingList::main()->items()->where(ShoppingListItem::IS_CHECKED_COLUMN, true)->delete();
        $this->refreshList();
    }

    public function clearAll(): void
    {
        ShoppingList::main()->items()->delete();
        $this->refreshList();
    }
};
?>

@php
    $isList = $tab === 'list';
    $list = $isList ? $this->list : null;
    $pantry = $isList ? $this->pantryToBuy : collect();
    $progress = $list ? [
        'bought' => $list['progress']['bought'],
        'total' => $list['progress']['total'] + $pantry->count(),
    ] : null;
    $tabs = ['list' => __('Meals'), 'pantry' => __('Supplies'), 'lists' => __('Custom'), 'cards' => __('Cards')];
@endphp

{{-- The meals tab follows changes made on the other phone (live sync, see app.js). --}}
<div @if ($isList) x-data="liveSync(@js(route('live-sync', 'shopping')))" data-sync-version="{{ \App\Support\LiveSync\DataVersion::for('shopping') }}" @endif>
    <div class="space-y-4">
        <div class="space-y-3">
            <x-ui.page-header :title="__('Shopping')">
                @switch($tab)
                @case('pantry')
                <x-ui.pill wire:click="$dispatch('pantry-new')">{{ __('Product') }}</x-ui.pill>
                @break
                @case('lists')
                <x-ui.pill wire:click="$dispatch('list-new')">{{ __('List') }}</x-ui.pill>
                @break
                @case('cards')
                <x-ui.pill wire:click="$dispatch('card-new')">{{ __('Card') }}</x-ui.pill>
                @break
                @default
                <flux:modal.trigger name="add-item-modal">
                    <x-ui.pill wire:click="resetNewItemForm">{{ __('Add') }}</x-ui.pill>
                </flux:modal.trigger>

                <flux:dropdown position="bottom" align="end">
                    <button type="button" class="flex items-center justify-center size-10 rounded-full border border-field bg-white text-ink" aria-label="{{ __('More') }}">
                        <x-icon.dots class="size-5" />
                    </button>
                    <flux:menu>
                        <flux:menu.item icon="calendar-days" :href="route('meals.index')" wire:navigate>{{ __('Generate from the meal plan') }}</flux:menu.item>
                        <flux:menu.item icon="check-circle" wire:click="clearBought">{{ __('Clear bought items') }}</flux:menu.item>
                        <flux:modal.trigger name="clear-list-modal">
                            <flux:menu.item icon="trash" variant="danger">{{ __('Clear the whole list') }}</flux:menu.item>
                        </flux:modal.trigger>
                    </flux:menu>
                </flux:dropdown>
                @endswitch
            </x-ui.page-header>

            <x-ui.segmented :options="$tabs" model="tab" :value="$tab" size="sm" />

            @if ($isList && $progress['total'] > 0)
            <div>
                <div class="flex items-center justify-between text-[13px] text-muted">
                    <span>{{ __('Bought :bought of :total', $progress) }}</span>
                    @if ($this->rangeLabel)
                    <span>{{ $this->rangeLabel }}</span>
                    @endif
                </div>
                <div class="mt-1.5 h-1.5 rounded-full bg-sand overflow-hidden" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $progress['total'] }}" aria-valuenow="{{ $progress['bought'] }}">
                    <div class="h-full rounded-full bg-sage transition-all" style="width: {{ round($progress['bought'] / $progress['total'] * 100) }}%"></div>
                </div>
            </div>
            @endif
        </div>

        @switch($tab)
        @case('pantry')
        <livewire:shopping-list.pantry />
        @break
        @case('lists')
        <livewire:shopping-list.custom-lists />
        @break
        @case('cards')
        <livewire:shopping-list.cards />
        @break
        @default
        @if ($progress['total'] === 0)
        <div class="rounded-[20px] border border-dashed border-field px-5 py-10 text-center space-y-3">
            <p class="text-[15px] text-muted">{{ __('The shopping list is empty.') }}</p>
            <a href="{{ route('meals.index') }}" wire:navigate class="inline-flex items-center gap-1.5 text-[15px] font-semibold text-terracotta">
                <x-icon.calendar class="size-4" />
                {{ __('Generate from the meal plan') }}
            </a>
        </div>
        @endif

        @if ($list['upcoming']['count'] > 0 || $pantry->isNotEmpty())
        <section x-data="{ open: $persist(true).as('shopping-upcoming-open') }">
            <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open" class="w-full flex items-start gap-2 text-left">
                <span class="flex-1 min-w-0">
                    <span class="block font-fraunces text-[22px] font-semibold leading-tight text-ink">{{ __('Upcoming shopping') }}</span>
                    <span class="block text-[12.5px] text-muted">{{ __('today, tomorrow and for the whole week') }}</span>
                </span>
                <span class="pt-1.5 text-[14px] text-muted">{{ $list['upcoming']['count'] + $pantry->count() }}</span>
                <x-icon.chevron-down class="mt-2 size-4 text-muted transition-transform" x-bind:class="open || '-rotate-90'" />
            </button>

            <div x-show="open" x-collapse class="mt-2 space-y-3">
                @foreach ($list['upcoming']['categories'] as $category)
                <x-shopping-list.category :category="$category" wire:key="upcoming-{{ $category['category'] }}" />
                @endforeach

                @if ($pantry->isNotEmpty())
                <div wire:key="upcoming-pantry">
                    <x-ui.section-label class="mb-1.5">{{ __('Household supplies') }}</x-ui.section-label>
                    <div class="rounded-2xl border border-line overflow-hidden divide-y divide-divider bg-white">
                        @foreach ($pantry as $pantryItem)
                        <div wire:key="pantry-row-{{ $pantryItem->id }}" x-data="{ checked: false }" class="flex items-center gap-3 px-3.5 py-2.5 min-h-[52px]">
                            <button
                                type="button"
                                x-on:click="checked = true; setTimeout(() => $wire.restock({{ $pantryItem->id }}), 250)"
                                aria-label="{{ __('Bought') }}: {{ $pantryItem->name }}"
                                class="shrink-0 -m-2.5 p-2.5">
                                <span class="size-[22px] rounded-[7px] border-[1.5px] flex items-center justify-center transition-colors" :class="checked ? 'bg-sage border-sage' : 'border-field bg-white'">
                                    <x-icon.check class="size-3.5 text-white" x-show="checked" />
                                </span>
                            </button>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-1.5">
                                    <span class="text-[15px] font-medium text-ink truncate" :class="checked && 'line-through text-muted'">{{ $pantryItem->name }}</span>
                                    <span class="shrink-0 rounded-full bg-sage-light px-1.5 py-px text-[11px] font-semibold text-forest">{{ __('pantry') }}</span>
                                </div>
                                <div class="text-[12.5px] text-muted">{{ $pantryItem->status === \App\Models\PantryItem::STATUS_OUT ? __('none at home') : __('running low') }}</div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </section>
        @endif

        @foreach ($list['days'] as $day)
        @php $dateKey = $day['date']->toDateString(); @endphp
        <section wire:key="day-{{ $dateKey }}" x-data="{ open: true }">
            <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open" class="w-full flex items-start gap-2 text-left">
                <span class="flex-1 min-w-0">
                    <span class="block font-fraunces text-[22px] font-semibold leading-tight text-ink">{{ $this->dayName($day['date']) }}</span>
                    <span class="block text-[12.5px] text-muted">{{ $this->daySubtitle($day) }}</span>
                </span>
                <span class="pt-1.5 text-[14px] text-muted">{{ $day['count'] }}</span>
                <x-icon.chevron-down class="mt-2 size-4 text-muted transition-transform" x-bind:class="open || '-rotate-90'" />
            </button>

            <div x-show="open" x-collapse class="mt-2 space-y-3">
                @foreach ($day['categories'] as $category)
                <x-shopping-list.category :category="$category" can-move-earlier wire:key="day-{{ $dateKey }}-{{ $category['category'] }}" />
                @endforeach
            </div>
        </section>
        @endforeach

        @if ($list['bought']['count'] > 0)
        <section x-data="{ open: $persist(false).as('shopping-bought-open') }">
            <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open" class="w-full flex items-start gap-2 text-left">
                <span class="flex-1 min-w-0">
                    <span class="block font-fraunces text-[22px] font-semibold leading-tight text-ink">{{ __('Bought') }}</span>
                    <span class="block text-[12.5px] text-muted">{{ __('this week') }}</span>
                </span>
                <span class="pt-1.5 text-[14px] text-muted">{{ $list['bought']['count'] }}</span>
                <x-icon.chevron-down class="mt-2 size-4 text-muted transition-transform" x-bind:class="open || '-rotate-90'" />
            </button>

            <div x-show="open" x-collapse class="mt-2">
                <div class="rounded-2xl border border-line overflow-hidden divide-y divide-divider bg-white">
                    @foreach ($list['bought']['groups'] as $group)
                    <x-shopping-list.item-tile :group="$group" bought />
                    @endforeach
                </div>
            </div>
        </section>
        @endif
        @endswitch
    </div>

    <flux:modal name="add-item-modal" variant="flyout" position="bottom" :closable="false" class="rounded-t-[24px] bg-white! max-h-[88dvh] overflow-y-auto">
        <form wire:submit="addItem" class="space-y-4">
            <div class="flex items-center justify-between gap-2">
                <h2 class="font-fraunces text-[20px] font-semibold text-ink">{{ __('Add item') }}</h2>
                <flux:modal.close>
                    <button type="button" class="-mr-2.5 flex size-11 items-center justify-center text-ink" aria-label="{{ __('Close') }}"><x-icon.x class="size-[18px]" /></button>
                </flux:modal.close>
            </div>

            <div>
                <label for="new-item-name" class="block text-[13px] font-medium text-muted mb-1.5">{{ __('Product name') }}</label>
                <x-ui.text-input id="new-item-name" wire:model="newItemName" class="w-full bg-field-bg" required autofocus />
                <x-ui.field-error name="newItemName" />
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="new-item-qty" class="block text-[13px] font-medium text-muted mb-1.5">{{ __('Quantity') }} <span class="font-normal">· {{ __('optional') }}</span></label>
                    <x-ui.text-input id="new-item-qty" wire:model="newItemQuantity" placeholder="{{ __('e.g. 500 g') }}" class="w-full bg-field-bg" />
                </div>
                <div>
                    <label for="new-item-date" class="block text-[13px] font-medium text-muted mb-1.5">{{ __('For when') }}</label>
                    <select id="new-item-date" wire:model="newItemDate" class="w-full h-11 border border-field bg-field-bg rounded-xl px-3 text-base text-ink">
                        <option value="">{{ __('Whole week') }}</option>
                        @foreach ($this->dateOptions as $option)
                        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label for="new-item-notes" class="block text-[13px] font-medium text-muted mb-1.5">{{ __('Note') }} <span class="font-normal">· {{ __('optional') }}</span></label>
                <x-ui.text-input id="new-item-notes" wire:model="newItemNotes" class="w-full bg-field-bg" />
            </div>

            <x-ui.button type="submit">{{ __('Add') }}</x-ui.button>
        </form>
    </flux:modal>

    <flux:modal name="item-modal" variant="flyout" position="bottom" :closable="false" class="rounded-t-[24px] bg-white! max-h-[88dvh] overflow-y-auto">
        @if ($group = $this->openGroupData)
        <div class="space-y-4">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <h2 class="font-fraunces text-[20px] font-semibold text-ink">{{ $group['name'] }}</h2>
                    @if ($group['total'])
                    <div class="text-[14px] text-muted">{{ __('Total') }}: {{ $group['total'] }}</div>
                    @endif
                </div>
                <flux:modal.close>
                    <button type="button" class="-mr-2.5 flex size-11 items-center justify-center text-ink" aria-label="{{ __('Close') }}"><x-icon.x class="size-[18px]" /></button>
                </flux:modal.close>
            </div>

            @if ($group['single'])
            <form wire:submit="saveItem" class="space-y-4">
                <div>
                    <label for="edit-item-name" class="block text-[13px] font-medium text-muted mb-1.5">{{ __('Product name') }}</label>
                    <x-ui.text-input id="edit-item-name" wire:model="editName" class="w-full bg-field-bg" required />
                    <x-ui.field-error name="editName" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="edit-item-qty" class="block text-[13px] font-medium text-muted mb-1.5">{{ __('Quantity') }}</label>
                        <x-ui.text-input id="edit-item-qty" wire:model="editQuantity" class="w-full bg-field-bg" />
                    </div>
                    <div>
                        <label for="edit-item-date" class="block text-[13px] font-medium text-muted mb-1.5">{{ __('For when') }}</label>
                        <select id="edit-item-date" wire:model="editDate" class="w-full h-11 border border-field bg-field-bg rounded-xl px-3 text-base text-ink">
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
                    <label for="edit-item-notes" class="block text-[13px] font-medium text-muted mb-1.5">{{ __('Note') }} <span class="font-normal">· {{ __('optional') }}</span></label>
                    <x-ui.text-input id="edit-item-notes" wire:model="editNotes" class="w-full bg-field-bg" />
                </div>
                @if ($recipe = $group['items'][0]->recipe)
                <p class="text-[13px] text-muted">{{ __('From recipe') }}: {{ $recipe->name }}</p>
                @endif
                <x-ui.button type="submit">{{ __('Save') }}</x-ui.button>
            </form>
            @else
            <div class="rounded-2xl border border-line bg-white divide-y divide-divider">
                @foreach ($group['items'] as $row)
                <div wire:key="open-row-{{ $row->id }}" class="flex items-center gap-3 px-3.5 py-2">
                    <div class="flex-1 min-w-0">
                        <div class="text-[14px] font-semibold text-ink truncate">{{ $row->recipe?->name ?? $row->name }}</div>
                        <div class="text-[12px] text-muted">
                            {{ $row->needed_on ? $this->dayName($row->needed_on) . ' ' . $row->needed_on->format('d.m') : __('Whole week') }}
                        </div>
                    </div>
                    <label class="sr-only" for="row-qty-{{ $row->id }}">{{ __('Quantity') }}</label>
                    <input
                        id="row-qty-{{ $row->id }}"
                        type="text"
                        value="{{ $row->displayQuantity() }}"
                        x-on:change="$wire.updateRowQuantity({{ $row->id }}, $event.target.value)"
                        class="w-24 h-10 rounded-xl border border-field bg-field-bg px-2.5 text-right text-base text-ink focus:outline-none focus:border-ink" />
                    <button type="button" wire:click="deleteItems([{{ $row->id }}])" class="flex size-10 items-center justify-center text-muted" aria-label="{{ __('Delete') }}">
                        <x-icon.x class="size-4" />
                    </button>
                </div>
                @endforeach
            </div>
            @endif

            <x-ui.delete-footer :name="$group['name']" action="deleteItems({{ \Illuminate\Support\Js::from($openIds) }})">
                @if ($group['canMoveEarlier'])
                <button type="button" wire:click="moveEarlier({{ \Illuminate\Support\Js::from($openIds) }})" class="h-11 rounded-full border border-field bg-white px-4 text-[14px] font-semibold text-ink">
                    {{ __('Buy a day earlier') }}
                </button>
                @endif
            </x-ui.delete-footer>
        </div>
        @endif
    </flux:modal>

    <flux:modal name="clear-list-modal" variant="flyout" position="bottom" :closable="false" class="rounded-t-[24px] bg-white! max-h-[88dvh] overflow-y-auto">
        <div class="space-y-4">
            <h2 class="font-fraunces text-[20px] font-semibold text-ink">{{ __('Clear the whole list?') }}</h2>
            <p class="text-[14px] text-muted">{{ __('All items will be removed, including bought ones. This cannot be undone.') }}</p>
            <div class="flex gap-2 justify-end">
                <flux:modal.close>
                    <button type="button" class="h-11 rounded-full border border-field bg-white px-4 text-[14px] font-semibold text-ink">{{ __('No') }}</button>
                </flux:modal.close>
                <flux:modal.close>
                    <button type="button" wire:click="clearAll" class="h-11 rounded-full bg-terracotta px-4 text-[14px] font-semibold text-white">{{ __('Clear list') }}</button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</div>
