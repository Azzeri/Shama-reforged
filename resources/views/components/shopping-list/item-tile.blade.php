@props(['item', 'bought' => false])

<div
    x-data="{
        pressTimer: null,
        longPressed: false,
        startPress() {
            this.longPressed = false;
            this.pressTimer = setTimeout(() => {
                this.longPressed = true;
                $wire.editItem({{ $item->id }}).then(() => {
                    $dispatch('modal-show', { name: 'edit-item-modal' });
                });
            }, 500);
        },
        endPress() {
            clearTimeout(this.pressTimer);
        },
        handleClick() {
            if (this.longPressed) {
                this.longPressed = false;
                return;
            }
            $wire.toggle({{ $item->id }});
        },
    }"
    @mousedown="startPress"
    @mouseup="endPress"
    @mouseleave="endPress"
    @touchstart="startPress"
    @touchend="endPress"
    @click="handleClick"
    @contextmenu.prevent
    class="h-[68px] flex flex-col justify-center rounded-xl px-2.5 py-[9px] select-none cursor-pointer {{ $bought ? 'bg-[#F7F3EA]' : 'bg-white border border-ink/5 shadow-[0_1px_2px_rgba(43,33,24,0.06),0_4px_10px_rgba(43,33,24,0.05)]' }}">
    <div class="font-manrope text-[12.5px] font-bold leading-tight mb-0.5 truncate {{ $bought ? 'text-ink/45 line-through' : 'text-ink' }}">
        {{ $item->name }}
    </div>
    <div class="font-manrope text-[10.5px] font-semibold leading-tight truncate {{ $bought ? 'text-ink/40 line-through' : 'text-ink/50' }}">
        {{ $item->quantity ?: "\u{00A0}" }}
    </div>
    <div class="font-manrope text-[10px] leading-tight truncate {{ $bought ? 'text-ink/35 line-through' : 'text-ink/45' }}">
        {{ $item->notes ?: "\u{00A0}" }}
    </div>
</div>
