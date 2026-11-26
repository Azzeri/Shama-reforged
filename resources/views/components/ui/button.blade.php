@props(['variant' => 'primary', 'type' => 'button', 'href' => null])

{{-- Full-width 48px button: primary (red), outline, ghost (red text). --}}
@php
$classes = 'flex h-12 w-full items-center justify-center gap-2 rounded-[14px] px-4 text-[15px] font-semibold transition-colors disabled:opacity-45 disabled:cursor-not-allowed '
    . match ($variant) {
        'outline' => 'border border-field bg-white text-ink',
        'ghost' => 'text-terracotta',
        'success' => 'bg-sage text-white',
        default => 'bg-terracotta text-white hover:bg-terracotta-dark',
    };
@endphp
@if ($href)
<a href="{{ $href }}" wire:navigate {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
<button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
