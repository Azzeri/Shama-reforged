@props(['item', 'bought' => false, 'ids' => null, 'displayQuantity' => null, 'anytime' => false, 'showDaysBadge' => false, 'daysCount' => 0, 'grouped' => false])

@php
    $ids = $ids ?? [$item->id];
    $quantityText = $displayQuantity ?? $item->displayQuantity();
    $isSolo = count($ids) === 1;
    $canMerge = $isSolo && $item->week_day;
    $revealWidth = $canMerge ? 140 : 70;
@endphp

<div
    x-data="{
        checked: {{ $bought ? 'true' : 'false' }},
        originalChecked: {{ $bought ? 'true' : 'false' }},
        pending: false,
        removing: false,
        checkTimer: null,
        removeTimer: null,
        pressTimer: null,
        longPressed: false,
        dx: 0,
        dragging: false,
        open: false,
        startX: 0,
        startY: 0,

        toggleChecked() {
            if (this.pending) {
                clearTimeout(this.checkTimer);
                this.pending = false;
                this.checked = this.originalChecked;
                return;
            }
            this.checked = ! this.originalChecked;
            this.pending = true;
            this.checkTimer = setTimeout(() => {
                this.removing = true;
                this.removeTimer = setTimeout(() => {
                    $wire.toggle({{ Illuminate\Support\Js::from($ids) }});
                }, 300);
            }, 1100);
        },

        startDrag(e) {
            this.startX = e.touches[0].clientX;
            this.startY = e.touches[0].clientY;
            this.dragging = false;
        },
        onDrag(e) {
            const dxRaw = e.touches[0].clientX - this.startX;
            const dyRaw = e.touches[0].clientY - this.startY;
            if (! this.dragging) {
                if (Math.abs(dxRaw) < 8 || Math.abs(dyRaw) > Math.abs(dxRaw)) return;
                this.dragging = true;
            }
            e.preventDefault();
            const base = this.open ? -{{ $revealWidth }} : 0;
            this.dx = Math.max(-{{ $revealWidth }}, Math.min(0, base + dxRaw));
        },
        endDrag() {
            if (this.dragging) {
                if (this.dx < -{{ $revealWidth / 2 }}) {
                    this.dx = -{{ $revealWidth }};
                    this.open = true;
                } else {
                    this.dx = 0;
                    this.open = false;
                }
            }
            setTimeout(() => { this.dragging = false; }, 50);
        },
        closeSwipe() {
            this.dx = 0;
            this.open = false;
        },

        openModal() {
            @if ($isSolo)
            $wire.editItem({{ $item->id }}).then(() => {
                $dispatch('modal-show', { name: 'edit-item-modal' });
            });
            @else
            $wire.showGroupDetails({{ Illuminate\Support\Js::from($ids) }}).then(() => {
                $dispatch('modal-show', { name: 'grouped-item-modal' });
            });
            @endif
        },
        startPress() {
            this.longPressed = false;
            this.pressTimer = setTimeout(() => {
                this.longPressed = true;
                this.openModal();
            }, 500);
        },
        endPress() {
            clearTimeout(this.pressTimer);
        },
        handleRowClick() {
            if (this.dragging) return;
            if (this.open) { this.closeSwipe(); return; }
            if (this.longPressed) { this.longPressed = false; return; }
            this.openModal();
        },
    }"
    x-show="! removing"
    x-collapse.duration.300ms
    class="relative overflow-hidden {{ $grouped ? '' : 'rounded-xl' }}"
    wire:key="row-wrap-{{ $ids[0] }}-{{ count($ids) }}">

    <div class="absolute inset-y-0 right-0 flex">
        @if ($canMerge)
        <button
            type="button"
            wire:click="mergeToPreviousDay({{ $item->id }})"
            @click="closeSwipe()"
            class="w-[70px] flex items-center justify-center bg-gold text-white font-manrope text-[12px] font-extrabold">
            {{ __('Merge') }}
        </button>
        @endif
        <button
            type="button"
            wire:click="deleteItems({{ Illuminate\Support\Js::from($ids) }})"
            class="w-[70px] flex items-center justify-center bg-terracotta text-white font-manrope text-[12px] font-extrabold">
            {{ __('Delete') }}
        </button>
    </div>

    <div
        @touchstart="startDrag"
        @touchmove="onDrag($event)"
        @touchend="endDrag"
        :style="`transform: translateX(${dx}px)`"
        @mousedown="startPress"
        @mouseup="endPress"
        @mouseleave="endPress"
        @click="handleRowClick"
        @contextmenu.prevent
        {{ $attributes->class([
            'relative flex items-center gap-3 px-3 py-3 select-none cursor-pointer transition-transform',
            'bg-[#F7F3EA]' => $bought,
            'bg-white' => ! $bought && $grouped,
            'bg-white border border-gold/45 rounded-xl' => $anytime && ! $bought && ! $grouped,
            'bg-white border border-ink/5 shadow-[0_1px_2px_rgba(43,33,24,0.06),0_4px_10px_rgba(43,33,24,0.05)] rounded-xl' => ! $anytime && ! $bought && ! $grouped,
        ]) }}>
        <button
            type="button"
            @click.stop="toggleChecked()"
            class="shrink-0 size-6 rounded-md border-2 flex items-center justify-center"
            :class="checked ? 'bg-forest border-forest' : 'border-ink/25 bg-white'">
            <flux:icon.check class="size-3.5 text-white" x-show="checked" />
        </button>

        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="font-manrope text-[13.5px] font-bold truncate" :class="checked ? 'text-ink/40 line-through' : 'text-ink'">{{ $item->name }}</span>
                @if ($showDaysBadge)
                <span class="font-manrope text-[10.5px] font-extrabold text-gold-dark whitespace-nowrap">{{ $daysCount }} {{ __('days') }} ›</span>
                @endif
            </div>
            @if ($isSolo && $item->recipe)
            <div class="font-manrope text-[11px] text-ink/40 truncate">📖 {{ $item->recipe->name }}</div>
            @endif
        </div>

        <span class="shrink-0 font-manrope text-[12.5px] font-semibold" :class="checked ? 'text-ink/35 line-through' : 'text-ink/55'">{{ $quantityText }}</span>
    </div>
</div>
