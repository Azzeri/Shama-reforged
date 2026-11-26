@props(['title'])

{{-- Main screen header: title on the left, small action pill(s) on the right. --}}
<div {{ $attributes->merge(['class' => 'flex items-center justify-between gap-3 min-h-11']) }}>
    <h1 data-large-title class="font-fraunces text-[28px] lg:text-[32px] font-semibold leading-none tracking-[-0.01em] text-ink">{{ $title }}</h1>
    @if ($slot->isNotEmpty())
    <div class="flex items-center gap-2 shrink-0">{{ $slot }}</div>
    @endif
</div>
