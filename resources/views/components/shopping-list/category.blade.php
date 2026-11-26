@props(['category', 'canMoveEarlier' => false])

{{-- One aisle (category) block: label + a card with its rows. --}}
<div {{ $attributes }}>
    <x-ui.section-label class="mb-1.5">{{ $category['label'] }}</x-ui.section-label>
    <div class="rounded-2xl border border-line overflow-hidden divide-y divide-divider bg-white">
        @foreach ($category['groups'] as $group)
        <x-shopping-list.item-tile :group="$group" :can-move-earlier="$canMoveEarlier" />
        @endforeach
    </div>
</div>
