@props(['category', 'canMoveEarlier' => false])

{{-- One aisle (category) block: label + a card with its rows. --}}
<div {{ $attributes }}>
    <div class="mb-2 font-inter text-[12px] font-semibold uppercase tracking-[0.1em] text-muted">{{ $category['label'] }}</div>
    <div class="rounded-[18px] border border-line overflow-hidden divide-y divide-divider bg-white">
        @foreach ($category['groups'] as $group)
        <x-shopping-list.item-tile :group="$group" :can-move-earlier="$canMoveEarlier" />
        @endforeach
    </div>
</div>
