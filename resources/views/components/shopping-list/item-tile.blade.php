@props(['item', 'bought' => false, 'ids' => null, 'displayQuantity' => null, 'anytime' => false, 'showDaysBadge' => false, 'daysCount' => 0])

@php
    $ids = $ids ?? [$item->id];
    $quantityText = $displayQuantity ?? $item->displayQuantity();
@endphp

<div
    x-data="{
        pressTimer: null,
        longPressed: false,
        startPress() {
            this.longPressed = false;
            @if (count($ids) > 1)
            this.pressTimer = setTimeout(() => {
                this.longPressed = true;
                $wire.showGroupDetails({{ Illuminate\Support\Js::from($ids) }}).then(() => {
                    $dispatch('modal-show', { name: 'grouped-item-modal' });
                });
            }, 500);
            @else
            this.pressTimer = setTimeout(() => {
                this.longPressed = true;
                $wire.editItem({{ $item->id }}).then(() => {
                    $dispatch('modal-show', { name: 'edit-item-modal' });
                });
            }, 500);
            @endif
        },
        endPress() {
            clearTimeout(this.pressTimer);
        },
        handleClick() {
            if (this.longPressed) {
                this.longPressed = false;
                return;
            }
            $wire.toggle({{ Illuminate\Support\Js::from($ids) }});
        },
    }"
    @mousedown="startPress"
    @mouseup="endPress"
    @mouseleave="endPress"
    @touchstart="startPress"
    @touchend="endPress"
    @click="handleClick"
    @contextmenu.prevent
    {{ $attributes->class([
        'flex flex-col justify-center rounded-xl px-2.5 py-[9px] select-none cursor-pointer',
        'bg-[#F7F3EA]' => $bought,
        'bg-white border border-gold/45' => $anytime && ! $bought,
        'bg-white border border-ink/5 shadow-[0_1px_2px_rgba(43,33,24,0.06),0_4px_10px_rgba(43,33,24,0.05)]' => ! $anytime && ! $bought,
    ]) }}>
    <div class="font-manrope text-[12.5px] font-bold leading-[1.3] mb-0.5 line-clamp-2 min-h-[2.6em] {{ $bought ? 'text-ink/45 line-through' : 'text-ink' }}">
        {{ $item->name }}
    </div>
    <div class="flex items-center justify-between gap-1.5">
        <span class="font-manrope text-[10.5px] font-semibold leading-tight {{ $bought ? 'text-ink/40 line-through' : 'text-ink/50' }}">
            {{ $quantityText ?: "\u{00A0}" }}
        </span>
        @if ($showDaysBadge)
        <span class="font-manrope text-[10.5px] font-extrabold text-gold-dark whitespace-nowrap">{{ $daysCount }} {{ __('days') }} ›</span>
        @endif
    </div>
</div>
