@props(['type' => 'text'])

<input
    type="{{ $type }}"
    {{ $attributes->merge(['class' => 'border border-ink/15 bg-white rounded-[14px] px-3.5 py-3 font-manrope text-base sm:text-sm text-ink placeholder:text-ink/35 focus:outline-none focus:ring-2 focus:ring-terracotta/30']) }}
/>
