@props(['href' => null, 'variant' => 'solid', 'icon' => 'plus'])

{{-- Small red action pill ("+ Nowy", "Edytuj"); outline variant for secondary actions. --}}
@php
$classes = 'inline-flex h-10 items-center gap-1.5 rounded-full px-3.5 text-[14px] font-semibold whitespace-nowrap transition-colors '
    . ($variant === 'outline'
        ? 'border-[1.5px] border-terracotta bg-surface text-terracotta hover:bg-today/40'
        : 'bg-terracotta text-white hover:bg-terracotta-dark');
@endphp

@if ($href)
<a href="{{ $href }}" wire:navigate {{ $attributes->merge(['class' => $classes]) }}>
    @if ($icon)<x-dynamic-component :component="'icon.' . $icon" class="size-4" />@endif
    {{ $slot }}
</a>
@else
<button type="button" {{ $attributes->merge(['class' => $classes]) }}>
    @if ($icon)<x-dynamic-component :component="'icon.' . $icon" class="size-4" />@endif
    {{ $slot }}
</button>
@endif
