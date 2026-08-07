@props(['color' => 'ink', 'optional' => false])

@php
$colorClasses = match ($color) {
    'forest' => 'text-forest',
    default => 'text-ink/60',
};
@endphp

<div {{ $attributes->merge(['class' => "font-manrope text-[11px] font-extrabold uppercase tracking-[0.12em] mb-2.5 $colorClasses"]) }}>
    {{ $slot }}
    @if ($optional)
    <span class="normal-case font-semibold text-ink/25">({{ __('optional') }})</span>
    @endif
</div>
