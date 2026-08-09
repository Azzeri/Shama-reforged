@props(['item', 'bought' => false, 'ids' => null, 'displayQuantity' => null])

@php
    $ids = $ids ?? [$item->id];
    $isMerged = count($ids) > 1;
    $quantityText = $displayQuantity ?? $item->displayQuantity();
@endphp

<div
    x-data="{
        pressTimer: null,
        longPressed: false,
        startPress() {
            this.longPressed = false;
            @if (! $isMerged)
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
    {{ $attributes->class(['h-[68px] flex flex-col justify-center rounded-xl px-2.5 py-[9px] select-none cursor-pointer', 'bg-[#F7F3EA]' => $bought, 'bg-white border border-ink/5 shadow-[0_1px_2px_rgba(43,33,24,0.06),0_4px_10px_rgba(43,33,24,0.05)]' => ! $bought]) }}>
    <div class="font-manrope text-[12.5px] font-bold leading-tight mb-0.5 truncate {{ $bought ? 'text-ink/45 line-through' : 'text-ink' }}">
        {{ $item->name }}
    </div>
    <div class="font-manrope text-[10.5px] font-semibold leading-tight truncate {{ $bought ? 'text-ink/40 line-through' : 'text-ink/50' }}">
        {{ $quantityText ?: "\u{00A0}" }}
    </div>
    <div class="font-manrope text-[10px] leading-tight truncate {{ $bought ? 'text-ink/35 line-through' : 'text-ink/45' }}">
        {{ $isMerged ? "\u{00A0}" : ($item->notes ?: "\u{00A0}") }}
    </div>
</div>
