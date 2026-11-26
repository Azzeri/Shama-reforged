@props(['group', 'bought' => false, 'canMoveEarlier' => false])

{{--
    One row of the shopping list (a merged group of item rows).
    Tap the checkbox to tick it off (short delay so a mis-tap can be undone),
    tap the row to open details, swipe left for "earlier" / "delete".
    Behaviour lives in the shared Alpine component `shoppingTile` (app.js).
--}}
@php
    $ids = $group['ids'];
    $idsJs = \Illuminate\Support\Js::from($ids);
    $revealWidth = $canMoveEarlier ? 150 : 76;
@endphp

<div
    x-data="shoppingTile({ ids: {{ $idsJs }}, bought: {{ $bought ? 'true' : 'false' }}, reveal: {{ $revealWidth }} })"
    x-show="! removing"
    x-collapse.duration.250ms
    wire:key="row-{{ $group['key'] }}-{{ implode('-', $ids) }}"
    class="relative overflow-hidden">

    <div class="absolute inset-y-0 right-0 flex">
        @if ($canMoveEarlier)
        <button
            type="button"
            wire:click="moveEarlier({{ $idsJs }})"
            x-on:click="close()"
            class="w-[74px] flex items-center justify-center bg-gold text-ink text-[12px] font-semibold">
            {{ __('Day earlier') }}
        </button>
        @endif
        <button
            type="button"
            wire:click="deleteItems({{ $idsJs }})"
            class="w-[76px] flex items-center justify-center bg-terracotta text-white font-inter text-[12px] font-semibold">
            {{ __('Delete') }}
        </button>
    </div>

    <div
        x-on:touchstart="startDrag"
        x-on:touchmove="onDrag($event)"
        x-on:touchend="endDrag"
        x-on:click="tapRow"
        :style="`transform: translateX(${dx}px)`"
        :class="dragging && 'transition-none'"
        class="relative flex items-center gap-3 px-3.5 py-2.5 min-h-[52px] select-none cursor-pointer transition-transform {{ $bought ? 'bg-field-bg' : 'bg-white' }}">
        <button
            type="button"
            x-on:click.stop="toggle()"
            aria-label="{{ __('Bought') }}: {{ $group['name'] }}"
            x-bind:aria-pressed="checked ? 'true' : 'false'"
            class="shrink-0 -m-2.5 p-2.5">
            <span class="size-[22px] rounded-[7px] border-[1.5px] flex items-center justify-center transition-colors"
                :class="checked ? 'bg-sage border-sage' : 'border-field bg-white'">
                <x-icon.check class="size-3.5 text-white" x-show="checked" />
            </span>
        </button>

        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-1.5 min-w-0">
                <span class="text-[15px] font-medium leading-snug truncate" :class="checked ? 'text-muted line-through' : 'text-ink'">{{ $group['name'] }}</span>
                @if (($group['when'] ?? null) === 'today')
                <span class="shrink-0 rounded-full bg-today px-1.5 py-px text-[11px] font-semibold text-today-ink">{{ __('today') }}</span>
                @elseif (($group['when'] ?? null) === 'tomorrow')
                <span class="shrink-0 rounded-full bg-gold-light px-1.5 py-px text-[11px] font-semibold text-gold-dark">{{ __('tomorrow') }}</span>
                @endif
                @if ($group['optional'] ?? false)
                <span class="shrink-0 rounded-full border border-dashed border-faint px-1.5 text-[11px] font-medium text-muted">{{ __('optionally') }}</span>
                @endif
            </div>
            @if ($group['subtitle'])
            <div class="text-[12.5px] text-muted truncate">{{ $group['subtitle'] }}</div>
            @endif
            @if ($group['substitute'] ?? null)
            <div class="text-[12.5px] text-muted truncate">{{ __('or') }}: {{ $group['substitute'] }}</div>
            @endif
        </div>

        @if ($group['quantity'])
        <span class="shrink-0 max-w-[40%] text-right text-[14px] tabular-nums" :class="checked ? 'text-muted line-through' : 'text-ink'">{{ $group['quantity'] }}</span>
        @endif
    </div>
</div>
