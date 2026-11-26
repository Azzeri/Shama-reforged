@props(['active' => false, 'color' => 'ink', 'href' => null])

{{--
    36px pill chip for filters and single/multi choice. Selected colours:
    gold = meal, sage = main ingredient, ink = everything else.
--}}
@php
$activeClasses = match ($color) {
    'gold' => 'bg-gold border-gold text-ink',
    'sage' => 'bg-sage border-sage text-white',
    default => 'bg-ink border-ink text-surface',
};
$classes = 'inline-flex h-9 shrink-0 items-center gap-1.5 rounded-full border px-3.5 text-[14px] font-medium whitespace-nowrap transition-colors '
    . ($active ? $activeClasses : 'border-field bg-surface text-ink');
@endphp

@if ($href)
<a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
<button type="button" aria-pressed="{{ $active ? 'true' : 'false' }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
