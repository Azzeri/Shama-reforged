@props(['value', 'color' => 'gold'])

@php
$checkedClasses = $color === 'sage'
    ? 'peer-checked:bg-forest peer-checked:border-forest peer-checked:text-white'
    : 'peer-checked:bg-gold peer-checked:border-gold peer-checked:text-white';
@endphp

<label class="cursor-pointer">
    <input type="checkbox" value="{{ $value }}" {{ $attributes->merge(['class' => 'peer sr-only']) }} />
    <span class="inline-flex rounded-full px-3.5 py-2 font-manrope text-[14px] font-semibold border border-ink/12 bg-white text-ink transition-colors {{ $checkedClasses }}">
        {{ $slot }}
    </span>
</label>
