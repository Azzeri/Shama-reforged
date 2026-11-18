@props(['tag'])

@php
$colorClasses = $tag->isMealType()
    ? 'bg-gold/20 text-gold-dark'
    : 'bg-sage/18 text-forest';
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-md px-2 py-0.5 font-manrope text-[12px] font-semibold $colorClasses"]) }}>
    {{ $tag->label() }}
</span>
