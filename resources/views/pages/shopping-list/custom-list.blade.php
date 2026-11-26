<?php

use App\Models\CustomList;
use App\Models\CustomListItem;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::app')] class extends Component {
    public CustomList $customList;

    public string $newItem = '';

    /** Desktop: shown next to the list overview (no back link, no own page). */
    public bool $embedded = false;

    public function render()
    {
        return $this->view()->title($this->customList->name);
    }

    private function item(int $id): CustomListItem
    {
        return $this->customList->items()->whereKey($id)->firstOrFail();
    }

    public function addItem(): void
    {
        $this->newItem = trim($this->newItem);

        if ($this->newItem === '') {
            return;
        }

        $this->validate(['newItem' => ['string', 'max:120']]);
        $this->customList->items()->create(['name' => $this->newItem]);
        $this->customList->touch();
        $this->newItem = '';
        $this->dispatch('custom-list-changed');
    }

    /** Sets the requested state (not a flip) so two phones don't undo each other. */
    public function setChecked(int $id, bool $checked): void
    {
        $this->item($id)->update(['is_checked' => $checked]);
        $this->customList->touch();
        $this->dispatch('custom-list-changed');
    }

    public function removeItem(int $id): void
    {
        $this->item($id)->delete();
        $this->dispatch('custom-list-changed');
    }

    public function deleteList(): void
    {
        $this->customList->delete();

        if ($this->embedded) {
            $this->dispatch('custom-list-deleted');

            return;
        }

        $this->redirect(route('shopping-list.index', ['tab' => 'lists']), navigate: true);
    }
};
?>

@php
    $items = $customList->items()->get();
    $done = $items->where('is_checked', true)->count();
@endphp

<div data-pull-refresh x-data="liveSync(@js(route('live-sync', ['scope' => 'custom-list', 'id' => $customList->id])))" data-sync-version="{{ \App\Support\LiveSync\DataVersion::for('custom-list', $customList->id) }}" data-sync-gone="{{ route('shopping-list.index', ['tab' => 'lists']) }}" class="space-y-4 lg:max-w-[720px]">
    <div>
        @unless ($embedded)
        <x-ui.back-button href="{{ route('shopping-list.index', ['tab' => 'lists']) }}" :label="__('Custom lists')" />
        @endunless
        <div class="flex items-baseline gap-2.5">
            <h1 data-large-title class="font-fraunces text-[26px] font-semibold leading-tight text-ink truncate">{{ $customList->name }}</h1>
            <span class="shrink-0 text-[14px] text-muted">{{ __(':done of :total', ['done' => $done, 'total' => $items->count()]) }}</span>
            <button type="button" x-data x-on:click="window.shareOrCopy({ title: @js($customList->name), text: @js($customList->name . ":\n" . $items->where('is_checked', false)->map(fn ($i) => '• ' . $i->name)->implode("\n")) })"
                class="ml-auto flex size-10 shrink-0 items-center justify-center rounded-full border border-field bg-white text-ink" aria-label="{{ __('Share') }}">
                <x-icon.share class="size-[18px]" />
            </button>
        </div>
    </div>

    <x-ui.card class="divide-y divide-divider">
        @foreach ($items as $item)
        <div wire:key="custom-item-{{ $item->id }}" x-data="swipeToDelete(() => $wire.removeItem({{ $item->id }}))" x-show="! removing" x-collapse.duration.200ms class="relative overflow-hidden">
        <div class="absolute inset-0 flex items-center justify-end bg-terracotta px-5 text-[14px] font-semibold text-white" aria-hidden="true">{{ __('Delete') }}</div>
        <div x-on:touchstart="touchstart" x-on:touchmove="touchmove" x-on:touchend="touchend" x-on:touchcancel="touchend"
            :style="`transform: translateX(${dx}px)`" :class="dragging ? '' : 'transition-transform duration-200'"
            class="relative flex items-center gap-3 bg-white pl-3.5 pr-1.5 min-h-[52px]">
            <button type="button" x-on:click="window.haptic?.()" wire:click="setChecked({{ $item->id }}, {{ $item->is_checked ? 'false' : 'true' }})"
                aria-pressed="{{ $item->is_checked ? 'true' : 'false' }}" aria-label="{{ __('Bought') }}: {{ $item->name }}" class="shrink-0 -m-2 p-2">
                <span @class(['size-[22px] rounded-[7px] border-[1.5px] flex items-center justify-center', 'bg-sage border-sage' => $item->is_checked, 'border-field bg-white' => ! $item->is_checked])>
                    @if ($item->is_checked)<x-icon.check class="size-3.5 text-white" />@endif
                </span>
            </button>
            <span @class(['flex-1 min-w-0 text-[15px] truncate', 'text-muted line-through' => $item->is_checked, 'text-ink' => ! $item->is_checked])>{{ $item->name }}</span>
            <button type="button" wire:click="removeItem({{ $item->id }})" class="flex size-11 items-center justify-center text-muted" aria-label="{{ __('Remove :name', ['name' => $item->name]) }}">
                <x-icon.x class="size-4" />
            </button>
        </div>
        </div>
        @endforeach

        <form wire:submit="addItem" class="flex items-center gap-2 pl-3.5 pr-1.5 py-1.5">
            <label for="custom-new-item" class="sr-only">{{ __('Add item') }}</label>
            <input id="custom-new-item" type="text" wire:model.live="newItem" placeholder="{{ __('Add item') }}" maxlength="120" enterkeyhint="done"
                class="flex-1 min-w-0 h-11 bg-transparent text-base text-ink placeholder:text-muted focus:outline-none" />
            <button type="submit" @disabled(trim($newItem) === '') aria-label="{{ __('Add') }}" class="flex size-10 items-center justify-center rounded-full bg-terracotta text-white disabled:opacity-35">
                <x-icon.plus class="size-4" />
            </button>
        </form>
    </x-ui.card>

    <div x-data="{ confirming: false }">
        <button type="button" x-show="! confirming" x-on:click="confirming = true" class="inline-flex h-11 items-center gap-2 text-[15px] font-semibold text-terracotta">
            <svg class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M6 7l1 13h10l1-13"/><path d="M9 7V4h6v3"/></svg>
            {{ __('Delete list') }}
        </button>
        <div x-show="confirming" x-cloak class="flex items-center gap-2">
            <span class="flex-1 text-[15px] font-semibold text-ink">{{ __('Delete “:name”?', ['name' => $customList->name]) }}</span>
            <button type="button" x-on:click="confirming = false" class="h-11 rounded-full border border-field bg-white px-4 text-[14px] font-semibold text-ink">{{ __('No') }}</button>
            <button type="button" wire:click="deleteList" class="h-11 rounded-full bg-terracotta px-4 text-[14px] font-semibold text-white">{{ __('Delete') }}</button>
        </div>
    </div>
</div>
