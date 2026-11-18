@props(['group', 'bought' => false, 'canMoveEarlier' => false])

{{--
    One row of the shopping list (a merged group of item rows).
    Tap the checkbox to tick it off (short delay so a mis-tap can be undone),
    tap the row to open details, swipe left for "earlier" / "delete".
--}}
@php
    $ids = $group['ids'];
    $idsJs = \Illuminate\Support\Js::from($ids);
    $revealWidth = $canMoveEarlier ? 150 : 76;
@endphp

<div
    x-data="{
        checked: {{ $bought ? 'true' : 'false' }},
        original: {{ $bought ? 'true' : 'false' }},
        pending: false,
        removing: false,
        timer: null,
        dx: 0,
        dragging: false,
        open: false,
        startX: 0,
        startY: 0,

        toggle() {
            if (this.pending) {
                clearTimeout(this.timer);
                this.pending = false;
                this.checked = this.original;
                return;
            }
            this.checked = ! this.original;
            this.pending = true;
            this.timer = setTimeout(() => {
                this.removing = true;
                setTimeout(() => $wire.toggle({{ $idsJs }}, ! this.original), 250);
            }, 900);
        },
        startDrag(e) {
            this.startX = e.touches[0].clientX;
            this.startY = e.touches[0].clientY;
            this.dragging = false;
        },
        onDrag(e) {
            const dx = e.touches[0].clientX - this.startX;
            const dy = e.touches[0].clientY - this.startY;
            if (! this.dragging) {
                if (Math.abs(dx) < 8 || Math.abs(dy) > Math.abs(dx)) return;
                this.dragging = true;
            }
            e.preventDefault();
            const base = this.open ? -{{ $revealWidth }} : 0;
            this.dx = Math.max(-{{ $revealWidth }}, Math.min(0, base + dx));
        },
        endDrag() {
            if (this.dragging) {
                this.open = this.dx < -{{ $revealWidth / 2 }};
                this.dx = this.open ? -{{ $revealWidth }} : 0;
            }
            setTimeout(() => { this.dragging = false }, 50);
        },
        close() {
            this.dx = 0;
            this.open = false;
        },
        tapRow() {
            if (this.dragging) return;
            if (this.open) { this.close(); return; }
            $wire.openGroup({{ $idsJs }}).then(() => $dispatch('modal-show', { name: 'item-modal' }));
        },
    }"
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
            class="w-[74px] flex items-center justify-center bg-gold text-white font-manrope text-[12px] font-extrabold">
            {{ __('Day earlier') }}
        </button>
        @endif
        <button
            type="button"
            wire:click="deleteItems({{ $idsJs }})"
            class="w-[76px] flex items-center justify-center bg-terracotta text-white font-manrope text-[12px] font-extrabold">
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
        class="relative flex items-center gap-3.5 px-3.5 py-3 select-none cursor-pointer transition-transform {{ $bought ? 'bg-[#FBF9F4]' : 'bg-white' }}">
        <button
            type="button"
            x-on:click.stop="toggle()"
            aria-label="{{ __('Bought') }}"
            class="shrink-0 size-[26px] rounded-[8px] border-[1.5px] flex items-center justify-center transition-colors"
            :class="checked ? 'bg-forest border-forest' : 'border-ink/25 bg-white'">
            <flux:icon.check class="size-4 text-white" variant="mini" x-show="checked" />
        </button>

        <div class="flex-1 min-w-0">
            <div class="font-manrope text-[16px] font-medium leading-snug truncate" :class="checked ? 'text-ink/40 line-through' : 'text-ink'">{{ $group['name'] }}</div>
            @if ($group['subtitle'])
            <div class="font-manrope text-[12.5px] text-ink/50 truncate">{{ $group['subtitle'] }}</div>
            @endif
        </div>

        @if ($group['quantity'])
        <span class="shrink-0 max-w-[45%] text-right font-manrope text-[15px]" :class="checked ? 'text-ink/35 line-through' : 'text-ink/70'">{{ $group['quantity'] }}</span>
        @endif
    </div>
</div>
