{{-- White card, 1px border, 20px radius. Rows inside are separated by dividers (no cards in cards). --}}
<div {{ $attributes->merge(['class' => 'bg-surface border border-line rounded-[20px] overflow-hidden']) }}>
    {{ $slot }}
</div>
