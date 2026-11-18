@props(['category', 'canMoveEarlier' => false])

{{-- One aisle (category) block: label + a card with its rows. --}}
<div {{ $attributes }}>
    <div class="mb-2 font-manrope text-[12px] font-extrabold uppercase tracking-[0.1em] text-ink/55">{{ $category['label'] }}</div>
    <div class="rounded-[18px] border border-ink/10 overflow-hidden divide-y divide-ink/8 bg-white">
        @foreach ($category['groups'] as $group)
        <x-shopping-list.item-tile :group="$group" :can-move-earlier="$canMoveEarlier" />
        @endforeach
    </div>
</div>
