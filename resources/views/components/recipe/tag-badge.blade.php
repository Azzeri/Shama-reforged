@props(['tag'])

@php
$colorClasses = $tag->isMealType()
    ? 'bg-gold/20 text-gold-dark'
    : 'bg-sage/18 text-forest';
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-1 font-manrope text-[11px] font-bold $colorClasses"]) }}>
    {{ $tag->name }}
</span>
