@props(['type' => 'text'])

<input
    type="{{ $type }}"
    {{ $attributes->merge(['class' => 'border-[1.5px] border-ink/25 bg-white rounded-2xl px-3.5 py-[13px] font-manrope text-sm text-ink placeholder:text-ink/35 focus:outline-none focus:ring-2 focus:ring-terracotta/30']) }}
/>
